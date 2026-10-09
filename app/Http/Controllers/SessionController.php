<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:50'], 'password' => ['required', 'string']]);
        $key = 'login:'.Str::lower($data['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['username' => 'Terlalu banyak percobaan. Coba kembali dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        if (! Auth::attempt([...$data, 'is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['username' => 'Username atau password tidak sesuai, atau akun tidak aktif.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        AuditLogger::record('auth.login', 'Login berhasil.', null, [
            'username' => $data['username'],
        ], $request);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        $actor = $request->user();
        AuditLogger::record('auth.logout', 'Logout dari sistem.', null, [], $request, $actor);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
