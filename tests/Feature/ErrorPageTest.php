<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_forbidden_page_is_used_for_real_permission_denial(): void
    {
        config(['app.debug' => false]);
        $this->actingAs(User::factory()->create(['role' => Role::Guest]))
            ->get('/admin/pengguna')
            ->assertForbidden()
            ->assertSee('Akses belum tersedia untuk akun Anda')
            ->assertSee('Hubungi administrator klinik')
            ->assertDontSee('This action is unauthorized');
    }

    public function test_unknown_page_uses_branded_not_found_response(): void
    {
        config(['app.debug' => false]);
        $this->get('/halaman-tidak-ada-qa')
            ->assertNotFound()
            ->assertSee('Halaman yang dicari tidak ditemukan')
            ->assertSee('Klinik Shifa Medika')
            ->assertSee('Kembali ke aplikasi')
            ->assertSee('href="'.url('/').'"', false)
            ->assertDontSee('halaman-tidak-ada-qa');
    }

    public function test_server_error_hides_exception_details_and_warns_against_duplicate_transactions(): void
    {
        config(['app.debug' => false]);
        Route::get('/__qa/server-error', function () {
            throw new \RuntimeException('QA_PRIVATE_EXCEPTION_MARKER');
        });
        $this->get('/__qa/server-error')
            ->assertStatus(500)
            ->assertSee('Permintaan belum dapat diproses')
            ->assertSee('agar transaksi tidak tercatat dua kali')
            ->assertDontSee('QA_PRIVATE_EXCEPTION_MARKER')
            ->assertDontSee('RuntimeException')
            ->assertDontSee(base_path());
    }

    #[DataProvider('errorCodes')]
    public function test_error_views_need_no_database_session_authentication_or_frontend_manifest(int $status): void
    {
        config(['app.debug' => false, 'session.driver' => 'database']);
        DB::shouldReceive('connection')->never();
        app('auth')->shouldUse('web');
        $this->mock(Factory::class)->shouldNotReceive('guard');
        app(Vite::class)->useManifestFilename('nonexistent-error-test-manifest.json');

        $html = view('errors.'.$status)->render();

        $this->assertStringContainsString('lang="id"', $html);
        $this->assertStringContainsString((string) $status, $html);
        $this->assertStringContainsString('shifa-medika.png', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('/build/', $html);
    }

    public function test_json_errors_remain_json_for_api_and_frontend_requests(): void
    {
        config(['app.debug' => false]);
        $this->getJson('/halaman-tidak-ada-qa')->assertNotFound()->assertJsonStructure(['message']);
        $this->actingAs(User::factory()->create(['role' => Role::Guest]))
            ->postJson('/transfer', [])->assertForbidden()->assertJsonStructure(['message']);
        Route::get('/__qa/server-error-json', function () {
            throw new \RuntimeException('QA_PRIVATE_EXCEPTION_MARKER');
        });
        $this->getJson('/__qa/server-error-json')->assertStatus(500)
            ->assertExactJson(['message' => 'Server Error']);
    }

    public static function errorCodes(): array
    {
        return [[403], [404], [500]];
    }
}
