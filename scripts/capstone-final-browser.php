<?php

// Isolated browser fixture. No migrations or writes target the configured clinic database.
use App\Enums\Role;
use App\Http\Controllers\InventoryController;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\StockParameter;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$phase = ($argv[1] ?? '') === 'baseline' ? 'baseline' : 'final';
$output = base_path('Docs/Capstone_Testing_Final/Bukti_Pengujian');
$private = storage_path('app/qa/final-'.bin2hex(random_bytes(8)));
foreach ([$output, $private, $private.'/views'] as $dir) if (! is_dir($dir)) mkdir($dir, 0777, true);
$database = 'shifastock_final_'.bin2hex(random_bytes(8));
$password = bin2hex(random_bytes(24));
$connection = config('database.connections.mysql');
$connection['url'] = null;
$connection['database'] = 'information_schema';
config(['database.connections.final_admin' => $connection]);
$admin = DB::connection('final_admin');
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (! $socket) throw new RuntimeException('Port pengujian tidak tersedia.');
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$env = ['APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:'.$port,
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
    'DB_URL' => '', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
    'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'] ?? '',
    'DB_SOCKET' => $connection['unix_socket'] ?? '', 'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'array',
    'SESSION_COOKIE' => 'final_'.bin2hex(random_bytes(8)), 'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false',
    'APP_CONFIG_CACHE' => $private.'/unused-config.php', 'APP_ROUTES_CACHE' => $private.'/unused-routes.php',
    'APP_EVENTS_CACHE' => $private.'/unused-events.php', 'VIEW_COMPILED_PATH' => $private.'/views',
    'LOG_CHANNEL' => 'stderr', 'BCRYPT_ROUNDS' => '4', 'QUEUE_CONNECTION' => 'sync'];
$created = false;
$code = 1;
try {
    if (! preg_match('/\Ashifastock_final_[a-f0-9]{16}\z/', $database)) throw new RuntimeException('Nama database tidak aman.');
    $admin->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    (new Process([PHP_BINARY, base_path('artisan'), 'migrate', '--seed', '--force'], base_path(), $env))->setTimeout(300)->mustRun();
    $connection['database'] = $database;
    config(['database.default' => 'mysql', 'database.connections.mysql' => $connection,
        'hashing.bcrypt.rounds' => 4, 'session.driver' => 'array', 'cache.default' => 'array']);
    DB::purge('mysql');
    if (DB::connection()->getDatabaseName() !== $database) throw new RuntimeException('Database uji tidak sesuai.');
    $staff = User::create(['username' => 'petugas_simulasi', 'name' => 'Petugas Simulasi', 'email' => 'petugas@simulasi.example.test',
        'password' => $password, 'role' => Role::Pharmacy, 'is_active' => true]);
    User::create(['username' => 'guest_simulasi', 'name' => 'Guest Simulasi', 'email' => 'guest@simulasi.example.test',
        'password' => $password, 'role' => Role::Guest, 'is_active' => true]);
    Auth::setUser($staff);
    $unit = Unit::where('name', 'Tablet')->sole();
    $medicine = Medicine::create(['code' => 'FINAL-001', 'name' => 'Obat Simulasi Final', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
    $other = Medicine::create(['code' => 'FINAL-002', 'name' => 'Obat Simulasi Lain', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
    $warehouse = Location::where('code', 'GDG')->sole();
    $pharmacy = Location::where('code', 'APT')->sole();
    foreach ([$warehouse->id => 50, $pharmacy->id => 20] as $location => $quantity) {
        $request = Request::create('/qa-final-seed', 'POST', ['reference' => 'SIM-FINAL-'.$location, 'medicine_id' => $medicine->id,
            'location_id' => $location, 'batch_number' => 'SIM-BATCH-SAMA', 'expiry_date' => now('Asia/Jakarta')->addMonth()->toDateString(),
            'quantity' => $quantity, 'occurred_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i')]);
        $request->setUserResolver(fn () => $staff);
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);
        app()->call([app(InventoryController::class), 'storeOpeningBalance'], ['request' => $request]);
        StockParameter::create(['medicine_id' => $medicine->id, 'location_id' => $location, 'minimum_quantity' => 60]);
    }
    $meta = ['phase' => $phase, 'base_url' => $env['APP_URL'], 'port' => $port, 'medicine' => $medicine->id, 'other_medicine' => $other->id,
        'warehouse' => $warehouse->id, 'pharmacy' => $pharmacy->id, 'batch' => Batch::where('number', 'SIM-BATCH-SAMA')->sole()->id,
        'commit' => trim((new Process(['git', 'rev-parse', 'HEAD'], base_path()))->mustRun()->getOutput()),
        'started_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format(DATE_ATOM), 'simulation' => true];
    file_put_contents($private.'/metadata.json', json_encode($meta, JSON_THROW_ON_ERROR));
    $browser = new Process(['node', base_path('scripts/capstone-final-browser.cjs')], base_path(), $env + [
        'FINAL_META' => $private.'/metadata.json', 'FINAL_PASSWORD' => $password, 'FINAL_OUTPUT' => $output, 'FINAL_PHP' => PHP_BINARY]);
    $browser->setTimeout(300);
    $code = $browser->run(fn ($type, $text) => print str_replace($password, '[disembunyikan]', $text));
    if ($phase === 'final' && $code === 0) {
        $gudang = (float) StockBalance::where('medicine_id', $medicine->id)->where('location_id', $warehouse->id)->value('quantity');
        $apotek = (float) StockBalance::where('medicine_id', $medicine->id)->where('location_id', $pharmacy->id)->value('quantity');
        $report = app(\App\Services\InventoryReconciliationService::class)->report();
        if ($gudang !== 50.0 || $apotek !== 18.0 || $report['mismatched_pairs'] || $report['batch_identity_issues']) {
            throw new RuntimeException('Pascakondisi browser atau rekonsiliasi stok tidak sesuai.');
        }
        file_put_contents($output.'/Browser_Rekonsiliasi.json', json_encode(['warehouse_quantity' => $gudang, 'pharmacy_quantity' => $apotek,
            'expected' => ['warehouse' => 50, 'pharmacy' => 18], 'reconciliation' => $report, 'status' => 'Lulus'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo "PASS stok browser: Gudang tetap 50, Apotek 20 menjadi 18; rekonsiliasi tanpa selisih.\n";
    }
} catch (Throwable $error) {
    $message = $error->getMessage();
    foreach ([$password, $env['APP_KEY'], $env['DB_PASSWORD']] as $secret) if ($secret !== '') $message = str_replace($secret, '[disembunyikan]', $message);
    fwrite(STDERR, $message.PHP_EOL);
    $code = 1;
} finally {
    if ($created) {
        DB::disconnect('mysql');
        $admin->statement('DROP DATABASE `'.$database.'`');
        echo "Database simulasi browser dihapus; database klinik tidak diubah.\n";
    }
}
exit($code);
