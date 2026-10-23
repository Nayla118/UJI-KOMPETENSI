<?php

namespace App\Services;

use App\Contracts\FirebaseIdentityVerifier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Shared helpers for Firebase-based authentication.
 *
 * - verifyIdToken(): delegates to the Firebase Admin SDK verifier. The token
 *   is never trusted just because it came from the client.
 * - syncUser(): create-or-update the local user and issue a Sanctum token.
 */
class FirebaseAuthService
{
    public function __construct(
        private readonly FirebaseIdentityVerifier $verifier
    ) {}

    /**
     * @return array{
     *     uid: string,
     *     email: ?string,
     *     name: ?string,
     *     photo: ?string,
     *     provider: ?string,
     *     email_verified: bool
     * }
     *
     * @throws \App\Exceptions\InvalidFirebaseTokenException
     */
    public function verifyIdToken(string $token): array
    {
        return $this->verifier->verify($token);
    }

    /**
     * Create or update the local user for a verified Firebase identity and
     * return the user plus a fresh Sanctum token.
     *
     * @return array{user: User, token: string}|array{error: string}
     */
    public function syncUser(string $firebaseUid, ?string $email, ?string $name = null, ?string $photoUrl = null): array
    {
        if (!$email) {
            return ['error' => 'Email is required from Firebase token'];
        }

        $user = User::where('firebase_uid', $firebaseUid)->first();

        if (!$user) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->update([
                    'firebase_uid' => $firebaseUid,
                    // A locally uploaded profile photo wins over the provider
                    // picture so signing in again does not replace it.
                    'photo' => $user->hasCustomPhoto()
                        ? $user->getRawOriginal('photo')
                        : ($photoUrl ?? $user->getRawOriginal('photo')),
                ]);
                Log::info('Updated existing user with firebase_uid', ['user_id' => $user->id, 'firebase_uid' => $firebaseUid]);
            } else {
                $user = User::create([
                    'firebase_uid' => $firebaseUid,
                    'name' => $name ?? 'User',
                    'email' => $email,
                    'photo' => $photoUrl,
                ]);
                Log::info('Created new user', ['user_id' => $user->id]);
            }
        } else {
            // The local name and a custom profile photo are editable in the
            // app, so a later sign-in must not overwrite them with provider
            // values. Refresh the provider photo only when no custom upload
            // exists.
            $user->update([
                'photo' => $user->hasCustomPhoto()
                    ? $user->getRawOriginal('photo')
                    : ($photoUrl ?? $user->getRawOriginal('photo')),
            ]);
            Log::info('User already exists, refreshed provider photo', ['user_id' => $user->id, 'firebase_uid' => $firebaseUid]);
        }

        return [
            'user' => $user,
            'token' => $user->createToken('auth-token')->plainTextToken,
        ];
    }
}
