<?php

// Persistent local UAT. No reset command; existing UAT work must never be silently erased.
use App\Enums\Role;
use App\Models\Medicine;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use App\Support\UatEnvironment;
use Database\Seeders\UatSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$root = dirname(__DIR__);
$directory = $root.'/storage/app/private/uat';
$command = $argv[1] ?? 'status';
$secrets = [];
$writeJson = function (string $file, array $data): void {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($file, $json, LOCK_EX) !== strlen($json)) {
        throw new RuntimeException('File privat UAT gagal disimpan.');
    }
    @chmod($file, 0600);
};
$fingerprint = function (): string {
    $hash = hash_init('sha256');
    foreach (['medicines', 'medicine_packages', 'units', 'locations', 'batches', 'stock_balances', 'batch_stock_balances',
        'inventory_movements', 'inventory_receipts', 'inventory_receipt_items', 'nightly_checks', 'nightly_check_items',
        'stock_opname_sessions', 'stock_opname_items'] as $table) {
        hash_update($hash, $table.DB::table($table)->orderBy('id')->get()->toJson());
    }

    return hash_final($hash);
};

try {
    if (! in_array($command, ['setup', 'status', 'serve', 'seed', 'verify', 'browser-check', 'migrate'], true)) {
        throw new RuntimeException('Gunakan: php scripts/uat.php setup|status|serve|verify|browser-check|migrate');
    }
    if (! in_array($command, ['setup', 'verify'], true)) {
        if (! is_file($directory.'/environment.json')) {
            throw new RuntimeException('UAT belum disiapkan. Jalankan php scripts/uat.php setup.');
        }
        $manifest = json_decode(file_get_contents($directory.'/environment.json'), true, flags: JSON_THROW_ON_ERROR);
        $environment = $manifest['variables'];
        if (($environment['APP_ENV'] ?? '') !== 'uat' || ($environment['DB_DATABASE'] ?? '') !== UatEnvironment::DATABASE
            || ! preg_match('/\Ashifa_uat_[a-f0-9]{12}\z/', $environment['DB_USERNAME'] ?? '')) {
            throw new RuntimeException('Manifest UAT tidak valid.');
        }
        if ($command !== 'seed' && ! ($manifest['ready'] ?? false)) {
            throw new RuntimeException('Setup UAT belum selesai; tidak boleh dipakai untuk transaksi.');
        }
        foreach ($environment as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $secrets = [$environment['DB_PASSWORD'], $environment['APP_KEY']];
    } elseif ($command === 'setup' && is_file($directory.'/environment.json')) {
        throw new RuntimeException('Manifest UAT sudah ada. Gunakan status/serve. Setup ulang tidak menimpa hasil UAT.');
    }
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    if ($command === 'verify') {
        if (! app()->environment('local') || config('database.default') !== 'mysql') {
            throw new RuntimeException('Verifikasi sumber hanya melalui konfigurasi klinik lokal.');
        }
        $manifest = json_decode(file_get_contents($directory.'/environment.json'), true, flags: JSON_THROW_ON_ERROR);
        $environment = $manifest['variables'];
        $source = DB::connection()->getConfig();
        $secrets = [$source['password'], $environment['DB_PASSWORD'], $environment['APP_KEY']];
        if (! ($manifest['ready'] ?? false) || ! preg_match('/\A[a-zA-Z0-9_]+\z/', $source['database'])
            || $source['database'] === UatEnvironment::DATABASE || $environment['DB_DATABASE'] !== UatEnvironment::DATABASE) {
            throw new RuntimeException('Sumber atau manifest verifikasi tidak sesuai.');
        }
        config(['database.connections.uat_verify' => array_replace($source, ['url' => null,
            'database' => UatEnvironment::DATABASE, 'host' => $environment['DB_HOST'], 'port' => $environment['DB_PORT'],
            'username' => $environment['DB_USERNAME'], 'password' => $environment['DB_PASSWORD'], 'unix_socket' => ''])]);
        $uat = DB::connection('uat_verify');
        foreach ($uat->select('SHOW GRANTS FOR CURRENT_USER()') as $row) {
            $grant = str_replace(['`', '\\'], '', array_values((array) $row)[0]);
            if (str_starts_with($grant, 'GRANT USAGE ON *.* TO ')) {
                continue;
            }
            if (! str_contains($grant, ' ON '.UatEnvironment::DATABASE.'.* TO ') || str_contains($grant, 'WITH GRANT OPTION')) {
                throw new RuntimeException('Hak akses MySQL UAT melampaui database UAT.');
            }
        }
        $denied = false;
        try {
            $uat->select('SELECT COUNT(*) FROM `'.$source['database'].'`.`medicines`');
        } catch (QueryException $error) {
            $denied = in_array((int) ($error->errorInfo[1] ?? 0), [1044, 1142], true);
        }
        $unchanged = hash_equals($manifest['source_fingerprint'], $fingerprint());
        $report = ['tested_at' => now('Asia/Jakarta')->toIso8601String(), 'clinic_inventory_unchanged_since_setup' => $unchanged,
            'uat_mysql_grants_limited_to_uat' => true, 'uat_read_clinic_denied' => $denied];
        $qaDirectory = storage_path('app/qa');
        if (! is_dir($qaDirectory) && ! mkdir($qaDirectory, 0700, true)) {
            throw new RuntimeException('Folder bukti UAT gagal dibuat.');
        }
        $writeJson($qaDirectory.'/uat-isolation.json', $report);
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
        exit($unchanged && $denied ? 0 : 1);
    }

    if ($command === 'setup') {
        if (! app()->environment('local') || config('database.default') !== 'mysql') {
            throw new RuntimeException('Setup UAT hanya dari konfigurasi MySQL lokal.');
        }
        $source = config('database.connections.mysql');
        if (! in_array($source['host'], ['127.0.0.1', 'localhost'], true) || $source['database'] === UatEnvironment::DATABASE) {
            throw new RuntimeException('Sumber harus database klinik lokal yang berbeda dari UAT.');
        }
        $secrets[] = $source['password'];
        $before = $fingerprint();
        $medicines = Medicine::orderBy('id')->get()->filter(function ($medicine) {
            $file = $medicine->import_source['source'] ?? $medicine->import_source['file'] ?? '';

            return str_ends_with(strtolower($file), '.xlsx');
        });
        if ($medicines->isEmpty()) {
            throw new RuntimeException('Tidak ditemukan master obat bersumber Excel. Tidak ada database yang dibuat.');
        }
        $snapshot = ['units' => DB::table('units')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'locations' => DB::table('locations')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'medicines' => $medicines->map(fn ($m) => array_replace($m->getAttributes(), ['imported_by' => null, 'verified_by' => null]))->values()->all(),
            'medicine_packages' => DB::table('medicine_packages')->whereIn('medicine_id', $medicines->pluck('id'))->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()];
        $adminConfig = $source;
        $adminConfig['database'] = 'information_schema';
        $adminConfig['url'] = null;
        config(['database.connections.uat_admin' => $adminConfig]);
        $admin = DB::connection('uat_admin');
        if ($admin->table('SCHEMATA')->where('SCHEMA_NAME', UatEnvironment::DATABASE)->exists()) {
            throw new RuntimeException('Database shifastock_uat sudah ada; setup tidak akan menimpa atau menghapusnya.');
        }
        $username = 'shifa_uat_'.bin2hex(random_bytes(6));
        $password = bin2hex(random_bytes(24));
        $secrets[] = $password;
        if (! is_dir($directory.'/views') && ! mkdir($directory.'/views', 0700, true)) {
            throw new RuntimeException('Folder privat UAT tidak dapat dibuat.');
        }
        $socket = stream_socket_server('tcp://127.0.0.1:8010', $errno, $error);
        if (! $socket) {
            throw new RuntimeException('Port 8010 sudah dipakai. Tidak ada database yang dibuat.');
        }
        fclose($socket);
        $environment = UatEnvironment::variables($source, $username, $password);
        $secrets[] = $environment['APP_KEY'];
        $manifest = ['format' => 1, 'ready' => false, 'created_at' => now('Asia/Jakarta')->toIso8601String(),
            'variables' => $environment, 'source_fingerprint' => $before, 'copied_medicines' => $medicines->count()];
        $writeJson($directory.'/environment.json', $manifest);
        $accounts = [];
        foreach (['admin_uat' => Role::Administrator, 'petugas_uat' => Role::Pharmacy, 'apoteker_uat' => Role::Pharmacist, 'guest' => Role::Guest] as $login => $role) {
            $secret = bin2hex(random_bytes(10));
            $secrets[] = $secret;
            $accounts[] = ['username' => $login, 'name' => $role->label().' (UAT)', 'role' => $role->value, 'password' => $secret];
        }
        $writeJson($directory.'/accounts.json', ['notice' => 'RAHASIA LOKAL: akun simulasi UAT, bukan akun klinik. Jangan commit atau bagikan ke publik.', 'accounts' => $accounts]);
        $admin->statement('CREATE DATABASE `'.UatEnvironment::DATABASE.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        foreach (['localhost', '127.0.0.1'] as $host) {
            // Username, host and password are generated/constant, never user-provided SQL.
            $admin->statement("CREATE USER '$username'@'$host' IDENTIFIED BY '$password'");
            $admin->statement('GRANT ALL PRIVILEGES ON `'.UatEnvironment::DATABASE."`.* TO '$username'@'$host'");
        }
        $run = function (array $arguments) use ($root, $environment): void {
            $process = new Process([PHP_BINARY, ...$arguments], $root, $environment);
            $process->setTimeout(300)->mustRun();
        };
        $run([$root.'/artisan', 'migrate', '--force']);
        $target = $source;
        $target['url'] = null;
        $target['database'] = UatEnvironment::DATABASE;
        $target['username'] = $username;
        $target['password'] = $password;
        $target['unix_socket'] = '';
        config(['database.connections.uat_target' => $target]);
        $uat = DB::connection('uat_target');
        $uat->transaction(function () use ($uat, $snapshot) {
            foreach ($snapshot as $table => $rows) {
                foreach (array_chunk($rows, 50) as $chunk) {
                    $uat->table($table)->insert($chunk);
                }
            }
        });
        $run([__FILE__, 'seed']);
        if ($before !== $fingerprint()) {
            throw new RuntimeException('Fingerprint data klinik berubah selama setup; periksa sebelum melanjutkan.');
        }
        $manifest['ready'] = true;
        $manifest['source_unchanged'] = true;
        $writeJson($directory.'/environment.json', $manifest);
        echo 'UAT siap: '.$environment['APP_URL'].PHP_EOL;
        echo 'Database terpisah: '.UatEnvironment::DATABASE.'; master Excel: '.$medicines->count().'; data klinik tidak berubah.'.PHP_EOL;
        echo 'Akun: '.implode(', ', array_column($accounts, 'username')).PHP_EOL;
        echo 'Password hanya di storage/app/private/uat/accounts.json (diabaikan Git).'.PHP_EOL;
        exit(0);
    }

    UatEnvironment::assertSafe();
    if (DB::connection()->getDatabaseName() !== UatEnvironment::DATABASE) {
        throw new RuntimeException('Koneksi database UAT tidak sesuai.');
    }
    if ($command === 'migrate') {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        echo \Illuminate\Support\Facades\Artisan::output();
        exit($exitCode);
    }
    if ($command === 'seed') {
        app(UatSeeder::class)->run();
        exit(0);
    }
    if ($command === 'browser-check') {
        $before = $fingerprint();
        $accounts = [];
        $ids = [];
        try {
            foreach ([Role::Guest, Role::Pharmacy, Role::Pharmacist] as $role) {
                $secret = bin2hex(random_bytes(24));
                $secrets[] = $secret;
                $username = 'qa_'.bin2hex(random_bytes(6));
                $user = User::create(['name' => 'QA '.$role->label(), 'username' => $username,
                    'email' => $username.'@uat.example.test', 'role' => $role, 'is_active' => true, 'password' => $secret]);
                $ids[] = $user->id;
                $accounts[$role->value] = ['username' => $username, 'password' => $secret];
            }
            $metadata = ['url' => $environment['APP_URL'], 'check' => DB::table('nightly_checks')->value('id'),
                'session' => DB::table('stock_opname_sessions')->value('id'),
                'receipt' => DB::table('inventory_receipts')->value('id'),
                'medicine' => DB::table('stock_balances')->value('medicine_id'), 'location' => DB::table('locations')->where('code', 'GDG')->value('id')];
            $process = new Process(['node', $root.'/scripts/test-uat-browser.cjs'], $root, $environment + [
                'UAT_BROWSER_ACCOUNTS' => json_encode($accounts, JSON_THROW_ON_ERROR),
                'UAT_BROWSER_META' => json_encode($metadata, JSON_THROW_ON_ERROR),
            ]);
            $process->setTimeout(300);
            $result = $process->run(fn ($type, $text) => print $text);
            if ($before !== $fingerprint()) {
                throw new RuntimeException('Pengujian baca-saja mengubah inventori UAT.');
            }
            echo 'Inventori UAT tidak berubah selama pengujian browser.'.PHP_EOL;
        } finally {
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
            DB::table('audit_logs')->whereIn('user_id', $ids)->delete();
            User::whereIn('id', $ids)->delete();
        }
        exit($result);
    }
    if ($command === 'serve') {
        $port = UatEnvironment::listenPort($manifest);
        echo 'UAT lokal: '.$environment['APP_URL'].' | database '.UatEnvironment::DATABASE.PHP_EOL;
        echo 'Listening: 127.0.0.1:'.$port.PHP_EOL;
        $process = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $root.'/public',
            $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], $root.'/public', $environment);
        $process->setTimeout(null);
        exit($process->run(fn ($type, $text) => print $text));
    }
    echo json_encode(['environment' => app()->environment(), 'database' => DB::connection()->getDatabaseName(),
        'listen_port' => UatEnvironment::listenPort($manifest),
        'url' => $environment['APP_URL'], 'simulation' => true, 'copied_medicines' => $manifest['copied_medicines'],
        'source_unchanged_at_setup' => $manifest['source_unchanged'] ?? false,
        'users' => User::orderBy('id')->get(['username', 'role'])->toArray(),
        'medicines' => DB::table('medicines')->count(), 'movements' => DB::table('inventory_movements')->count(),
        'reconciliation' => app(InventoryReconciliationService::class)->report(),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    $message = $error->getMessage();
    foreach ($secrets as $secret) {
        if (is_string($secret) && $secret !== '') {
            $message = str_replace($secret, '[disembunyikan]', $message);
        }
    }
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}
