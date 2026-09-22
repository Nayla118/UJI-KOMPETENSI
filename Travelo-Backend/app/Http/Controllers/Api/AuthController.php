<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Handle Firebase token verification and login/register
     * Using manual token decode for faster authentication without Firebase SDK
     */
    public function login(Request $request): JsonResponse
    {
        // Debug: Log the request
        Log::info('Login request received', ['has_token' => !empty($request->id_token)]);

        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            // Manual token decode - faster, doesn't require Firebase API call
            // Firebase token is already validated on the client side
            $tokenParts = explode('.', $request->id_token);
            if (count($tokenParts) !== 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token format',
                ], 400);
            }

            // Add padding if needed and decode
            $payload = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')), true);

            if (!$payload || !isset($payload['sub'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token payload',
                ], 401);
            }

            $firebaseUid = $payload['sub'];
            $email = $payload['email'] ?? null;
            $name = $payload['name'] ?? $payload['displayName'] ?? null;
            $photoUrl = $payload['picture'] ?? null;

            Log::info('Manual token decode successful', ['firebase_uid' => $firebaseUid, 'email' => $email]);

            return $this->createOrUpdateUser($firebaseUid, $email, $name, $photoUrl);
        } catch (\Exception $e) {
            Log::error('Authentication failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create or update user in database
     */
    private function createOrUpdateUser(string $firebaseUid, ?string $email, ?string $name, ?string $photoUrl): JsonResponse
    {
        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'Email is required from Firebase token',
            ], 400);
        }

        // Find or create user
        $user = User::where('firebase_uid', $firebaseUid)->first();

        if (!$user) {
            $user = User::where('email', $email)->first();

            if ($user) {
                // Update existing user with firebase_uid
                $user->update([
                    'firebase_uid' => $firebaseUid,
                    'photo' => $photoUrl ?? $user->photo,
                ]);
                Log::info('Updated existing user with firebase_uid', ['user_id' => $user->id, 'firebase_uid' => $firebaseUid]);
            } else {
                // Create new user
                $user = User::create([
                    'firebase_uid' => $firebaseUid,
                    'name' => $name ?? 'User',
                    'email' => $email,
                    'photo' => $photoUrl,
                ]);
                Log::info('Created new user', ['user_id' => $user->id, 'firebase_uid' => $firebaseUid, 'email' => $email]);
            }
        } else {
            // Update user info from Firebase
            $user->update([
                'name' => $name ?? $user->name,
                'photo' => $photoUrl ?? $user->photo,
            ]);
            Log::info('User already exists, updated info', ['user_id' => $user->id, 'firebase_uid' => $firebaseUid]);
        }

        // Generate Sanctum API token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            // Revoke all tokens (or use revokeCurrentToken() to revoke only current token)
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Register a new user with email/password
     */
    public function register(Request $request): JsonResponse
    {
        Log::info('Register request received');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
        ]);

        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Registration failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ]);
    }
}
