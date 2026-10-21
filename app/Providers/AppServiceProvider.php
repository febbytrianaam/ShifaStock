<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissions;
use App\Support\UatEnvironment;
use App\Support\WorkspaceFilesystem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('files', fn () => new WorkspaceFilesystem);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('uat')) {
            UatEnvironment::assertSafe();
        }

        foreach (Permissions::all() as $permission) {
            Gate::define($permission, fn (User $user) => $user->is_active && Permissions::roleCan($user->role, $permission));
        }
    }
}
