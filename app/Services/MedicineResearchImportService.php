<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineAudit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MedicineResearchImportService
{
    private const DATA_PATH = 'database/data/master_obat_160.json';

    public function preview(): Collection
    {
        return $this->rows()->map(function (array $row) {
            $mapped = $this->mapRow($row);
            $existing = Medicine::where('code', $mapped['code'])->first();

            return $mapped + [
                'row_number' => $row['No Asli'] ?? null,
                'import_status' => $existing ? 'existing' : 'new',
                'existing_name' => $existing?->name,
            ];
        });
    }

    public function import(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $created = 0;
            $skipped = 0;

            foreach ($this->rows() as $row) {
                $mapped = $this->mapRow($row);
                if (Medicine::where('code', $mapped['code'])->exists()) {
                    $skipped++;
                    continue;
                }

                $unitId = $this->unitIdFor($mapped['stock_unit_suggestion']);
                $medicine = Medicine::create([
                    'code' => $mapped['code'],
                    'name' => $mapped['name'],
                    'original_name' => $mapped['original_name'],
                    'generic_name' => $mapped['generic_name'],
                    'strength' => $mapped['strength'],
                    'dosage_form' => $mapped['dosage_form'],
                    'unit_id' => $unitId,
                    'stock_unit_suggestion' => $mapped['stock_unit_suggestion'],
                    'purchase_unit_name' => $mapped['purchase_unit_name'],
                    'package_reference' => $mapped['package_reference'],
                    'conversion_candidate' => $mapped['conversion_candidate'],
                    'conversion_type' => $mapped['conversion_type'],
                    'is_active' => false,
                    'verification_status' => $mapped['verification_status'],
                    'verification_note' => $mapped['verification_note'],
                    'research_priority' => $mapped['research_priority'],
                    'evidence_status' => $mapped['evidence_status'],
                    'kfa_code' => $mapped['kfa_code'],
                    'bpom_nie' => $mapped['bpom_nie'],
                    'source_reference_1' => $mapped['source_reference_1'],
                    'source_reference_2' => $mapped['source_reference_2'],
                    'import_source' => [
                        'source' => 'Riset_Master_Obat_Shifa_Medika_160_Item_Review.xlsx',
                        'sheet' => 'Riset Produk 160',
                        'row' => $row['No Asli'] ?? null,
                        'decision' => $row['Keputusan Rival'] ?? null,
                    ],
                    'imported_by' => $user->id,
                    'imported_at' => now(),
                ]);

                MedicineAudit::create([
                    'medicine_id' => $medicine->id,
                    'user_id' => $user->id,
                    'action' => 'imported',
                    'after' => $medicine->only(['code', 'name', 'original_name', 'verification_status', 'is_active']),
                ]);
                $created++;
            }

            return ['created' => $created, 'skipped' => $skipped, 'total' => $created + $skipped];
        });
    }

    private function rows(): Collection
    {
        $path = base_path(self::DATA_PATH);
        if (! is_file($path)) {
            return collect();
        }

        return collect(json_decode(file_get_contents($path), true) ?: [])
            ->filter(fn ($row) => filled($row['ID Klinik'] ?? null))
            ->values();
    }

    private function mapRow(array $row): array
    {
        $notes = collect([$row['Catatan Hasil Riset'] ?? null, $row['Catatan Rival (asli)'] ?? null])
            ->filter()
            ->implode(' | ');

        return [
            'code' => $this->clean($row['ID Klinik'] ?? null),
            'name' => $this->clean($row['Nama Kandidat'] ?? null) ?: $this->clean($row['Nama Asli Klinik'] ?? null),
            'original_name' => $this->clean($row['Nama Asli Klinik'] ?? null),
            'generic_name' => $this->clean($row['Kandungan / Keterangan Kandidat'] ?? null),
            'strength' => $this->clean($row['Kekuatan Kandidat'] ?? null),
            'dosage_form' => $this->clean($row['Bentuk Sediaan Kandidat'] ?? null),
            'stock_unit_suggestion' => $this->clean($row['Satuan Stok Usulan'] ?? null),
            'purchase_unit_name' => $this->clean($row['Satuan Beli Rival'] ?? null),
            'package_reference' => $this->clean($row['Kemasan dari Referensi'] ?? null),
            'conversion_candidate' => $this->positiveNumber($row['Konversi Kandidat (unit/kemasan)'] ?? null),
            'conversion_type' => $this->clean($row['Tipe Faktor Konversi'] ?? null),
            'verification_status' => $this->verificationStatus($row, $notes),
            'verification_note' => $notes ?: null,
            'research_priority' => $this->clean($row['Prioritas'] ?? null),
            'evidence_status' => $this->clean($row['Status Bukti'] ?? null),
            'kfa_code' => $this->clean($row['Kode KFA Kandidat'] ?? null),
            'bpom_nie' => $this->clean($row['NIE BPOM Kandidat'] ?? null),
            'source_reference_1' => $this->clean($row['Sumber Referensi 1'] ?? null),
            'source_reference_2' => $this->clean($row['Sumber Referensi 2'] ?? null),
        ];
    }

    private function verificationStatus(array $row, string $notes): string
    {
        $decision = Str::lower((string) ($row['Keputusan Rival'] ?? ''));
        $evidence = Str::lower((string) ($row['Status Bukti'] ?? ''));
        $noteText = Str::lower($notes);

        if (
            str_contains($decision, 'revisi') ||
            str_contains($decision, 'tanya') ||
            str_contains($evidence, 'ambigu') ||
            str_contains($noteText, 'perlu cek') ||
            str_contains($noteText, 'jangan impor sebagai sku pasti')
        ) {
            return 'perlu_revisi';
        }

        return 'belum_diverifikasi';
    }

    private function unitIdFor(?string $name): ?int
    {
        if (! $this->isCertainUnit($name)) {
            return null;
        }

        return Unit::firstOrCreate(['name' => Str::title(trim($name))])->id;
    }

    private function isCertainUnit(?string $name): bool
    {
        if (blank($name)) {
            return false;
        }

        $value = Str::lower(trim($name));

        return ! str_contains($value, 'perlu') && ! str_contains($value, 'belum') && ! str_contains($value, 'cek');
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function positiveNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;

        return $number > 0 ? $number : null;
    }
}
