<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Symfony\Component\HttpFoundation\Response;

class VerifyFirebaseToken
{
    public function __construct(
        protected Auth $firebaseAuth
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token not provided',
            ], 401);
        }

        try {
            $verifiedIdToken = $this->firebaseAuth->verifyIdToken($token);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');

            // Store firebase_uid in request for later use
            $request->attributes->set('firebase_uid', $firebaseUid);

            return $next($request);
        } catch (FailedToVerifyToken $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token: ' . $e->getMessage(),
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token verification failed: ' . $e->getMessage(),
            ], 401);
        }
    }
}
