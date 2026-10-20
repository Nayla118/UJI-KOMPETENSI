<?php

namespace Tests\Feature;

use App\Contracts\FirebaseIdentityVerifier;
use App\Exceptions\InvalidFirebaseTokenException;
use App\Mail\LoginOtpMail;
use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

class OtpFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{uid: string, email: string, name: string, photo: null, provider: string, email_verified: bool}
     */
    private function identity(array $overrides = []): array
    {
        return array_merge([
            'uid' => 'otp-uid-'.uniqid(),
            'email' => 'otp-'.uniqid().'@example.com',
            'name' => 'OTP User',
            'photo' => null,
            'provider' => 'password',
            'email_verified' => false,
        ], $overrides);
    }

    private function mockVerifier(array $identity): void
    {
        $this->mock(FirebaseIdentityVerifier::class, function (MockInterface $mock) use ($identity) {
            $mock->shouldReceive('verify')->andReturn($identity);
        });
    }

    public function test_otp_send_hashes_the_code_and_emails_it(): void
    {
        Mail::fake();
        $identity = $this->identity();
        $this->mockVerifier($identity);

        $response = $this->postJson('/api/auth/otp/send', [
            'id_token' => 'valid-token',
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $otp = LoginOtp::firstOrFail();
        $this->assertSame($identity['email'], $otp->email);
        $this->assertSame(0, $otp->attempts);
        $this->assertNull($otp->verified_at);

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$code) {
            $code = $mail->code;
            return true;
        });

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));
    }

    public function test_otp_send_is_rate_limited_per_email(): void
    {
        Mail::fake();
        $this->mockVerifier($this->identity());

        $this->postJson('/api/auth/otp/send', ['id_token' => 'valid-token'])
            ->assertOk();

        $this->postJson('/api/auth/otp/send', ['id_token' => 'valid-token'])
            ->assertStatus(429)
            ->assertJsonPath('success', false);

        $this->postJson('/api/auth/otp/resend', ['id_token' => 'valid-token'])
            ->assertStatus(429)
            ->assertJsonPath('success', false);

        Mail::assertSent(LoginOtpMail::class, 1);
        $this->assertDatabaseCount('login_otps', 1);
    }

    public function test_wrong_code_increments_attempts_and_correct_code_issues_a_token_once(): void
    {
        Mail::fake();
        $identity = $this->identity();
        $this->mockVerifier($identity);

        $this->postJson('/api/auth/otp/send', ['id_token' => 'valid-token'])
            ->assertOk();

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$code) {
            $code = $mail->code;
            return true;
        });

        $wrongCode = $code === '000000' ? '111111' : '000000';

        $this->postJson('/api/auth/otp/verify', [
            'id_token' => 'valid-token',
            'code' => $wrongCode,
        ])->assertStatus(422)
            ->assertJsonPath('data.attempts_left', LoginOtp::MAX_ATTEMPTS - 1);

        $this->assertSame(1, LoginOtp::firstOrFail()->attempts);

        $this->postJson('/api/auth/otp/verify', [
            'id_token' => 'valid-token',
            'code' => $code,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $user = User::where('email', $identity['email'])->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull(LoginOtp::firstOrFail()->verified_at);

        // Single use: the same code cannot issue a second session.
        $this->postJson('/api/auth/otp/verify', [
            'id_token' => 'valid-token',
            'code' => $code,
        ])->assertStatus(400);
    }

    public function test_a_verified_code_does_not_block_a_fresh_send(): void
    {
        Mail::fake();
        $identity = $this->identity();
        $this->mockVerifier($identity);

        $this->postJson('/api/auth/otp/send', ['id_token' => 'valid-token'])
            ->assertOk();

        $code = null;
        Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$code) {
            $code = $mail->code;
            return true;
        });

        $this->postJson('/api/auth/otp/verify', [
            'id_token' => 'valid-token',
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/auth/otp/send', ['id_token' => 'valid-token'])
            ->assertOk();

        Mail::assertSent(LoginOtpMail::class, 2);
    }

    public function test_expired_code_is_rejected(): void
    {
        $identity = $this->identity();
        $this->mockVerifier($identity);

        LoginOtp::create([
            'email' => $identity['email'],
            'firebase_uid' => $identity['uid'],
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/auth/otp/verify', [
            'id_token' => 'valid-token',
            'code' => '123456',
        ])->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_attempt_limit_cancels_the_code(): void
    {
        $identity = $this->identity();
        $this->mockVerifier($identity);

        LoginOtp::create([
            'email' => $identity['email'],
            'firebase_uid' => $identity['uid'],
            'code_hash' => Hash::make('123456'),
            'attempts' => LoginOtp::MAX_ATTEMPTS,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/auth/otp/verify', [
            'id_token' => 'valid-token',
            'code' => '123456',
        ])->assertStatus(429);

        $this->assertDatabaseCount('login_otps', 0);
    }

    public function test_invalid_firebase_token_is_rejected_before_sending_mail(): void
    {
        Mail::fake();

        $this->mock(FirebaseIdentityVerifier::class, function (MockInterface $mock) {
            $mock->shouldReceive('verify')
                ->andThrow(InvalidFirebaseTokenException::because('bad token'));
        });

        $this->postJson('/api/auth/otp/send', ['id_token' => 'bad'])
            ->assertStatus(401);

        Mail::assertNothingSent();
        $this->assertDatabaseCount('login_otps', 0);
    }
}
