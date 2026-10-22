<?php

use App\Enums\Role;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NightlyCheckController;
use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Medicine;
use App\Models\StockBalance;
use App\Models\StockOpnameSession;
use App\Models\Unit;
use App\Models\User;
use App\Services\InventoryReconciliationService;
use App\Services\InventoryStockService;
use App\Services\InventoryTransaction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = storage_path('app/qa/inventory/mysql');
if (! is_dir($directory)) mkdir($directory, 0777, true);

function invokeInventory(string $method, array $payload, array $parameters = [], string $controller = InventoryController::class): mixed
{
    $request = Request::create('/qa-inventory', 'POST', $payload);
    $request->setUserResolver(fn () => Auth::user());
    $request->setLaravelSession(app('session')->driver());
    app()->instance('request', $request);

    return app()->call([app($controller), $method], ['request' => $request, 'r' => $request] + $parameters);
}

function verify(bool $condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}

if (($argv[1] ?? '') === '--worker') {
    $database = getenv('DB_DATABASE');
    verify(app()->environment('testing') && preg_match('/\Ashifastock_qa_[a-f0-9]{16}\z/', $database)
        && config('database.default') === 'mysql' && DB::connection()->getDatabaseName() === $database,
        'Worker refuses to run outside its isolated MySQL QA database.');
    verify((bool) preg_match('/\A[a-f0-9]{16}\z/', getenv('INVENTORY_QA_RUN')), 'Invalid worker run directory.');
    $run = $directory.'/'.getenv('INVENTORY_QA_RUN');
    $fixture = json_decode(file_get_contents($run.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
    $operation = $argv[2];
    $id = $argv[3];
    Auth::setUser(User::findOrFail($fixture['user']));
    if ($operation === 'daily-save' && $id === '0') Auth::setUser(User::findOrFail($fixture['staff']));
    file_put_contents($run.'/ready-'.$id, 'ready');
    $deadline = microtime(true) + 60;
    while (! is_file($run.'/go')) {
        if (microtime(true) > $deadline) throw new RuntimeException('Worker barrier timed out.');
        usleep(10000);
        clearstatcache();
    }
    $entered = $finished = $reason = null;
    DB::listen(function ($query) use (&$entered, &$finished) {
        if ($entered === null && str_contains($query->sql, 'inventory_write_locks') && str_contains($query->sql, 'for update')) {
            $entered = microtime(true);
            usleep(200000);
        }
        if ($entered !== null && DB::transactionLevel() > 0) $finished = microtime(true);
    });
    try {
                match ($operation) {
                    'multi-transfer', 'duplicate-transfer' => invokeInventory('storeTransfer', [
                        'reference' => $operation === 'duplicate-transfer' ? 'QA-MULTI-DUPLICATE' : 'QA-MULTI-'.bin2hex(random_bytes(4)),
                        'source_location_id' => $fixture['location'], 'destination_location_id' => $fixture['transfer_target'],
                        'occurred_at' => '2026-10-18T10:00',
                        'items' => [
                            ['medicine_id' => $fixture['multi_a'], 'quantity' => $operation === 'duplicate-transfer' ? 1 : 6],
                            ['medicine_id' => $fixture['multi_b'], 'quantity' => $operation === 'duplicate-transfer' ? 1 : 6],
                        ],
                    ]),
                    'issue', 'duplicate' => invokeInventory('storeIssue', ['reference' => $operation === 'duplicate' ? 'QA-DUPLICATE' : 'QA-ISSUE-'.bin2hex(random_bytes(4)),
                        'medicine_id' => $fixture['medicine'], 'source_location_id' => $fixture['location'],
                        'quantity' => $operation === 'duplicate' ? 1 : 7, 'occurred_at' => '2026-10-18T10:00']),
                    'reverse' => invokeInventory('reverseMovement', ['reason' => 'Concurrent regression'],
                        ['movement' => InventoryMovement::findOrFail($fixture['movement'])]),
                    'finalize' => invokeInventory('finalizeOpnameSession', [],
                        ['session' => StockOpnameSession::findOrFail($fixture['session'])]),
                    'daily' => invokeInventory('review', ['action' => 'approve'], ['id' => $fixture['daily']], NightlyCheckController::class),
                    'daily-save' => invokeInventory('save', ['draft_revision' => 0, 'action' => 'save',
                        'items' => [$fixture['daily_item'] => ['quantity' => null, 'unit' => 'Tablet', 'note' => 'Worker '.$id]]],
                        ['id' => $fixture['daily']], NightlyCheckController::class),
                    'unit' => InventoryTransaction::run(fn () => Medicine::findOrFail($fixture['new_medicine'])->update(['unit_id' => $fixture['new_unit']])),
                    'receipt', 'receive' => invokeInventory('storeReceipt', ['reference' => 'QA-FIRST-RECEIPT-'.bin2hex(random_bytes(4)), 'action' => 'post',
                        'destination_location_id' => $fixture['location'], 'occurred_at' => '2026-10-18T10:00',
                        'items' => [['medicine_id' => $fixture['new_medicine'], 'batch_number' => 'QA-NEW',
                            'expiry_date' => '2028-01-01', 'quantity' => 5]]]),
                    default => throw new RuntimeException('Unknown worker operation.'),
                };
        $status = 'ok';
    } catch (ValidationException|HttpException $exception) {
        if ($exception instanceof HttpException && ! in_array($exception->getStatusCode(), [409, 422], true)) throw $exception;
        $status = 'rejected';
        $reason = $exception->getMessage();
    }
    echo json_encode(compact('status', 'entered', 'finished', 'reason'), JSON_THROW_ON_ERROR);
    exit;
}

$database = 'shifastock_qa_'.bin2hex(random_bytes(8));
verify((bool) preg_match('/\Ashifastock_qa_[a-f0-9]{16}\z/', $database), 'Unsafe QA database name.');
$connection = config('database.connections.mysql');
$connection['url'] = null;
$connection['database'] = 'information_schema';
config(['database.connections.qa_admin' => $connection]);
$admin = DB::connection('qa_admin');
$created = false;
$exitCode = 0;
$children = [];
$environment = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
    'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'],
    'DB_PASSWORD' => $connection['password'] ?? '', 'DB_SOCKET' => $connection['unix_socket'] ?? '', 'DB_URL' => '',
    'APP_CONFIG_CACHE' => $directory.'/unused-'.bin2hex(random_bytes(8)).'.php', 'SESSION_DRIVER' => 'array',
    'CACHE_STORE' => 'array', 'BCRYPT_ROUNDS' => '4'];
try {
    $admin->statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    echo "Created isolated MySQL QA database; clinic database is not migrated or written.\n";
    $migration = new Process([PHP_BINARY, base_path('artisan'), 'migrate', '--force'], base_path(), $environment);
    $migration->setTimeout(300);
    $migration->mustRun();
    $connection['database'] = $database;
    config(['database.default' => 'mysql', 'database.connections.mysql' => $connection,
        'session.driver' => 'array', 'cache.default' => 'array', 'hashing.bcrypt.rounds' => 4]);
    DB::purge('mysql');
    verify(DB::connection()->getDatabaseName() === $database, 'QA connection mismatch.');
    $user = User::factory()->create(['role' => Role::Pharmacist]);
    Auth::setUser($user);
    $unit = Unit::create(['name' => 'Tablet']);
    $location = Location::create(['code' => 'QA', 'name' => 'QA location', 'is_active' => true]);
    $medicine = Medicine::create(['code' => 'QA', 'name' => 'QA medicine', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
    invokeInventory('storeOpeningBalance', ['reference' => 'QA-SEED', 'medicine_id' => $medicine->id,
        'location_id' => $location->id, 'batch_number' => 'QA-BATCH', 'expiry_date' => '2028-01-01',
        'quantity' => 10, 'occurred_at' => '2026-10-18T09:00']);
    $fixture = ['user' => $user->id, 'medicine' => $medicine->id, 'location' => $location->id];

    $race = function (array $operations) use (&$fixture, &$children, $directory, $environment): array {
        $runId = bin2hex(random_bytes(8));
        $run = $directory.'/'.$runId;
        mkdir($run);
        file_put_contents($run.'/fixture.json', json_encode($fixture, JSON_THROW_ON_ERROR));
        $children = [];
        foreach ($operations as $index => $operation) {
            $child = new Process([PHP_BINARY, __FILE__, '--worker', $operation, (string) $index], base_path(),
                $environment + ['INVENTORY_QA_RUN' => $runId]);
            $child->setTimeout(120);
            $child->start();
            $children[] = $child;
        }
        $deadline = microtime(true) + 60;
        while (count(glob($run.'/ready-*')) !== count($operations)) {
            foreach ($children as $child) verify($child->isRunning(), 'Worker failed before barrier: '.$child->getErrorOutput());
            verify(microtime(true) < $deadline, 'Parent barrier timed out.');
            usleep(10000);
            clearstatcache();
        }
        file_put_contents($run.'/go', 'go');
        $results = [];
        foreach ($children as $child) {
            $child->wait();
            verify($child->isSuccessful(), 'Worker failed: '.$child->getErrorOutput().$child->getOutput());
            $results[] = json_decode($child->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        $ordered = $results;
        usort($ordered, fn ($a, $b) => $a['entered'] <=> $b['entered']);
        verify($ordered[0]['finished'] <= $ordered[1]['entered'], 'Critical sections overlapped.');
        verify(app(InventoryReconciliationService::class)->report()['mismatched_pairs'] === 0, 'Stock representations disagree.');

        return $results;
    };
    $oneWinner = function (array $results): void {
        $statuses = array_column($results, 'status');
        sort($statuses);
        verify($statuses === ['ok', 'rejected'], 'Expected one success and one safe rejection: '.json_encode($results));
    };
    $oneWinner($race(['issue', 'issue']));
    verify((float) StockBalance::where('medicine_id', $medicine->id)->sole()->quantity === 3.0, 'Overselling stock.');
    echo "PASS concurrent FEFO issues: one accepted, no overselling.\n";
    $fixture['movement'] = InventoryMovement::where('type', 'issue')->sole()->id;
    $oneWinner($race(['reverse', 'reverse']));
    verify(InventoryMovement::where('type', 'reversal')->count() === 1, 'Duplicate reversal.');
    echo "PASS concurrent reversal: applied once.\n";
    invokeInventory('storeOpnameSession', ['period' => '2026-10', 'location_id' => $location->id, 'cutoff_at' => '2026-10-18T10:00']);
    $session = StockOpnameSession::sole();
    invokeInventory('saveOpnameSession', ['items' => [$session->items()->sole()->id => ['physical_quantity' => 8]]], ['session' => $session]);
    $fixture['session'] = $session->id;
    $oneWinner($race(['finalize', 'finalize']));
    verify((float) StockBalance::where('medicine_id', $medicine->id)->sole()->quantity === 8.0, 'Duplicate finalization.');
    echo "PASS concurrent opname finalization: applied once.\n";
    invokeInventory('store', ['date' => now('Asia/Jakarta')->toDateString(), 'location_id' => $location->id], [], NightlyCheckController::class);
    $fixture['daily'] = DB::table('nightly_checks')->sole()->id;
    $item = DB::table('nightly_check_items')->sole();
    $fixture['daily_item'] = $item->id;
    $fixture['staff'] = User::factory()->create(['role' => Role::Pharmacy])->id;
    $movementsBefore = InventoryMovement::count();
    $results = $race(['daily-save', 'daily-save']);
    $oneWinner($results);
    $winner = $results[0]['status'] === 'ok' ? $fixture['staff'] : $fixture['user'];
    verify(DB::table('nightly_checks')->sole()->created_by === $fixture['user'], 'Shared save replaced the creator.');
    verify((int) DB::table('nightly_checks')->sole()->draft_revision === 1, 'Stale shared save advanced revision.');
    verify(DB::table('nightly_check_items')->sole()->last_edited_by === $winner, 'Shared save attribution is incorrect.');
    verify(DB::table('nightly_check_activities')->where('action', 'saved')->count() === 1, 'Rejected shared save recorded an activity.');
    verify(InventoryMovement::count() === $movementsBefore, 'Draft shared save moved stock.');
    echo "PASS concurrent shared daily drafts: one save accepted, stale write rejected, creator and actor preserved.\n";
    invokeInventory('save', ['draft_revision' => 1, 'action' => 'submit', 'items' => [$item->id => ['quantity' => 7, 'unit' => 'Tablet',
        'batch_counts' => [Batch::where('number', 'QA-BATCH')->sole()->id => 7]]]], ['id' => $fixture['daily']], NightlyCheckController::class);
    $oneWinner($race(['daily', 'daily']));
    verify((float) StockBalance::where('medicine_id', $medicine->id)->sole()->quantity === 7.0, 'Daily approval applied twice.');
    echo "PASS concurrent daily approval and MySQL JSON snapshot round-trip.\n";
    $oneWinner($race(['duplicate', 'duplicate']));
    verify(InventoryMovement::where('reference', 'QA-DUPLICATE')->count() === 1, 'Duplicate reference was posted twice.');
    echo "PASS concurrent duplicate reference: posted once.\n";
    $newMedicine = Medicine::create(['code' => 'QA-NEW', 'name' => 'QA new medicine', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
    $fixture['new_medicine'] = $newMedicine->id;
    $fixture['new_unit'] = Unit::create(['name' => 'Botol'])->id;
    $results = $race(['unit', 'receipt']);
    verify($results[1]['status'] === 'ok', 'First receipt failed.');
    verify($newMedicine->fresh()->unit_id === ($results[0]['status'] === 'ok' ? $fixture['new_unit'] : $unit->id), 'Unit changed after receipt history.');
    echo "PASS unit edit versus first receipt: serialized, no historical relabeling.\n";
    $fixture['new_medicine'] = Medicine::create(['code' => 'QA-EMPTY', 'name' => 'QA empty balance', 'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true])->id;
    $results = $race(['receive', 'receive']);
    verify(array_column($results, 'status') === ['ok', 'ok'], 'Concurrent first receipts failed.');
    verify((float) StockBalance::where('medicine_id', $fixture['new_medicine'])->sole()->quantity === 10.0, 'Concurrent creation lost a receipt.');
    echo "PASS concurrent first receipts: one balance row, both additions retained.\n";
    $fixture['transfer_target'] = Location::create(['code' => 'QA-DEST', 'name' => 'QA destination', 'is_active' => true])->id;
    foreach (['a' => 20, 'b' => 10] as $suffix => $quantity) {
        $multiMedicine = Medicine::create(['code' => 'QA-MULTI-'.$suffix, 'name' => 'QA multi '.$suffix,
            'unit_id' => $unit->id, 'dosage_form' => 'Tablet', 'is_active' => true]);
        $fixture['multi_'.$suffix] = $multiMedicine->id;
        invokeInventory('storeOpeningBalance', ['reference' => 'QA-MULTI-SEED-'.$suffix,
            'medicine_id' => $multiMedicine->id, 'location_id' => $location->id,
            'batch_number' => 'QA-MULTI-BATCH-'.$suffix, 'expiry_date' => '2028-01-01',
            'quantity' => $quantity, 'occurred_at' => '2026-10-18T09:00']);
    }
    $oneWinner($race(['multi-transfer', 'multi-transfer']));
    foreach (['a' => 14.0, 'b' => 4.0] as $suffix => $remaining) {
        verify((float) StockBalance::where('medicine_id', $fixture['multi_'.$suffix])->where('location_id', $location->id)->value('quantity') === $remaining,
            'Failed second item left a partial source transfer.');
        verify((float) StockBalance::where('medicine_id', $fixture['multi_'.$suffix])->where('location_id', $fixture['transfer_target'])->value('quantity') === 6.0,
            'Concurrent multi-transfer destination mismatch.');
    }
    echo "PASS concurrent multi-item transfers: second-item failure rolls back the entire losing transfer.\n";
    $oneWinner($race(['duplicate-transfer', 'duplicate-transfer']));
    verify(InventoryMovement::where('reference', 'QA-MULTI-DUPLICATE')->count() === 2,
        'Duplicate multi-item reference was posted more than once.');
    foreach (['a', 'b'] as $suffix) {
        verify((float) StockBalance::where('medicine_id', $fixture['multi_'.$suffix])->where('location_id', $fixture['transfer_target'])->value('quantity') === 7.0,
            'Duplicate multi-item posting changed stock twice.');
    }
    echo "PASS concurrent multi-item duplicate reference: one complete transfer posted once.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL: '.$exception->getMessage().PHP_EOL);
    $exitCode = 1;
} finally {
    foreach ($children as $child) if ($child->isRunning()) $child->stop();
    DB::disconnect('mysql');
    if ($created && preg_match('/\Ashifastock_qa_[a-f0-9]{16}\z/', $database)) {
        $admin->statement('DROP DATABASE `'.$database.'`');
        echo "Removed isolated MySQL QA database.\n";
    }
}
exit($exitCode);
