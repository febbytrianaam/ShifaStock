<?php

// Documentation runner: never migrates or seeds the configured clinic database.
use App\Enums\Role;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NightlyCheckController;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\MedicinePackage;
use App\Models\StockBalance;
use App\Models\StockOpnameSession;
use App\Models\StockParameter;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$root = base_path();
$output = base_path('Docs/Capstone_Screenshots');
$private = storage_path('app/qa/capstone-'.bin2hex(random_bytes(8)));
foreach ([$output, $output.'/Bukti_Pengujian', $private, $private.'/views'] as $directory) {
    if (! is_dir($directory)) mkdir($directory, 0777, true);
}
$commit = trim((new Process(['git', 'rev-parse', 'HEAD'], $root))->mustRun()->getOutput());
$runTime = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s P');
$database = 'shifastock_capstone_'.bin2hex(random_bytes(8));
$password = bin2hex(random_bytes(24));
$connection = config('database.connections.mysql');
$connection['url'] = null;
$connection['database'] = 'information_schema';
config(['database.connections.capstone_admin' => $connection]);
$admin = DB::connection('capstone_admin');
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (! $socket) throw new RuntimeException('Tidak dapat memilih port lokal.');
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$url = 'http://127.0.0.1:'.$port;
$environment = [
    'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_URL' => $url,
    'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_URL' => '', 'DB_HOST' => $connection['host'],
    'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'] ?? '',
    'DB_SOCKET' => $connection['unix_socket'] ?? '', 'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database',
    'SESSION_COOKIE' => 'capstone_'.bin2hex(random_bytes(8)), 'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false',
    'APP_CONFIG_CACHE' => $private.'/unused-config.php', 'APP_ROUTES_CACHE' => $private.'/unused-routes.php',
    'APP_EVENTS_CACHE' => $private.'/unused-events.php', 'VIEW_COMPILED_PATH' => $private.'/views',
    'BCRYPT_ROUNDS' => '4', 'LOG_CHANNEL' => 'stderr', 'QUEUE_CONNECTION' => 'sync',
];
$created = false;
$server = null;
$exitCode = 0;
$evidence = [];
$safeError = function (string $message) use ($password, $environment): string {
    foreach ([$password, $environment['APP_KEY'], $environment['DB_PASSWORD']] as $secret) {
        if ($secret !== '') $message = str_replace($secret, '[disembunyikan]', $message);
    }
    return preg_replace('/\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}/', '[hash disembunyikan]', $message);
};
$record = function (string $name, array $command, array $env = []) use (&$evidence, $output, $root, $safeError): Process {
    $process = new Process($command, $root, $env);
    $process->setTimeout(900);
    $process->run();
    $text = $safeError($process->getOutput().$process->getErrorOutput());
    $text = preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    file_put_contents($output.'/Bukti_Pengujian/'.$name.'.txt', $text);
    $evidence[] = ['command' => implode(' ', array_map(fn ($part) => is_file($part) ? basename($part) : $part, $command)),
        'file' => $name.'.txt', 'exit_code' => $process->getExitCode(), 'output' => $text];
    echo $name.': exit '.$process->getExitCode().PHP_EOL;
    return $process;
};

function capstoneAction(string $method, array $payload, array $parameters = [], string $controller = InventoryController::class): mixed
{
    $request = Request::create('/capstone-seed', 'POST', $payload);
    $request->setUserResolver(fn () => Auth::user());
    $request->setLaravelSession(app('session')->driver());
    app()->instance('request', $request);
    return app()->call([app($controller), $method], ['request' => $request, 'r' => $request] + $parameters);
}

try {
    if (! preg_match('/\Ashifastock_capstone_[a-f0-9]{16}\z/', $database)) throw new RuntimeException('Nama database uji tidak aman.');
    $admin->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    echo "Database simulasi terpisah dibuat; tidak mengubah database klinik.\n";
    $migration = new Process([PHP_BINARY, base_path('artisan'), 'migrate', '--seed', '--force'], $root, $environment);
    $migration->setTimeout(300)->mustRun();
    $connection['database'] = $database;
    config(['database.default' => 'mysql', 'database.connections.mysql' => $connection, 'session.driver' => 'array', 'cache.default' => 'array', 'hashing.bcrypt.rounds' => 4]);
    DB::purge('mysql');
    if (DB::connection()->getDatabaseName() !== $database) throw new RuntimeException('Koneksi simulasi tidak sesuai.');
    $users = [];
    foreach (['admin' => Role::Administrator, 'petugas' => Role::Pharmacy, 'kepala' => Role::Pharmacist, 'guest' => Role::Guest] as $username => $role) {
        $users[$username] = User::create(['username' => $username.'_simulasi', 'name' => match ($username) {
            'admin' => 'Admin Simulasi', 'petugas' => 'Petugas Simulasi', 'kepala' => 'Apoteker Simulasi', default => 'Guest Simulasi',
        }, 'email' => $username.'@simulasi.example.test', 'role' => $role, 'is_active' => true, 'password' => $password]);
    }
    Auth::setUser($users['kepala']);
    $warehouse = Location::where('code', 'GDG')->sole();
    $pharmacy = Location::where('code', 'APT')->sole();
    $tablet = Unit::where('name', 'Tablet')->sole();
    $capsule = Unit::where('name', 'Kapsul')->sole();
    $medicines = [];
    foreach (['Paracetamol 500 mg', 'Amoxicillin 500 mg', 'Cetirizine 10 mg', 'Antasida DOEN', 'Omeprazole 20 mg', 'Salbutamol 2 mg'] as $index => $name) {
        $unit = in_array($index, [1, 4]) ? $capsule : $tablet;
        $medicine = Medicine::create(['code' => sprintf('SIM-%03d', $index + 1), 'name' => $name, 'original_name' => $name,
            'dosage_form' => $unit->name, 'unit_id' => $unit->id, 'is_active' => true, 'verification_status' => 'terverifikasi',
            'verified_by' => $users['kepala']->id, 'verified_at' => now(), 'verification_note' => 'Data simulasi dokumentasi capstone; bukan persediaan klinik.']);
        MedicinePackage::create(['medicine_id' => $medicine->id, 'name' => 'Box', 'conversion_factor' => $index === 0 ? 100 : 30]);
        $medicines[] = $medicine;
        foreach ([$warehouse, $pharmacy] as $location) StockParameter::create(['medicine_id' => $medicine->id, 'location_id' => $location->id, 'minimum_quantity' => 50]);
    }
    $today = now('Asia/Jakarta');
    $date = fn (int $days) => $today->copy()->subDays($days)->format('Y-m-d').'T09:00';
    $expiry = $today->copy()->addYear()->format('Y-m-d');
    capstoneAction('storeOpeningBalance', ['reference' => 'SIM-STOK-001', 'medicine_id' => $medicines[0]->id,
        'location_id' => $warehouse->id, 'batch_number' => 'SIM-PCT-01', 'expiry_date' => $today->copy()->addMonths(2)->format('Y-m-d'),
        'quantity' => 120, 'occurred_at' => $date(6), 'notes' => 'Stok awal simulasi capstone.']);
    foreach ([0, 1, 2] as $index) capstoneAction('storeReceipt', ['reference' => 'SIM-FKT-00'.($index + 1), 'action' => 'post',
        'supplier_invoice_number' => 'SIM-PBF-00'.($index + 1), 'counterparty' => 'PBF Simulasi Capstone',
        'destination_location_id' => $warehouse->id, 'occurred_at' => $date(5 - $index), 'notes' => 'Penerimaan simulasi, bukan transaksi klinik.',
        'items' => [['medicine_id' => $medicines[$index]->id, 'batch_number' => 'SIM-BATCH-'.($index + 1), 'expiry_date' => $expiry,
            'input_quantity' => $index === 0 ? 2 : 3, 'input_unit_name' => 'Box', 'conversion_factor' => $index === 0 ? 100 : 30]]]);
    foreach ([3, 4, 5] as $index) capstoneAction('storeOpeningBalance', ['reference' => 'SIM-STOK-00'.($index + 1),
        'medicine_id' => $medicines[$index]->id, 'location_id' => $warehouse->id, 'batch_number' => 'SIM-BATCH-'.($index + 1),
        'expiry_date' => $expiry, 'quantity' => 30 + $index * 10, 'occurred_at' => $date(3), 'notes' => 'Persediaan simulasi capstone.']);
    foreach ([50, 40, 30] as $index => $quantity) capstoneAction('storeTransfer', ['reference' => 'SIM-TRF-00'.($index + 1),
        'medicine_id' => $medicines[$index]->id, 'source_location_id' => $warehouse->id, 'destination_location_id' => $pharmacy->id,
        'quantity' => $quantity, 'occurred_at' => $date(2), 'notes' => 'Distribusi internal simulasi.']);
    capstoneAction('storeIssue', ['reference' => 'SIM-OUT-001', 'medicine_id' => $medicines[0]->id, 'source_location_id' => $pharmacy->id,
        'quantity' => 10, 'occurred_at' => $date(1), 'source_reference' => 'SIM-LAYANAN-001', 'notes' => 'Pengeluaran simulasi tanpa data pasien.']);
    $firstBatch = Batch::where('number', 'SIM-PCT-01')->sole();
    capstoneAction('storeReturn', ['reference' => 'SIM-RET-001', 'medicine_id' => $medicines[0]->id, 'batch_id' => $firstBatch->id,
        'source_location_id' => $warehouse->id, 'quantity' => 5, 'occurred_at' => $date(1), 'counterparty' => 'PBF Simulasi Capstone',
        'reason' => 'Kemasan rusak pada data simulasi', 'notes' => 'Retur dokumentasi akademik.']);
    capstoneAction('storeSpecialAdjustment', ['reference' => 'SIM-ADJ-001', 'medicine_id' => $medicines[0]->id, 'batch_id' => $firstBatch->id,
        'location_id' => $pharmacy->id, 'condition' => 'rusak', 'direction' => 'decrease', 'quantity' => 2,
        'occurred_at' => $date(1), 'notes' => 'Simulasi tablet rusak, bukan kejadian klinik.']);
    capstoneAction('storeReceipt', ['reference' => 'SIM-FKT-004', 'action' => 'draft', 'supplier_invoice_number' => 'SIM-PBF-004',
        'counterparty' => 'PBF Simulasi Capstone', 'destination_location_id' => $warehouse->id, 'occurred_at' => $date(0),
        'notes' => 'Draf simulasi untuk demonstrasi input faktur.', 'items' => [
            ['medicine_id' => $medicines[0]->id, 'batch_number' => 'SIM-DRAF-01', 'expiry_date' => $expiry, 'input_quantity' => 2, 'input_unit_name' => 'Box', 'conversion_factor' => 100],
            ['medicine_id' => $medicines[1]->id, 'batch_number' => 'SIM-DRAF-02', 'expiry_date' => $expiry, 'input_quantity' => 3, 'input_unit_name' => 'Box', 'conversion_factor' => 30],
        ]]);
    Auth::setUser($users['petugas']);
    $makeDaily = function (string $day, string $action) use ($pharmacy, $medicines): int {
        capstoneAction('store', ['date' => $day, 'location_id' => $pharmacy->id], [], NightlyCheckController::class);
        $check = DB::table('nightly_checks')->where('date', $day)->sole();
        $items = [];
        foreach (DB::table('nightly_check_items')->where('nightly_check_id', $check->id)->get() as $item) {
            $quantity = (int) StockBalance::where('medicine_id', $item->medicine_id)->where('location_id', $pharmacy->id)->value('quantity');
            $items[$item->id] = ['quantity' => $quantity, 'unit' => Medicine::findOrFail($item->medicine_id)->unit->name,
                'is_low' => $quantity < 50 ? 1 : 0, 'requested_quantity' => $quantity < 50 ? 30 : 0, 'note' => 'Hitungan simulasi capstone.'];
        }
        capstoneAction('save', ['action' => $action, 'items' => $items], ['id' => $check->id], NightlyCheckController::class);
        return $check->id;
    };
    $baselineId = $makeDaily($today->copy()->subDay()->toDateString(), 'submit');
    Auth::setUser($users['kepala']);
    capstoneAction('review', ['action' => 'approve', 'review_note' => 'Baseline simulasi telah diperiksa.'], ['id' => $baselineId], NightlyCheckController::class);
    Auth::setUser($users['petugas']);
    $dailyId = $makeDaily($today->toDateString(), 'save');
    capstoneAction('storeOpnameSession', ['period' => $today->format('Y-m'), 'location_id' => $warehouse->id,
        'cutoff_at' => $date(0), 'notes' => 'Sesi opname bulanan simulasi capstone.']);
    $session = StockOpnameSession::sole();
    $counts = [];
    foreach ($session->items as $item) $counts[$item->id] = ['physical_quantity' => (float) $item->system_quantity, 'reason' => 'Sesuai hitungan simulasi.'];
    capstoneAction('saveOpnameSession', ['items' => $counts], ['session' => $session]);
    $reconciliation = app(InventoryReconciliationService::class)->report();
    if ($reconciliation['mismatched_pairs'] !== 0) throw new RuntimeException('Data simulasi belum konsisten.');
    $metadata = ['commit' => $commit, 'captured_at' => $runTime, 'simulation' => true, 'viewport' => ['width' => 1440, 'height' => 900],
        'base_url' => $url, 'daily_id' => $dailyId, 'session_id' => $session->id,
        'draft_id' => App\Models\InventoryReceipt::where('reference', 'SIM-FKT-004')->sole()->id,
        'medicine_id' => $medicines[0]->id, 'batch_id' => $firstBatch->id, 'warehouse_id' => $warehouse->id, 'pharmacy_id' => $pharmacy->id,
        'start' => $today->copy()->subDays(7)->toDateString(), 'end' => $today->toDateString(), 'period' => $today->format('Y-m')];
    file_put_contents($private.'/metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $sqlite = $record('01_Regresi_SQLite', [PHP_BINARY, base_path('scripts/test-inventory.php')], ['INVENTORY_TEST_TIMEOUT' => '900']);
    if ($sqlite->isSuccessful() && is_file(storage_path('app/qa/inventory/phpunit-results.xml'))) {
        copy(storage_path('app/qa/inventory/phpunit-results.xml'), $output.'/Bukti_Pengujian/phpunit-results.xml');
    } elseif (is_file($output.'/Bukti_Pengujian/phpunit-results.xml')) {
        unlink($output.'/Bukti_Pengujian/phpunit-results.xml');
    }
    $record('02_Konkruensi_MySQL', [PHP_BINARY, base_path('scripts/test-inventory-mysql.php')]);
    $record('03_Build_Frontend', ['cmd.exe', '/d', '/s', '/c', 'npm run build']);
    $record('04_Rekonsiliasi_Simulasi', [PHP_BINARY, base_path('artisan'), 'inventory:reconcile'], $environment);
    $lines = ["SHIFASTOCK - BUKTI EKSEKUSI PENGUJIAN AKTUAL", 'Commit: '.$commit, 'Waktu mulai: '.$runTime,
        'Data simulasi; database pengujian terpisah. Tidak menggunakan data klinik.', ''];
    foreach ($evidence as $entry) {
        $lines[] = '=== '.$entry['file'].' | exit '.$entry['exit_code'].' ===';
        if ($entry['file'] === '01_Regresi_SQLite.txt') {
            foreach (explode("\n", $entry['output']) as $line) if (preg_match('/PHPUnit |Runtime:|Tests:|Assertions:|OK \(|FAILURES|ERRORS|Time:/', $line)) $lines[] = trim($line);
        } elseif ($entry['file'] === '03_Build_Frontend.txt') {
            foreach (explode("\n", $entry['output']) as $line) if (preg_match('/vite v|built in|error/i', $line)) $lines[] = trim($line);
        } else $lines[] = trim($entry['output']);
        $lines[] = '';
    }
    file_put_contents($output.'/Bukti_Pengujian/Ringkasan_Eksekusi_Aktual.txt', implode("\n", $lines));
    file_put_contents($output.'/Bukti_Pengujian/hasil-eksekusi.json', json_encode(['commit' => $commit, 'started_at' => $runTime,
        'commands' => array_map(fn ($entry) => array_diff_key($entry, ['output' => true]), $evidence)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    (new Process([PHP_BINARY, base_path('scripts/capstone-test-report.php')], $root))->mustRun();
    $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', public_path(),
        base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], public_path(), $environment);
    $server->setTimeout(null)->start();
    $deadline = microtime(true) + 40;
    do {
        if (! $server->isRunning()) throw new RuntimeException('Server simulasi gagal dimulai.');
        $probe = @file_get_contents($url.'/login', false, stream_context_create(['http' => ['timeout' => 2]]));
        if ($probe !== false && str_contains($probe, 'Masuk')) break;
        usleep(200000);
    } while (microtime(true) < $deadline);
    if (! $probe) throw new RuntimeException('Server simulasi tidak siap.');
    $browser = new Process(['node', base_path('scripts/capstone-capture.cjs')], $root, ['CAPSTONE_METADATA' => $private.'/metadata.json',
        'CAPSTONE_PASSWORD' => $password, 'CAPSTONE_OUTPUT' => $output]);
    $browser->setTimeout(900);
    $browser->run(fn ($type, $text) => print($safeError($text)));
    if (! $browser->isSuccessful()) $exitCode = 1;
    foreach ($evidence as $entry) if ($entry['exit_code'] !== 0) $exitCode = 1;
} catch (Throwable $exception) {
    file_put_contents($output.'/Kendala_Eksekusi.txt', $safeError($exception->getMessage()));
    fwrite(STDERR, 'Kendala: '.$safeError($exception->getMessage()).PHP_EOL);
    $exitCode = 1;
} finally {
    if ($server?->isRunning()) $server->stop();
    DB::disconnect('mysql');
    if ($created && preg_match('/\Ashifastock_capstone_[a-f0-9]{16}\z/', $database)) {
        $admin->statement('DROP DATABASE `'.$database.'`');
        echo "Database simulasi dan server dokumentasi sudah dihentikan/dihapus.\n";
    }
}
exit($exitCode);
