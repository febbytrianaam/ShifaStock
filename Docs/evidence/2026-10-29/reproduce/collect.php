<?php

// Documentation-only runner. Never migrates or seeds the configured clinic/UAT database.
use App\Enums\Role;
use App\Http\Controllers\InventoryController;
use App\Models\Batch;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$output = dirname(__DIR__);
foreach (['screenshots', 'testing', 'supporting'] as $folder) {
    if (! is_dir($output.'/'.$folder)) mkdir($output.'/'.$folder, 0777, true);
}
$private = storage_path('app/qa/evidence-'.bin2hex(random_bytes(8)));
mkdir($private.'/views', 0700, true);
$commit = trim((new Process(['git', 'rev-parse', 'HEAD'], $root))->mustRun()->getOutput());
if ($commit !== '05ba910ce8804ffc9e049a17e96295bb59714cb9') throw new RuntimeException('Baseline differs; review the documentation runner first.');
(new Process(['git', 'diff', '--exit-code', 'HEAD', '--', 'app', 'routes', 'resources', 'database', 'composer.json', 'composer.lock'], $root))->mustRun();
$legacy = [];
foreach (glob(base_path('Docs/Capstone_Screenshots/Gambar_*.png')) as $file) $legacy[basename($file)] = hash_file('sha256', $file);
$connection = config('database.connections.mysql');
if (! in_array($connection['host'], ['127.0.0.1', 'localhost'], true)) throw new RuntimeException('Only a local MySQL server is allowed.');
$database = 'shifastock_evidence_'.bin2hex(random_bytes(8));
$connection['url'] = null;
$connection['database'] = 'information_schema';
config(['database.connections.evidence_admin' => $connection]);
$admin = DB::connection('evidence_admin');
$socket = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$password = bin2hex(random_bytes(24));
$env = ['APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:'.$port,
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
    'DB_URL' => '', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
    'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'] ?? '',
    'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'evidence_'.bin2hex(random_bytes(8)), 'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'stderr', 'BCRYPT_ROUNDS' => '4',
    'APP_CONFIG_CACHE' => $private.'/unused-config.php', 'APP_ROUTES_CACHE' => $private.'/unused-routes.php',
    'APP_EVENTS_CACHE' => $private.'/unused-events.php', 'VIEW_COMPILED_PATH' => $private.'/views',
    'EVIDENCE_PASSWORD' => $password, 'EVIDENCE_OUTPUT' => $output, 'EVIDENCE_META' => $private.'/meta.json'];
$safe = fn (string $text) => str_replace(array_filter([$password, $env['APP_KEY'], $env['DB_PASSWORD']]), '[redacted]', $text);
$execute = function (array $command, string $log, array $environment = []) use ($root, $output, $safe): string {
    $process = new Process($command, $root, $environment);
    $process->setTimeout(900)->run();
    $text = preg_replace('/\x1b\[[0-9;]*m/', '', $safe($process->getOutput().$process->getErrorOutput()));
    file_put_contents($output.'/testing/'.$log, $text);
    if (! $process->isSuccessful()) throw new RuntimeException('Execution failed: '.$log.'; see sanitized log.');
    echo 'PASS '.$log.PHP_EOL;

    return $text;
};
$created = false;
$server = null;
$exitCode = 0;
$audit = ['commit' => $commit, 'started_at' => now('Asia/Jakarta')->toIso8601String(), 'simulation' => true, 'php' => PHP_VERSION,
    'laravel' => app()->version(), 'viewport' => ['width' => 1440, 'height' => 900], 'legacy_count' => count($legacy), 'cleanup' => false];
try {
    $captureOnly = in_array('--capture-only', $argv, true);
    if ($captureOnly) {
        $previous = json_decode(file_get_contents($output.'/testing/Run.json'), true, flags: JSON_THROW_ON_ERROR);
        if ($previous['commit'] !== $commit || ! $previous['cleanup']) throw new RuntimeException('Cannot reuse evidence from another baseline or an unclean run.');
        $junit = simplexml_load_file($output.'/testing/phpunit-results.xml')->testsuite[0];
        $browserResults = json_decode(file_get_contents($output.'/testing/Playwright.json'), true, flags: JSON_THROW_ON_ERROR);
        if ((int) $junit['failures'] || (int) $junit['errors'] || count($browserResults['results']) !== 16 || $browserResults['errors']) throw new RuntimeException('Reusable test evidence is not successful.');
        foreach ($browserResults['results'] as $result) if ($result['status'] !== 'Lulus') throw new RuntimeException('Browser scenario failed.');
        $audit['tests_reused_from_same_session'] = $previous['tests_reused_from_same_session'] ?? $previous['started_at'];
        $audit['import_evidence'] = $previous['import_evidence'];
        echo 'Reusing actual test artifacts from the preceding attempt on the same commit.'.PHP_EOL;
    } else {
    $execute([PHP_BINARY, base_path('scripts/test-inventory.php')], 'PHPUnit.log', ['INVENTORY_TEST_TIMEOUT' => '900']);
    copy(storage_path('app/qa/inventory/phpunit-results.xml'), $output.'/testing/phpunit-results.xml');
    $text = $execute([PHP_BINARY, base_path('scripts/test-issue-import.php')], 'Playwright.log');
    if (! preg_match('/Evidence:\s*([^\r\n]+)/', $text, $match)) throw new RuntimeException('Import evidence directory missing.');
    $importEvidence = realpath(trim($match[1]));
    if (! $importEvidence || ! str_starts_with($importEvidence, realpath(storage_path('app/qa/issue-imports')).DIRECTORY_SEPARATOR)) throw new RuntimeException('Unexpected import evidence directory.');
    foreach (['Playwright.json', 'MySQL.json'] as $file) copy($importEvidence.'/'.$file, $output.'/testing/'.$file);
    foreach (['Posting_Berhasil.png' => 'screenshots/SS-03-import-neoklinik.png',
        'Pratinjau_Belum_Disetujui.png' => 'supporting/SS-03-validasi-pencocokan.png',
        'Impor_1440.png' => 'supporting/SS-03-form-impor.png'] as $from => $to) copy($importEvidence.'/'.$from, $output.'/'.$to);
    $audit['import_evidence'] = 'storage/app/qa/issue-imports/'.basename($importEvidence);
    $execute(['cmd.exe', '/d', '/s', '/c', 'npm run build'], 'Build.log');
    }
    if (! preg_match('/\Ashifastock_evidence_[a-f0-9]{16}\z/', $database)) throw new RuntimeException('Unsafe temporary database name.');
    $admin->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $execute([PHP_BINARY, base_path('artisan'), 'migrate', '--seed', '--force'], 'Fixture_Migration.log', $env);
    $connection['database'] = $database;
    config(['database.default' => 'mysql', 'database.connections.mysql' => $connection, 'session.driver' => 'array', 'cache.default' => 'array', 'hashing.bcrypt.rounds' => 4]);
    DB::purge('mysql');
    if (DB::connection()->getDatabaseName() !== $database) throw new RuntimeException('Fixture connection mismatch.');
    $users = [];
    foreach (['petugas' => Role::Pharmacy, 'apoteker' => Role::Pharmacist] as $name => $role) {
        $users[$name] = User::create(['username' => $name.'_simulasi', 'name' => ucfirst($name).' Simulasi',
            'email' => $name.'@evidence.example.test', 'password' => $password, 'role' => $role, 'is_active' => true, 'onboarding_seen_at' => now()]);
    }
    Auth::setUser($users['apoteker']);
    $warehouse = Location::where('code', 'GDG')->sole();
    $pharmacy = Location::where('code', 'APT')->sole();
    $unit = Unit::where('name', 'Tablet')->sole();
    $medicines = [];
    foreach (['Paracetamol 500 mg', 'Cetirizine 10 mg', 'Salbutamol 2 mg'] as $index => $name) {
        $medicines[] = Medicine::create(['code' => sprintf('SIM-%03d', $index + 1), 'name' => $name.' (Simulasi)',
            'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true, 'verification_status' => 'terverifikasi']);
    }
    $counter = 0;
    foreach ($medicines as $index => $medicine) {
        foreach ([$warehouse, $pharmacy] as $location) {
            $payload = ['reference' => 'SIM-AWAL-'.(++$counter), 'medicine_id' => $medicine->id, 'location_id' => $location->id,
                'batch_number' => $index === 0 ? 'SIM-BATCH-SAMA' : 'SIM-BATCH-'.($index + 1),
                'expiry_date' => now('Asia/Jakarta')->addMonths(2)->toDateString(),
                'quantity' => $location->id === $warehouse->id ? [120, 80, 60][$index] : [30, 20, 15][$index],
                'occurred_at' => now('Asia/Jakarta')->subDays(3)->format('Y-m-d\TH:i'), 'notes' => 'Fixture simulasi akademik, bukan stok klinik.'];
            $request = Request::create('/documentation-fixture', 'POST', $payload);
            $request->setUserResolver(fn () => $users['apoteker']);
            $request->setLaravelSession(app('session')->driver());
            app()->instance('request', $request);
            app()->call([app(InventoryController::class), 'storeOpeningBalance'], ['request' => $request]);
        }
    }
    foreach ([$warehouse, $pharmacy] as $location) {
        $request = Request::create('/documentation-fixture', 'POST', ['reference' => 'SIM-AWAL-'.(++$counter),
            'medicine_id' => $medicines[0]->id, 'location_id' => $location->id, 'batch_number' => 'SIM-BATCH-LANJUT',
            'expiry_date' => now('Asia/Jakarta')->addYear()->toDateString(), 'quantity' => $location->id === $warehouse->id ? 80 : 40,
            'occurred_at' => now('Asia/Jakarta')->subDays(3)->format('Y-m-d\TH:i'), 'notes' => 'Batch kedua simulasi.']);
        $request->setUserResolver(fn () => $users['apoteker']);
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);
        app()->call([app(InventoryController::class), 'storeOpeningBalance'], ['request' => $request]);
    }
    $meta = ['base_url' => $env['APP_URL'], 'commit' => $commit, 'simulation' => true, 'date' => now('Asia/Jakarta')->toDateString(),
        'warehouse' => $warehouse->id, 'pharmacy' => $pharmacy->id, 'medicines' => array_map(fn ($medicine) => ['id' => $medicine->id, 'name' => $medicine->name, 'code' => $medicine->code], $medicines),
        'shared_batch' => Batch::where('number', 'SIM-BATCH-SAMA')->sole()->id];
    file_put_contents($private.'/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', public_path(), base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], public_path(), $env);
    $server->setTimeout(null)->start();
    $execute(['node', __DIR__.'/capture.cjs'], 'Feature_Capture.log', $env);
    $reconciliation = app(InventoryReconciliationService::class)->report();
    if ($reconciliation['mismatched_pairs'] || $reconciliation['batch_identity_issues']) throw new RuntimeException('Fixture stock/batch reconciliation mismatch.');
    foreach ([[$medicines[0]->id, $warehouse->id, 185], [$medicines[0]->id, $pharmacy->id, 80],
        [$medicines[1]->id, $warehouse->id, 72], [$medicines[1]->id, $pharmacy->id, 28]] as [$medicine, $location, $expected]) {
        if ((float) StockBalance::where('medicine_id', $medicine)->where('location_id', $location)->value('quantity') !== (float) $expected) throw new RuntimeException('Captured workflow stock outcome mismatch.');
    }
    file_put_contents($output.'/testing/Capture_Reconciliation.json', json_encode(['status' => 'Lulus', 'mismatched_pairs' => 0, 'batch_identity_issues' => 0,
        'simulation' => true, 'paracetamol' => ['gudang' => 185, 'apotek' => 80], 'cetirizine' => ['gudang' => 72, 'apotek' => 28]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} catch (Throwable $error) {
    $exitCode = 1;
    $audit['error'] = $safe($error->getMessage());
    fwrite(STDERR, $audit['error'].PHP_EOL);
} finally {
    if ($server?->isRunning()) {
        if (PHP_OS_FAMILY === 'Windows') {
            // Symfony uses a cmd wrapper on Windows. Identify its PHP listener by the runner's private loopback port.
            $pattern = '^\s*TCP\s+127\.0\.0\.1:'.$port.'\s+0\.0\.0\.0:0\s+LISTENING\s+(\d+)\s*$';
            $stop = '$listener = netstat -ano -p tcp | Select-String \''.$pattern.'\'; if ($listener) { Stop-Process -Id ([int] $listener.Matches[0].Groups[1].Value) -ErrorAction Stop }';
            (new Process(['powershell.exe', '-NoProfile', '-Command', $stop]))->setTimeout(15)->mustRun();
            $server->wait();
        } else $server->stop();
    }
    DB::disconnect('mysql');
    if ($created && preg_match('/\Ashifastock_evidence_[a-f0-9]{16}\z/', $database)) $admin->statement('DROP DATABASE `'.$database.'`');
    $audit['cleanup'] = true;
    $audit['completed_at'] = now('Asia/Jakarta')->toIso8601String();
    $audit['legacy_preserved'] = true;
    foreach ($legacy as $file => $hash) if (hash_file('sha256', base_path('Docs/Capstone_Screenshots/'.$file)) !== $hash) $audit['legacy_preserved'] = false;
    $audit['exit_code'] = $exitCode;
    file_put_contents($output.'/testing/Run.json', json_encode($audit, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo 'Evidence: '.$output.PHP_EOL;
}
exit($exitCode);
