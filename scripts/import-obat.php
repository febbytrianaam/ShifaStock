<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$source = json_decode(file_get_contents(__DIR__.'/../storage/app/imports/obat-analysis.json'), true, 512, JSON_THROW_ON_ERROR);
$counts = ['inserted' => 0, 'skipped' => 0, 'held' => count($source['held'])];
Illuminate\Support\Facades\DB::transaction(function () use ($source, &$counts) {
 foreach ($source['items'] as $row) {
  $existing = App\Models\Medicine::where('code', $row['code'])->first();
  if ($existing) {
   if ($existing->name !== $row['name']) throw new RuntimeException('Konflik kode: '.$row['code']);
   $counts['skipped']++; continue;
  }
  App\Models\Medicine::create([
   'code' => $row['code'], 'name' => $row['name'], 'dosage_form' => null,
   'unit_id' => null, 'is_active' => false,
   'import_source' => ['file' => $source['file'], 'sha256' => $source['sha256'], 'sheet' => $source['sheet'], 'row' => $row['row'], 'original' => $row['original'], 'needs_verification' => true],
  ]);
  $counts['inserted']++;
 }
});
echo json_encode($counts, JSON_PRETTY_PRINT).PHP_EOL;
