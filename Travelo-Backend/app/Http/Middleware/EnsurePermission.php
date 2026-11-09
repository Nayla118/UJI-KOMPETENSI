<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce a named permission from config/permissions.php.
 *
 * Multiple names are treated as "any of" so a route can accept e.g.
 * reports.checkin OR reports.revenue. Role middleware is intentionally not
 * stacked on portal routes: permissions are the single source of truth.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isActive(), 403);

        $allowed = collect($permissions)->contains(
            fn (string $permission): bool => Gate::forUser($user)->allows('permission', $permission)
        );

        abort_unless($allowed, 403);

        return $next($request);
    }
}
