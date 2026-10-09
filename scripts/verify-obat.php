<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode(['total_master' => App\Models\Medicine::count(), 'imported' => App\Models\Medicine::whereNotNull('import_source')->count(), 'imported_active' => App\Models\Medicine::whereNotNull('import_source')->where('is_active', true)->count()], JSON_PRETTY_PRINT).PHP_EOL;
