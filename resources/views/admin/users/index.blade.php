<x-layouts.app title="Pengguna">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex w-fit rounded-lg border border-slate-200 bg-white p-1 text-sm font-semibold shadow-xs">
            <a href="{{ route('admin.users.index', ['tab' => 'users']) }}" class="rounded-md px-3 py-1.5 {{ $activeTab === 'users' ? 'bg-[#3277B2] text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}">
                Daftar Pengguna
            </a>
            <a href="{{ route('admin.users.index', ['tab' => 'permissions']) }}" class="rounded-md px-3 py-1.5 {{ $activeTab === 'permissions' ? 'bg-[#3277B2] text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' }}">
                Hak Akses
            </a>
        </div>

        @if($activeTab === 'users')
            <a href="{{ route('admin.users.create') }}" class="btn"><span aria-hidden="true">+</span> Tambah Pengguna</a>
        @endif
    </div>

    @if(session('status'))
        <div class="mb-5 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
    @endif

    @if($activeTab === 'permissions')
        <form method="POST" action="{{ route('admin.users.permissions.update') }}" class="card overflow-hidden">
            @csrf
            <div class="border-b border-slate-100 p-4">
                <h2 class="text-base font-bold text-slate-900">Matrix Hak Akses Role</h2>
                <p class="mt-1 text-sm text-slate-500">Ceklis permission yang boleh dipakai setiap role. Akses administrasi utama untuk Administrator dikunci agar akun superadmin tidak terkunci keluar.</p>
                <p class="mt-1 text-xs text-slate-500">Guest hanya dapat melihat data persediaan. Izin perubahan data dan menu administrasi tidak dapat diaktifkan untuk Guest.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="table-cell min-w-64">Permission</th>
                            @foreach($roles as $role)
                                <th class="table-cell text-center">{{ $role->label() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($permissionGroups as $group => $permissions)
                            <tr class="bg-slate-50/70">
                                <td colspan="{{ count($roles) + 1 }}" class="px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $group }}</td>
                            </tr>
                            @foreach($permissions as $permission => $label)
                                <tr>
                                    <td class="table-cell">
                                        <p class="font-semibold text-slate-800">{{ $label }}</p>
                                        <p class="mt-1 font-mono text-[11px] text-slate-400">{{ $permission }}</p>
                                    </td>
                                    @foreach($roles as $role)
                                        @php
                                            $locked = in_array($permission, $lockedPermissions[$role->value] ?? [], true);
                                            $forbidden = in_array($permission, $forbiddenPermissions[$role->value] ?? [], true);
                                            $checked = (bool) ($permissionMatrix[$role->value][$permission] ?? false);
                                        @endphp
                                        <td class="table-cell text-center">
                                            <label class="inline-flex items-center justify-center">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[{{ $role->value }}][{{ $permission }}]"
                                                    value="1"
                                                    @checked($checked)
                                                    @disabled($locked || $forbidden)
                                                    class="h-4 w-4 rounded border-slate-300 text-[#3277B2] focus:ring-[#3277B2]"
                                                >
                                                @if($locked)
                                                    <span class="ml-2 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-500">Wajib</span>
                                                @elseif($forbidden)
                                                    <span class="ml-2 text-[10px] text-slate-400">Dibatasi</span>
                                                @endif
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end border-t border-slate-100 p-4">
                <button class="btn">Simpan Hak Akses</button>
            </div>
        </form>
        <form id="reset-permissions-form" method="POST" action="{{ route('admin.users.permissions.reset') }}" class="mt-3 flex justify-end" data-confirm-action data-confirm-title="Reset hak akses?" data-confirm-text="Semua role akan dikembalikan ke permission default sistem. Akses wajib Administrator tetap dikunci." data-confirm-button="Ya, reset default" data-confirm-color="#e11d48">
            @csrf
            <button class="btn-secondary text-rose-700">Kembalikan Default Role</button>
        </form>

        <section class="card mt-5 overflow-hidden">
            <div class="border-b border-slate-100 p-4">
                <h2 class="text-base font-bold text-slate-900">Preview Akses Role</h2>
                <p class="mt-1 text-sm text-slate-500">Gunakan bagian ini untuk mengecek menu dan aksi yang terlihat per role sebelum atau setelah matrix disimpan.</p>
            </div>
            <div class="grid gap-4 p-4 lg:grid-cols-[260px_1fr]">
                <div>
                    <label class="label" for="role-preview-select">Role</label>
                    <select id="role-preview-select" class="field text-xs" data-role-preview-select>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-4">
                    @foreach($roles as $role)
                        @php
                            $visibleMenus = collect($menuPreview)->filter(fn ($item) => (bool) ($permissionMatrix[$role->value][$item['permission']] ?? false));
                            $allowedPermissions = collect($permissionGroups)->map(fn ($permissions) => collect($permissions)->filter(fn ($label, $permission) => (bool) ($permissionMatrix[$role->value][$permission] ?? false)));
                        @endphp
                        <div data-role-preview-panel="{{ $role->value }}" @if(!$loop->first) hidden @endif>
                            <div class="grid gap-4 xl:grid-cols-2">
                                <div class="rounded-lg border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Menu terlihat</p>
                                    @if($visibleMenus->isEmpty())
                                        <p class="mt-3 text-sm font-semibold text-rose-700">Role ini belum punya menu yang bisa dibuka.</p>
                                    @else
                                        <div class="mt-3 space-y-3">
                                            @foreach($visibleMenus->groupBy('group') as $group => $items)
                                                <div>
                                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $group }}</p>
                                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                                        @foreach($items as $item)
                                                            <span class="badge badge-brand">{{ $item['label'] }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="rounded-lg border border-slate-200 bg-white p-4">
                                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Aksi diizinkan</p>
                                    <div class="mt-3 space-y-3">
                                        @foreach($allowedPermissions as $group => $permissions)
                                            <div>
                                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ $group }}</p>
                                                @if($permissions->isEmpty())
                                                    <p class="mt-1 text-xs text-slate-400">Tidak ada.</p>
                                                @else
                                                    <ul class="mt-1 space-y-1 text-sm text-slate-700">
                                                        @foreach($permissions as $permission => $label)
                                                            <li class="flex items-start gap-2">
                                                                <span class="mt-1 h-1.5 w-1.5 rounded-full bg-[#3277B2]"></span>
                                                                <span>{{ $label }}</span>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @else
    <form method="GET" class="card mb-5 grid gap-3 p-4 md:grid-cols-[1fr_220px_180px_auto]">
        <input type="hidden" name="tab" value="users">
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
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm-action data-confirm-title="Nonaktifkan pengguna?" data-confirm-text="Akun {{ $user->name }} tidak bisa login lagi sampai diaktifkan kembali." data-confirm-button="Ya, nonaktifkan">
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
    @endif
</x-layouts.app>
