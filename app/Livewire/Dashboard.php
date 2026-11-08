<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Support\Dashboard\DashboardSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public const TABS = ['semua', 'segera', 'pantau', 'menunggu'];

    public const VISIBLE_ACTIONS = 8;

    public string $tab = 'semua';

    public function mount(): void
    {
        Gate::authorize('view-dashboard');
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'semua';
    }

    public function render()
    {
        Gate::authorize('view-dashboard');
        $user = auth()->user();
        $data = ['adminStats' => null];
        if ($user->can('view-inventory')) {
            $data = (new DashboardSummary($user))->build();
            $actions = collect($data['actions']);
            $filtered = $this->tab === 'semua' ? $actions : $actions->where('group', $this->tab)->values();
            $data['actionCounts'] = ['semua' => $actions->count()] + $actions->countBy('group')->all() + ['segera' => 0, 'pantau' => 0, 'menunggu' => 0];
            $data['visibleActions'] = $filtered->take(self::VISIBLE_ACTIONS)->all();
            $data['hiddenActions'] = max(0, $filtered->count() - self::VISIBLE_ACTIONS);
        } elseif ($user->can('manage-users') || $user->can('view-audit-logs') || $user->can('manage-system-references') || $user->can('manage-system-settings')) {
            $data['adminStats'] = [
                'users' => DB::table('users')->count(),
                'active_users' => DB::table('users')->where('is_active', true)->count(),
                'roles' => count(Role::cases()),
            ];
        }

        return view('livewire.dashboard', $data)
            ->layout('components.layouts.app', ['title' => 'Dashboard', 'hideDescription' => true]);
    }
}
