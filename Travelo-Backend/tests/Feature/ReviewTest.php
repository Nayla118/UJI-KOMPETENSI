<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\Review;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verified-visit review rules:
 * - only the owner of a paid, confirmed booking may review
 * - the ticket must have been redeemed at the gate (ticket_status=used)
 * - one review per booking, enforced by a unique index
 * - only published reviews feed public aggregates
 * - admin moderation is audited and unavailable to scanner staff
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Alice Wonderland', array $overrides = []): User
    {
        return User::create(array_merge([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'-'.uniqid().'@example.com',
        ], $overrides));
    }

    private function makeDestination(array $overrides = []): Destination
    {
        return Destination::create(array_merge([
            'name' => 'Bali '.uniqid(),
            'city' => 'Denpasar',
            'country' => 'Indonesia',
            'description' => 'Island destination',
            'rating' => 1.25,
        ], $overrides));
    }

    private function makePackage(Destination $destination, array $overrides = []): TourPackage
    {
        return TourPackage::create(array_merge([
            'destination_id' => $destination->id,
            'title' => 'Bali Adventure',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
        ], $overrides));
    }

    private function makeBooking(
        User $user,
        Destination $destination,
        ?TourPackage $package = null,
        array $overrides = []
    ): Booking {
        $package ??= $this->makePackage($destination);

        return Booking::create(array_merge([
            'user_id' => $user->id,
            'tour_package_id' => $package->id,
            'booking_date' => now()->subDay()->toDateString(),
            'people_count' => 1,
            'total_price' => 1000000,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ], $overrides));
    }

    private function pay(Booking $booking, array $paymentOverrides = []): Booking
    {
        Payment::create(array_merge([
            'booking_id' => $booking->id,
            'midtrans_order_id' => 'REVIEW-ORDER-'.$booking->id,
            'payment_status' => Payment::STATUS_PAID,
            'payment_method' => 'qris',
            'amount' => $booking->total_price,
            'paid_at' => now(),
            'transaction_status' => 'settlement',
        ], $paymentOverrides));

        $booking->update([
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
        ]);

        return $booking->refresh();
    }

    private function checkIn(Booking $booking): Booking
    {
        $booking->update([
            'ticket_status' => Booking::TICKET_USED,
            'ticket_used_at' => now(),
        ]);

        return $booking->refresh();
    }

    private function eligibleBooking(User $user, Destination $destination): Booking
    {
        return $this->checkIn($this->pay(
            $this->makeBooking($user, $destination)
        ));
    }

    private function createReviewFor(
        Booking $booking,
        int $rating = 5,
        string $status = Review::STATUS_PUBLISHED,
        ?string $comment = 'Lovely trip.'
    ): Review {
        return Review::create([
            'booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'destination_id' => $booking->tourPackage->destination_id,
            'rating' => $rating,
            'comment' => $comment,
            'status' => $status,
        ]);
    }

    public function test_signed_in_guest_without_booking_sees_not_booked(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();

        Sanctum::actingAs($user);

        $this->getJson("/api/destinations/{$destination->id}/review-eligibility")
            ->assertOk()
            ->assertJsonPath('data.status', 'not_booked')
            ->assertJsonPath('data.can_review', false);
    }

    public function test_unpaid_booking_is_ineligible_even_when_ticket_is_marked_used(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $booking = $this->makeBooking($user, $destination, overrides: [
            'ticket_status' => Booking::TICKET_USED,
            'ticket_used_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/destinations/{$destination->id}/review-eligibility")
            ->assertOk()
            ->assertJsonPath('data.status', 'unpaid');

        $this->postJson("/api/bookings/{$booking->id}/review", ['rating' => 5])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'unpaid');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_paid_but_unchecked_in_booking_cannot_be_reviewed(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $booking = $this->pay($this->makeBooking($user, $destination));

        Sanctum::actingAs($user);

        $this->getJson("/api/destinations/{$destination->id}/review-eligibility")
            ->assertOk()
            ->assertJsonPath('data.status', 'not_checked_in');

        $this->postJson("/api/bookings/{$booking->id}/review", ['rating' => 5])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'not_checked_in');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_checked_in_booking_can_be_reviewed_once(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $booking = $this->eligibleBooking($user, $destination);

        Sanctum::actingAs($user);

        $this->postJson("/api/bookings/{$booking->id}/review", [
            'rating' => 5,
            'comment' => 'The gate scan was quick and the trip was excellent.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.booking_id', $booking->id);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'destination_id' => $destination->id,
            'rating' => 5,
        ]);

        $this->postJson("/api/bookings/{$booking->id}/review", ['rating' => 4])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'already_reviewed');

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_destination_is_derived_from_the_booking_not_the_client(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $otherDestination = $this->makeDestination();
        $booking = $this->eligibleBooking($user, $destination);

        Sanctum::actingAs($user);

        $this->postJson("/api/bookings/{$booking->id}/review", [
            'rating' => 4,
            'destination_id' => $otherDestination->id,
        ])->assertCreated();

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'destination_id' => $destination->id,
        ]);
        $this->assertDatabaseMissing('reviews', [
            'booking_id' => $booking->id,
            'destination_id' => $otherDestination->id,
        ]);
    }

    public function test_cancelled_and_refunded_bookings_are_ineligible(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();

        $cancelled = $this->checkIn($this->pay($this->makeBooking($user, $destination)));
        $cancelled->update(['booking_status' => 'cancelled']);

        $refunded = $this->checkIn($this->pay($this->makeBooking($user, $destination), [
            'transaction_status' => 'refund',
        ]));

        Sanctum::actingAs($user);

        $this->getJson("/api/destinations/{$destination->id}/review-eligibility")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->postJson("/api/bookings/{$cancelled->id}/review", ['rating' => 5])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'cancelled');

        $this->postJson("/api/bookings/{$refunded->id}/review", ['rating' => 5])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'cancelled');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_legacy_used_ticket_without_redeemed_at_is_ineligible(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $booking = $this->pay($this->makeBooking($user, $destination));
        $booking->update([
            'ticket_status' => Booking::TICKET_USED,
            'ticket_used_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/bookings/{$booking->id}/review", ['rating' => 5])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'not_checked_in');
    }

    public function test_rating_must_be_an_integer_from_one_to_five(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $booking = $this->eligibleBooking($user, $destination);

        Sanctum::actingAs($user);

        foreach ([0, 6, 'five', 4.5] as $rating) {
            $this->postJson("/api/bookings/{$booking->id}/review", ['rating' => $rating])
                ->assertStatus(422);
        }

        $this->postJson("/api/bookings/{$booking->id}/review", [])
            ->assertStatus(422);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_comment_is_optional_and_limited_to_1000_characters(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $booking = $this->eligibleBooking($user, $destination);

        Sanctum::actingAs($user);

        $this->postJson("/api/bookings/{$booking->id}/review", [
            'rating' => 4,
            'comment' => str_repeat('a', 1001),
        ])->assertStatus(422);

        $this->postJson("/api/bookings/{$booking->id}/review", [
            'rating' => 4,
            'comment' => '   ',
        ])->assertCreated()
            ->assertJsonPath('data.comment', null);
    }

    public function test_database_unique_booking_id_blocks_concurrent_duplicates(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $booking = $this->eligibleBooking($user, $destination);

        $this->createReviewFor($booking);

        $blocked = false;

        try {
            $this->createReviewFor($booking, 4);
        } catch (QueryException) {
            $blocked = true;
        }

        $this->assertTrue($blocked, 'The unique booking_id index must reject a duplicate review.');
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_another_user_cannot_review_or_change_someone_elses_booking(): void
    {
        $owner = $this->makeUser('Owner Person');
        $stranger = $this->makeUser('Stranger Person');
        $destination = $this->makeDestination();
        $booking = $this->eligibleBooking($owner, $destination);

        Sanctum::actingAs($stranger);

        $this->postJson("/api/bookings/{$booking->id}/review", ['rating' => 5])
            ->assertStatus(404);

        $review = $this->createReviewFor($booking);

        $this->patchJson("/api/reviews/{$review->id}", ['rating' => 1])
            ->assertStatus(404);

        $this->deleteJson("/api/reviews/{$review->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 5]);
    }

    public function test_owner_can_edit_and_delete_a_published_review(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $review = $this->createReviewFor($this->eligibleBooking($user, $destination));

        Sanctum::actingAs($user);

        $this->patchJson("/api/reviews/{$review->id}", [
            'rating' => 3,
            'comment' => 'Updated after thinking about it.',
        ])
            ->assertOk()
            ->assertJsonPath('data.rating', 3)
            ->assertJsonPath('data.comment', 'Updated after thinking about it.');

        $this->deleteJson("/api/reviews/{$review->id}")
            ->assertOk();

        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_hidden_review_cannot_be_edited_or_deleted_by_owner(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $review = $this->createReviewFor(
            $this->eligibleBooking($user, $destination),
            status: Review::STATUS_HIDDEN,
            comment: 'Hidden content'
        );

        Sanctum::actingAs($user);

        $this->patchJson("/api/reviews/{$review->id}", ['rating' => 1])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'review_hidden');

        $this->deleteJson("/api/reviews/{$review->id}")
            ->assertStatus(409)
            ->assertJsonPath('reason', 'review_hidden');

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => Review::STATUS_HIDDEN,
            'comment' => 'Hidden content',
        ]);
    }

    public function test_public_aggregates_only_count_published_reviews_and_mask_pii(): void
    {
        $user = $this->makeUser('Alice Wonderland');
        $destination = $this->makeDestination(['rating' => 1.25]);
        $package = $this->makePackage($destination);
        $published = $this->createReviewFor(
            $this->eligibleBooking($user, $destination),
            5,
            comment: 'Published experience'
        );
        $this->createReviewFor(
            $this->eligibleBooking($this->makeUser('Bob Secret'), $destination),
            1,
            Review::STATUS_HIDDEN,
            'Hidden experience'
        );

        $this->getJson("/api/destinations/{$destination->id}/reviews")
            ->assertOk()
            ->assertJsonPath('data.summary.count', 1)
            ->assertJsonPath('data.summary.distribution.5', 1)
            ->assertJsonPath('data.summary.distribution.1', 0)
            ->assertJsonPath('data.reviews.0.comment', 'Published experience')
            ->assertJsonPath('data.reviews.0.reviewer_name', 'Alice W.')
            ->assertJsonPath('data.reviews.0.verified_visit', true)
            ->assertJsonMissingPath('data.reviews.0.user_id')
            ->assertJsonMissingPath('data.reviews.0.booking_id')
            ->assertJsonMissingPath('data.reviews.0.email')
            ->assertJsonMissingPath('data.reviews.0.identity_number')
            ->assertJsonMissing(['comment' => 'Hidden experience']);

        $summary = $this->getJson("/api/destinations/{$destination->id}/reviews")->json('data.summary');
        $this->assertEquals(5.0, (float) $summary['average']);

        // Legacy static destination/package ratings must never be exposed.
        $this->getJson("/api/destinations/{$destination->id}")
            ->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.review_count', 1);

        $this->getJson("/api/tour-packages/{$package->id}")
            ->assertOk()
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.review_count', 1);
    }

    public function test_public_reviewer_name_never_exposes_an_embedded_email(): void
    {
        $user = $this->makeUser('Nayla secret@example.com');
        $destination = $this->makeDestination();
        $this->createReviewFor($this->eligibleBooking($user, $destination), 4);

        $response = $this->getJson("/api/destinations/{$destination->id}/reviews")
            ->assertOk()
            ->assertJsonPath('data.reviews.0.reviewer_name', 'Nayla');

        $response->assertDontSee('secret@example.com');
    }

    public function test_destination_without_reviews_has_no_fake_rating(): void
    {
        $destination = $this->makeDestination(['rating' => 4.9]);
        $package = $this->makePackage($destination);

        $this->getJson("/api/destinations/{$destination->id}")
            ->assertOk()
            ->assertJsonPath('data.rating', null)
            ->assertJsonPath('data.review_count', 0);

        $this->getJson("/api/tour-packages/{$package->id}")
            ->assertOk()
            ->assertJsonPath('data.rating', null)
            ->assertJsonPath('data.review_count', 0);

        $this->getJson("/api/destinations/{$destination->id}/reviews")
            ->assertOk()
            ->assertJsonPath('data.summary.count', 0)
            ->assertJsonPath('data.summary.average', null)
            ->assertJsonCount(0, 'data.reviews');
    }

    public function test_hidden_review_is_excluded_from_aggregate_until_restored(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $review = $this->createReviewFor($this->eligibleBooking($user, $destination), 4);
        $admin = $this->makeUser('Admin Person', ['role' => User::ROLE_ADMIN]);

        $this->getJson("/api/destinations/{$destination->id}/reviews")
            ->assertJsonPath('data.summary.count', 1);

        $this->actingAs($admin)
            ->patch("/admin/reviews/{$review->id}/moderate", [
                'action' => 'hide',
                'reason' => 'Contains personal contact details',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => Review::STATUS_HIDDEN,
            'moderated_by' => $admin->id,
            'moderation_reason' => 'Contains personal contact details',
        ]);

        $this->getJson("/api/destinations/{$destination->id}/reviews")
            ->assertJsonPath('data.summary.count', 0)
            ->assertJsonPath('data.summary.average', null);

        $this->assertDatabaseHas('activity_logs', [
            'log_name' => 'review_moderation',
            'subject_type' => Review::class,
            'subject_id' => $review->id,
            'causer_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch("/admin/reviews/{$review->id}/moderate", [
                'action' => 'restore',
                'reason' => 'Author removed the private details',
            ])
            ->assertRedirect();

        $this->getJson("/api/destinations/{$destination->id}/reviews")
            ->assertJsonPath('data.summary.count', 1);
    }

    public function test_admin_can_view_review_moderation_with_filters(): void
    {
        $user = $this->makeUser('Visible Reviewer');
        $destination = $this->makeDestination(['name' => 'Filter Destination']);
        $review = $this->createReviewFor(
            $this->eligibleBooking($user, $destination),
            5,
            comment: 'Visible comment for moderation'
        );
        $admin = $this->makeUser('Admin Person', ['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get('/admin/reviews?status=published&rating=5&destination_id='.$destination->id)
            ->assertOk()
            ->assertSee('Verified reviews')
            ->assertSee('Visible Reviewer')
            ->assertSee('Visible comment for moderation')
            ->assertSee('Published')
            ->assertSee('Filter Destination');

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => Review::STATUS_PUBLISHED,
        ]);
    }

    public function test_scanner_staff_cannot_moderate_reviews(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $review = $this->createReviewFor($this->eligibleBooking($user, $destination));
        $staff = $this->makeUser('Gate Staff', ['role' => User::ROLE_STAFF]);
        $staff->staffDestinations()->attach($destination);

        $this->actingAs($staff)
            ->get('/admin/reviews')
            ->assertForbidden();

        $this->actingAs($staff)
            ->patch("/admin/reviews/{$review->id}/moderate", [
                'action' => 'hide',
                'reason' => 'Should not be allowed',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => Review::STATUS_PUBLISHED,
        ]);
    }

    public function test_moderation_requires_a_reason_and_valid_action(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $review = $this->createReviewFor($this->eligibleBooking($user, $destination));
        $admin = $this->makeUser('Admin Person', ['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->patch("/admin/reviews/{$review->id}/moderate", [
                'action' => 'hide',
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->patch("/admin/reviews/{$review->id}/moderate", [
                'action' => 'hide',
                'reason' => '     ',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin)
            ->patch("/admin/reviews/{$review->id}/moderate", [
                'action' => 'delete',
                'reason' => 'Invalid action',
            ])
            ->assertSessionHasErrors('action');

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'status' => Review::STATUS_PUBLISHED,
        ]);
    }

    public function test_my_bookings_exposes_safe_review_state(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $booking = $this->eligibleBooking($user, $destination);

        Sanctum::actingAs($user);

        $this->getJson('/api/my-bookings')
            ->assertOk()
            ->assertJsonPath('data.0.id', $booking->id)
            ->assertJsonPath('data.0.review_status', 'eligible')
            ->assertJsonPath('data.0.can_review', true)
            ->assertJsonPath('data.0.review', null)
            ->assertJsonPath('data.0.tour_package.id', $booking->tour_package_id)
            ->assertJsonPath('data.0.tour_package.review_count', 0)
            ->assertJsonPath('data.0.tour_package.rating', null)
            ->assertJsonPath('data.0.tour_package.destination.review_count', 0)
            ->assertJsonPath('data.0.tour_package.destination.rating', null);

        $this->postJson("/api/bookings/{$booking->id}/review", [
            'rating' => 4,
            'comment' => 'Great visit.',
        ])->assertCreated();

        $this->getJson('/api/my-bookings')
            ->assertOk()
            ->assertJsonPath('data.0.review_status', 'reviewed')
            ->assertJsonPath('data.0.can_review', false)
            ->assertJsonPath('data.0.review.rating', 4)
            ->assertJsonPath('data.0.review.booking_id', $booking->id)
            ->assertJsonPath('data.0.review.status', Review::STATUS_PUBLISHED)
            ->assertJsonPath('data.0.tour_package.review_count', 1)
            ->assertJsonPath('data.0.tour_package.destination.review_count', 1)
            ->assertJsonMissingPath('data.0.review.user_id');
    }

    public function test_destination_eligibility_prefers_a_new_unreviewed_visit(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();

        $older = $this->eligibleBooking($user, $destination);
        $this->createReviewFor($older);

        $newer = $this->eligibleBooking($user, $destination);

        Sanctum::actingAs($user);

        $this->getJson("/api/destinations/{$destination->id}/review-eligibility")
            ->assertOk()
            ->assertJsonPath('data.status', 'eligible')
            ->assertJsonPath('data.can_review', true)
            ->assertJsonPath('data.booking_id', $newer->id);
    }

    public function test_older_unreviewed_visit_does_not_revive_after_a_newer_visit_is_reviewed(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();

        $older = $this->eligibleBooking($user, $destination);
        $newer = $this->eligibleBooking($user, $destination);
        $this->createReviewFor($newer, 5);

        Sanctum::actingAs($user);

        $this->getJson("/api/destinations/{$destination->id}/review-eligibility")
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJsonPath('data.can_review', false)
            ->assertJsonPath('data.booking_id', $newer->id)
            ->assertJsonPath('data.review.booking_id', $newer->id);

        // Even a direct request must not create a second review for an older
        // ticket after a newer visit has already been reviewed.
        $this->postJson("/api/bookings/{$older->id}/review", ['rating' => 4])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'superseded');

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_my_bookings_marks_superseded_older_visit_without_rate_action(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();

        $older = $this->eligibleBooking($user, $destination);
        $newer = $this->eligibleBooking($user, $destination);
        $this->createReviewFor($newer, 4);

        Sanctum::actingAs($user);

        $rows = collect(
            $this->getJson('/api/my-bookings')->assertOk()->json('data')
        )->keyBy('id');

        $this->assertSame('superseded', $rows[$older->id]['review_status']);
        $this->assertFalse($rows[$older->id]['can_review']);
        $this->assertNull($rows[$older->id]['review']);

        $this->assertSame('reviewed', $rows[$newer->id]['review_status']);
        $this->assertFalse($rows[$newer->id]['can_review']);
        $this->assertSame($newer->id, $rows[$newer->id]['review']['booking_id']);

        $this->getJson("/api/bookings/{$older->id}")
            ->assertOk()
            ->assertJsonPath('data.review_status', 'superseded')
            ->assertJsonPath('data.can_review', false);
    }

    public function test_hidden_review_owner_state_says_under_moderation(): void
    {
        $user = $this->makeUser();
        $destination = $this->makeDestination();
        $this->createReviewFor(
            $this->eligibleBooking($user, $destination),
            status: Review::STATUS_HIDDEN,
            comment: 'Under moderation'
        );

        Sanctum::actingAs($user);

        $this->getJson("/api/destinations/{$destination->id}/review-eligibility")
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed')
            ->assertJsonPath('data.can_review', false)
            ->assertJsonPath('data.review.status', Review::STATUS_HIDDEN)
            ->assertJsonPath('data.message', 'Your review is under moderation.');
    }

    public function test_guests_cannot_check_eligibility_or_submit_reviews(): void
    {
        $this->getJson('/api/destinations/1/review-eligibility')->assertUnauthorized();
        $this->postJson('/api/bookings/1/review', ['rating' => 5])->assertUnauthorized();
    }
}
