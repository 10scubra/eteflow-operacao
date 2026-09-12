<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->role === 'master' ? 'master' : 'operation.home');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:60'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = Str::lower($credentials['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['username' => 'Muitas tentativas. Aguarde um minuto.'])->onlyInput('username');
        }

        if (! Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
            'is_active' => true,
        ])) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['username' => 'Usuário ou senha inválidos.'])->onlyInput('username');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route(Auth::user()->role === 'master' ? 'master' : 'operation.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
