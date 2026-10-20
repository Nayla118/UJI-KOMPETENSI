<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidFirebaseTokenException;
use App\Http\Controllers\Controller;
use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Services\FirebaseAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email OTP verification for login.
 *
 * Flow:
 *   1. client signs in with Firebase (email/password or Google)
 *   2. POST /api/auth/otp/send    -> 6-digit code emailed (stored HASHED)
 *   3. POST /api/auth/otp/verify  -> only a correct, unexpired, single-use
 *                                    code issues the Sanctum session token
 *
 * The session token is intentionally NOT issued before verification.
 */
class OtpController extends Controller
{
    public function __construct(
        protected FirebaseAuthService $firebase
    ) {}

    public function send(Request $request): JsonResponse
    {
        return $this->issue($request, isResend: false);
    }

    public function resend(Request $request): JsonResponse
    {
        return $this->issue($request, isResend: true);
    }

    public function verify(Request $request): JsonResponse
    {
        // Manual validation (not $request->validate()) so the API always
        // answers JSON instead of a 302 redirect for non-browser clients.
        $idToken = $request->input('id_token');
        $code = (string) $request->input('code', '');

        if (!is_string($idToken) || $idToken === '') {
            return response()->json([
                'success' => false,
                'message' => __('travelo.otp.api.missing_parameters'),
                'errors' => ['id_token' => [__('travelo.otp.api.id_token_required')]],
            ], 422);
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            return response()->json([
                'success' => false,
                'message' => __('travelo.otp.api.code_format'),
                'errors' => ['code' => [__('travelo.otp.api.code_format')]],
            ], 422);
        }

        $identity = $this->identityFromToken($idToken);
        if ($identity instanceof JsonResponse) {
            return $identity;
        }

        $email = $identity['email'];
        if (!$email) {
            return response()->json(['success' => false, 'message' => __('travelo.otp.api.no_email')], 400);
        }

        $otp = LoginOtp::activeForEmail($email)->first();

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => __('travelo.otp.api.code_invalid'),
            ], 400);
        }

        if ($otp->hasExceededAttempts()) {
            $otp->delete();
            Log::warning('OTP attempt limit reached', ['email' => $this->maskEmail($email)]);

            return response()->json([
                'success' => false,
                'message' => __('travelo.otp.api.too_many_attempts'),
            ], 429);
        }

        if (!Hash::check((string) $request->input('code'), $otp->code_hash)) {
            $otp->increment('attempts');
            $attemptsLeft = max(0, LoginOtp::MAX_ATTEMPTS - $otp->attempts);

            return response()->json([
                'success' => false,
                'message' => __('travelo.otp.api.wrong_code', ['attempts' => $attemptsLeft]),
                'data' => ['attempts_left' => $attemptsLeft],
            ], 422);
        }

        // Single-use: burn the code before issuing the session.
        $otp->update(['verified_at' => now()]);

        $result = $this->firebase->syncUser(
            $identity['uid'],
            $email,
            $identity['name'],
            $identity['photo']
        );

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'message' => $result['error']], 400);
        }

        // Record the successful verification on the user record.
        if ($result['user']->email_verified_at === null) {
            $result['user']->forceFill(['email_verified_at' => now()])->save();
        }

        Log::info('OTP verified, session issued', ['user_id' => $result['user']->id]);

        return response()->json([
            'success' => true,
            'message' => __('travelo.otp.api.verified'),
            'data' => [
                'user' => $result['user'],
                'token' => $result['token'],
            ],
        ]);
    }

    /**
     * Generate a fresh code and email it (send + resend share this logic).
     */
    private function issue(Request $request, bool $isResend): JsonResponse
    {
        $idToken = $request->input('id_token');

        if (!is_string($idToken) || $idToken === '') {
            return response()->json([
                'success' => false,
                'message' => __('travelo.otp.api.missing_parameters'),
                'errors' => ['id_token' => [__('travelo.otp.api.id_token_required')]],
            ], 422);
        }

        $identity = $this->identityFromToken($idToken);
        if ($identity instanceof JsonResponse) {
            return $identity;
        }

        $email = $identity['email'];
        if (!$email) {
            return response()->json(['success' => false, 'message' => __('travelo.otp.api.no_email')], 400);
        }

        // One active code per minute for both endpoints. Without this, a
        // caller can bypass the resend limit by alternating between send and
        // resend and mail-bomb the address. A verified/expired code no longer
        // blocks a fresh login attempt.
        $latest = LoginOtp::activeForEmail($email)->first();
        $cooldown = $latest?->resendCooldownRemaining() ?? 0;

        if ($cooldown > 0) {
            return response()->json([
                'success' => false,
                'message' => __('travelo.otp.api.resend_wait', ['seconds' => $cooldown]),
                'data' => ['retry_after' => $cooldown],
            ], 429);
        }

        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Only the newest code may be valid: drop any previous one.
        LoginOtp::where('email', $email)->delete();

        $otp = LoginOtp::create([
            'email' => $email,
            'firebase_uid' => $identity['uid'],
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(LoginOtp::TTL_MINUTES),
            'ip' => $request->ip(),
        ]);

        try {
            Mail::to($email)->send(new LoginOtpMail($code, LoginOtp::TTL_MINUTES, (string) config('app.url')));
        } catch (\Throwable $e) {
            // Do not leave a code alive that nobody received.
            $otp->delete();
            Log::error('Failed to send OTP email', [
                'email' => $this->maskEmail($email),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('travelo.otp.api.mail_failed'),
            ], 500);
        }

        Log::info('OTP sent', [
            'email' => $this->maskEmail($email),
            'expires_in' => LoginOtp::TTL_MINUTES * 60,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('travelo.otp.api.sent'),
            'data' => [
                'email' => $this->maskEmail($email),
                'expires_in' => LoginOtp::TTL_MINUTES * 60,
                'resend_after' => LoginOtp::RESEND_COOLDOWN_SECONDS,
            ],
        ]);
    }

    /**
     * Verify the Firebase token before using any identity claim.
     *
     * @return array{uid: string, email: ?string, name: ?string, photo: ?string, provider: ?string, email_verified: bool}|JsonResponse
     */
    private function identityFromToken(string $idToken): array|JsonResponse
    {
        try {
            return $this->firebase->verifyIdToken($idToken);
        } catch (InvalidFirebaseTokenException $e) {
            Log::notice('Rejected invalid Firebase ID token for OTP');

            return response()->json([
                'success' => false,
                'message' => __('travelo.otp.api.invalid_session'),
            ], 401);
        } catch (Throwable $e) {
            Log::error('Firebase token verification unavailable for OTP', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => __('travelo.otp.api.verification_unavailable'),
            ], 503);
        }
    }

    private function maskEmail(string $email): string
    {
        $at = strpos($email, '@');
        if ($at === false || $at < 2) {
            return $email;
        }

        return substr($email, 0, 2) . str_repeat('*', max(1, $at - 2)) . substr($email, $at);
    }
}
