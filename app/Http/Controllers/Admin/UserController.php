<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'status' => ['nullable', 'in:all,active,inactive'],
            'tab' => ['nullable', 'in:users,permissions'],
        ]);

        $users = User::query()
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('username', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')))
            ->when($data['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when(($data['status'] ?? 'all') !== 'all', fn ($query) => $query->where('is_active', ($data['status'] ?? 'all') === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => Role::cases(),
            'filters' => $data + ['status' => 'all'],
            'activeTab' => $data['tab'] ?? 'users',
            'permissionGroups' => Permissions::definitions(),
            'permissionMatrix' => Permissions::matrix(),
            'lockedPermissions' => Permissions::locked(),
        ]);
    }

    public function updatePermissions(Request $request)
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
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

    public function create()
    {
        return view('admin.users.form', [
            'user' => new User(['role' => Role::Pharmacy, 'is_active' => true]),
            'roles' => Role::cases(),
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
        return view('admin.users.form', [
            'user' => $pengguna,
            'roles' => Role::cases(),
        ]);
    }

    public function update(Request $request, User $pengguna)
    {
        $before = $this->auditSnapshot($pengguna);
        $data = $this->validated($request, $pengguna);
        if (! array_key_exists('password', $data)) {
            unset($data['password']);
        }
        if ($pengguna->is(auth()->user())) {
            $data['is_active'] = true;
            $data['role'] = Role::Administrator;
        }
        $pengguna->update($data);
        $pengguna->refresh();

        AuditLogger::record('user.updated', 'Data pengguna '.$pengguna->username.' diperbarui.', $pengguna, [
            'before' => $before,
            'after' => $this->auditSnapshot($pengguna),
            'password_changed' => array_key_exists('password', $data),
        ], $request);

        return redirect()->route('admin.users.index')->with('status', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(User $pengguna)
    {
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
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
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
}
