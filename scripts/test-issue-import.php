<?php

// Synthetic fixtures only. Never migrate, seed, or reset the clinic/UAT database.
use App\Enums\Role;
use App\Models\Batch;
use App\Models\BatchStockBalance;
use App\Models\IssueImport;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Services\IssueImportService;
use App\Services\NeoklinikIssueReader;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$mode = $argv[1] ?? 'run';

if (in_array($mode, ['fixture', 'worker', 'verify'], true)) {
    $database = getenv('DB_DATABASE');
    if (! app()->environment('testing') || ! preg_match('/\Ashifastock_issue_qa_[a-f0-9]{16}\z/', $database)
        || config('database.default') !== 'mysql' || DB::connection()->getDatabaseName() !== $database) {
        throw new RuntimeException('Refusing work outside isolated issue-import QA database.');
    }
    $output = getenv('ISSUE_QA_OUTPUT');
    if ($mode === 'fixture') {
        $actor = User::create(['username' => 'petugas_simulasi', 'name' => 'Petugas Simulasi', 'email' => 'staff@issue.example.test',
            'password' => getenv('ISSUE_QA_PASSWORD'), 'role' => Role::Pharmacy, 'onboarding_seen_at' => now(), 'is_active' => true]);
        User::create(['username' => 'guest_simulasi', 'name' => 'Guest Simulasi', 'email' => 'guest@issue.example.test',
            'password' => getenv('ISSUE_QA_PASSWORD'), 'role' => Role::Guest, 'onboarding_seen_at' => now(), 'is_active' => true]);
        User::query()->update(['onboarding_seen_at' => now()]);
        $location = Location::create(['code' => 'APT', 'name' => 'Apotek']);
        $unit = Unit::create(['name' => 'Tablet']);
        $medicine = Medicine::create(['code' => 'SIM-IMPORT', 'name' => 'Obat Simulasi Impor', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet']);
        StockBalance::create(['medicine_id' => $medicine->id, 'location_id' => $location->id, 'quantity' => 100]);
        foreach ([['SIM-AWAL', 10, 1], ['SIM-AKHIR', 90, 2]] as [$number, $quantity, $years]) {
            $batch = Batch::create(['medicine_id' => $medicine->id, 'number' => $number, 'expiry_date' => now('Asia/Jakarta')->addYears($years)]);
            BatchStockBalance::create(['medicine_id' => $medicine->id, 'location_id' => $location->id, 'batch_id' => $batch->id, 'quantity' => $quantity]);
        }
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Sheet');
        $sheet->fromArray(NeoklinikIssueReader::HEADERS, null, 'A1');
        $date = now('Asia/Jakarta')->subDay()->setTime(9, 0, 1);
        foreach ([15, 2] as $index => $quantity) {
            $sheet->fromArray([Date::PHPToExcel($date), null, 'SIM-SALE-'.($index + 1), 'Internal', 'Klinik Simulasi', 'Poli Simulasi',
                'DOKTER_SINTETIS_PRIVASI', 'PASIEN_SINTETIS_PRIVASI', 'Resep', null, 'SIM-IMPORT', 'Obat Simulasi Impor', $quantity, 'BUTIR', null, null], null, 'A'.($index + 2));
        }
        (new Xlsx($book))->save($output.'/Simulasi.xlsx');
        $sheet->setCellValue('M2', 16);
        (new Xlsx($book))->save($output.'/Simulasi_Berubah.xlsx');
        $sheet->setCellValue('M2', 15);
        $sheet->setCellValue('C2', 'SIM-UI-KNOWN');
        $sheet->setCellValue('A2', Date::PHPToExcel($date->copy()->startOfDay()->subHour()));
        $sheet->setCellValue('C3', 'SIM-UI-NEW');
        $sheet->setCellValue('K3', 'SIM-UNMATCHED');
        $sheet->setCellValue('L3', 'Bahan Simulasi Baru');
        $sheet->setCellValue('N3', '-');
        (new Xlsx($book))->save($output.'/Simulasi_Periksa.xlsx');
        // Long synthetic list exercises independent list scrolling without posting new stock.
        for ($index = 0; $index < 48; $index++) {
            $sheet->fromArray([Date::PHPToExcel($date), null, 'SIM-LAYOUT-'.($index + 1), 'Internal', 'Klinik Simulasi', 'Poli Simulasi',
                'DOKTER_SINTETIS_PRIVASI', 'PASIEN_SINTETIS_PRIVASI', 'Resep', null, 'SIM-LIST-'.($index + 1),
                'Obat Simulasi Tata Letak '.($index + 1).' Tablet 500 mg', 1, 'BUTIR', null, null], null, 'A'.($index + 2));
        }
        (new Xlsx($book))->save($output.'/Simulasi_Banyak.xlsx');
        $book->disconnectWorksheets();
        $service = app(IssueImportService::class);
        $rows = app(NeoklinikIssueReader::class)->read($output.'/Simulasi.xlsx');
        // A distinct sale for concurrent workers, independent of browser-created transactions.
        $rows = [array_replace($rows[0], ['source_key' => hash('sha256', 'synthetic-concurrent-issue'), 'source_quantity' => '3.0000'])];
        $first = $service->create($rows, hash('sha256', 'synthetic-1'), $location->id, $date->copy()->startOfDay()->format('Y-m-d H:i:s'), $actor);
        $second = $service->create($rows, hash('sha256', 'synthetic-2'), $location->id, $date->copy()->startOfDay()->format('Y-m-d H:i:s'), $actor);
        $key = array_key_first($service->groups($first));
        $service->saveMappings($first, [$key => ['medicine_id' => $medicine->id, 'factor' => 1, 'confirmed' => 1]], $actor);
        // Remove this saved mapping so the browser must explicitly approve its own proposal.
        DB::table('issue_import_mappings')->delete();
        file_put_contents($output.'/meta.json', json_encode(['base_url' => getenv('APP_URL'), 'port' => (int) parse_url(getenv('APP_URL'), PHP_URL_PORT),
            'simulation' => true, 'location_id' => $location->id, 'medicine_id' => $medicine->id, 'actor_id' => $actor->id,
            'cutoff' => $date->copy()->startOfDay()->format('Y-m-d\TH:i'), 'race_documents' => [$first->id, $second->id]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo "PASS isolated MySQL fixture created\n";
    } elseif ($mode === 'worker') {
        $meta = json_decode(file_get_contents($output.'/meta.json'), true, flags: JSON_THROW_ON_ERROR);
        $document = IssueImport::findOrFail((int) ($argv[2] ?? 0));
        $service = app(IssueImportService::class);
        $token = $service->preview($document)['token'];
        file_put_contents($output.'/ready-'.$document->id, 'ready');
        $deadline = microtime(true) + 60;
        while (! is_file($output.'/go')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Concurrent worker timed out.');
            }
            usleep(10000);
            clearstatcache();
        }
        try {
            $service->post($document, $token, User::findOrFail($meta['actor_id']));
            echo "PASS concurrent worker posted or skipped\n";
        } catch (ValidationException) {
            echo "PASS concurrent stale preview rejected\n";
        }
    } else {
        $meta = json_decode(file_get_contents($output.'/meta.json'), true, flags: JSON_THROW_ON_ERROR);
        $service = app(IssueImportService::class);
        foreach ($meta['race_documents'] as $id) {
            $document = IssueImport::findOrFail($id);
            if ($document->status === 'draft') {
                $service->post($document, $service->preview($document)['token'], User::findOrFail($meta['actor_id']));
            }
        }
        $quantity = (float) StockBalance::where('medicine_id', $meta['medicine_id'])->value('quantity');
        $batchTotal = (float) BatchStockBalance::sum('quantity');
        if ($quantity !== 80.0 || $batchTotal !== 80.0 || DB::table('issue_import_lines')->count() !== 3) {
            throw new RuntimeException('MySQL ledger/concurrent import mismatch.');
        }
        file_put_contents($output.'/MySQL.json', json_encode(['status' => 'Lulus', 'checked_at' => now('Asia/Jakarta')->toIso8601String(),
            'stock_total' => $quantity, 'batch_total' => $batchTotal, 'unique_source_lines' => 3,
            'checks' => ['browser posting FEFO', 'overlap and changed data', 'concurrent imports', 'retry stale preview without duplicate']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo "PASS MySQL stock=80, batches=80, unique source lines=3\n";
    }
    exit(0);
}

if ($mode !== 'run') {
    throw new RuntimeException('Use php scripts/test-issue-import.php');
}
$connection = config('database.connections.mysql');
if (! in_array($connection['host'], ['127.0.0.1', 'localhost'], true)) {
    throw new RuntimeException('QA must use local MySQL.');
}
$connection['url'] = null;
$connection['database'] = 'information_schema';
config(['database.connections.issue_qa_admin' => $connection]);
$admin = DB::connection('issue_qa_admin');
$database = 'shifastock_issue_qa_'.bin2hex(random_bytes(8));
$output = storage_path('app/qa/issue-imports/'.bin2hex(random_bytes(8)));
mkdir($output.'/views', 0700, true);
$socket = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$env = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:'.$port,
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database, 'DB_URL' => '',
    'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'] ?? '',
    'SESSION_DRIVER' => 'database', 'SESSION_CONNECTION' => 'mysql', 'SESSION_COOKIE' => 'issue_qa_'.bin2hex(random_bytes(8)),
    'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'stderr', 'BCRYPT_ROUNDS' => '4',
    'APP_CONFIG_CACHE' => $output.'/unused-config.php', 'APP_ROUTES_CACHE' => $output.'/unused-routes.php', 'APP_EVENTS_CACHE' => $output.'/unused-events.php',
    'VIEW_COMPILED_PATH' => $output.'/views', 'ISSUE_QA_OUTPUT' => $output, 'ISSUE_QA_PASSWORD' => bin2hex(random_bytes(24)), 'ISSUE_QA_PHP' => PHP_BINARY];
$created = false;
$exitCode = 0;
try {
    if (! preg_match('/\Ashifastock_issue_qa_[a-f0-9]{16}\z/', $database)) {
        throw new RuntimeException('Unsafe QA database name.');
    }
    $admin->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    foreach ([[PHP_BINARY, base_path('artisan'), 'migrate', '--force'], [PHP_BINARY, __FILE__, 'fixture'],
        ['node', base_path('scripts/test-issue-import-browser.cjs')]] as $command) {
        $process = new Process($command, base_path(), $env);
        $process->setTimeout(300)->mustRun();
        echo $process->getOutput();
    }
    $meta = json_decode(file_get_contents($output.'/meta.json'), true, flags: JSON_THROW_ON_ERROR);
    $workers = [];
    foreach ($meta['race_documents'] as $id) {
        $process = new Process([PHP_BINARY, __FILE__, 'worker', (string) $id], base_path(), $env);
        $process->setTimeout(120)->start();
        $workers[] = $process;
    }
    $deadline = microtime(true) + 60;
    do {
        clearstatcache();
        $ready = count(array_filter($meta['race_documents'], fn ($id) => is_file($output.'/ready-'.$id))) === 2;
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Workers failed to reach barrier.');
        }
        usleep(10000);
    } while (! $ready);
    file_put_contents($output.'/go', 'go');
    foreach ($workers as $worker) {
        $worker->wait();
        if (! $worker->isSuccessful()) {
            throw new RuntimeException('Concurrent import worker failed.');
        }
        echo $worker->getOutput();
    }
    $verify = new Process([PHP_BINARY, __FILE__, 'verify'], base_path(), $env);
    $verify->setTimeout(120)->mustRun();
    echo $verify->getOutput();
    echo 'Evidence: '.$output.PHP_EOL;
} catch (Throwable $error) {
    $exitCode = 1;
    $message = str_replace(array_filter([$env['APP_KEY'], $env['DB_PASSWORD'], $env['ISSUE_QA_PASSWORD']]), '[redacted]', $error->getMessage());
    fwrite(STDERR, $message.PHP_EOL);
} finally {
    foreach ($workers ?? [] as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    if ($created) {
        $admin->statement('DROP DATABASE `'.$database.'`');
    }
}
exit($exitCode);
