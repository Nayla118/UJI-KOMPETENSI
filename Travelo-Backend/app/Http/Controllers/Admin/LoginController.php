<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = $request->user();

            // The web panel is for portal roles only. A local-password
            // traveller account must not receive an authenticated admin
            // session, even before route-level permission middleware runs.
            if (! $user->isPortalUser() || ! $user->isActive()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Email atau password salah, atau akun tidak aktif.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            // Redirect by role after login.
            if ($user->isAdmin()) {
                return redirect()->intended(route('admin.dashboard'));
            }

            // Gate staff have exactly one operational screen.
            if ($user->isGateStaff()) {
                return redirect()->intended(route('owner.scanner'));
            }

            // Owner and manager both use the owner portal.
            return redirect()->intended(route('owner.dashboard'));
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
