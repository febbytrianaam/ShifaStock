<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
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
        ]);
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
        User::create($data);

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
        $data = $this->validated($request, $pengguna);
        if (! array_key_exists('password', $data)) {
            unset($data['password']);
        }
        if ($pengguna->is(auth()->user())) {
            $data['is_active'] = true;
            $data['role'] = Role::Administrator;
        }
        $pengguna->update($data);

        return redirect()->route('admin.users.index')->with('status', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(User $pengguna)
    {
        abort_if($pengguna->is(auth()->user()), 403);
        $pengguna->update(['is_active' => false]);

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
}
