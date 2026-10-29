<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\IssueImport;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IssueImportService
{
    public static function groupKey(array $row): string
    {
        return hash('sha256', json_encode([$row['source_code'], $row['source_unit']], JSON_THROW_ON_ERROR));
    }

    public function create(array $rows, string $hash, int $location, string $startsAt, User $actor): IssueImport
    {
        return InventoryTransaction::run(function () use ($rows, $hash, $location, $startsAt, $actor) {
            $this->activeLocation($location);
            $start = CarbonImmutable::parse($startsAt, 'Asia/Jakarta');
            $policy = DB::table('issue_import_policies')->where('location_id', $location)->first();
            if ($policy && ! $start->equalTo(CarbonImmutable::parse($policy->starts_at, 'Asia/Jakarta'))) {
                $this->fail('Batas mulai impor lokasi ini sudah ditetapkan. Muat ulang halaman dan gunakan batas yang tersimpan.');
            }
            $document = IssueImport::create([
                'file_hash' => $hash, 'location_id' => $location, 'starts_at' => $start,
                'row_count' => count($rows), 'sale_count' => count(array_unique(array_column($rows, 'sale_key'))),
                'compound_count' => count(array_filter($rows, fn ($row) => $row['compound'])),
                'period_start' => min(array_column($rows, 'occurred_at')), 'period_end' => max(array_column($rows, 'occurred_at')),
                'payload' => $rows, 'created_by' => $actor->id,
            ]);
            AuditLogger::record('inventory.issue-import.previewed', 'Pratinjau impor pengeluaran dibuat.', $document,
                ['rows' => count($rows), 'location_id' => $location], actor: $actor);

            return $document;
        });
    }

    public function groups(IssueImport $document): array
    {
        $groups = [];
        foreach ($document->payload ?? [] as $row) {
            $key = self::groupKey($row);
            $groups[$key] ??= ['key' => $key, 'code' => $row['source_code'], 'name' => $row['source_name'],
                'unit' => $row['source_unit'], 'quantity' => 0, 'count' => 0];
            $groups[$key]['quantity'] += (float) $row['source_quantity'];
            $groups[$key]['count']++;
        }

        return $groups;
    }

    public function saveMappings(IssueImport $document, array $input, User $actor): void
    {
        InventoryTransaction::run(function () use ($document, $input, $actor) {
            $document = IssueImport::lockForUpdate()->findOrFail($document->id);
            $this->draft($document);
            $groups = $this->groups($document);
            foreach ($input as $key => $mapping) {
                if (! isset($groups[$key]) || empty($mapping['confirmed'])) {
                    continue;
                }
                $medicine = Medicine::with('unit')->find($mapping['medicine_id'] ?? null);
                if (! $this->operational($medicine)) {
                    $this->fail('Pilih obat aktif dengan satuan dan bentuk sediaan lengkap untuk setiap pemetaan yang disetujui.');
                }
                DB::table('issue_import_mappings')->updateOrInsert(
                    ['source_code' => $groups[$key]['code'], 'source_unit' => $groups[$key]['unit']],
                    ['medicine_id' => $medicine->id, 'unit_id' => $medicine->unit_id, 'factor' => $mapping['factor'],
                        'confirmed_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            AuditLogger::record('inventory.issue-import.mappings-confirmed', 'Pemetaan impor pengeluaran ditinjau.', $document,
                ['document_id' => $document->id], actor: $actor);
        });
    }

    public function preview(IssueImport $document): array
    {
        $groups = $this->groups($document);
        $mappings = DB::table('issue_import_mappings')->orderBy('id')->get()->keyBy(fn ($map) => self::groupKey(['source_code' => $map->source_code, 'source_unit' => $map->source_unit]));
        $medicines = Medicine::with('unit')->get()->keyBy('id');
        $lines = DB::table('issue_import_lines')->whereIn('source_key', array_column($document->payload ?? [], 'source_key'))->get()->keyBy('source_key');
        $details = $totals = $snapshots = [];
        $new = $duplicates = $blocked = 0;
        foreach ($groups as $key => &$group) {
            $map = $mappings->get($key);
            $medicine = $map ? $medicines->get($map->medicine_id) : null;
            $group['mapping'] = $map;
            $group['medicine'] = $medicine;
            $group['mapping_valid'] = $map && $this->operational($medicine) && $map->unit_id === $medicine->unit_id;
            $group['has_new_source'] = false;
            $group['suggestion'] = $map ? null : $medicines->first(fn ($item) => NeoklinikIssueReader::normalize($item->code) === $group['code'] && $this->operational($item));
            $group['ready'] = $group['duplicate'] = $group['blocked'] = 0;
            $group['new_quantity'] = 0;
            $group['details'] = [];
            $group['problems'] = [];
        }
        unset($group);
        foreach ($document->payload ?? [] as $row) {
            $key = self::groupKey($row);
            $map = $groups[$key]['mapping'];
            $medicine = $groups[$key]['medicine'];
            $previous = $lines->get($row['source_key']);
            if (! $previous) {
                $groups[$key]['has_new_source'] = true;
            }
            $quantity = $map ? round((float) $row['source_quantity'] * (float) $map->factor, 4) : null;
            $status = 'ready';
            $message = 'Siap diposting';
            if ($previous) {
                $status = $previous->fingerprint === $row['fingerprint'] ? 'duplicate' : 'blocked';
                $message = $status === 'duplicate' ? 'Sudah diimpor; tidak dikurangi lagi' : 'Data sumber berubah; tinjau koreksi transaksi lama';
            } elseif (CarbonImmutable::parse($row['occurred_at'], 'Asia/Jakarta')->lessThan($document->starts_at)) {
                $status = 'blocked';
                $message = 'Sebelum batas mulai impor; periksa periode ekspor';
            } elseif (! $map || ! $this->operational($medicine) || $map->unit_id !== $medicine->unit_id) {
                $status = 'blocked';
                $message = 'Pemetaan obat/satuan belum disetujui atau master berubah';
            } elseif (! is_finite($quantity) || $quantity < 0.0001 || $quantity > 999999999999.9999) {
                $status = 'blocked';
                $message = 'Hasil konversi jumlah di luar batas';
            } else {
                if (! isset($snapshots[$medicine->id])) {
                    $snapshot = app(DailyPhysicalCountService::class)->snapshot($medicine->id, $document->location_id);
                    $reconciledAt = InventoryMovement::where('medicine_id', $medicine->id)
                        ->whereIn('type', ['opening_balance', 'physical_count', 'stock_opname_adjustment'])
                        ->where(fn ($query) => $query->where('source_location_id', $document->location_id)->orWhere('destination_location_id', $document->location_id))
                        ->max('occurred_at');
                    $lastCounted = DB::table('stock_balances')->where('medicine_id', $medicine->id)->where('location_id', $document->location_id)->value('last_counted_at');
                    $snapshot['reconciled_at'] = max($reconciledAt ?? '', $lastCounted ?? '');
                    $snapshot['eligible'] = (float) DB::table('batch_stock_balances as balance')->join('batches as batch', 'batch.id', '=', 'balance.batch_id')
                        ->where('balance.medicine_id', $medicine->id)->where('batch.medicine_id', $medicine->id)->where('balance.location_id', $document->location_id)
                        ->where('balance.quantity', '>', 0)->where(fn ($query) => $query->whereNull('batch.expiry_date')->orWhere('batch.expiry_date', '>=', now('Asia/Jakarta')->toDateString()))->sum('balance.quantity');
                    $snapshots[$medicine->id] = $snapshot;
                }
                if ($snapshots[$medicine->id]['reconciled_at'] && CarbonImmutable::parse($row['occurred_at'], 'Asia/Jakarta')->lessThanOrEqualTo(CarbonImmutable::parse($snapshots[$medicine->id]['reconciled_at'], 'Asia/Jakarta'))) {
                    $status = 'blocked';
                    $message = 'Sudah melewati stok awal/hitung fisik; pastikan tidak mengurangi dua kali';
                } else {
                    $totals[$medicine->id] = round(($totals[$medicine->id] ?? 0) + $quantity, 4);
                }
            }
            $details[] = $row + ['group_key' => $key, 'medicine_id' => $map?->medicine_id, 'unit_id' => $map?->unit_id,
                'conversion_factor' => $map?->factor, 'target_name' => $medicine?->name, 'target_unit' => $medicine?->unit?->name,
                'quantity' => $quantity, 'status' => $status, 'message' => $message];
        }
        foreach ($details as &$detail) {
            if ($detail['status'] === 'ready') {
                $snapshot = $snapshots[$detail['medicine_id']];
                if ((float) $snapshot['quantity'] < $totals[$detail['medicine_id']] || $snapshot['eligible'] < $totals[$detail['medicine_id']]) {
                    $detail['status'] = 'blocked';
                    $detail['message'] = 'Stok total atau batch layak pakai tidak cukup untuk seluruh pengeluaran obat ini';
                } elseif (round(array_sum(array_map('floatval', $snapshot['batches'])), 4) !== (float) $snapshot['quantity']) {
                    $detail['status'] = 'blocked';
                    $detail['message'] = 'Stok total dan rincian batch berbeda; rekonsiliasi dahulu';
                }
            }
            $groups[$detail['group_key']][$detail['status']]++;
            $groups[$detail['group_key']]['details'][] = $detail;
            if ($detail['status'] === 'ready') {
                $groups[$detail['group_key']]['new_quantity'] += $detail['quantity'];
            } elseif ($detail['status'] === 'blocked') {
                $groups[$detail['group_key']]['problems'][] = $detail['message'];
            }
            match ($detail['status']) {
                'ready' => $new++, 'duplicate' => $duplicates++, default => $blocked++,
            };
        }
        unset($detail);
        foreach ($groups as &$group) {
            $group['needs_mapping'] = $group['has_new_source'] && ! $group['mapping_valid'];
        }
        unset($group);
        $token = hash_hmac('sha256', json_encode([$document->id, $details,
            $mappings->map(fn ($map) => [$map->medicine_id, $map->unit_id, $map->factor])->all(), $snapshots], JSON_THROW_ON_ERROR), config('app.key'));

        return compact('groups', 'details', 'new', 'duplicates', 'blocked', 'token');
    }

    public function post(IssueImport $document, string $token, User $actor): IssueImport
    {
        return InventoryTransaction::run(function () use ($document, $token, $actor) {
            $document = IssueImport::lockForUpdate()->findOrFail($document->id);
            if ($document->status === 'posted') {
                return $document;
            }
            $this->draft($document);
            $this->activeLocation($document->location_id);
            $preview = $this->preview($document);
            if (! hash_equals($preview['token'], $token)) {
                $this->fail('Stok, data impor, atau pemetaan berubah sejak pratinjau. Muat ulang dan periksa kembali sebelum posting.');
            }
            if ($preview['blocked']) {
                $this->fail($preview['blocked'].' baris belum siap. Perbaiki pemetaan, periode, atau stok sebelum posting; belum ada stok yang dikurangi.');
            }
            if ($preview['new']) {
                $policy = DB::table('issue_import_policies')->where('location_id', $document->location_id)->first();
                if ($policy && ! $document->starts_at->equalTo(CarbonImmutable::parse($policy->starts_at, 'Asia/Jakarta'))) {
                    $this->fail('Batas mulai impor sudah ditetapkan oleh posting lain. Buat pratinjau baru dengan batas yang tersimpan.');
                }
                if (! $policy) {
                    DB::table('issue_import_policies')->insert(['location_id' => $document->location_id,
                        'starts_at' => $document->starts_at, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $stock = app(InventoryStockService::class);
            foreach ($preview['details'] as $row) {
                if ($row['status'] !== 'ready') {
                    continue;
                }
                $lineId = DB::table('issue_import_lines')->insertGetId(['issue_import_id' => $document->id,
                    'source_key' => $row['source_key'], 'fingerprint' => $row['fingerprint'], 'medicine_id' => $row['medicine_id'],
                    'unit_id' => $row['unit_id'], 'quantity' => $row['quantity'], 'occurred_at' => CarbonImmutable::parse($row['occurred_at'], 'Asia/Jakarta')->format('Y-m-d H:i:s'),
                    'created_at' => now(), 'updated_at' => now()]);
                $reference = 'OUT-IMP-'.$document->id.'-'.$lineId;
                if (InventoryMovement::where('reference', $reference)->exists()) {
                    $this->fail('Referensi impor sudah dipakai transaksi lain. Hubungi administrator.');
                }
                $allocations = $stock->fefoAllocations($row['medicine_id'], $document->location_id, $row['quantity']);
                $stock->add($row['medicine_id'], $document->location_id, -$row['quantity']);
                foreach ($allocations as $allocation) {
                    $stock->addBatch($row['medicine_id'], $allocation['batch_id'], $document->location_id, -$allocation['quantity']);
                    $movement = InventoryMovement::create(['type' => 'issue', 'reference' => $reference,
                        'medicine_id' => $row['medicine_id'], 'batch_id' => $allocation['batch_id'], 'source_location_id' => $document->location_id,
                        'quantity' => $allocation['quantity'], 'occurred_at' => $row['occurred_at'], 'created_by' => $actor->id,
                        'counterparty' => 'Impor Neoklinik #'.$document->id,
                        'notes' => 'Pengeluaran impor; alokasi FEFO ShifaStock, bukan informasi batch dari Neoklinik.']);
                    DB::table('issue_import_line_movements')->insert(['issue_import_line_id' => $lineId, 'inventory_movement_id' => $movement->id]);
                }
            }
            $document->update(['status' => 'posted', 'posted_count' => $preview['new'], 'duplicate_count' => $preview['duplicates'],
                'posted_by' => $actor->id, 'posted_at' => now(),
                'payload' => array_values(array_filter($preview['details'], fn ($row) => $row['status'] === 'ready'))]);
            AuditLogger::record('inventory.issue-import.posted', 'Impor pengeluaran diposting.', $document,
                ['posted' => $preview['new'], 'duplicates' => $preview['duplicates'], 'location_id' => $document->location_id,
                    'attestations' => ['delivered', 'units_and_compounds', 'fefo', 'not_previously_counted', 'source_identity']], actor: $actor);

            return $document;
        });
    }

    public function delete(IssueImport $document, User $actor): void
    {
        InventoryTransaction::run(function () use ($document, $actor) {
            $document = IssueImport::lockForUpdate()->findOrFail($document->id);
            if (! in_array($document->status, ['draft', 'expired'], true) || $document->posted_at || $document->posted_by
                || $document->posted_count > 0 || $document->duplicate_count > 0
                || DB::table('issue_import_lines')->where('issue_import_id', $document->id)->exists()) {
                $this->fail('Impor yang sudah diposting tidak dapat dihapus. Riwayat dan pengaman duplikasi harus tetap tersimpan. Jika stok perlu dikoreksi, gunakan pembalikan transaksi dengan hak akses yang sesuai.');
            }
            AuditLogger::record('inventory.issue-import.deleted', 'Pratinjau impor pengeluaran dihapus.', $document,
                ['document_id' => $document->id, 'previous_status' => $document->status,
                    'row_count' => $document->row_count, 'location_id' => $document->location_id], actor: $actor);
            $document->delete();
        });
    }

    private function activeLocation(int $id): void
    {
        if (! Location::whereKey($id)->where('is_active', true)->exists()) {
            $this->fail('Lokasi sumber tidak aktif. Pilih lokasi yang masih digunakan.');
        }
    }

    private function operational(?Medicine $medicine): bool
    {
        return $medicine && $medicine->is_active && $medicine->unit_id && $medicine->unit && trim((string) $medicine->dosage_form) !== '';
    }

    private function draft(IssueImport $document): void
    {
        if ($document->status !== 'draft' || ! $document->payload || $document->created_at->lt(now()->subDays(7))) {
            $this->fail('Pratinjau sudah selesai atau kedaluwarsa (7 hari). Unggah kembali file untuk pemeriksaan terbaru.');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['import' => $message]);
    }
}
