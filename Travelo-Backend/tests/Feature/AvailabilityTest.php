<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Destination;
use App\Models\TourPackage;
use App\Models\TourPackageDateAvailability;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Per-date capacity: the package default (`max_people`) applies to every
 * date unless a date override lowers/raises it or closes the date, and the
 * booking endpoint must never sell more seats than the date allows.
 */
class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Destination $destination;

    private TourPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Traveller',
            'email' => 'traveller-'.uniqid().'@example.com',
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
    }

    private function date(int $days = 7): string
    {
        return now()->addDays($days)->toDateString();
    }

    private function booking(string $date, int $people, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->user->id,
            'tour_package_id' => $this->package->id,
            'booking_date' => $date,
            'people_count' => $people,
            'total_price' => 1000000 * $people,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ], $overrides));
    }

    // ---------- Capacity derivation ----------

    public function test_default_capacity_comes_from_the_package(): void
    {
        $this->booking($this->date(), 3);

        $availability = app(AvailabilityService::class)
            ->forDate($this->package, $this->date(), 1);

        $this->assertSame(10, $availability['capacity']);
        $this->assertSame(3, $availability['booked']);
        $this->assertSame(7, $availability['remaining']);
        $this->assertTrue($availability['available']);
        $this->assertNull($availability['reason']);
    }

    public function test_date_override_can_lower_capacity(): void
    {
        TourPackageDateAvailability::create([
            'tour_package_id' => $this->package->id,
            'date' => $this->date(),
            'capacity' => 4,
        ]);

        $this->booking($this->date(), 3);

        $availability = app(AvailabilityService::class)
            ->forDate($this->package, $this->date(), 1);

        $this->assertSame(4, $availability['capacity']);
        $this->assertSame(1, $availability['remaining']);
        $this->assertTrue($availability['available']);

        $tooMany = app(AvailabilityService::class)
            ->forDate($this->package, $this->date(), 2);

        $this->assertFalse($tooMany['available']);
        $this->assertSame('full', $tooMany['reason']);
    }

    public function test_closed_date_is_never_available(): void
    {
        TourPackageDateAvailability::create([
            'tour_package_id' => $this->package->id,
            'date' => $this->date(),
            'is_closed' => true,
            'note' => 'Private event',
        ]);

        $availability = app(AvailabilityService::class)
            ->forDate($this->package, $this->date(), 1);

        $this->assertTrue($availability['is_closed']);
        $this->assertFalse($availability['available']);
        $this->assertSame('closed', $availability['reason']);
    }

    public function test_cancelled_failed_and_expired_bookings_release_seats(): void
    {
        $date = $this->date();

        $this->booking($date, 3, ['booking_status' => 'cancelled']);
        $this->booking($date, 2, ['payment_status' => 'failed']);
        $this->booking($date, 4, ['payment_status' => 'expired']);
        $this->booking($date, 1);

        $availability = app(AvailabilityService::class)
            ->forDate($this->package, $date, 1);

        $this->assertSame(1, $availability['booked']);
        $this->assertSame(9, $availability['remaining']);
    }

    public function test_month_view_covers_every_day_and_marks_overrides(): void
    {
        $month = now()->format('Y-m');
        $closedDate = now()->startOfMonth()->addDays(2)->toDateString();

        TourPackageDateAvailability::create([
            'tour_package_id' => $this->package->id,
            'date' => $closedDate,
            'is_closed' => true,
            'note' => 'Maintenance',
        ]);

        $data = app(AvailabilityService::class)->forMonth($this->package, $month);

        $this->assertSame($month, $data['month']);
        $this->assertSame(10, $data['default_capacity']);
        $this->assertCount(now()->daysInMonth, $data['days']);

        $closed = collect($data['days'])->firstWhere('date', $closedDate);
        $this->assertTrue($closed['is_closed']);
        $this->assertFalse($closed['available']);
        $this->assertSame('Maintenance', $closed['note']);
        $this->assertTrue($closed['has_override']);

        // A date elsewhere in the same month with no override row.
        $otherDate = now()->startOfMonth()->addDays(10)->toDateString();
        $other = collect($data['days'])->firstWhere('date', $otherDate);
        $this->assertNotNull($other);
        $this->assertFalse($other['has_override']);
        $this->assertTrue($other['available']);
    }

    // ---------- Public API ----------

    public function test_public_month_endpoint_returns_days(): void
    {
        $this->getJson("/api/tour-packages/{$this->package->id}/availability?month=".now()->format('Y-m'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.default_capacity', 10)
            ->assertJsonCount(now()->daysInMonth, 'data.days');
    }

    public function test_public_month_endpoint_validates_month_format(): void
    {
        $this->getJson("/api/tour-packages/{$this->package->id}/availability?month=not-a-month")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ---------- Booking creation ----------

    public function test_booking_within_capacity_is_created(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/bookings', [
            'tour_package_id' => $this->package->id,
            'booking_date' => $this->date(),
            'people_count' => 2,
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $created = Booking::query()->where('user_id', $this->user->id)->sole();
        $this->assertSame($this->date(), $created->booking_date->toDateString());
        $this->assertSame(2, (int) $created->people_count);
    }

    public function test_booking_is_rejected_when_the_date_is_full(): void
    {
        $this->booking($this->date(), 9);

        Sanctum::actingAs($this->user);

        $this->postJson('/api/bookings', [
            'tour_package_id' => $this->package->id,
            'booking_date' => $this->date(),
            'people_count' => 2,
        ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.reason', 'full')
            ->assertJsonPath('data.remaining', 1);

        $this->assertSame(1, Booking::query()->count());
    }

    public function test_booking_is_rejected_when_the_date_is_closed(): void
    {
        TourPackageDateAvailability::create([
            'tour_package_id' => $this->package->id,
            'date' => $this->date(),
            'is_closed' => true,
        ]);

        Sanctum::actingAs($this->user);

        $this->postJson('/api/bookings', [
            'tour_package_id' => $this->package->id,
            'booking_date' => $this->date(),
            'people_count' => 1,
        ])
            ->assertStatus(409)
            ->assertJsonPath('data.reason', 'closed');

        $this->assertSame(0, Booking::query()->count());
    }

    public function test_booking_rejects_more_people_than_the_package_sells(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/bookings', [
            'tour_package_id' => $this->package->id,
            'booking_date' => $this->date(),
            'people_count' => 11,
        ])
            ->assertStatus(422);

        $this->assertSame(0, Booking::query()->count());
    }

    // ---------- Admin overrides ----------

    public function test_admin_can_create_and_remove_a_date_override(): void
    {
        $admin = User::create([
            'firebase_uid' => 'admin-'.uniqid(),
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'role' => User::ROLE_ADMIN,
        ]);

        $date = $this->date();

        $this->actingAs($admin)
            ->post(route('admin.tour-packages.availability.store', $this->package), [
                'date' => $date,
                'capacity' => 4,
                'note' => 'Small group only',
            ])
            ->assertRedirect();

        $override = TourPackageDateAvailability::query()->firstOrFail();
        $this->assertSame(4, $override->capacity);
        $this->assertSame('Small group only', $override->note);

        $this->actingAs($admin)
            ->get(route('admin.tour-packages.availability', [
                'tourPackage' => $this->package,
                'month' => substr($date, 0, 7),
            ]))
            ->assertOk();

        $this->actingAs($admin)
            ->delete(route('admin.tour-packages.availability.destroy', [
                'tourPackage' => $this->package,
                'availability' => $override,
            ]))
            ->assertRedirect();

        $this->assertDatabaseCount('tour_package_date_availabilities', 0);
    }

    public function test_non_admin_cannot_manage_availability(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.tour-packages.availability.store', $this->package), [
                'date' => $this->date(),
                'capacity' => 4,
            ])
            ->assertForbidden();
    }
}
