<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$root = base_path();
$output = base_path('Docs/Capstone_Testing_Final/Bukti_Pengujian');
$started = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
$run = 'Run_'.$started->format('Ymd_His');
$directory = $output.'/'.$run;
if (! is_dir($directory)) mkdir($directory, 0777, true);
$commit = trim((new Process(['git', 'rev-parse', 'HEAD'], $root))->mustRun()->getOutput());
$sanitize = function (string $text): string {
    foreach ([config('app.key'), config('database.connections.mysql.password')] as $secret) {
        if (is_string($secret) && $secret !== '') $text = str_replace($secret, '[disembunyikan]', $text);
    }
    $text = preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    return preg_replace('/\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{53}/', '[hash disembunyikan]', $text);
};
$results = [];
$runCommand = function (string $name, array $command) use ($directory, $root, $sanitize, &$results): void {
    $start = (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format(DATE_ATOM);
    $process = new Process($command, $root);
    $process->setTimeout(900);
    $process->run();
    file_put_contents($directory.'/'.$name.'.txt', $sanitize($process->getOutput().$process->getErrorOutput()));
    $results[] = ['name' => $name, 'command' => implode(' ', array_map(fn ($s) => is_file($s) ? basename($s) : $s, $command)),
        'started_at' => $start, 'finished_at' => date(DATE_ATOM), 'exit_code' => $process->getExitCode(), 'log' => $name.'.txt'];
    echo $name.': exit '.$process->getExitCode().PHP_EOL;
};
$junit = storage_path('app/qa/inventory/phpunit-results.xml');
if (is_file($junit)) unlink($junit);
$runCommand('01_PHPUnit_SQLite', [PHP_BINARY, base_path('scripts/test-inventory.php')]);
if (is_file($junit)) copy($junit, $directory.'/phpunit-results.xml');
$runCommand('02_Konkurensi_MySQL', [PHP_BINARY, base_path('scripts/test-inventory-mysql.php')]);
$runCommand('03_Frontend_Build', ['npm.cmd', 'run', 'build']);
$hashes = [];
foreach (['resources/js/app.js', 'tests/Feature/CapstoneFunctionalTest.php', 'scripts/capstone-final-browser.php',
    'scripts/capstone-final-browser.cjs', 'scripts/capstone-final-tests.php'] as $file) $hashes[$file] = hash_file('sha256', base_path($file));
$meta = ['commit' => $commit, 'working_tree_changes' => 'Perbaikan resources/js/app.js dan tes/script tambahan belum di-commit; SHA-256 dicatat.',
    'source_sha256' => $hashes, 'started_at' => $started->format(DATE_ATOM), 'finished_at' => date(DATE_ATOM),
    'environment' => ['os' => PHP_OS_FAMILY, 'php' => PHP_VERSION, 'laravel' => app()->version(),
        'phpunit_database' => 'SQLite :memory:', 'mysql_database' => 'Database acak shifastock_qa_*; dibuat dan dihapus oleh runner',
        'clinic_database_written' => false], 'run_directory' => $run, 'commands' => $results];
file_put_contents($directory.'/metadata.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents($output.'/Eksekusi_Terbaru.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
exit(count(array_filter($results, fn ($r) => $r['exit_code'] !== 0)) ? 1 : 0);
