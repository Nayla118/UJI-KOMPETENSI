<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to one or more explicit user roles.
 *
 * The admin panel is authenticated with the web session guard, not the
 * Sanctum API guard. Authentication alone is not authorization: a local
 * account with the `staff` role must not reach traveller/user exports or
 * booking management, and a traveller account must never reach the panel.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && in_array($user->role, $roles, true),
            403
        );

        return $next($request);
    }
}
