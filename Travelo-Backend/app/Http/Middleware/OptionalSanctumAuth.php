<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Resolve a Sanctum user when a valid bearer token is present, but never block
 * the request when it is missing or invalid. Public catalog endpoints stay
 * public; only the optional `is_wishlisted` field depends on the token.
 */
class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null && $request->bearerToken() !== null) {
            try {
                $user = auth('sanctum')->user();

                // Deactivated accounts are anonymous for public endpoints;
                // protected routes are also guarded by the `active` middleware.
                if ($user !== null && $user->isActive()) {
                    $request->setUserResolver(static fn () => $user);
                }
            } catch (Throwable) {
                // Invalid/expired token: continue as an anonymous request.
            }
        }

        return $next($request);
    }
}
