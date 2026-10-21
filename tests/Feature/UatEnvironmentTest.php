<?php

namespace Tests\Feature;

use App\Support\UatEnvironment;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use RuntimeException;
use Tests\TestCase;

class UatEnvironmentTest extends TestCase
{
    private function configureUat(): void
    {
        config(['app.env' => 'uat', 'app.debug' => false, 'database.default' => 'mysql',
            'database.connections.mysql.database' => UatEnvironment::DATABASE,
            'database.connections.mysql.username' => 'shifa_uat_123456abcdef',
            'database.connections.mysql.url' => null, 'database.connections.mysql.host' => '127.0.0.1',
            'session.driver' => 'database', 'session.connection' => 'mysql', 'session.cookie' => 'shifastock_uat_session',
            'cache.default' => 'database', 'cache.stores.database.connection' => 'mysql', 'cache.stores.database.lock_connection' => 'mysql', 'cache.prefix' => 'shifastock_uat_',
            'queue.default' => 'sync', 'logging.default' => 'uat', 'filesystems.default' => 'uat']);
    }

    public function test_guard_accepts_only_the_dedicated_configuration_without_connecting_to_a_database(): void
    {
        $this->configureUat();
        UatEnvironment::assertSafe();
        $this->assertSame('shifastock_uat', config('database.connections.mysql.database'));
    }

    public function test_guard_rejects_clinic_database_root_account_and_shared_session_or_cache(): void
    {
        foreach (['app.debug' => true, 'database.connections.mysql.database' => 'shifa_medika', 'database.connections.mysql.username' => 'root',
            'database.connections.mysql.url' => 'mysql://invalid.example.test/clinic', 'database.connections.mysql.host' => 'external.example.test',
            'session.connection' => 'clinic', 'session.cookie' => 'clinic_session', 'cache.stores.database.connection' => 'clinic',
            'cache.prefix' => 'clinic_', 'cache.stores.database.lock_connection' => 'clinic', 'queue.default' => 'database', 'logging.default' => 'single', 'filesystems.default' => 'local'] as $key => $value) {
            $this->configureUat();
            config([$key => $value]);
            try {
                UatEnvironment::assertSafe();
                $this->fail('Unsafe UAT configuration accepted: '.$key);
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('tidak terisolasi', $error->getMessage());
            }
        }
    }

    public function test_generated_environment_does_not_reuse_clinic_credentials_or_cookie(): void
    {
        $variables = UatEnvironment::variables(['host' => '127.0.0.1', 'port' => 3306, 'username' => 'root'], 'shifa_uat_123456abcdef', 'not-a-real-secret');
        $this->assertSame('uat', $variables['APP_ENV']);
        $this->assertSame(UatEnvironment::DATABASE, $variables['DB_DATABASE']);
        $this->assertNotSame('root', $variables['DB_USERNAME']);
        $this->assertSame('false', $variables['APP_DEBUG']);
        $this->assertSame('shifastock_uat_session', $variables['SESSION_COOKIE']);
        $this->assertStringContainsString('private/uat', $variables['APP_CONFIG_CACHE']);
    }

    public function test_listen_port_is_local_configuration_independent_of_the_public_url(): void
    {
        $this->assertSame(8010, UatEnvironment::listenPort([]));
        $this->assertSame(4002, UatEnvironment::listenPort(['listen_port' => 4002]));
        foreach ([0, 80, 65536, '4002;unexpected', 'external.example.test:4002'] as $port) {
            try {
                UatEnvironment::listenPort(['listen_port' => $port]);
                $this->fail('Unsafe port accepted.');
            } catch (RuntimeException $error) {
                $this->assertStringContainsString('Port UAT', $error->getMessage());
            }
        }
    }

    public function test_public_https_uat_requires_secure_session_cookies(): void
    {
        $this->configureUat();
        config(['app.url' => 'https://uat.example.test', 'session.secure' => true]);
        UatEnvironment::assertSafe();
        $this->assertTrue(config('session.secure'));
        config(['session.secure' => false]);
        $this->expectException(RuntimeException::class);
        UatEnvironment::assertSafe();
    }

    public function test_only_loopback_proxy_can_forward_https_and_cannot_override_the_host(): void
    {
        config(['trustedproxy.proxies' => ['127.0.0.1', '::1']]);
        foreach (['127.0.0.1' => true, '198.51.100.20' => false] as $remote => $secure) {
            $request = Request::create('http://uat.example.test/login', 'GET', server: [
                'REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_HOST' => 'untrusted.example.test', 'HTTP_X_FORWARDED_PORT' => '9999',
            ]);
            app(TrustProxies::class)->handle($request, function (Request $request) use ($secure) {
                $this->assertSame($secure, $request->isSecure());
                $this->assertSame('uat.example.test', $request->getHost());
                $this->assertSame($secure ? 443 : 80, $request->getPort());

                return response('OK');
            });
        }
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
    }
}
