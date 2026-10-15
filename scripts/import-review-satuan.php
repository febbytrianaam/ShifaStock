<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path = __DIR__.'/../storage/app/imports/review-satuan-obat.json';
if (! is_file($path)) {
    throw new RuntimeException('File import tidak ditemukan: '.$path);
}

$source = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$user = App\Models\User::where('role', 'administrator')->first() ?? App\Models\User::first();

$counts = [
    'source_rows' => count($source['rows'] ?? []),
    'created' => 0,
    'updated' => 0,
    'skipped' => 0,
    'units_created' => 0,
    'needs_revision' => 0,
    'missing_unit' => 0,
    'missing_conversion' => 0,
];

$clean = static function (mixed $value): ?string {
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);

    return $value === '' || $value === '-' ? null : $value;
};

$positiveNumber = static function (mixed $value): ?float {
    if ($value === null || $value === '') {
        return null;
    }

    $number = (float) $value;

    return $number > 0 ? $number : null;
};

$limit = static function (?string $value, int $max): ?string {
    if ($value === null) {
        return null;
    }

    return Illuminate\Support\Str::limit($value, $max, '');
};

$certainUnit = static function (?string $value): bool {
    if ($value === null) {
        return false;
    }

    $lower = Illuminate\Support\Str::lower($value);

    return ! str_contains($lower, 'belum') && ! str_contains($lower, 'perlu') && ! str_contains($lower, 'cek');
};

$unitIdFor = static function (?string $name) use ($certainUnit, &$counts): ?int {
    if (! $certainUnit($name)) {
        return null;
    }

    $unitName = Illuminate\Support\Str::of($name)->squish()->title()->toString();
    $unit = App\Models\Unit::firstOrCreate(['name' => $unitName], ['is_active' => true]);
    if ($unit->wasRecentlyCreated) {
        $counts['units_created']++;
    }

    return $unit->id;
};

$verificationStatus = static function (array $row, ?string $stockUnit, ?float $conversion) use ($clean): string {
    $status = Illuminate\Support\Str::lower((string) $clean($row['Status Verifikasi'] ?? null));
    $decision = Illuminate\Support\Str::lower((string) $clean($row['Keputusan Review Rival'] ?? null));
    $stockUnitText = Illuminate\Support\Str::lower((string) $stockUnit);

    if (
        str_contains($status, 'perlu') ||
        str_contains($decision, 'revisi') ||
        str_contains($decision, 'tanya') ||
        str_contains($stockUnitText, 'belum') ||
        $conversion === null
    ) {
        return 'perlu_revisi';
    }

    return 'belum_diverifikasi';
};

$notesFor = static function (array $row) use ($clean): ?string {
    return collect([
        $clean($row['Catatan / Pertanyaan untuk Klinik'] ?? null),
        $clean($row['Catatan Rival'] ?? null),
        $clean($row['Temuan dari laporan WA'] ?? null),
        $clean($row['Hal yang wajib dikonfirmasi'] ?? null),
        $clean($row['Bukti / jawaban Rival'] ?? null),
        $clean($row['Keputusan mapping'] ?? null),
    ])->filter()->implode(' | ') ?: null;
};

Illuminate\Support\Facades\DB::transaction(function () use ($source, $user, $clean, $positiveNumber, $limit, $unitIdFor, $verificationStatus, $notesFor, &$counts) {
    foreach ($source['rows'] ?? [] as $row) {
        $code = $limit($clean($row['ID Produk'] ?? null), 50);
        $name = $limit($clean($row['Nama Produk'] ?? null), 255);

        if (! $code || ! $name) {
            $counts['skipped']++;
            continue;
        }

        $stockUnit = $limit($clean($row['Satuan Stok Final'] ?? null) ?: $clean($row['Satuan Stok Usulan'] ?? null), 100);
        $purchaseUnit = $limit($clean($row['Satuan Beli Final'] ?? null) ?: $clean($row['Satuan Beli Usulan'] ?? null), 100);
        $conversion = $positiveNumber($row['Konversi Final'] ?? null) ?? $positiveNumber($row['Konversi Beli ke Stok'] ?? null);
        $unitId = $unitIdFor($stockUnit);
        $statusValue = $verificationStatus($row, $stockUnit, $conversion);
        $notes = $notesFor($row);

        if ($statusValue === 'perlu_revisi') {
            $counts['needs_revision']++;
        }
        if (! $unitId) {
            $counts['missing_unit']++;
        }
        if ($conversion === null) {
            $counts['missing_conversion']++;
        }

        $waParts = collect([
            $clean($row['Nama di WA'] ?? null),
            $clean($row['Jumlah WA'] ?? null),
            $clean($row['Unit WA'] ?? null),
        ])->filter()->implode(' ');
        $packageNotes = collect([
            ($value = $clean($row['Kemasan Beli Asli'] ?? null)) ? 'Kemasan beli asli: '.$value : null,
            ($value = $clean($row['Satuan Keluar Usulan'] ?? null)) ? 'Satuan keluar usulan: '.$value : null,
            ($value = $clean($row['Isi 1 box (unit stok)'] ?? null)) ? 'Isi 1 box: '.$value : null,
            ($value = $clean($row['Isi 1 strip (unit stok)'] ?? null)) ? 'Isi 1 strip: '.$value : null,
            $waParts ? 'WA: '.$waParts : null,
            ($value = $clean($row['Lokasi yang dilaporkan'] ?? null)) ? 'Lokasi WA: '.$value : null,
        ])->filter()->implode(' | ');

        $medicine = App\Models\Medicine::where('code', $code)->first();
        $payload = [
            'name' => $name,
            'original_name' => $name,
            'generic_name' => $limit($clean($row['Keterangan Sumber'] ?? null), 255),
            'dosage_form' => $limit($clean($row['Bentuk Sediaan'] ?? null), 100),
            'stock_unit_suggestion' => $stockUnit,
            'purchase_unit_name' => $purchaseUnit,
            'package_reference' => $limit($packageNotes ?: null, 2000),
            'conversion_candidate' => $conversion,
            'conversion_type' => $conversion === null ? null : 'Beli ke stok',
            'verification_status' => $medicine?->verification_status === 'terverifikasi' ? 'terverifikasi' : $statusValue,
            'verification_note' => $limit($notes, 4000),
            'research_priority' => $limit($clean($row['Status Verifikasi'] ?? null), 255),
            'evidence_status' => $limit($clean($row['Keputusan Review Rival'] ?? null), 255),
            'import_source' => [
                'source' => 'Review_Satuan_Obat_Klinik_Shifa_Medika_Revisi_Laporan_WA.xlsx',
                'sheet' => $source['sheet'] ?? 'Review Satuan',
                'row' => $row['_excel_row'] ?? null,
                'status_pencocokan' => $clean($row['Status pencocokan'] ?? null),
                'ada_di_wa' => $clean($row['Ada di WA'] ?? null),
            ],
            'imported_by' => $user?->id,
            'imported_at' => now(),
        ];

        if ($unitId) {
            $payload['unit_id'] = $unitId;
        }

        if ($medicine) {
            $before = $medicine->replicate()->toArray();
            $medicine->fill($payload);
            $medicine->save();
            $counts['updated']++;

            App\Models\MedicineAudit::create([
                'medicine_id' => $medicine->id,
                'user_id' => $user?->id,
                'action' => 'import_review_satuan_updated',
                'before' => $before,
                'after' => $medicine->fresh()->toArray(),
            ]);
        } else {
            $medicine = App\Models\Medicine::create($payload + [
                'code' => $code,
                'is_active' => false,
            ]);
            $counts['created']++;

            App\Models\MedicineAudit::create([
                'medicine_id' => $medicine->id,
                'user_id' => $user?->id,
                'action' => 'import_review_satuan_created',
                'after' => $medicine->only(['code', 'name', 'verification_status', 'is_active', 'unit_id']),
            ]);
        }
    }
});

echo json_encode($counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
