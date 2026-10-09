<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
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
        Gate::define('view-inventory', fn (User $user) => $user->is_active && in_array($user->role, [Role::Pharmacy, Role::Pharmacist], true));
        Gate::define('manage-medicines', fn (User $user) => $user->is_active && $user->role === Role::Pharmacist);
        Gate::define('manage-users', fn (User $user) => $user->is_active && $user->role === Role::Administrator);
        Gate::define('finalize-opname', fn (User $user) => $user->is_active && $user->role === Role::Pharmacist);
    }
}
