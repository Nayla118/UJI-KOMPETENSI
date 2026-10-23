<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingVisitor;
use App\Models\Destination;
use App\Models\SavedIdentity;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Account-level booker identity reuse.
 *
 * All identity numbers and documents in these tests are synthetic.
 */
class SavedIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makeUser(string $name = 'Budi Santoso'): User
    {
        return User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => $name,
            'email' => 'user-'.uniqid().'@example.com',
        ]);
    }

    private function makeTour(): TourPackage
    {
        $destination = Destination::create([
            'name' => 'Bali',
            'city' => 'Denpasar',
            'country' => 'Indonesia',
        ]);

        return TourPackage::create([
            'destination_id' => $destination->id,
            'title' => 'Bali Trip',
            'price' => 1000000,
            'duration_days' => 3,
            'max_people' => 10,
        ]);
    }

    private function mockMidtrans(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token');
        });
    }

    private function bookerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'identity_type' => BookingVisitor::TYPE_KTP,
            'identity_number' => '3171234567891234',
            'date_of_birth' => '1990-01-01',
            'phone' => '081234567890',
            'is_booker' => true,
        ], $overrides);
    }

    private function createBooking(User $user, TourPackage $tour, array $visitors, bool $reuse = false)
    {
        Sanctum::actingAs($user);

        return $this->postJson('/api/bookings', [
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => count($visitors),
            'privacy_consent' => true,
            'reuse_saved_identity_document' => $reuse,
            'visitors' => $visitors,
        ]);
    }

    private function uploadDocument(User $user, int $bookingId, int $visitorId, ?UploadedFile $file = null)
    {
        Sanctum::actingAs($user);

        return $this->postJson(
            "/api/bookings/{$bookingId}/visitors/{$visitorId}/identity-document",
            ['document' => $file ?? UploadedFile::fake()->image('ktp.jpg', 600, 400)]
        );
    }

    public function test_first_booking_saves_the_booker_identity_for_later_bookings(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $response = $this->createBooking($user, $tour, [
            $this->bookerPayload(),
            [
                'name' => 'Ani Santoso',
                'identity_type' => BookingVisitor::TYPE_KIA,
                'identity_number' => '3171234567899999',
                'date_of_birth' => '2015-05-05',
                'is_booker' => false,
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseCount('saved_identities', 1);
        $this->assertDatabaseHas('saved_identities', [
            'user_id' => $user->id,
            'name' => 'Budi Santoso',
            'identity_number' => '3171234567891234',
        ]);

        Sanctum::actingAs($user);

        $identity = $this->getJson('/api/auth/identity')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Budi Santoso')
            ->assertJsonPath('data.identity_type', BookingVisitor::TYPE_KTP)
            ->assertJsonPath('data.identity_number', '3171234567891234')
            ->assertJsonPath('data.date_of_birth', '1990-01-01')
            ->assertJsonPath('data.phone', '081234567890')
            ->assertJsonPath('data.has_document', false)
            ->assertJsonPath('data.can_reuse_document', false)
            ->assertJsonMissingPath('data.document_path');

        $this->assertStringContainsString('no-store', (string) $identity->headers->get('Cache-Control'));
    }

    public function test_later_booking_reuses_the_saved_document_without_uploading_again(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $first = $this->createBooking($user, $tour, [$this->bookerPayload()]);
        $first->assertCreated();

        $firstBookingId = (int) $first->json('data.booking.id');
        $firstVisitorId = (int) $first->json('data.booking.visitors.0.id');

        $this->uploadDocument($user, $firstBookingId, $firstVisitorId)->assertStatus(201);

        $firstVisitor = BookingVisitor::findOrFail($firstVisitorId);
        $firstPath = $firstVisitor->getRawOriginal('document_path');

        $saved = SavedIdentity::where('user_id', $user->id)->firstOrFail();
        $savedPath = $saved->getRawOriginal('document_path');

        $this->assertNotNull($firstPath);
        $this->assertNotNull($savedPath);
        $this->assertNotSame($firstPath, $savedPath);
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('local')->assertExists($savedPath);

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/identity')
            ->assertOk()
            ->assertJsonPath('data.has_document', true)
            ->assertJsonPath('data.can_reuse_document', true)
            ->assertJsonMissingPath('data.document_path');

        $second = $this->createBooking($user, $tour, [$this->bookerPayload()], true);
        $second->assertCreated()
            ->assertJsonPath('data.booking.visitors.0.has_document', true)
            ->assertJsonPath('data.booking.visitors.0.document_status', BookingVisitor::DOC_PENDING)
            ->assertJsonMissingPath('data.booking.visitors.0.document_path');

        $secondBookingId = (int) $second->json('data.booking.id');
        $secondVisitor = BookingVisitor::where('booking_id', $secondBookingId)->firstOrFail();
        $secondPath = $secondVisitor->getRawOriginal('document_path');

        $this->assertNotNull($secondPath);
        $this->assertNotSame($firstPath, $secondPath);
        $this->assertNotSame($savedPath, $secondPath);
        $this->assertStringContainsString(
            'visitor-identities/'.$secondBookingId.'/',
            $secondPath
        );

        // Reuse must not consume or move the earlier copies.
        Storage::disk('local')->assertExists($savedPath);
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('local')->assertExists($secondPath);
    }

    public function test_reuse_is_rejected_when_no_saved_document_exists(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $this->createBooking($user, $tour, [$this->bookerPayload()])->assertCreated();

        $this->assertDatabaseCount('saved_identities', 1);
        $this->assertDatabaseCount('bookings', 1);

        $response = $this->createBooking($user, $tour, [$this->bookerPayload()], true);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['reuse_saved_identity_document']);

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_saved_identity_data_is_owner_scoped(): void
    {
        $owner = $this->makeUser('Owner');
        $other = $this->makeUser('Other');
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $booking = $this->createBooking($owner, $tour, [$this->bookerPayload()]);
        $booking->assertCreated();

        $bookingId = (int) $booking->json('data.booking.id');
        $visitorId = (int) $booking->json('data.booking.visitors.0.id');
        $this->uploadDocument($owner, $bookingId, $visitorId)->assertStatus(201);

        Sanctum::actingAs($other);

        $response = $this->getJson('/api/auth/identity')->assertOk();
        $this->assertNull($response->json('data'));

        // Give the other account its own saved scalar identity, then make
        // sure it still cannot reuse the owner's document.
        $otherBooking = $this->createBooking($other, $tour, [
            $this->bookerPayload([
                'name' => 'Other Person',
                'identity_number' => '3171234567890002',
            ]),
        ]);
        $otherBooking->assertCreated();

        $this->getJson('/api/auth/identity')
            ->assertOk()
            ->assertJsonPath('data.name', 'Other Person')
            ->assertJsonPath('data.identity_number', '3171234567890002')
            ->assertJsonPath('data.can_reuse_document', false);

        $this->createBooking($other, $tour, [
            $this->bookerPayload([
                'name' => 'Other Person',
                'identity_number' => '3171234567890002',
            ]),
        ], true)->assertStatus(422);

        // The owner still has the saved document after the other account's
        // failed attempt.
        $this->assertDatabaseHas('saved_identities', [
            'user_id' => $owner->id,
            'identity_number' => '3171234567891234',
        ]);
        $this->assertDatabaseHas('saved_identities', [
            'user_id' => $other->id,
            'identity_number' => '3171234567890002',
        ]);
    }

    public function test_replacing_a_booker_document_replaces_the_saved_private_copy(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $booking = $this->createBooking($user, $tour, [$this->bookerPayload()]);
        $booking->assertCreated();

        $bookingId = (int) $booking->json('data.booking.id');
        $visitorId = (int) $booking->json('data.booking.visitors.0.id');

        $this->uploadDocument($user, $bookingId, $visitorId)->assertStatus(201);

        $firstSavedPath = SavedIdentity::where('user_id', $user->id)
            ->firstOrFail()
            ->getRawOriginal('document_path');

        $this->uploadDocument(
            $user,
            $bookingId,
            $visitorId,
            UploadedFile::fake()->image('replacement.png', 600, 400)
        )->assertStatus(201);

        $secondSavedPath = SavedIdentity::where('user_id', $user->id)
            ->firstOrFail()
            ->getRawOriginal('document_path');

        $this->assertNotSame($firstSavedPath, $secondSavedPath);
        Storage::disk('local')->assertMissing($firstSavedPath);
        Storage::disk('local')->assertExists($secondSavedPath);
    }

    public function test_deleting_the_booker_document_removes_the_saved_private_copy(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $booking = $this->createBooking($user, $tour, [$this->bookerPayload()]);
        $booking->assertCreated();

        $bookingId = (int) $booking->json('data.booking.id');
        $visitorId = (int) $booking->json('data.booking.visitors.0.id');

        $this->uploadDocument($user, $bookingId, $visitorId)->assertStatus(201);

        $savedPath = SavedIdentity::where('user_id', $user->id)
            ->firstOrFail()
            ->getRawOriginal('document_path');
        Storage::disk('local')->assertExists($savedPath);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/bookings/{$bookingId}/visitors/{$visitorId}/identity-document")
            ->assertOk();

        $saved = SavedIdentity::where('user_id', $user->id)->firstOrFail();

        $this->assertNull($saved->getRawOriginal('document_path'));
        $this->assertSame(BookingVisitor::DOC_MISSING, $saved->document_status);
        $this->assertFalse($saved->has_document);
        Storage::disk('local')->assertMissing($savedPath);

        // Scalar identity is still available for the next booking form.
        $this->getJson('/api/auth/identity')
            ->assertOk()
            ->assertJsonPath('data.identity_number', '3171234567891234')
            ->assertJsonPath('data.can_reuse_document', false);
    }

    public function test_editing_the_booker_identity_drops_an_outdated_saved_document(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $booking = $this->createBooking($user, $tour, [$this->bookerPayload()]);
        $booking->assertCreated();

        $bookingId = (int) $booking->json('data.booking.id');
        $visitorId = (int) $booking->json('data.booking.visitors.0.id');

        $this->uploadDocument($user, $bookingId, $visitorId)->assertStatus(201);

        $savedPath = SavedIdentity::where('user_id', $user->id)
            ->firstOrFail()
            ->getRawOriginal('document_path');

        Sanctum::actingAs($user);

        $this->patchJson("/api/bookings/{$bookingId}/visitors/{$visitorId}", [
            'identity_number' => '3171234567890009',
        ])->assertOk();

        $saved = SavedIdentity::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('3171234567890009', $saved->getRawOriginal('identity_number'));
        $this->assertNull($saved->getRawOriginal('document_path'));
        $this->assertFalse($saved->has_document);
        Storage::disk('local')->assertMissing($savedPath);
    }

    public function test_reuse_is_rejected_when_the_booker_identity_does_not_match_the_saved_document(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $first = $this->createBooking($user, $tour, [$this->bookerPayload()]);
        $first->assertCreated();

        $bookingId = (int) $first->json('data.booking.id');
        $visitorId = (int) $first->json('data.booking.visitors.0.id');
        $this->uploadDocument($user, $bookingId, $visitorId)->assertStatus(201);

        $savedPath = SavedIdentity::where('user_id', $user->id)
            ->firstOrFail()
            ->getRawOriginal('document_path');

        $response = $this->createBooking($user, $tour, [
            $this->bookerPayload(['identity_number' => '3171234567890009']),
        ], true);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['reuse_saved_identity_document']);

        $this->assertDatabaseCount('bookings', 1);
        Storage::disk('local')->assertExists($savedPath);
    }

    public function test_creating_a_booking_with_a_new_identity_drops_the_saved_document(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $first = $this->createBooking($user, $tour, [$this->bookerPayload()]);
        $first->assertCreated();

        $bookingId = (int) $first->json('data.booking.id');
        $visitorId = (int) $first->json('data.booking.visitors.0.id');
        $this->uploadDocument($user, $bookingId, $visitorId)->assertStatus(201);

        $savedPath = SavedIdentity::where('user_id', $user->id)
            ->firstOrFail()
            ->getRawOriginal('document_path');
        Storage::disk('local')->assertExists($savedPath);

        $this->createBooking($user, $tour, [
            $this->bookerPayload(['identity_number' => '3171234567890009']),
        ])->assertCreated();

        $saved = SavedIdentity::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('3171234567890009', $saved->getRawOriginal('identity_number'));
        $this->assertNull($saved->getRawOriginal('document_path'));
        $this->assertFalse($saved->has_document);
        Storage::disk('local')->assertMissing($savedPath);
    }

    public function test_owner_can_delete_saved_identity_without_deleting_booking_history(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $booking = $this->createBooking($user, $tour, [$this->bookerPayload()]);
        $booking->assertCreated();

        $bookingId = (int) $booking->json('data.booking.id');
        $visitorId = (int) $booking->json('data.booking.visitors.0.id');
        $this->uploadDocument($user, $bookingId, $visitorId)->assertStatus(201);

        $visitor = BookingVisitor::findOrFail($visitorId);
        $bookingPath = $visitor->getRawOriginal('document_path');
        $savedPath = SavedIdentity::where('user_id', $user->id)
            ->firstOrFail()
            ->getRawOriginal('document_path');

        Sanctum::actingAs($user);

        $this->deleteJson('/api/auth/identity')->assertOk();

        $this->assertDatabaseMissing('saved_identities', ['user_id' => $user->id]);
        Storage::disk('local')->assertMissing($savedPath);
        // The per-booking copy remains available for staff review and follows
        // its own retention schedule.
        Storage::disk('local')->assertExists($bookingPath);

        // A real delete must not be undone by a lazy backfill.
        $afterDelete = $this->getJson('/api/auth/identity')->assertOk();
        $this->assertNull($afterDelete->json('data'));
        $this->assertDatabaseMissing('saved_identities', ['user_id' => $user->id]);
    }

    public function test_reuse_only_fills_the_booker_not_additional_travellers(): void
    {
        $user = $this->makeUser();
        $tour = $this->makeTour();
        $this->mockMidtrans();

        $first = $this->createBooking($user, $tour, [$this->bookerPayload()]);
        $first->assertCreated();

        $firstBookingId = (int) $first->json('data.booking.id');
        $firstVisitorId = (int) $first->json('data.booking.visitors.0.id');
        $this->uploadDocument($user, $firstBookingId, $firstVisitorId)->assertStatus(201);

        $second = $this->createBooking($user, $tour, [
            $this->bookerPayload(),
            [
                'name' => 'Citra Santoso',
                'identity_type' => BookingVisitor::TYPE_KTP,
                'identity_number' => '3171234567898888',
                'is_booker' => false,
            ],
        ], true);

        $second->assertCreated()
            ->assertJsonPath('data.booking.visitors.0.has_document', true)
            ->assertJsonPath('data.booking.visitors.1.has_document', false)
            ->assertJsonPath('data.booking.visitors.1.document_status', BookingVisitor::DOC_MISSING);
    }
}
