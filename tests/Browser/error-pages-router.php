<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Only used by the isolated browser runner; never loaded by application routes.
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
    http_response_code(403);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/images/shifa-medika.png') {
    return false;
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Route::get('/__qa/error/403', fn () => abort(403, 'QA_PRIVATE_EXCEPTION_MARKER'));
Route::match(['get', 'post'], '/__qa/error/500', function () {
    throw new RuntimeException('QA_PRIVATE_EXCEPTION_MARKER');
});

$app->handleRequest(Request::capture());
