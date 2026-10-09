<x-layouts.app title="Pengguna">
    <div class="mb-5 flex justify-end">
        <a href="{{ route('admin.users.create') }}" class="btn"><span aria-hidden="true">+</span> Tambah Pengguna</a>
    </div>

    @if(session('status'))
        <div class="mb-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="GET" class="card mb-5 grid gap-3 p-4 md:grid-cols-[1fr_220px_180px_auto]">
        <input name="search" value="{{ $filters['search'] ?? '' }}" class="field" placeholder="Cari nama, username, atau email">
        <select name="role" class="field">
            <option value="">Semua role</option>
            @foreach($roles as $role)
                <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>
            @endforeach
        </select>
        <select name="status" class="field">
            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Semua status</option>
            <option value="active" @selected(($filters['status'] ?? 'all') === 'active')>Aktif</option>
            <option value="inactive" @selected(($filters['status'] ?? 'all') === 'inactive')>Nonaktif</option>
        </select>
        <button class="btn-secondary">Filter</button>
    </form>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100">
                <thead class="bg-slate-50 text-left text-slate-500">
                    <tr>
                        <th class="table-cell">Pengguna</th>
                        <th class="table-cell">Role</th>
                        <th class="table-cell">Status</th>
                        <th class="table-cell text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        <tr>
                            <td class="table-cell">
                                <p class="font-semibold text-slate-800">{{ $user->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $user->username }} · {{ $user->email }}</p>
                            </td>
                            <td class="table-cell">
                                <span class="badge badge-brand">{{ $user->role->label() }}</span>
                            </td>
                            <td class="table-cell">
                                <span class="badge {{ $user->is_active ? 'badge-ok' : 'badge-danger' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>
                            <td class="table-cell">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary">Edit</a>
                                    @if(!$user->is(auth()->user()) && $user->is_active)
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Nonaktifkan pengguna ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn-secondary text-rose-600">Nonaktifkan</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="table-cell text-center text-sm text-slate-500">Belum ada pengguna yang sesuai.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 p-4">{{ $users->links() }}</div>
    </div>
</x-layouts.app>
