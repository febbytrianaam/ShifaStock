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
$phase = in_array($argv[1] ?? '', ['baseline', 'onboarding', 'transfer', 'selected-nightly', 'partial-nightly'], true) ? $argv[1] : 'final';
$output = match (true) {
    $phase === 'onboarding' => storage_path('app/qa/onboarding'),
    $phase === 'transfer' => storage_path('app/qa/multi-transfer-browser'),
    $phase === 'selected-nightly' => storage_path('app/qa/selected-nightly-browser'),
    $phase === 'partial-nightly' => storage_path('app/qa/partial-nightly-browser'),
    ($argv[1] ?? '') === 'regression' => storage_path('app/qa/inventory-browser'),
    default => base_path('Docs/Capstone_Testing_Final/Bukti_Pengujian'),
};
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
    if (in_array($phase, ['onboarding', 'selected-nightly', 'partial-nightly'], true)) {
        foreach (['admin_simulasi' => Role::Administrator, 'apoteker_simulasi' => Role::Pharmacist] as $username => $role) {
            User::create(['username' => $username, 'name' => $role->label(), 'email' => $username.'@simulasi.example.test', 'password' => $password, 'role' => $role, 'is_active' => true]);
        }
    }
    if ($phase !== 'onboarding') User::query()->update(['onboarding_seen_at' => now()]);
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
    if ($phase === 'transfer') {
        foreach ([[$medicine, 'SIM-LATE', 10, 6], [$other, 'SIM-OTHER', 20, 3]] as [$item, $number, $quantity, $months]) {
            $request = Request::create('/qa-transfer-seed', 'POST', ['reference' => 'SEED-'.$number, 'medicine_id' => $item->id,
                'location_id' => $warehouse->id, 'batch_number' => $number, 'expiry_date' => now('Asia/Jakarta')->addMonths($months)->toDateString(),
                'quantity' => $quantity, 'occurred_at' => now('Asia/Jakarta')->format('Y-m-d\TH:i')]);
            $request->setUserResolver(fn () => $staff);
            $request->setLaravelSession(app('session')->driver());
            app()->instance('request', $request);
            app()->call([app(InventoryController::class), 'storeOpeningBalance'], ['request' => $request]);
        }
    }
    if ($phase === 'selected-nightly') {
        $printMedicines = collect(range(1, 70))->map(fn ($index) => Medicine::create([
            'code' => sprintf('SIM-PRINT-%03d', $index), 'name' => sprintf('Obat Simulasi Cetak %03d', $index),
            'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true,
        ]));
        $date = now('Asia/Jakarta')->subDay()->toDateString();
        $invokeDaily = function (string $method, array $payload, array $parameters = []) use ($staff) {
            $request = Request::create('/qa-report-fixture', 'POST', $payload);
            $request->setUserResolver(fn () => $staff);
            $request->setLaravelSession(app('session')->driver());
            app()->instance('request', $request);
            return app()->call([app(\App\Http\Controllers\NightlyCheckController::class), $method], ['r' => $request] + $parameters);
        };
        $invokeDaily('store', ['date' => $date, 'location_id' => $warehouse->id, 'scope' => 'selected', 'medicine_ids' => $printMedicines->pluck('id')->all()]);
        $longCheckId = DB::table('nightly_checks')->where('date', $date)->where('location_id', $warehouse->id)->value('id');
        $longItems = DB::table('nightly_check_items')->where('nightly_check_id', $longCheckId)->get()->values();
        $invokeDaily('save', ['action' => 'save', 'items' => $longItems->mapWithKeys(fn ($item, $index) => [$item->id => [
            'quantity' => $index % 7 === 0 ? null : ($index % 3 === 0 ? 2 : 0), 'unit' => 'Tablet',
            'is_low' => $index % 3 === 0, 'requested_quantity' => $index % 2 === 0 ? 10 : null,
            'note' => 'Catatan simulasi pemeriksaan. Periksa kemasan dan jumlah fisik sebelum laporan diajukan untuk peninjauan.',
        ]])->all()], ['id' => $longCheckId]);
        // Historical reports must still print every row after masters become inactive.
        Medicine::whereIn('id', $printMedicines->pluck('id'))->update(['is_active' => false]);
    }
    if ($phase === 'partial-nightly') {
        $stressMedicines = collect(range(1, 30))->map(fn ($n) => Medicine::create([
            'code' => sprintf('WARN-%03d', $n), 'name' => sprintf('Obat Uji Peringatan %03d', $n).($n === 26 ? ' <img src=x onerror=window.unsafeWarning=1>' : ''),
            'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true,
        ]));
        $request = Request::create('/qa-partial-fixture', 'POST', ['date' => now('Asia/Jakarta')->subDay()->toDateString(), 'location_id' => $warehouse->id,
            'scope' => 'selected', 'medicine_ids' => $stressMedicines->pluck('id')->all()]);
        $request->setUserResolver(fn () => $staff);
        $request->setLaravelSession(app('session')->driver());
        app()->instance('request', $request);
        app()->call([app(\App\Http\Controllers\NightlyCheckController::class), 'store'], ['r' => $request]);
        $stressCheckId = DB::table('nightly_checks')->latest('id')->value('id');
        Medicine::whereIn('id', $stressMedicines->pluck('id'))->update(['is_active' => false]);
    }
    $meta = ['phase' => $phase, 'base_url' => $env['APP_URL'], 'port' => $port, 'medicine' => $medicine->id, 'other_medicine' => $other->id,
        'stress_check' => $stressCheckId ?? null,
        'long_check' => $longCheckId ?? null,
        'warehouse' => $warehouse->id, 'pharmacy' => $pharmacy->id, 'batch' => Batch::where('number', 'SIM-BATCH-SAMA')->sole()->id,
        'commit' => trim((new Process(['git', 'rev-parse', 'HEAD'], base_path()))->mustRun()->getOutput()),
        'started_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format(DATE_ATOM), 'simulation' => true];
    file_put_contents($private.'/metadata.json', json_encode($meta, JSON_THROW_ON_ERROR));
    $browserScript = match ($phase) {
        'onboarding' => 'scripts/test-onboarding-browser.cjs',
        'transfer' => 'scripts/test-multi-transfer-browser.cjs',
        'selected-nightly' => 'scripts/test-selected-nightly-browser.cjs',
        'partial-nightly' => 'scripts/test-partial-nightly-browser.cjs',
        default => 'scripts/capstone-final-browser.cjs',
    };
    $browser = new Process(['node', base_path($browserScript)], base_path(), $env + [
        'FINAL_META' => $private.'/metadata.json', 'FINAL_PASSWORD' => $password, 'FINAL_OUTPUT' => $output, 'FINAL_PHP' => PHP_BINARY]);
    $browser->setTimeout(300);
    $code = $browser->run(fn ($type, $text) => print str_replace($password, '[disembunyikan]', $text));
    if ($phase === 'partial-nightly' && $code === 0) {
        $report = app(\App\Services\InventoryReconciliationService::class)->report();
        if ((float) StockBalance::where('medicine_id', $medicine->id)->where('location_id', $warehouse->id)->value('quantity') !== 50.0
            || (float) StockBalance::where('medicine_id', $medicine->id)->where('location_id', $pharmacy->id)->value('quantity') !== 18.0
            || StockBalance::where('medicine_id', $other->id)->exists()
            || $report['mismatched_pairs'] || $report['batch_identity_issues']) throw new RuntimeException('Pemeriksaan sebagian mengubah obat yang belum diperiksa atau rekonsiliasi tidak sesuai.');
        $approved = DB::table('nightly_checks')->where('status', 'approved')->sole();
        if (DB::table('nightly_check_items')->where('nightly_check_id', $approved->id)->whereNull('quantity')->count() !== 1) throw new RuntimeException('Obat belum diperiksa tidak dipertahankan sebagai null.');
        echo "PASS hitungan sebagian: Apotek 20 menjadi 18, Gudang tetap 50, obat belum diperiksa tetap tanpa stok baru; rekonsiliasi sesuai.\n";
    }
    if ($phase === 'selected-nightly' && $code === 0) {
        $report = app(\App\Services\InventoryReconciliationService::class)->report();
        if ((float) StockBalance::where('medicine_id', $medicine->id)->where('location_id', $warehouse->id)->value('quantity') !== 50.0
            || (float) StockBalance::where('medicine_id', $medicine->id)->where('location_id', $pharmacy->id)->value('quantity') !== 0.0
            || $report['mismatched_pairs'] || $report['batch_identity_issues']) throw new RuntimeException('Persetujuan sebagian mengubah stok di luar laporan atau rekonsiliasi tidak sesuai.');
        $checked = DB::table('nightly_checks')->where('status', 'approved')->sole();
        if ($checked->scope !== 'selected' || DB::table('nightly_check_items')->where('nightly_check_id', $checked->id)->count() !== 2
            || (float) StockBalance::where('medicine_id', $other->id)->where('location_id', $pharmacy->id)->value('quantity') !== 0.0) throw new RuntimeException('Cakupan laporan sebagian atau obat tambahan tidak sesuai.');
        echo "PASS persetujuan hanya mengubah Apotek 20 menjadi 0, Gudang tetap 50; rekonsiliasi sesuai.\n";
    }
    if ($phase === 'transfer' && $code === 0) {
        $report = app(\App\Services\InventoryReconciliationService::class)->report();
        foreach ([[$medicine->id, $warehouse->id, 8], [$medicine->id, $pharmacy->id, 72],
            [$other->id, $warehouse->id, 13], [$other->id, $pharmacy->id, 7]] as [$medicineId, $locationId, $expected]) {
            if ((float) StockBalance::where('medicine_id', $medicineId)->where('location_id', $locationId)->value('quantity') !== (float) $expected) {
                throw new RuntimeException('Hasil stok multi-transfer tidak sesuai atau transfer gagal tersimpan sebagian.');
            }
        }
        if ($report['mismatched_pairs'] || $report['batch_identity_issues']) throw new RuntimeException('Rekonsiliasi multi-transfer tidak sesuai.');
        echo "PASS stok multi-transfer terverifikasi dan transfer gagal tidak mengubah stok.\n";
    }
    if ($phase === 'onboarding' && $code === 0) {
        $report = app(\App\Services\InventoryReconciliationService::class)->report();
        if ((float) StockBalance::where('medicine_id', $medicine->id)->where('location_id', $warehouse->id)->value('quantity') !== 50.0
            || (float) StockBalance::where('medicine_id', $medicine->id)->where('location_id', $pharmacy->id)->value('quantity') !== 20.0
            || $report['mismatched_pairs'] || $report['batch_identity_issues']) throw new RuntimeException('Panduan mengubah stok simulasi.');
        echo "PASS panduan tidak mengubah stok; rekonsiliasi sesuai.\n";
    }
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
