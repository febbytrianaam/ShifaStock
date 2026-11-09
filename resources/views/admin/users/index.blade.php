<x-layouts.app :title="$canManageUsers ? 'Pengguna' : 'Hak Akses'">
    @php
        $tabs = [];
        if ($canManageUsers) {
            $tabs[] = ['label' => 'Daftar Pengguna', 'href' => route('admin.users.index', ['tab' => 'users']), 'active' => $activeTab === 'users'];
        }
        if ($canManagePermissions) {
            $tabs[] = ['label' => 'Hak Akses', 'href' => route('admin.users.index', ['tab' => 'permissions']), 'active' => $activeTab === 'permissions'];
        }
        $initials = fn ($user) => collect(preg_split('/\s+/', trim($user->name ?: $user->username)))->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('') ?: 'U';
    @endphp

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.tabs :links="$tabs" label="Pengguna dan hak akses" />
        @if($activeTab === 'users')
            <a href="{{ route('admin.users.create') }}" class="btn min-h-11 lg:min-h-0"><x-ui.icon name="plus" /> Tambah Pengguna</a>
        @endif
    </div>

    @if(session('status'))
        <div class="callout-ok mb-4 font-semibold">{{ session('status') }}</div>
    @endif

    @if($activeTab === 'permissions')
        <form method="POST" action="{{ route('admin.users.permissions.update') }}">
            @csrf
            <x-ui.section title="Matrix Hak Akses Role" icon="key" flush
                subtitle="Ceklis permission yang boleh dipakai setiap role. Akses administrasi utama untuk Administrator dikunci agar akun superadmin tidak terkunci keluar.">
                <p class="border-b border-slate-100 bg-slate-50/60 px-4 py-2.5 text-xs text-slate-500">Guest hanya dapat melihat data persediaan. Izin perubahan data dan menu administrasi tidak dapat diaktifkan untuk Guest.</p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-[13px]">
                        <thead class="table-head text-left">
                            <tr>
                                <th class="min-w-64 px-4 py-2.5">Permission</th>
                                @foreach($roles as $role)
                                    <th class="px-4 py-2.5 text-center">{{ $role->label() }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($permissionGroups as $group => $permissions)
                                <tr class="border-t border-slate-100 bg-slate-50/70">
                                    <td colspan="{{ count($roles) + 1 }}" class="px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $group }}</td>
                                </tr>
                                @foreach($permissions as $permission => $label)
                                    <tr class="border-t border-slate-100">
                                        <td class="px-4 py-2.5">
                                            <p class="font-bold text-slate-900">{{ $label }}</p>
                                            <p class="mt-0.5 font-mono text-[11px] text-slate-400">{{ $permission }}</p>
                                        </td>
                                        @foreach($roles as $role)
                                            @php
                                                $locked = in_array($permission, $lockedPermissions[$role->value] ?? [], true);
                                                $forbidden = in_array($permission, $forbiddenPermissions[$role->value] ?? [], true);
                                                $checked = (bool) ($permissionMatrix[$role->value][$permission] ?? false);
                                            @endphp
                                            <td class="px-4 py-2.5 text-center">
                                                <label class="inline-flex min-h-11 items-center justify-center gap-2 lg:min-h-0">
                                                    <input
                                                        type="checkbox"
                                                        name="permissions[{{ $role->value }}][{{ $permission }}]"
                                                        value="1"
                                                        @checked($checked)
                                                        @disabled($locked || $forbidden)
                                                        class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-600"
                                                    >
                                                    @if($locked)
                                                        <span class="badge badge-muted">Wajib</span>
                                                    @elseif($forbidden)
                                                        <span class="text-[11px] text-slate-400">Dibatasi</span>
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
                    <button class="btn min-h-11 lg:min-h-0">Simpan Hak Akses</button>
                </div>
            </x-ui.section>
        </form>
        <form id="reset-permissions-form" method="POST" action="{{ route('admin.users.permissions.reset') }}" class="mt-3 flex justify-end" data-confirm-action data-confirm-title="Reset hak akses?" data-confirm-text="Semua role akan dikembalikan ke permission default sistem. Akses wajib Administrator tetap dikunci." data-confirm-button="Ya, reset default" data-confirm-color="#e11d48">
            @csrf
            <button class="btn-danger min-h-11 lg:min-h-0">Kembalikan Default Role</button>
        </form>

        <x-ui.section class="mt-4" title="Preview Akses Role" icon="users" tone="teal"
            subtitle="Gunakan bagian ini untuk mengecek menu dan aksi yang terlihat per role sebelum atau setelah matrix disimpan.">
            <div class="grid gap-4 lg:grid-cols-[260px_1fr]">
                <div>
                    <label class="label" for="role-preview-select">Role</label>
                    <select id="role-preview-select" class="field" data-role-preview-select>
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
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <p class="text-[13px] font-extrabold text-slate-900">Menu terlihat</p>
                                    @if($visibleMenus->isEmpty())
                                        <p class="mt-3 text-[13px] font-bold text-rose-700">Role ini belum punya menu yang bisa dibuka.</p>
                                    @else
                                        <div class="mt-3 space-y-3">
                                            @foreach($visibleMenus->groupBy('group') as $group => $items)
                                                <div>
                                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $group }}</p>
                                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                        @foreach($items as $item)
                                                            <span class="badge badge-brand">{{ $item['label'] }}</span>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <div class="rounded-xl border border-slate-200 p-4">
                                    <p class="text-[13px] font-extrabold text-slate-900">Aksi diizinkan</p>
                                    <div class="mt-3 space-y-3">
                                        @foreach($allowedPermissions as $group => $permissions)
                                            <div>
                                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $group }}</p>
                                                @if($permissions->isEmpty())
                                                    <p class="mt-1 text-xs text-slate-400">Tidak ada.</p>
                                                @else
                                                    <ul class="mt-1.5 space-y-1 text-[13px] text-slate-700">
                                                        @foreach($permissions as $permission => $label)
                                                            <li class="flex items-start gap-2">
                                                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-600"></span>
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
        </x-ui.section>
    @else
        <form method="GET" class="mb-3.5 flex flex-wrap items-center gap-2">
            <input type="hidden" name="tab" value="users">
            <x-ui.search name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Cari nama, username, atau email" label="Cari pengguna" width="sm:w-80" />
            <select name="role" class="field min-h-11 w-full sm:w-52 lg:min-h-[34px] lg:py-1.5" aria-label="Role">
                <option value="">Semua role</option>
                @foreach($roles as $role)
                    <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            <select name="status" class="field min-h-11 w-full sm:w-40 lg:min-h-[34px] lg:py-1.5" aria-label="Status">
                <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Semua status</option>
                <option value="active" @selected(($filters['status'] ?? 'all') === 'active')>Aktif</option>
                <option value="inactive" @selected(($filters['status'] ?? 'all') === 'inactive')>Nonaktif</option>
            </select>
            <button class="btn-secondary min-h-11 lg:min-h-[34px] lg:py-1.5">Filter</button>
        </form>

        <x-ui.section title="Daftar pengguna" icon="users" flush
            subtitle="Setiap petugas memakai akun sendiri agar aktivitasnya tercatat di Audit Log."
            :meta="$users->total().' pengguna'">
            <div class="overflow-x-auto">
                <table class="block min-w-full text-[13px] md:table">
                    <thead class="table-head hidden text-left md:table-header-group">
                        <tr>
                            <th class="px-4 py-2.5">Pengguna</th>
                            <th class="px-4 py-2.5">Role</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="block md:table-row-group">
                        @forelse($users as $user)
                            <tr class="flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-slate-100 px-4 py-3 first:border-t-0 md:table-row md:p-0 md:first:border-t">
                                <td class="w-full md:w-auto md:px-4 md:py-2.5">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $user->is_active ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-500' }} text-xs font-extrabold" aria-hidden="true">{{ $initials($user) }}</span>
                                        <div class="min-w-0">
                                            <p class="font-extrabold text-slate-900">{{ $user->name }}</p>
                                            <p class="mt-0.5 text-xs text-slate-500">{{ $user->username }} · {{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="md:px-4 md:py-2.5">
                                    <span class="badge badge-brand">{{ $user->role->label() }}</span>
                                </td>
                                <td class="md:px-4 md:py-2.5">
                                    <span class="badge {{ $user->is_active ? 'badge-ok' : 'badge-danger' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </td>
                                <td class="w-full md:w-auto md:px-4 md:py-2.5">
                                    <div class="flex gap-2 md:justify-end">
                                        @if($user->role !== \App\Enums\Role::Administrator || auth()->user()->role === \App\Enums\Role::Administrator)
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn-secondary min-h-11 lg:min-h-8 lg:py-1">Edit</a>
                                        @if(!$user->is(auth()->user()) && $user->is_active)
                                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm-action data-confirm-title="Nonaktifkan pengguna?" data-confirm-text="Akun {{ $user->name }} tidak bisa login lagi sampai diaktifkan kembali." data-confirm-button="Ya, nonaktifkan">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn-danger min-h-11 lg:min-h-8 lg:py-1">Nonaktifkan</button>
                                            </form>
                                        @endif
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">Belum ada pengguna yang sesuai.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="border-t border-slate-100 px-4 py-3">{{ $users->links() }}</div>
            @endif
        </x-ui.section>
    @endif
</x-layouts.app>
