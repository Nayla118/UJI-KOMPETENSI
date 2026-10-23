<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidFirebaseTokenException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FirebaseAuthService;
use App\Services\ProfilePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        protected FirebaseAuthService $firebase,
        protected ProfilePhotoService $profilePhotos
    ) {}

    /**
     * Verify a Firebase ID token and issue a Sanctum session.
     *
     * The token is verified server-side (signature, issuer, audience, expiry)
     * before any claim is used. A new local user may only be provisioned by a
     * provider that has proven ownership of the email address (Google or an
     * already email-verified Firebase account). Email/password accounts must
     * finish the OTP flow first, which creates the local user.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $identity = $this->firebase->verifyIdToken($request->id_token);
        } catch (InvalidFirebaseTokenException $e) {
            Log::notice('Rejected invalid Firebase ID token');

            return response()->json([
                'success' => false,
                'message' => 'Your sign-in session is invalid or has expired. Please sign in again.',
            ], 401);
        } catch (Throwable $e) {
            Log::error('Firebase token verification unavailable', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Sign-in is temporarily unavailable. Please try again.',
            ], 503);
        }

        $email = $identity['email'];

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'This account has no email address.',
            ], 400);
        }

        $existingByUid = User::where('firebase_uid', $identity['uid'])->first();
        $existingByEmail = User::where('email', $email)->first();

        // Never silently re-link an existing Travelo account to a different
        // Firebase identity just because the email string matches.
        if (
            !$existingByUid
            && $existingByEmail
            && $existingByEmail->firebase_uid
            && $existingByEmail->firebase_uid !== $identity['uid']
        ) {
            Log::warning('Blocked Firebase identity relink attempt', [
                'user_id' => $existingByEmail->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'This email is already linked to another sign-in method.',
            ], 409);
        }

        $trustedEmail = $identity['email_verified'] || $identity['provider'] === 'google.com';

        // Email/password accounts created directly in Firebase must complete
        // the Travelo OTP step before they can get a local session.
        if (!$existingByUid && !$trustedEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before signing in.',
            ], 403);
        }

        $result = $this->firebase->syncUser(
            $identity['uid'],
            $email,
            $identity['name'],
            $identity['photo']
        );

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $result['user'],
                'token' => $result['token'],
            ],
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Register a verified Firebase account.
     *
     * The previous implementation trusted a caller-supplied name/email and
     * issued a Sanctum token without any proof of identity. Registration now
     * requires a verified Firebase ID token. The Android client registers
     * through Firebase + the email OTP endpoints; this endpoint remains for
     * compatible clients that can supply an ID token.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => 'required|string',
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $identity = $this->firebase->verifyIdToken($request->id_token);
        } catch (InvalidFirebaseTokenException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Your sign-in session is invalid or has expired. Please sign in again.',
            ], 401);
        } catch (Throwable $e) {
            Log::error('Firebase token verification unavailable during registration', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Registration is temporarily unavailable. Please try again.',
            ], 503);
        }

        $email = $identity['email'];

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'This account has no email address.',
            ], 400);
        }

        $trustedEmail = $identity['email_verified'] || $identity['provider'] === 'google.com';

        if (!$trustedEmail) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email address before registering.',
            ], 403);
        }

        $existing = User::where('firebase_uid', $identity['uid'])
            ->orWhere('email', $email)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This account is already registered. Please sign in.',
            ], 409);
        }

        $result = $this->firebase->syncUser(
            $identity['uid'],
            $email,
            $identity['name'] ?? $request->input('name'),
            $identity['photo']
        );

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        }

        // Firebase has verified ownership of this address.
        $result['user']->forceFill(['email_verified_at' => now()])->save();

        return response()->json([
            'success' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => $result['user'],
                'token' => $result['token'],
            ],
        ]);
    }

    /**
     * Get current user.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ]);
    }

    /**
     * Replace the signed-in traveller's profile photo.
     *
     * The upload is public (profile photos are meant to be shown in the app)
     * but it must be a real raster image. SVG is deliberately not accepted
     * because it can carry active content.
     */
    public function updatePhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        try {
            $user = $this->profilePhotos->store(
                $request->user(),
                $request->file('photo')
            );
        } catch (\InvalidArgumentException) {
            return response()->json([
                'success' => false,
                'message' => 'Choose a JPG, PNG, or WebP image up to 10 MB.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile photo updated',
            'data' => $user,
        ]);
    }

    /**
     * Update the traveller-maintained part of the profile.
     *
     * Email is intentionally not editable here: it belongs to the verified
     * Firebase identity used to sign in.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $input = $request->all();
        $input['name'] = is_string($input['name'] ?? null) ? trim($input['name']) : '';
        $input['date_of_birth'] = $input['date_of_birth'] ?? null;

        if (is_string($input['date_of_birth']) && trim($input['date_of_birth']) === '') {
            $input['date_of_birth'] = null;
        }

        foreach (['phone', 'city'] as $field) {
            $value = $input[$field] ?? null;
            if (is_string($value)) {
                $value = trim($value);
                $input[$field] = $value === '' ? null : $value;
            } else {
                $input[$field] = $value;
            }
        }

        $validated = Validator::make($input, [
            'name' => 'required|string|min:2|max:80',
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{5,29}$/'],
            'date_of_birth' => 'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before:today',
            'city' => 'nullable|string|max:100',
        ])->validate();

        $user = $request->user();
        $user->fill($validated)->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated',
            'data' => $user->fresh(),
        ]);
    }
}
