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
            // The web panel is for operational roles only. A local-password
            // traveller account must not receive an authenticated admin
            // session, even before route-level role middleware runs.
            if (!$request->user()->isStaffOrAdmin()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'email' => 'Email atau password salah.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            // Gate staff cannot open the admin-only management screens, so
            // always land on the scanner instead of a 403 dashboard (even if
            // an earlier request stored an inaccessible intended URL).
            if ($request->user()->isAdmin()) {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->route('admin.scanner');
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
