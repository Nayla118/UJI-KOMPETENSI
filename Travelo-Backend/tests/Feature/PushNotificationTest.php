<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\Destination;
use App\Models\DeviceToken;
use App\Models\Review;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * FCM registration and best-effort delivery.
 *
 * Push must never be required for a core flow to succeed: with an
 * unconfigured service account every send is skipped and logged, not thrown.
 */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Traveller',
            'email' => 'traveller-'.uniqid().'@example.com',
        ]);

        // Force the "service account not configured" path regardless of what
        // exists on the machine running the suite.
        config([
            'firebase.projects.app.credentials' => storage_path('app/does-not-exist-test.json'),
        ]);
    }

    private function token(string $suffix = 'a'): string
    {
        return 'fcm-'.str_pad($suffix, 32, 'x');
    }

    private function booking(User $user): Booking
    {
        $destination = Destination::create([
            'name' => 'Bali '.uniqid(),
            'city' => 'Denpasar',
            'country' => 'Indonesia',
        ]);

        $package = TourPackage::create([
            'destination_id' => $destination->id,
            'title' => 'Bali Trip',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
        ]);

        return Booking::create([
            'user_id' => $user->id,
            'tour_package_id' => $package->id,
            'booking_date' => now()->addDay()->toDateString(),
            'people_count' => 2,
            'total_price' => 2000000,
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
        ]);
    }

    /**
     * Run one notification against a mocked Firebase transport and return the
     * message that would have been sent.
     *
     * @return array<string, mixed>
     */
    private function capturePush(callable $send): array
    {
        DeviceToken::query()->delete();
        DeviceToken::create([
            'user_id' => $this->user->id,
            'token' => $this->token(),
            'platform' => 'android',
        ]);

        $messages = [];
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')
            ->once()
            ->andReturnUsing(function (array $message) use (&$messages) {
                $messages[] = $message;

                return [];
            });
        $this->app->instance(Messaging::class, $messaging);

        // Only the credential probe is faked; payload construction stays real.
        $push = Mockery::mock(PushNotificationService::class)->makePartial();
        $push->shouldReceive('isConfigured')->andReturn(true);

        $send($push);

        $this->assertCount(1, $messages);

        return $messages[0];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertNoSensitivePushData(array $data): void
    {
        foreach ([
            'ticket_token',
            'identity_number',
            'identity_document',
            'document_path',
            'email',
            'phone',
            'visitors',
            'total_price',
        ] as $key) {
            $this->assertArrayNotHasKey($key, $data);
        }
    }

    // ---------- Token registration ----------

    public function test_authenticated_traveller_can_register_a_device_token(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/device-tokens', [
            'token' => $this->token(),
            'platform' => 'android',
            'device_name' => 'Pixel Test',
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $this->user->id,
            'token' => $this->token(),
            'platform' => 'android',
            'device_name' => 'Pixel Test',
        ]);

        $this->assertNotNull(DeviceToken::query()->firstOrFail()->last_used_at);
    }

    public function test_registering_the_same_token_moves_it_to_the_current_user(): void
    {
        $other = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.com',
        ]);

        DeviceToken::create([
            'user_id' => $other->id,
            'token' => $this->token(),
            'platform' => 'android',
        ]);

        Sanctum::actingAs($this->user);

        $this->postJson('/api/device-tokens', ['token' => $this->token()])
            ->assertStatus(201);

        // A shared device must not keep receiving the previous user's push.
        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', [
            'token' => $this->token(),
            'user_id' => $this->user->id,
        ]);
    }

    public function test_token_registration_validates_the_payload(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/device-tokens', ['token' => 'short'])
            ->assertStatus(422);

        $this->postJson('/api/device-tokens', [
            'token' => $this->token(),
            'platform' => 'blackberry',
        ])->assertStatus(422);
    }

    public function test_device_token_requires_authentication(): void
    {
        $this->postJson('/api/device-tokens', ['token' => $this->token()])
            ->assertUnauthorized();
    }

    public function test_logout_unregisters_only_the_callers_token(): void
    {
        $other = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Other',
            'email' => 'other-'.uniqid().'@example.com',
        ]);

        DeviceToken::create([
            'user_id' => $this->user->id,
            'token' => $this->token('mine'),
        ]);

        DeviceToken::create([
            'user_id' => $other->id,
            'token' => $this->token('theirs'),
        ]);

        Sanctum::actingAs($this->user);

        $this->deleteJson('/api/device-tokens', ['token' => $this->token('mine')])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => $this->token('mine')]);

        // Trying to remove somebody else's token is a no-op, not a deletion.
        $this->deleteJson('/api/device-tokens', ['token' => $this->token('theirs')])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', ['token' => $this->token('theirs')]);
    }

    // ---------- Graceful delivery ----------

    public function test_push_is_skipped_gracefully_when_firebase_is_not_configured(): void
    {
        DeviceToken::create([
            'user_id' => $this->user->id,
            'token' => $this->token(),
            'platform' => 'android',
        ]);

        $push = app(PushNotificationService::class);

        $this->assertFalse($push->isConfigured());

        $result = $push->sendToUser($this->user, 'Hello', 'World', ['type' => 'test']);

        $this->assertSame(
            ['configured' => false, 'sent' => 0, 'failed' => 0],
            $result
        );
    }

    public function test_push_to_a_user_without_devices_is_a_no_op(): void
    {
        $result = app(PushNotificationService::class)
            ->sendToUser($this->user, 'Hello', 'World');

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['failed']);
    }

    public function test_payment_and_ticket_notifications_never_throw_when_unconfigured(): void
    {
        DeviceToken::create([
            'user_id' => $this->user->id,
            'token' => $this->token(),
            'platform' => 'android',
        ]);

        $booking = $this->booking($this->user);
        $push = app(PushNotificationService::class);

        $push->notifyPayment($booking, 'paid');
        $push->notifyPayment($booking, 'failed');
        $push->notifyTicketScanned($booking);
        $push->notifyTripReminder($booking);

        $this->assertTrue(true);
    }

    // ---------- Deep-link payloads ----------

    public function test_payment_confirmations_are_data_only_and_open_the_e_ticket(): void
    {
        $booking = $this->booking($this->user);

        $message = $this->capturePush(
            fn (PushNotificationService $push) => $push->notifyPayment($booking, 'paid')
        );

        $this->assertArrayNotHasKey('notification', $message);
        $this->assertSame('payment_paid', $message['data']['type']);
        $this->assertSame('e_ticket', $message['data']['screen']);
        $this->assertSame((string) $booking->id, $message['data']['booking_id']);
        $this->assertSame('Payment confirmed', $message['data']['title']);
        $this->assertArrayHasKey('body', $message['data']);
        $this->assertSame('high', $message['android']['priority']);
        $this->assertNoSensitivePushData($message['data']);
    }

    public function test_failed_and_expired_payments_open_booking_detail(): void
    {
        $booking = $this->booking($this->user);

        $failed = $this->capturePush(
            fn (PushNotificationService $push) => $push->notifyPayment($booking, 'failed')
        );
        $this->assertSame('payment_failed', $failed['data']['type']);
        $this->assertSame('booking_detail', $failed['data']['screen']);
        $this->assertSame((string) $booking->id, $failed['data']['booking_id']);

        $expired = $this->capturePush(
            fn (PushNotificationService $push) => $push->notifyPayment($booking, 'expired')
        );
        $this->assertSame('payment_expired', $expired['data']['type']);
        $this->assertSame('booking_detail', $expired['data']['screen']);

        $released = $this->capturePush(
            fn (PushNotificationService $push) => $push->notifyBookingExpired($booking)
        );
        $this->assertSame('payment_expired', $released['data']['type']);
        $this->assertSame('booking_detail', $released['data']['screen']);
        $this->assertSame((string) $booking->id, $released['data']['booking_id']);
        $this->assertNoSensitivePushData($released['data']);
    }

    public function test_late_payment_payload_opens_booking_detail(): void
    {
        $booking = $this->booking($this->user);

        $message = $this->capturePush(
            fn (PushNotificationService $push) => $push->notifyLatePaymentRequiresRefund($booking)
        );

        $this->assertSame('payment_after_expiry', $message['data']['type']);
        $this->assertSame('booking_detail', $message['data']['screen']);
        $this->assertSame((string) $booking->id, $message['data']['booking_id']);
        $this->assertNoSensitivePushData($message['data']);
    }

    public function test_ticket_used_and_trip_reminder_open_the_e_ticket(): void
    {
        $booking = $this->booking($this->user);

        $scanned = $this->capturePush(
            fn (PushNotificationService $push) => $push->notifyTicketScanned($booking)
        );
        $this->assertSame('ticket_used', $scanned['data']['type']);
        $this->assertSame('e_ticket', $scanned['data']['screen']);
        $this->assertSame((string) $booking->id, $scanned['data']['booking_id']);
        $this->assertNoSensitivePushData($scanned['data']);

        $reminder = $this->capturePush(
            fn (PushNotificationService $push) => $push->notifyTripReminder($booking)
        );
        $this->assertSame('trip_reminder', $reminder['data']['type']);
        $this->assertSame('e_ticket', $reminder['data']['screen']);
        $this->assertSame((string) $booking->id, $reminder['data']['booking_id']);
        $this->assertNoSensitivePushData($reminder['data']);
    }

    public function test_change_request_outcomes_encode_the_type_and_open_booking_detail(): void
    {
        $booking = $this->booking($this->user);
        $request = BookingChangeRequest::create([
            'booking_id' => $booking->id,
            'user_id' => $this->user->id,
            'type' => BookingChangeRequest::TYPE_CANCELLATION,
            'status' => BookingChangeRequest::STATUS_APPROVED,
            'original_booking_date' => $booking->booking_date,
        ]);

        $approved = $this->capturePush(
            fn (PushNotificationService $push) => $push->notifyChangeRequest(
                $request,
                BookingChangeRequest::STATUS_APPROVED
            )
        );
        $this->assertSame('change_request_approved', $approved['data']['type']);
        $this->assertSame('booking_detail', $approved['data']['screen']);
        $this->assertSame((string) $booking->id, $approved['data']['booking_id']);
        $this->assertSame((string) $request->id, $approved['data']['change_request_id']);
        $this->assertNoSensitivePushData($approved['data']);

        $rejected = $this->capturePush(
            fn (PushNotificationService $push) => $push->notifyChangeRequest(
                $request,
                BookingChangeRequest::STATUS_REJECTED
            )
        );
        $this->assertSame('change_request_rejected', $rejected['data']['type']);
        $this->assertSame('booking_detail', $rejected['data']['screen']);
        $this->assertNoSensitivePushData($rejected['data']);
    }

    public function test_review_moderation_opens_the_review_on_its_destination(): void
    {
        $booking = $this->booking($this->user);
        $destinationId = $booking->tourPackage->destination_id;

        $review = Review::create([
            'booking_id' => $booking->id,
            'user_id' => $this->user->id,
            'destination_id' => $destinationId,
            'rating' => 5,
            'status' => Review::STATUS_HIDDEN,
        ]);

        $message = $this->capturePush(
            fn (PushNotificationService $push) => $push->notifyReviewModerated($review, 'hide')
        );

        $this->assertSame('review_moderated', $message['data']['type']);
        $this->assertSame('review', $message['data']['screen']);
        $this->assertSame((string) $review->id, $message['data']['review_id']);
        $this->assertSame((string) $destinationId, $message['data']['destination_id']);
        $this->assertNoSensitivePushData($message['data']);
    }

    // ---------- Scheduled reminders ----------

    public function test_trip_reminder_command_only_targets_tomorrows_confirmed_trips(): void
    {
        $tomorrow = now()
            ->timezone((string) config('travelo.ticket.timezone', config('app.timezone')))
            ->addDay()
            ->toDateString();

        $due = $this->booking($this->user);
        $due->update(['booking_date' => $tomorrow]);

        $later = $this->booking($this->user);
        $later->update(['booking_date' => now()->addDays(3)->toDateString()]);

        $pending = $this->booking($this->user);
        $pending->update([
            'booking_date' => $tomorrow,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);

        $this->artisan('travelo:send-trip-reminders')
            ->expectsOutputToContain('1 booking(s)')
            ->assertExitCode(0);
    }

    public function test_trip_reminder_command_is_idempotent_within_the_day(): void
    {
        $tomorrow = now()
            ->timezone((string) config('travelo.ticket.timezone', config('app.timezone')))
            ->addDay()
            ->toDateString();

        $this->booking($this->user)->update(['booking_date' => $tomorrow]);

        $this->artisan('travelo:send-trip-reminders')
            ->expectsOutputToContain('1 booking(s)')
            ->assertExitCode(0);

        $this->artisan('travelo:send-trip-reminders')
            ->expectsOutputToContain('0 booking(s)')
            ->assertExitCode(0);
    }
}
