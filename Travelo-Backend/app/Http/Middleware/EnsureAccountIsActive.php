<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject deactivated accounts even when they still hold a valid session or
 * Sanctum token. Deactivation also revokes tokens (User model event); this
 * middleware closes the window between an update and the next request.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isActive()) {
            return $next($request);
        }

        $user->tokens()->delete();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'This account has been deactivated. Contact an administrator.',
            ], 401);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->withErrors(['email' => 'This account has been deactivated.']);
    }
}
