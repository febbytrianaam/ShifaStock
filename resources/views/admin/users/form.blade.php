<x-layouts.app :title="$user->exists ? 'Edit Pengguna' : 'Tambah Pengguna'">
    @php($isSelf = $user->is(auth()->user()))

    <div class="mb-4">
        <a href="{{ route('admin.users.index') }}" class="link inline-flex min-h-11 items-center gap-1 text-[13px] lg:min-h-0">← Kembali ke daftar pengguna</a>
    </div>

    @if($errors->any())
        <div class="callout-danger mb-4 max-w-4xl font-semibold">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="max-w-4xl" data-required-alert="Lengkapi data pengguna" novalidate>
        @csrf
        @if($user->exists)
            @method('PUT')
        @endif

        <x-ui.section :title="$user->exists ? 'Data pengguna' : 'Pengguna baru'" icon="users"
            :subtitle="$user->exists ? 'Perubahan tercatat di Audit Log.' : 'Role menentukan menu dan aksi yang bisa dipakai akun ini.'">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="name" class="label">Nama</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="field">
                </div>
                <div>
                    <label for="username" class="label">Username</label>
                    <input id="username" name="username" value="{{ old('username', $user->username) }}" required class="field">
                </div>
                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="field">
                </div>
                <div>
                    <label for="role" class="label">Role</label>
                    <select id="role" name="role" class="field" @disabled($isSelf)>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                    @if($isSelf)
                        <input type="hidden" name="role" value="{{ $user->role->value }}">
                        <p class="mt-1 text-xs text-slate-500">Role akun sendiri tidak bisa diubah.</p>
                    @endif
                </div>
            </div>

            @if(!$user->exists || auth()->user()->role === \App\Enums\Role::Administrator)
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <p class="mb-3 flex items-center gap-2 text-[13px] font-extrabold text-slate-900"><x-ui.icon name="lock" class="h-4 w-4 text-slate-500" /> {{ $user->exists ? 'Ganti password' : 'Password' }}</p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label for="password" class="label">{{ $user->exists ? 'Password baru' : 'Password' }}</label>
                            <input id="password" name="password" type="password" autocomplete="new-password" @required(!$user->exists) class="field">
                            @if($user->exists)
                                <p class="mt-1 text-xs text-slate-500">Kosongkan jika password tidak diubah.</p>
                            @endif
                        </div>
                        <div>
                            <label for="password_confirmation" class="label">Konfirmasi password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!$user->exists) class="field">
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <label class="flex min-h-11 items-center gap-2 text-[13px] font-bold text-slate-700 lg:min-h-0">
                    <input type="checkbox" name="is_active" value="1" class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-600" @checked(old('is_active', $user->is_active)) @disabled($isSelf)>
                    Akun aktif
                </label>
                @if($isSelf)
                    <input type="hidden" name="is_active" value="1">
                @endif
                <div class="flex gap-2">
                    <a href="{{ route('admin.users.index') }}" class="btn-secondary min-h-11 lg:min-h-0">Batal</a>
                    <button class="btn min-h-11 lg:min-h-0">Simpan</button>
                </div>
            </div>
        </x-ui.section>
    </form>
</x-layouts.app>
