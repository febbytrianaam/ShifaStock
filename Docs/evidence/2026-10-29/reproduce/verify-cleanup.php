<?php

$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
if (! in_array($config['host'], ['127.0.0.1', 'localhost'], true)) throw new RuntimeException('Only local read-only verification is allowed.');
$config['database'] = 'information_schema';
$config['url'] = null;
config(['database.connections.documentation_readonly' => $config]);
$rows = Illuminate\Support\Facades\DB::connection('documentation_readonly')->select('SELECT SCHEMA_NAME FROM SCHEMATA');
$names = array_column(array_map(fn ($row) => (array) $row, $rows), 'SCHEMA_NAME');
$temporary = array_values(array_filter($names, fn ($name) => preg_match('/\Ashifastock_(?:evidence|issue_qa)_[a-f0-9]{16}\z/', $name)));
$result = ['checked_at' => now('Asia/Jakarta')->toIso8601String(), 'method' => 'Read-only information_schema query on localhost',
    'remaining_temporary_databases' => count($temporary), 'status' => $temporary ? 'Perlu ditinjau; tidak dihapus oleh verifier' : 'Lulus'];
file_put_contents(dirname(__DIR__).'/testing/Cleanup_Verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
