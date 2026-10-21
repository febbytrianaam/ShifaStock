<?php

namespace App\Support;

use RuntimeException;

class UatEnvironment
{
    public const DATABASE = 'shifastock_uat';

    public static function variables(array $connection, string $username, string $password, int $port = 8010): array
    {
        $directory = storage_path('app/private/uat');

        return [
            'APP_NAME' => 'ShifaStock UAT', 'APP_ENV' => 'uat', 'APP_DEBUG' => 'false',
            'APP_URL' => 'http://127.0.0.1:'.$port, 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_HOST' => $connection['host'],
            'DB_PORT' => (string) $connection['port'], 'DB_SOCKET' => '', 'DB_DATABASE' => self::DATABASE,
            'DB_USERNAME' => $username, 'DB_PASSWORD' => $password,
            'SESSION_DRIVER' => 'database', 'SESSION_CONNECTION' => 'mysql',
            'SESSION_COOKIE' => 'shifastock_uat_session', 'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false',
            'CACHE_STORE' => 'database', 'DB_CACHE_CONNECTION' => 'mysql', 'DB_CACHE_LOCK_CONNECTION' => 'mysql', 'CACHE_PREFIX' => 'shifastock_uat_',
            'QUEUE_CONNECTION' => 'sync', 'FILESYSTEM_DISK' => 'uat', 'LOG_CHANNEL' => 'uat',
            'MAIL_MAILER' => 'log', 'BROADCAST_CONNECTION' => 'log',
            'APP_CONFIG_CACHE' => $directory.'/config.php', 'APP_ROUTES_CACHE' => $directory.'/routes.php',
            'APP_EVENTS_CACHE' => $directory.'/events.php', 'VIEW_COMPILED_PATH' => $directory.'/views',
        ];
    }

    public static function assertSafe(): void
    {
        $connection = config('database.connections.mysql');
        if (config('app.env') !== 'uat' || config('app.debug') !== false || config('database.default') !== 'mysql'
            || ($connection['database'] ?? '') !== self::DATABASE || filled($connection['url'] ?? null)
            || ! preg_match('/\Ashifa_uat_[a-f0-9]{12}\z/', $connection['username'] ?? '')
            || ! in_array($connection['host'] ?? '', ['127.0.0.1', 'localhost'], true)
            || config('session.driver') !== 'database' || config('session.connection') !== 'mysql'
            || config('session.cookie') !== 'shifastock_uat_session'
            || config('cache.default') !== 'database' || config('cache.stores.database.connection') !== 'mysql'
            || ! in_array(config('cache.stores.database.lock_connection'), [null, 'mysql'], true)
            || config('cache.prefix') !== 'shifastock_uat_'
            || config('queue.default') !== 'sync' || config('logging.default') !== 'uat'
            || config('filesystems.default') !== 'uat') {
            throw new RuntimeException('Konfigurasi UAT tidak terisolasi. Jalankan melalui php scripts/uat.php; database klinik tidak boleh dipakai.');
        }
    }
}
