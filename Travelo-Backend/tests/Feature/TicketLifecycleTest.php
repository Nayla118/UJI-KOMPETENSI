<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingVisitor;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Payment status and ticket usage are separate.
 *
 * A paid booking must never become "used" without a gate redemption, and a
 * gate redemption must succeed exactly once per ticket.
 */
class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makeUser(string $suffix = '', array $overrides = []): User
    {
        return User::create(array_merge([
            'firebase_uid' => 'uid-'.$suffix.'-'.uniqid(),
            'name' => 'Test User '.$suffix,
            'email' => 'user-'.$suffix.'-'.uniqid().'@example.com',
        ], $overrides));
    }

    /**
     * @return array{0: Booking, 1: TourPackage, 2: Destination}
     */
    private function makeBooking(User $user, array $overrides = []): array
    {
        $destination = Destination::create([
            'name' => 'Bali',
            'city' => 'Denpasar',
            'country' => 'Indonesia',
        ]);

        $tour = TourPackage::create([
            'destination_id' => $destination->id,
            'title' => 'Bali Trip',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
        ]);

        $booking = Booking::create(array_merge([
            'user_id' => $user->id,
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 2,
            'total_price' => 2000000,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ], $overrides));

        return [$booking, $tour, $destination];
    }

    private function pay(Booking $booking): Booking
    {
        Payment::create([
            'booking_id' => $booking->id,
            'midtrans_order_id' => 'ORDER-'.$booking->id,
            'payment_status' => Payment::STATUS_PAID,
            'payment_method' => 'bank_transfer',
            'amount' => $booking->total_price,
            'paid_at' => now(),
        ]);

        $booking->update([
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
        ]);

        return $booking->refresh();
    }

    private function addVisitor(Booking $booking, bool $withDocument = true, array $overrides = []): BookingVisitor
    {
        $path = null;

        if ($withDocument) {
            $path = 'visitor-identities/'.$booking->id.'/'.uniqid().'.jpg';
            Storage::disk('local')->put($path, 'synthetic-image');
        }

        return $booking->visitors()->create(array_merge([
            'is_booker' => true,
            'name' => 'Budi Santoso',
            'identity_type' => BookingVisitor::TYPE_KTP,
            'identity_number' => '3171234567891234',
            'document_path' => $path,
            'document_mime' => $withDocument ? 'image/jpeg' : null,
            'document_size' => $withDocument ? 16 : null,
            'document_status' => $withDocument
                ? BookingVisitor::DOC_PENDING
                : BookingVisitor::DOC_MISSING,
            'document_uploaded_at' => $withDocument ? now() : null,
        ], $overrides));
    }

    private function issueToken(Booking $booking): string
    {
        return app(TicketService::class)->issue($booking->refresh())['token'];
    }

    private function staffFor(?int $destinationId = null): User
    {
        $staff = $this->makeUser('staff', ['role' => User::ROLE_STAFF]);

        if ($destinationId !== null) {
            $staff->staffDestinations()->attach($destinationId);
        }

        return $staff;
    }

    public function test_e_ticket_is_blocked_until_payment_is_confirmed(): void
    {
        $user = $this->makeUser('owner');
        [$booking] = $this->makeBooking($user);

        Sanctum::actingAs($user);

        $this->getJson("/api/bookings/{$booking->id}/e-ticket")
            ->assertStatus(409)
            ->assertJsonPath('data.payment_status', 'pending');
    }

    public function test_paid_booking_with_visitors_requires_complete_documents_before_e_ticket(): void
    {
        $user = $this->makeUser('owner');
        [$booking] = $this->makeBooking($user);
        $booking = $this->pay($booking);

        $this->addVisitor($booking, false);
        $this->addVisitor($booking, false, [
            'is_booker' => false,
            'name' => 'Ani Santoso',
            'identity_number' => '3171234567899999',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/bookings/{$booking->id}/e-ticket")
            ->assertStatus(409)
            ->assertJsonPath('data.identity_status', 'incomplete');
    }

    public function test_e_ticket_unlocks_only_after_every_missing_document_is_uploaded(): void
    {
        $user = $this->makeUser('partial');
        [$booking] = $this->makeBooking($user);
        $booking = $this->pay($booking);

        $first = $this->addVisitor($booking, false);
        $second = $this->addVisitor($booking, false, [
            'is_booker' => false,
            'name' => 'Ani Santoso',
            'identity_number' => '3171234567899999',
        ]);

        Sanctum::actingAs($user);

        // One of two documents is on file: the e-ticket must stay locked.
        $this->postJson(
            "/api/bookings/{$booking->id}/visitors/{$first->id}/identity-document",
            ['document' => UploadedFile::fake()->image('first.jpg', 600, 400)]
        )->assertCreated()
            ->assertJsonPath('data.document_status', BookingVisitor::DOC_PENDING);

        $this->getJson("/api/bookings/{$booking->id}/e-ticket")
            ->assertStatus(409)
            ->assertJsonPath('data.identity_status', 'incomplete')
            ->assertJsonPath('data.visitors_with_document', 1);

        // The second upload completes the set and unlocks the same booking.
        $this->postJson(
            "/api/bookings/{$booking->id}/visitors/{$second->id}/identity-document",
            ['document' => UploadedFile::fake()->image('second.jpg', 600, 400)]
        )->assertCreated();

        $this->getJson("/api/bookings/{$booking->id}/e-ticket")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking_id', $booking->id);

        $this->assertDatabaseCount('booking_visitors', 2);
        $this->assertSame(2, $booking->visitors()->count());
    }

    public function test_legacy_booking_without_visitor_rows_still_gets_an_e_ticket(): void
    {
        $user = $this->makeUser('legacy');
        [$booking] = $this->makeBooking($user);
        $booking = $this->pay($booking);

        $this->assertDatabaseCount('booking_visitors', 0);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/bookings/{$booking->id}/e-ticket")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.usage_status', Booking::TICKET_UNUSED);

        $this->assertNotEmpty($response->json('data.ticket_token'));
    }

    public function test_ticket_token_is_stable_across_reopens_and_verifies_as_authentic(): void
    {
        $user = $this->makeUser('owner');
        [$booking, $tour, $destination] = $this->makeBooking($user, [
            'booking_date' => now()->toDateString(),
        ]);
        $booking = $this->pay($booking);
        $this->addVisitor($booking);
        $this->addVisitor($booking, true, [
            'is_booker' => false,
            'name' => 'Ani Santoso',
            'identity_number' => '3171234567899999',
        ]);

        Sanctum::actingAs($user);

        $first = $this->getJson("/api/bookings/{$booking->id}/e-ticket")->assertOk();
        $second = $this->getJson("/api/bookings/{$booking->id}/e-ticket")->assertOk();

        $token = $first->json('data.ticket_token');

        $this->assertSame($token, $second->json('data.ticket_token'));
        $this->assertCount(2, explode('.', $token));
        $this->assertSame($destination->id, $first->json('data.destination.id'));

        $this->postJson('/api/tickets/verify', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.authentic', true)
            ->assertJsonPath('data.usage_status', Booking::TICKET_UNUSED)
            ->assertJsonPath('data.booking_id', $booking->id)
            ->assertJsonPath('data.tour_title', $tour->title);
    }

    public function test_fake_and_modified_tokens_are_rejected(): void
    {
        $user = $this->makeUser('owner');
        [$booking] = $this->makeBooking($user);
        $booking = $this->pay($booking);
        $this->addVisitor($booking);

        $token = $this->issueToken($booking);

        $this->postJson('/api/tickets/verify', ['token' => 'aaa.bbb'])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.authentic', false);

        $tampered = substr($token, 0, -1).(str_ends_with($token, '0') ? '1' : '0');

        $this->postJson('/api/tickets/verify', ['token' => $tampered])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.authentic', false);
    }

    public function test_e_ticket_of_another_account_is_not_accessible(): void
    {
        $owner = $this->makeUser('owner');
        $attacker = $this->makeUser('attacker');
        [$booking] = $this->makeBooking($owner);
        $this->pay($booking);
        $this->addVisitor($booking);

        Sanctum::actingAs($attacker);

        $this->getJson("/api/bookings/{$booking->id}/e-ticket")->assertStatus(404);
    }

    public function test_two_scans_of_the_same_ticket_only_redeem_once(): void
    {
        $user = $this->makeUser('owner');
        [$booking] = $this->makeBooking($user, [
            'booking_date' => now()->toDateString(),
        ]);
        $booking = $this->pay($booking);
        $this->addVisitor($booking);

        $token = $this->issueToken($booking);
        $admin = $this->makeUser('admin', ['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.usage_status', Booking::TICKET_USED);

        $booking->refresh();
        $this->assertSame(Booking::TICKET_USED, $booking->ticket_status);
        $this->assertNotNull($booking->ticket_used_at);
        // Payment did not change.
        $this->assertSame('paid', $booking->payment_status);

        $this->actingAs($admin)
            ->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertStatus(409)
            ->assertJsonPath('reason', TicketService::REASON_USED);

        $this->assertSame(Booking::TICKET_USED, $booking->fresh()->ticket_status);
    }

    public function test_ticket_cannot_be_redeemed_when_payment_is_not_confirmed(): void
    {
        $user = $this->makeUser('owner');
        [$booking] = $this->makeBooking($user);
        $booking = $this->pay($booking);
        $token = $this->issueToken($booking);

        // Simulate a stale/unpaid state after the token was signed.
        $booking->payment->update(['payment_status' => Payment::STATUS_PENDING, 'paid_at' => null]);
        $booking->update(['payment_status' => 'pending', 'booking_status' => 'pending']);

        $admin = $this->makeUser('admin', ['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertStatus(409)
            ->assertJsonPath('reason', TicketService::REASON_NOT_PAID);

        $this->assertSame(Booking::TICKET_UNUSED, $booking->fresh()->ticket_status);
    }

    public function test_ticket_cannot_be_redeemed_before_the_visit_date(): void
    {
        $user = $this->makeUser('owner');
        [$booking] = $this->makeBooking($user, [
            'booking_date' => now()->addDay()->toDateString(),
        ]);
        $booking = $this->pay($booking);
        $this->addVisitor($booking);

        $token = $this->issueToken($booking);
        $visitDate = $booking->booking_date->toDateString();

        $this->postJson('/api/tickets/verify', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.authentic', true)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.reason', TicketService::REASON_NOT_DUE)
            ->assertJsonPath('data.usage_status', Booking::TICKET_UNUSED)
            ->assertJsonPath('data.booking_date', $visitDate);

        $admin = $this->makeUser('admin', ['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertStatus(409)
            ->assertJsonPath('reason', TicketService::REASON_NOT_DUE)
            ->assertJsonPath('data.visit_date', $visitDate)
            ->assertJsonPath('data.reference', sprintf('TRV-%06d', $booking->id));

        $booking->refresh();
        $this->assertSame(Booking::TICKET_UNUSED, $booking->ticket_status);
        $this->assertNull($booking->ticket_used_at);
    }

    public function test_expired_ticket_is_authentic_but_not_usable(): void
    {
        $user = $this->makeUser('owner');
        [$booking] = $this->makeBooking($user, [
            'booking_date' => now()->subDay()->toDateString(),
        ]);
        $booking = $this->pay($booking);
        $this->addVisitor($booking);

        $token = $this->issueToken($booking);

        $this->postJson('/api/tickets/verify', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.authentic', true)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.reason', TicketService::REASON_EXPIRED)
            ->assertJsonPath('data.usage_status', Booking::TICKET_EXPIRED);

        $admin = $this->makeUser('admin', ['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertStatus(409)
            ->assertJsonPath('reason', TicketService::REASON_EXPIRED);
    }

    public function test_staff_can_only_redeem_for_assigned_destinations(): void
    {
        $user = $this->makeUser('owner');
        [$booking, , $destination] = $this->makeBooking($user, [
            'booking_date' => now()->toDateString(),
        ]);
        $booking = $this->pay($booking);
        $this->addVisitor($booking);

        $token = $this->issueToken($booking);

        $otherDestination = Destination::create([
            'name' => 'Yogyakarta',
            'city' => 'Yogyakarta',
            'country' => 'Indonesia',
        ]);

        $staff = $this->staffFor($otherDestination->id);

        $this->actingAs($staff)
            ->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertStatus(403)
            ->assertJsonPath('reason', TicketService::REASON_FORBIDDEN);

        $staff->staffDestinations()->attach($destination->id);

        $this->actingAs($staff)
            ->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertOk();
    }

    public function test_traveller_cannot_use_the_staff_redeem_endpoint(): void
    {
        $user = $this->makeUser('traveller');
        [$booking] = $this->makeBooking($user);
        $booking = $this->pay($booking);
        $this->addVisitor($booking);

        $token = $this->issueToken($booking);

        $this->actingAs($user)
            ->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertStatus(403);
    }

    public function test_reissue_invalidates_the_old_token_and_keeps_the_new_one_valid(): void
    {
        $user = $this->makeUser('owner');
        [$booking] = $this->makeBooking($user, [
            'booking_date' => now()->toDateString(),
        ]);
        $booking = $this->pay($booking);
        $this->addVisitor($booking);

        $oldToken = $this->issueToken($booking);
        $admin = $this->makeUser('admin', ['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)
            ->postJson("/admin/bookings/{$booking->id}/ticket/reissue")
            ->assertOk()
            ->assertJsonPath('data.ticket_version', 2);

        $newToken = $response->json('data.ticket_token');

        $this->assertNotSame($oldToken, $newToken);

        $this->postJson('/api/tickets/verify', ['token' => $oldToken])
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.authentic', false);

        $this->postJson('/api/tickets/verify', ['token' => $newToken])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.authentic', true);
    }

    public function test_confirmed_payment_does_not_mark_the_ticket_as_used(): void
    {
        $user = $this->makeUser('owner');
        [$booking] = $this->makeBooking($user, ['people_count' => 1]);
        $this->addVisitor($booking);

        app(MidtransService::class)->syncBookingPaymentStatus($booking, [
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'bank_transfer',
            'transaction_id' => 'tx-123',
            'order_id' => 'ORDER-'.$booking->id,
            'gross_amount' => (int) $booking->total_price,
            'status_code' => '200',
        ]);

        $booking->refresh();

        $this->assertSame('paid', $booking->payment_status);
        $this->assertSame('confirmed', $booking->booking_status);
        $this->assertSame(Booking::TICKET_UNUSED, $booking->ticket_status);
        $this->assertSame(Booking::TICKET_UNUSED, $booking->ticket_usage_status);
        $this->assertNull($booking->ticket_used_at);
    }
}
