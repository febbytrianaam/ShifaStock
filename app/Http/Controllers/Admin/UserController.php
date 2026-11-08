<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $canManageUsers = $request->user()->can(Permissions::MANAGE_USERS);
        $canManagePermissions = $request->user()->can(Permissions::MANAGE_PERMISSIONS);
        abort_unless($canManageUsers || $canManagePermissions, 403);

        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'status' => ['nullable', 'in:all,active,inactive'],
            'tab' => ['nullable', 'in:users,permissions'],
        ]);

        $activeTab = $data['tab'] ?? ($canManageUsers ? 'users' : 'permissions');
        abort_unless($activeTab === 'users' ? $canManageUsers : $canManagePermissions, 403);

        $users = $activeTab === 'users' ? User::query()
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('username', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->when($data['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when(($data['status'] ?? 'all') !== 'all', fn ($query) => $query->where('is_active', ($data['status'] ?? 'all') === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString() : null;

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::cases(),
            'filters' => $data + ['status' => 'all'],
            'activeTab' => $activeTab,
            'canManageUsers' => $canManageUsers,
            'canManagePermissions' => $canManagePermissions,
            ...($activeTab === 'permissions' ? [
                'permissionGroups' => Permissions::definitions(),
                'permissionMatrix' => Permissions::matrix(),
                'lockedPermissions' => Permissions::locked(),
                'forbiddenPermissions' => Permissions::forbidden(),
                'menuPreview' => $this->menuPreview(),
            ] : []),
        ]);
    }

    public function updatePermissions(Request $request)
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['boolean'],
        ]);

        $before = Permissions::matrix();
        Permissions::syncMatrix($validated['permissions'] ?? []);
        $after = Permissions::matrix();

        AuditLogger::record('permissions.updated', 'Matrix hak akses role diperbarui.', null, [
            'before' => $before,
            'after' => $after,
        ], $request);

        return redirect()->route('admin.users.index', ['tab' => 'permissions'])->with('status', 'Hak akses role berhasil diperbarui.');
    }

    public function resetPermissions(Request $request)
    {
        $before = Permissions::matrix();
        Permissions::syncMatrix(Permissions::defaults());
        $after = Permissions::matrix();

        AuditLogger::record('permissions.reset', 'Matrix hak akses role dikembalikan ke default.', null, [
            'before' => $before,
            'after' => $after,
        ], $request);

        return redirect()->route('admin.users.index', ['tab' => 'permissions'])->with('status', 'Hak akses role dikembalikan ke default.');
    }

    public function create()
    {
        return view('admin.users.form', [
            'user' => new User(['role' => Role::Pharmacy, 'is_active' => true]),
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = User::create($data);

        AuditLogger::record('user.created', 'Pengguna '.$user->username.' dibuat.', $user, [
            'after' => $this->auditSnapshot($user),
        ], $request);

        return redirect()->route('admin.users.index')->with('status', 'Pengguna baru berhasil dibuat.');
    }

    public function edit(User $pengguna)
    {
        $this->authorizeTarget($pengguna);

        return view('admin.users.form', [
            'user' => $pengguna,
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function update(Request $request, User $pengguna)
    {
        $this->authorizeTarget($pengguna);

        $before = $this->auditSnapshot($pengguna);
        $data = $this->validated($request, $pengguna);
        if ($pengguna->is(auth()->user())) {
            $data['is_active'] = true;
            $data['role'] = $pengguna->role;
        }
        $pengguna->fill($data);
        if ($pengguna->isDirty('password')) {
            $pengguna->setRememberToken(Str::random(60));
        }
        $pengguna->save();
        $pengguna->refresh();
        if ($pengguna->is($request->user())) {
            // AuthenticateSession stores this fresh hash after the response, keeping this device signed in.
            $request->user()->refresh();
        }

        AuditLogger::record('user.updated', 'Data pengguna '.$pengguna->username.' diperbarui.', $pengguna, [
            'before' => $before,
            'after' => $this->auditSnapshot($pengguna),
            'password_changed' => array_key_exists('password', $data),
        ], $request);

        return redirect()->route('admin.users.index')->with('status', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(User $pengguna)
    {
        $this->authorizeTarget($pengguna);
        abort_if($pengguna->is(auth()->user()), 403);
        $before = $this->auditSnapshot($pengguna);
        $pengguna->update(['is_active' => false]);
        $pengguna->refresh();

        AuditLogger::record('user.deactivated', 'Pengguna '.$pengguna->username.' dinonaktifkan.', $pengguna, [
            'before' => $before,
            'after' => $this->auditSnapshot($pengguna),
        ], request());

        return redirect()->route('admin.users.index')->with('status', 'Pengguna dinonaktifkan.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        if ($request->user()->role !== Role::Administrator) {
            abort_if($request->input('role') === Role::Administrator->value, 403);
            abort_if($user && $request->filled('password'), 403);
        }

        $requiredPassword = $user ? 'nullable' : 'required';
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$requiredPassword, 'string', 'min:6', 'confirmed'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        if (blank($data['password'] ?? null) || ($user && Hash::check($data['password'], $user->password))) {
            unset($data['password']);
        }

        return $data;
    }

    private function authorizeTarget(User $user): void
    {
        abort_if($user->role === Role::Administrator && auth()->user()->role !== Role::Administrator, 403);
    }

    private function assignableRoles(): array
    {
        return array_values(array_filter(Role::cases(), fn (Role $role) =>
            $role !== Role::Administrator || auth()->user()->role === Role::Administrator));
    }

    private function auditSnapshot(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role?->value,
            'is_active' => $user->is_active,
        ];
    }

    private function menuPreview(): array
    {
        return [
            ['group' => 'Menu Utama', 'label' => 'Dashboard', 'permission' => Permissions::VIEW_DASHBOARD],
            ['group' => 'Kerja Harian', 'label' => 'Pemeriksaan Stok', 'permission' => Permissions::VIEW_NIGHTLY_CHECKS],
            ['group' => 'Kerja Harian', 'label' => 'Stock Opname Periodik', 'permission' => Permissions::VIEW_OPNAME],
            ['group' => 'Kerja Harian', 'label' => 'Riwayat Pemeriksaan', 'permission' => Permissions::VIEW_NIGHTLY_HISTORY],
            ['group' => 'Kerja Harian', 'label' => 'Monitoring Stok', 'permission' => Permissions::VIEW_SHORTAGES],
            ['group' => 'Kerja Harian', 'label' => 'Monitoring Kedaluwarsa', 'permission' => Permissions::VIEW_EXPIRY],
            ['group' => 'Transaksi', 'label' => 'Penerimaan Obat', 'permission' => Permissions::VIEW_RECEIPTS],
            ['group' => 'Transaksi', 'label' => 'Transfer Stok', 'permission' => Permissions::VIEW_TRANSFERS],
            ['group' => 'Transaksi', 'label' => 'Pengeluaran & Impor', 'permission' => Permissions::VIEW_ISSUES],
            ['group' => 'Transaksi', 'label' => 'Retur PBF', 'permission' => Permissions::VIEW_RETURNS],
            ['group' => 'Transaksi', 'label' => 'Kondisi Khusus', 'permission' => Permissions::VIEW_SPECIAL_ADJUSTMENTS],
            ['group' => 'Data & Laporan', 'label' => 'Data Obat', 'permission' => Permissions::VIEW_MEDICINES],
            ['group' => 'Data & Laporan', 'label' => 'Stok Awal', 'permission' => Permissions::VIEW_OPENING_BALANCES],
            ['group' => 'Data & Laporan', 'label' => 'Parameter Monitoring', 'permission' => Permissions::VIEW_MONITORING_PARAMETERS],
            ['group' => 'Data & Laporan', 'label' => 'Laporan', 'permission' => Permissions::VIEW_REPORTS],
            ['group' => 'Data & Laporan', 'label' => 'Kartu Stok', 'permission' => Permissions::VIEW_STOCK_CARD],
            ['group' => 'Administrasi', 'label' => 'Pengguna', 'permission' => Permissions::MANAGE_USERS],
            ['group' => 'Administrasi', 'label' => 'Audit Log', 'permission' => Permissions::VIEW_AUDIT_LOGS],
            ['group' => 'Administrasi', 'label' => 'Referensi Sistem', 'permission' => Permissions::MANAGE_SYSTEM_REFERENCES],
            ['group' => 'Administrasi', 'label' => 'Pengaturan Sistem', 'permission' => Permissions::MANAGE_SYSTEM_SETTINGS],
        ];
    }
}
