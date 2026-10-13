<x-layouts.app :title="$user->exists ? 'Edit Pengguna' : 'Tambah Pengguna'">
    <div class="mb-5 flex justify-end">
        <a href="{{ route('admin.users.index') }}" class="btn-secondary">Kembali</a>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card max-w-3xl p-6" data-required-alert="Lengkapi data pengguna" novalidate>
        @csrf
        @if($user->exists)
            @method('PUT')
        @endif

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
                <select id="role" name="role" class="field" @disabled($user->is(auth()->user()))>
                    @foreach($roles as $role)
                        <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
                @if($user->is(auth()->user()))
                    <input type="hidden" name="role" value="{{ $user->role->value }}">
                @endif
            </div>
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

        <label class="mt-5 flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) @disabled($user->is(auth()->user()))>
            Akun aktif
        </label>
        @if($user->is(auth()->user()))
            <input type="hidden" name="is_active" value="1">
        @endif

        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('admin.users.index') }}" class="btn-secondary">Batal</a>
            <button class="btn">Simpan</button>
        </div>
    </form>
</x-layouts.app>
