<?php

namespace Tests\Feature;

use App\Jobs\ProcessAutoRefund;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\Destination;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cancellation is a request, not an immediate change.
 *
 * Only an admin approval mutates the booking, every transition is audited,
 * and refunds stay manual. Reschedule is intentionally not supported.
 */
class BookingChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $admin;

    private Destination $destination;

    private TourPackage $package;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Traveller',
            'email' => 'traveller-'.uniqid().'@example.com',
        ]);

        $this->admin = User::create([
            'firebase_uid' => 'admin-'.uniqid(),
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->destination = Destination::create([
            'name' => 'Bali',
            'city' => 'Denpasar',
            'country' => 'Indonesia',
        ]);

        $this->package = TourPackage::create([
            'destination_id' => $this->destination->id,
            'title' => 'Bali Trip',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
        ]);

        $this->booking = Booking::create([
            'user_id' => $this->user->id,
            'tour_package_id' => $this->package->id,
            'booking_date' => $this->date(10),
            'people_count' => 2,
            'total_price' => 2000000,
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
        ]);
    }

    private function date(int $days): string
    {
        return now()->addDays($days)->toDateString();
    }

    private function submit(array $payload)
    {
        Sanctum::actingAs($this->user);

        return $this->postJson("/api/bookings/{$this->booking->id}/change-requests", $payload);
    }

    private function approve(BookingChangeRequest $change)
    {
        return $this->actingAs($this->admin)
            ->patch(route('admin.change-requests.approve', $change), [
                'admin_note' => 'Approved by test',
            ]);
    }

    // ---------- Submission ----------

    public function test_traveller_can_submit_a_cancellation_request_without_changing_the_booking(): void
    {
        $this->submit([
            'type' => 'cancellation',
            'reason' => 'Family emergency',
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'cancellation')
            ->assertJsonPath('data.status', 'pending');

        $change = BookingChangeRequest::query()->firstOrFail();

        $this->assertSame(BookingChangeRequest::STATUS_PENDING, $change->status);
        $this->assertSame('Family emergency', $change->reason);
        $this->assertSame($this->booking->booking_date->toDateString(), $change->original_booking_date->toDateString());

        // The request alone must not cancel anything.
        $this->assertSame('confirmed', $this->booking->fresh()->booking_status);
        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Booking cancellation requested',
            'subject_id' => $this->booking->id,
        ]);
    }

    public function test_reschedule_is_no_longer_accepted(): void
    {
        $this->submit([
            'type' => 'reschedule',
            'requested_date' => $this->date(20),
        ])->assertStatus(422);

        $this->assertDatabaseCount('booking_change_requests', 0);
        $this->assertSame($this->date(10), $this->booking->fresh()->booking_date->toDateString());
    }

    public function test_unpaid_booking_cannot_request_a_cancellation(): void
    {
        $this->booking->update([
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);

        $this->submit(['type' => 'cancellation'])
            ->assertStatus(422)
            ->assertJsonPath('data.reason', 'not_paid');

        $this->assertDatabaseCount('booking_change_requests', 0);
    }

    public function test_a_traveller_cannot_cancel_another_users_booking(): void
    {
        $stranger = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Stranger',
            'email' => 'stranger-'.uniqid().'@example.com',
        ]);

        Sanctum::actingAs($stranger);

        $this->postJson("/api/bookings/{$this->booking->id}/change-requests", [
            'type' => 'cancellation',
        ])->assertNotFound();

        $this->assertDatabaseCount('booking_change_requests', 0);
    }

    public function test_only_one_pending_request_is_allowed_per_booking(): void
    {
        $this->submit(['type' => 'cancellation'])->assertStatus(201);

        $this->submit(['type' => 'cancellation', 'reason' => 'Again'])
            ->assertStatus(422)
            ->assertJsonPath('data.reason', 'pending_exists');

        $this->assertDatabaseCount('booking_change_requests', 1);
    }

    public function test_a_past_visit_cannot_be_cancelled(): void
    {
        $this->booking->update(['booking_date' => $this->date(-2)]);

        $this->submit(['type' => 'cancellation'])
            ->assertStatus(422)
            ->assertJsonPath('data.reason', 'past_visit');

        $this->assertDatabaseCount('booking_change_requests', 0);
    }

    public function test_an_already_cancelled_booking_cannot_request_again(): void
    {
        $this->booking->update(['booking_status' => 'cancelled']);

        $this->submit(['type' => 'cancellation'])
            ->assertStatus(422)
            ->assertJsonPath('data.reason', 'not_changeable');
    }

    // ---------- Approval / rejection ----------

    public function test_admin_approval_cancels_the_booking_and_starts_auto_refund(): void
    {
        Queue::fake();
        config(['travelo.refund.auto_refund' => true]);

        $this->submit(['type' => 'cancellation'])->assertStatus(201);
        $change = BookingChangeRequest::query()->firstOrFail();

        $this->approve($change)->assertRedirect();

        $this->assertSame('cancelled', $this->booking->fresh()->booking_status);
        $this->assertSame(BookingChangeRequest::STATUS_APPROVED, $change->fresh()->status);
        $this->assertSame(BookingChangeRequest::REFUND_PROCESSING, $change->fresh()->refund_status);
        $this->assertSame(100, $change->fresh()->refund_percentage);
        $this->assertEqualsWithDelta(2000000, (float) $change->fresh()->refund_amount, 0.01);
        $this->assertSame($this->admin->id, $change->fresh()->reviewed_by);
        $this->assertNotNull($change->fresh()->reviewed_at);

        Queue::assertPushed(ProcessAutoRefund::class, function (ProcessAutoRefund $job) use ($change) {
            return $job->changeRequest->is($change);
        });

        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Booking cancelled by approved request',
            'subject_id' => $this->booking->id,
        ]);
    }

    public function test_admin_approval_marks_refund_pending_when_auto_refund_is_off(): void
    {
        config(['travelo.refund.auto_refund' => false]);

        $this->submit(['type' => 'cancellation'])->assertStatus(201);
        $change = BookingChangeRequest::query()->firstOrFail();

        $this->approve($change)->assertRedirect();

        $this->assertSame('cancelled', $this->booking->fresh()->booking_status);
        $this->assertSame(BookingChangeRequest::STATUS_APPROVED, $change->fresh()->status);
        $this->assertSame(BookingChangeRequest::REFUND_PENDING, $change->fresh()->refund_status);
    }

    public function test_admin_approval_snapshots_a_partial_refund_for_the_50_percent_tier(): void
    {
        config(['travelo.refund.auto_refund' => false]);
        $this->booking->update([
            'booking_date' => $this->date(4),
            'total_price' => 2000000,
        ]);

        $this->submit(['type' => 'cancellation'])->assertStatus(201);
        $change = BookingChangeRequest::query()->firstOrFail();

        $this->approve($change)->assertRedirect();

        $change->refresh();
        $this->assertSame(BookingChangeRequest::REFUND_PENDING, $change->refund_status);
        $this->assertSame(50, $change->refund_percentage);
        $this->assertEqualsWithDelta(1000000, (float) $change->refund_amount, 0.01);
    }

    public function test_admin_approval_snapshots_a_zero_refund_for_the_no_refund_tier(): void
    {
        config(['travelo.refund.auto_refund' => true]);
        $this->booking->update(['booking_date' => $this->date(1)]);

        $this->submit(['type' => 'cancellation'])->assertStatus(201);
        $change = BookingChangeRequest::query()->firstOrFail();

        $this->approve($change)->assertRedirect();

        $change->refresh();
        $this->assertSame(BookingChangeRequest::REFUND_NOT_APPLICABLE, $change->refund_status);
        $this->assertSame(0, $change->refund_percentage);
        $this->assertEqualsWithDelta(0, (float) $change->refund_amount, 0.01);
    }

    public function test_admin_rejection_keeps_the_booking_unchanged_and_requires_a_note(): void
    {
        $this->submit(['type' => 'cancellation'])->assertStatus(201);
        $change = BookingChangeRequest::query()->firstOrFail();

        $this->actingAs($this->admin)
            ->patch(route('admin.change-requests.reject', $change), [])
            ->assertSessionHasErrors('admin_note');

        $this->actingAs($this->admin)
            ->patch(route('admin.change-requests.reject', $change), [
                'admin_note' => 'Outside the cancellation window.',
            ])
            ->assertRedirect();

        $this->assertSame(BookingChangeRequest::STATUS_REJECTED, $change->fresh()->status);
        $this->assertSame('Outside the cancellation window.', $change->fresh()->admin_note);
        $this->assertSame('confirmed', $this->booking->fresh()->booking_status);
    }

    public function test_a_reviewed_request_cannot_be_approved_again(): void
    {
        $this->submit(['type' => 'cancellation'])->assertStatus(201);
        $change = BookingChangeRequest::query()->firstOrFail();

        $this->approve($change)->assertRedirect();
        $this->approve($change)->assertSessionHasErrors('change_request');

        $this->assertSame(BookingChangeRequest::STATUS_APPROVED, $change->fresh()->status);
    }

    // ---------- Refund ----------

    public function test_refund_is_manual_then_marked_refunded_once(): void
    {
        $this->submit(['type' => 'cancellation'])->assertStatus(201);
        $change = BookingChangeRequest::query()->firstOrFail();
        $this->approve($change)->assertRedirect();

        $this->actingAs($this->admin)
            ->patch(route('admin.change-requests.refund', $change), [
                'refund_note' => 'Bank transfer ref 99881',
            ])
            ->assertRedirect();

        $change->refresh();
        $this->assertSame(BookingChangeRequest::REFUND_REFUNDED, $change->refund_status);
        $this->assertNotNull($change->refunded_at);
        $this->assertSame('Bank transfer ref 99881', $change->refund_note);

        $this->actingAs($this->admin)
            ->patch(route('admin.change-requests.refund', $change), [])
            ->assertSessionHasErrors('change_request');
    }

    // ---------- Payload ----------

    public function test_admin_can_read_the_refund_snapshot_on_cancellation_views(): void
    {
        config(['travelo.refund.auto_refund' => false]);
        $this->booking->update(['booking_date' => $this->date(4)]);

        $this->submit(['type' => 'cancellation'])->assertStatus(201);
        $change = BookingChangeRequest::query()->firstOrFail();
        $this->approve($change)->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('admin.bookings.show', $this->booking->id))
            ->assertOk()
            ->assertSee('50%')
            ->assertSee('1.000.000');

        $this->actingAs($this->admin)
            ->get(route('admin.change-requests.index', ['status' => 'approved']))
            ->assertOk()
            ->assertSee('50%')
            ->assertSee('1.000.000');
    }

    public function test_owner_can_read_the_refund_snapshot_on_cancellation_views(): void
    {
        config(['travelo.refund.auto_refund' => false]);
        $this->booking->update(['booking_date' => $this->date(4)]);

        $owner = User::create([
            'firebase_uid' => 'owner-'.uniqid(),
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@example.com',
            'role' => User::ROLE_OWNER,
        ]);
        $owner->destinations()->attach($this->destination->id);

        $this->submit(['type' => 'cancellation'])->assertStatus(201);
        $change = BookingChangeRequest::query()->firstOrFail();
        $this->approve($change)->assertRedirect();

        $this->actingAs($owner)
            ->get(route('owner.bookings.show', $this->booking->id))
            ->assertOk()
            ->assertSee('50%')
            ->assertSee('1.000.000');

        $this->actingAs($owner)
            ->get(route('owner.change-requests.index'))
            ->assertOk()
            ->assertSee('50%')
            ->assertSee('1.000.000');
    }

    public function test_latest_change_request_is_exposed_on_my_bookings(): void
    {
        $this->submit([
            'type' => 'cancellation',
            'reason' => 'Family emergency',
        ])->assertStatus(201);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/my-bookings')
            ->assertOk()
            ->assertJsonPath('data.0.latest_change_request.type', 'cancellation')
            ->assertJsonPath('data.0.latest_change_request.status', 'pending')
            ->assertJsonPath('data.0.latest_change_request.reason', 'Family emergency');
    }

    public function test_my_bookings_payload_includes_the_refund_snapshot(): void
    {
        config(['travelo.refund.auto_refund' => false]);

        $this->submit(['type' => 'cancellation'])->assertStatus(201);
        $change = BookingChangeRequest::query()->firstOrFail();
        $this->approve($change)->assertRedirect();

        Sanctum::actingAs($this->user);

        $this->getJson('/api/my-bookings')
            ->assertOk()
            ->assertJsonPath('data.0.latest_change_request.refund_status', 'pending')
            ->assertJsonPath('data.0.latest_change_request.refund_percentage', 100)
            ->assertJsonPath('data.0.latest_change_request.refund_amount', 2000000);
    }

    public function test_change_request_history_endpoint_returns_owner_requests(): void
    {
        $this->submit(['type' => 'cancellation'])->assertStatus(201);

        Sanctum::actingAs($this->user);

        $this->getJson("/api/bookings/{$this->booking->id}/change-requests")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'cancellation');
    }

    public function test_used_tickets_cannot_be_cancelled(): void
    {
        $this->booking->update([
            'ticket_status' => Booking::TICKET_USED,
            'ticket_used_at' => now(),
        ]);

        $this->submit(['type' => 'cancellation'])
            ->assertStatus(422)
            ->assertJsonPath('data.reason', 'ticket_finished');
    }
}
