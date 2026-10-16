<?php

use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

$root = dirname(__DIR__);
$directory = $root.'/storage/app/qa/inventory';
if (! is_dir($directory)) mkdir($directory, 0777, true);
$php = [PHP_BINARY];
if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) array_push($php, '-d', 'extension=pdo_sqlite');
$probe = new Process([...$php, '-r', 'exit(in_array("sqlite", PDO::getAvailableDrivers(), true) ? 0 : 1);']);
$probe->run();
if (! $probe->isSuccessful()) {
    fwrite(STDERR, "Driver pdo_sqlite diperlukan. Tidak ada pengujian/database klinik yang dijalankan.\n");
    exit(1);
}

// Force an isolated in-memory connection and bypass any cached local MySQL config.
$process = new Process([...$php, $root.'/vendor/bin/phpunit', '--configuration', $root.'/phpunit.xml',
    '--log-junit', $directory.'/phpunit-results.xml'], $root, [
    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
    'APP_CONFIG_CACHE' => $directory.'/unused-test-config-'.bin2hex(random_bytes(8)).'.php',
    'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array',
]);
$process->setTimeout(180);
echo "Menjalankan tes pada SQLite :memory:; database klinik tidak dipakai.\n";
$exitCode = $process->run(fn ($type, $output) => fwrite($type === Process::ERR ? STDERR : STDOUT, $output));
echo "Laporan: storage/app/qa/inventory/phpunit-results.xml\n";
exit($exitCode);
