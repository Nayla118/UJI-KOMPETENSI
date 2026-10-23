<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingVisitor;
use App\Models\Destination;
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
 * Visitor identity data and private document storage.
 *
 * Every uploaded file in these tests is synthetic.
 */
class VisitorIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makeUser(string $name = 'Test User'): User
    {
        return User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => $name,
            'email' => 'user-'.uniqid().'@example.com',
        ]);
    }

    /**
     * @return array{0: Booking, 1: TourPackage, 2: Destination}
     */
    private function makeBooking(User $user, int $peopleCount = 2): array
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

        $booking = Booking::create([
            'user_id' => $user->id,
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => $peopleCount,
            'total_price' => 1000000 * $peopleCount,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);

        return [$booking, $tour, $destination];
    }

    private function addVisitor(Booking $booking, array $overrides = []): BookingVisitor
    {
        return $booking->visitors()->create(array_merge([
            'is_booker' => false,
            'name' => 'Ani Pengunjung',
            'identity_type' => BookingVisitor::TYPE_KTP,
            'identity_number' => '3171234567891234',
            'date_of_birth' => null,
        ], $overrides));
    }

    private function uploadDocument(User $user, Booking $booking, BookingVisitor $visitor, ?UploadedFile $file = null)
    {
        Sanctum::actingAs($user);

        return $this->postJson(
            "/api/bookings/{$booking->id}/visitors/{$visitor->id}/identity-document",
            ['document' => $file ?? UploadedFile::fake()->image('ktp.jpg', 600, 400)]
        );
    }

    public function test_booking_visitor_count_must_match_people_count(): void
    {
        $user = $this->makeUser();
        [$booking, $tour] = $this->makeBooking($user, 2);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token');
        });

        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', [
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 2,
            'privacy_consent' => true,
            'visitors' => [
                [
                    'name' => 'Budi Santoso',
                    'identity_type' => 'ktp',
                    'identity_number' => '3171234567891234',
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['visitors']);
    }

    public function test_booking_accepts_every_supported_identity_type(): void
    {
        $user = $this->makeUser('Budi Santoso');
        [, $tour] = $this->makeBooking($user, 4);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token');
        });

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', [
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 4,
            'privacy_consent' => true,
            'visitors' => [
                [
                    'name' => 'Budi Santoso',
                    'identity_type' => BookingVisitor::TYPE_KTP,
                    'identity_number' => '3171234567890001',
                    'is_booker' => true,
                ],
                [
                    'name' => 'Ani Santoso',
                    'identity_type' => BookingVisitor::TYPE_KIA,
                    'identity_number' => '3171234567890002',
                    'date_of_birth' => '2015-05-05',
                ],
                [
                    'name' => 'Citra Santoso',
                    'identity_type' => BookingVisitor::TYPE_STUDENT_CARD,
                    'identity_number' => '3171234567890003',
                ],
                [
                    'name' => 'Dedi Santoso',
                    'identity_type' => BookingVisitor::TYPE_OTHER,
                    'identity_type_other' => 'Paspor',
                    'identity_number' => 'A1234567',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonCount(4, 'data.booking.visitors');

        $types = collect($response->json('data.booking.visitors'))
            ->pluck('identity_type')
            ->all();

        $this->assertEqualsCanonicalizing(BookingVisitor::TYPES, $types);
    }

    public function test_booking_creates_one_visitor_row_per_traveller_and_masks_identity_numbers(): void
    {
        $user = $this->makeUser('Budi Santoso');
        [$booking, $tour, $destination] = $this->makeBooking($user, 2);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token');
        });

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', [
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 2,
            'privacy_consent' => true,
            'visitors' => [
                [
                    'name' => 'Budi Santoso',
                    'identity_type' => 'ktp',
                    'identity_number' => '3171234567891234',
                    'phone' => '081234567890',
                    'is_booker' => true,
                ],
                [
                    'name' => 'Ani Santoso',
                    'identity_type' => 'kia',
                    'identity_number' => '3171234567899999',
                    'date_of_birth' => '2015-05-05',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.booking.visitors');

        $this->assertTrue($response->json('data.booking.visitors.0.is_booker'));
        $response->assertJsonMissingPath('data.booking.visitors.0.identity_number')
            ->assertJsonMissingPath('data.booking.visitors.0.document_path');

        $this->assertStringEndsWith('1234', $response->json('data.booking.visitors.0.identity_number_masked'));

        $this->assertDatabaseCount('booking_visitors', 2);
        $this->assertNotNull($response->json('data.booking.privacy_consent_at'));
        $this->assertSame('ktp', $response->json('data.booking.visitors.0.identity_type'));
        $this->assertSame('kia', $response->json('data.booking.visitors.1.identity_type'));
    }

    public function test_privacy_consent_is_required_when_visitors_are_provided(): void
    {
        $user = $this->makeUser();
        [, $tour] = $this->makeBooking($user, 1);

        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', [
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 1,
            'visitors' => [
                [
                    'name' => 'Budi Santoso',
                    'identity_type' => 'ktp',
                    'identity_number' => '3171234567891234',
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['privacy_consent']);
    }

    public function test_kia_requires_a_date_of_birth_and_other_identity_requires_a_label(): void
    {
        $user = $this->makeUser();
        [, $tour] = $this->makeBooking($user, 1);

        Sanctum::actingAs($user);

        $this->postJson('/api/bookings', [
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 1,
            'privacy_consent' => true,
            'visitors' => [
                [
                    'name' => 'Ani Santoso',
                    'identity_type' => 'kia',
                    'identity_number' => '3171234567899999',
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['visitors.0.date_of_birth']);

        $this->postJson('/api/bookings', [
            'tour_package_id' => $tour->id,
            'booking_date' => now()->addDays(7)->toDateString(),
            'people_count' => 1,
            'privacy_consent' => true,
            'visitors' => [
                [
                    'name' => 'Ani Santoso',
                    'identity_type' => 'other',
                    'identity_number' => 'A1234567',
                ],
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['visitors.0.identity_type_other']);
    }

    public function test_valid_upload_is_private_and_pending_not_verified(): void
    {
        $user = $this->makeUser();
        [$booking] = $this->makeBooking($user, 1);
        $visitor = $this->addVisitor($booking);

        $response = $this->uploadDocument($user, $booking, $visitor);

        $response->assertStatus(201)
            ->assertJsonPath('data.document_status', BookingVisitor::DOC_PENDING)
            ->assertJsonPath('data.has_document', true)
            ->assertJsonMissingPath('data.document_path')
            ->assertJsonMissingPath('data.identity_number');

        $visitor->refresh();

        $this->assertSame(BookingVisitor::DOC_PENDING, $visitor->document_status);
        $this->assertNotSame(BookingVisitor::DOC_VERIFIED, $visitor->document_status);
        Storage::disk('local')->assertExists($visitor->getRawOriginal('document_path'));
        $this->assertStringContainsString(
            'visitor-identities/'.$booking->id.'/',
            $visitor->getRawOriginal('document_path')
        );
        // The random name must not contain the client file name.
        $this->assertStringNotContainsString('ktp', $visitor->getRawOriginal('document_path'));
    }

    public function test_valid_png_and_pdf_uploads_are_accepted(): void
    {
        $user = $this->makeUser();
        [$booking] = $this->makeBooking($user, 2);
        $pngVisitor = $this->addVisitor($booking);
        $pdfVisitor = $this->addVisitor($booking, [
            'name' => 'Budi Pengunjung',
            'identity_number' => '3171234567890002',
        ]);

        // 1x1 transparent PNG, generated only for this test.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nXQAAAAASUVORK5CYII=',
            true
        );
        $this->assertIsString($png);

        $this->uploadDocument(
            $user,
            $booking,
            $pngVisitor,
            UploadedFile::fake()->createWithContent('identity.png', $png)
        )->assertStatus(201)
            ->assertJsonPath('data.document_status', BookingVisitor::DOC_PENDING);

        $this->assertSame('image/png', $pngVisitor->fresh()->document_mime);

        // Minimal, non-personal PDF structure with a valid header/trailer.
        $pdf = "%PDF-1.4\n"
            ."1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            ."trailer\n<< /Root 1 0 R >>\n%%EOF\n";

        $this->uploadDocument(
            $user,
            $booking,
            $pdfVisitor,
            UploadedFile::fake()->createWithContent('identity.pdf', $pdf)
        )->assertStatus(201)
            ->assertJsonPath('data.document_status', BookingVisitor::DOC_PENDING);

        $this->assertSame('application/pdf', $pdfVisitor->fresh()->document_mime);
        Storage::disk('local')->assertExists($pngVisitor->fresh()->getRawOriginal('document_path'));
        Storage::disk('local')->assertExists($pdfVisitor->fresh()->getRawOriginal('document_path'));
    }

    public function test_replacing_a_document_removes_the_previous_private_file(): void
    {
        $user = $this->makeUser();
        [$booking] = $this->makeBooking($user, 1);
        $visitor = $this->addVisitor($booking);

        $this->uploadDocument($user, $booking, $visitor)->assertStatus(201);
        $oldPath = $visitor->fresh()->getRawOriginal('document_path');

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nXQAAAAASUVORK5CYII=',
            true
        );

        $this->uploadDocument(
            $user,
            $booking,
            $visitor,
            UploadedFile::fake()->createWithContent('replacement.png', (string) $png)
        )->assertStatus(201)
            ->assertJsonPath('data.document_status', BookingVisitor::DOC_PENDING);

        $newPath = $visitor->fresh()->getRawOriginal('document_path');

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);

        // A replacement updates the same visitor row; it must not create a
        // second traveller or a hidden duplicate upload row.
        $this->assertDatabaseCount('booking_visitors', 1);
        $this->assertSame(1, $booking->visitors()->count());
    }

    public function test_owner_can_delete_a_document_and_its_private_file(): void
    {
        $user = $this->makeUser();
        [$booking] = $this->makeBooking($user, 1);
        $visitor = $this->addVisitor($booking);

        $this->uploadDocument($user, $booking, $visitor)->assertStatus(201);
        $path = $visitor->fresh()->getRawOriginal('document_path');

        Sanctum::actingAs($user);

        $this->deleteJson("/api/bookings/{$booking->id}/visitors/{$visitor->id}/identity-document")
            ->assertOk()
            ->assertJsonPath('data.document_status', BookingVisitor::DOC_MISSING)
            ->assertJsonPath('data.has_document', false)
            ->assertJsonMissingPath('data.document_path');

        $visitor->refresh();

        $this->assertNull($visitor->getRawOriginal('document_path'));
        $this->assertSame(BookingVisitor::DOC_MISSING, $visitor->document_status);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $user = $this->makeUser();
        [$booking] = $this->makeBooking($user, 1);
        $visitor = $this->addVisitor($booking);

        $this->uploadDocument(
            $user,
            $booking,
            $visitor,
            UploadedFile::fake()->create('identity.php', 10, 'application/x-php')
        )->assertStatus(422)
            ->assertJsonValidationErrors(['document']);

        $this->assertSame(BookingVisitor::DOC_MISSING, $visitor->fresh()->document_status);
    }

    public function test_oversized_file_is_rejected(): void
    {
        $user = $this->makeUser();
        [$booking] = $this->makeBooking($user, 1);
        $visitor = $this->addVisitor($booking);

        $file = UploadedFile::fake()->image('ktp.jpg', 600, 400)->size(6000);

        $this->uploadDocument($user, $booking, $visitor, $file)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['document']);
    }

    public function test_another_user_cannot_list_upload_or_download_someone_elses_document(): void
    {
        $owner = $this->makeUser('Owner');
        $attacker = $this->makeUser('Attacker');
        [$booking] = $this->makeBooking($owner, 1);
        $visitor = $this->addVisitor($booking);

        $this->uploadDocument($owner, $booking, $visitor)->assertStatus(201);

        Sanctum::actingAs($attacker);

        $this->getJson("/api/bookings/{$booking->id}/visitors")->assertStatus(404);
        $this->patchJson("/api/bookings/{$booking->id}/visitors/{$visitor->id}", [
            'name' => 'Changed',
        ])->assertStatus(404);
        $this->postJson("/api/bookings/{$booking->id}/visitors/{$visitor->id}/identity-document", [
            'document' => UploadedFile::fake()->image('other.jpg'),
        ])->assertStatus(404);
        $this->deleteJson("/api/bookings/{$booking->id}/visitors/{$visitor->id}/identity-document")
            ->assertStatus(404);
        $this->get("/api/bookings/{$booking->id}/visitors/{$visitor->id}/identity-document")
            ->assertStatus(404);
    }

    public function test_owner_can_download_own_document_with_no_store_headers(): void
    {
        $user = $this->makeUser();
        [$booking] = $this->makeBooking($user, 1);
        $visitor = $this->addVisitor($booking);

        $this->uploadDocument($user, $booking, $visitor)->assertStatus(201);

        Sanctum::actingAs($user);

        $response = $this->get("/api/bookings/{$booking->id}/visitors/{$visitor->id}/identity-document");

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_changing_identity_data_resets_a_verified_document_to_pending(): void
    {
        $user = $this->makeUser();
        [$booking] = $this->makeBooking($user, 1);
        $visitor = $this->addVisitor($booking);

        $this->uploadDocument($user, $booking, $visitor)->assertStatus(201);

        $visitor->refresh()->forceFill([
            'document_status' => BookingVisitor::DOC_VERIFIED,
            'document_reviewed_at' => now(),
        ])->save();

        Sanctum::actingAs($user);

        $this->patchJson("/api/bookings/{$booking->id}/visitors/{$visitor->id}", [
            'identity_number' => '3171234567890001',
        ])->assertOk()
            ->assertJsonPath('data.document_status', BookingVisitor::DOC_PENDING);

        $this->assertSame(BookingVisitor::DOC_PENDING, $visitor->fresh()->document_status);
    }

    public function test_retention_command_purges_expired_documents(): void
    {
        $user = $this->makeUser();
        [$booking] = $this->makeBooking($user, 1);
        $booking->update(['booking_date' => now()->subDays(100)->toDateString()]);

        $visitor = $this->addVisitor($booking);
        $this->uploadDocument($user, $booking, $visitor)->assertStatus(201);

        $path = $visitor->fresh()->getRawOriginal('document_path');
        Storage::disk('local')->assertExists($path);

        config(['travelo.identity.retention_days' => 30]);

        $this->artisan('travelo:purge-identity-documents')->assertSuccessful();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(BookingVisitor::DOC_PURGED, $visitor->fresh()->document_status);
        $this->assertNull($visitor->fresh()->document_path);
    }
}
