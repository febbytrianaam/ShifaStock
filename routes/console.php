<?php

use App\Services\DemoInventoryCleanup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inventory:clean-demo {--apply : Terapkan manifest uji lokal setelah backup}', function () {
    try {
        $result = app(DemoInventoryCleanup::class)->run((bool) $this->option('apply'));
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return 0;
    } catch (Throwable $error) {
        $this->error($error->getMessage());

        return 1;
    }
})->purpose('Pratinjau/pembersihan terbatas data demo yang sudah diaudit; tidak untuk produksi');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('issues:purge-import-drafts', function () {
    $count = \App\Models\IssueImport::where('status', 'draft')->where('created_at', '<', now()->subDays(7))
        ->update(['payload' => null, 'status' => 'expired']);
    $this->info($count.' pratinjau kedaluwarsa dibersihkan; ledger transaksi terposting tetap disimpan.');
})->purpose('Hapus payload pratinjau impor yang belum diposting setelah tujuh hari');

\Illuminate\Support\Facades\Schedule::command('issues:purge-import-drafts')->daily();
