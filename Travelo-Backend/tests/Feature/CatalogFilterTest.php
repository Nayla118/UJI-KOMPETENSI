<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Destination;
use App\Models\Review;
use App\Models\TourPackage;
use App\Models\TourPackageDateAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-end coverage for the catalog filters, sorts, pagination and
 * availability-date filtering on both public list endpoints.
 */
class CatalogFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Destination $bali;

    private Destination $bromo;

    private Destination $yogya;

    private Destination $semarang;

    /** @var array<string, TourPackage> */
    private array $packages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'firebase_uid' => 'uid-'.uniqid(),
            'name' => 'Catalog Tester',
            'email' => 'catalog-'.uniqid().'@example.com',
        ]);

        $this->bali = Destination::create([
            'name' => 'Bali',
            'city' => 'Denpasar',
            'country' => 'Indonesia',
            'category' => Destination::CATEGORY_PANTAI,
            'description' => 'Island of the gods',
        ]);
        $this->bromo = Destination::create([
            'name' => 'Bromo',
            'city' => 'Malang',
            'country' => 'Indonesia',
            'category' => Destination::CATEGORY_GUNUNG,
            'description' => 'Sunrise volcano',
        ]);
        $this->yogya = Destination::create([
            'name' => 'Yogyakarta',
            'city' => 'Yogyakarta',
            'country' => 'Indonesia',
            'category' => Destination::CATEGORY_BUDAYA,
            'description' => 'Temple city',
        ]);
        $this->semarang = Destination::create([
            'name' => 'Semarang',
            'city' => 'Semarang',
            'country' => 'Indonesia',
            'category' => Destination::CATEGORY_KULINER,
            'description' => 'Street food harbour',
        ]);

        $this->packages['bali_cheap'] = $this->makePackage($this->bali, 'Cheap Beach', 500000, 1, 2);
        $this->packages['bali_luxury'] = $this->makePackage($this->bali, 'Luxury Beach', 5000000, 5, 4);
        $this->packages['bromo_sunrise'] = $this->makePackage($this->bromo, 'Sunrise', 800000, 2, 3);
        $this->packages['yogya_heritage'] = $this->makePackage($this->yogya, 'Heritage', 1200000, 3, 3);
        $this->packages['semarang_food'] = $this->makePackage($this->semarang, 'Culinary', 700000, 4, 2);

        // Published vs hidden review aggregates. Review bookings are pending
        // so they never inflate the popularity ranking.
        $this->makeReview($this->packages['bali_cheap'], $this->bali, 5, Review::STATUS_PUBLISHED);
        $this->makeReview($this->packages['bali_cheap'], $this->bali, 4, Review::STATUS_PUBLISHED);
        $this->makeReview($this->packages['bali_cheap'], $this->bali, 1, Review::STATUS_HIDDEN);
        $this->makeReview($this->packages['bromo_sunrise'], $this->bromo, 5, Review::STATUS_PUBLISHED);
        $this->makeReview($this->packages['semarang_food'], $this->semarang, 3, Review::STATUS_PUBLISHED);

        // Popularity: paid+confirmed/completed count, everything else does not.
        $this->makeBooking($this->packages['bali_cheap'], 'paid', 'confirmed');
        $this->makeBooking($this->packages['bali_cheap'], 'paid', 'completed');
        $this->makeBooking($this->packages['bali_cheap'], 'paid', 'pending'); // pending trip
        $this->makeBooking($this->packages['bali_cheap'], 'failed', 'confirmed'); // failed payment
        $this->makeBooking($this->packages['bromo_sunrise'], 'paid', 'confirmed');
        $this->makeBooking($this->packages['bromo_sunrise'], 'paid', 'completed');
        $this->makeBooking($this->packages['bromo_sunrise'], 'paid', 'confirmed');

        $this->setCreatedAt($this->packages['bali_cheap'], now()->subDays(10));
        $this->setCreatedAt($this->packages['bali_luxury'], now()->subDays(8));
        $this->setCreatedAt($this->packages['bromo_sunrise'], now()->subDays(6));
        $this->setCreatedAt($this->packages['yogya_heritage'], now()->subDays(4));
        $this->setCreatedAt($this->packages['semarang_food'], now()->subDays(2));
    }

    private function makePackage(
        Destination $destination,
        string $title,
        int $price,
        int $durationDays,
        int $maxPeople
    ): TourPackage {
        return TourPackage::create([
            'destination_id' => $destination->id,
            'title' => $title,
            'price' => $price,
            'duration_days' => $durationDays,
            'max_people' => $maxPeople,
            'image' => 'tour-packages/'.str($title)->slug().'.jpg',
        ]);
    }

    private function makeReview(
        TourPackage $package,
        Destination $destination,
        int $rating,
        string $status
    ): Review {
        $booking = Booking::create([
            'user_id' => $this->user->id,
            'tour_package_id' => $package->id,
            'booking_date' => now()->addDays(30)->toDateString(),
            'people_count' => 1,
            'total_price' => 100000,
            'payment_status' => 'pending',
            'booking_status' => 'pending',
        ]);

        return Review::create([
            'booking_id' => $booking->id,
            'user_id' => $this->user->id,
            'destination_id' => $destination->id,
            'rating' => $rating,
            'status' => $status,
        ]);
    }

    private function makeBooking(
        TourPackage $package,
        string $paymentStatus,
        string $bookingStatus,
        ?Destination $destination = null,
        int $people = 1,
        ?string $date = null
    ): Booking {
        $package = $destination !== null
            ? $this->makePackage($destination, 'Review trip '.uniqid(), 100000, 1, 10)
            : $package;

        return Booking::create([
            'user_id' => $this->user->id,
            'tour_package_id' => $package->id,
            'booking_date' => $date ?? now()->addDays(14)->toDateString(),
            'people_count' => $people,
            'total_price' => 100000 * $people,
            'payment_status' => $paymentStatus,
            'booking_status' => $bookingStatus,
        ]);
    }

    private function setCreatedAt(TourPackage $package, \DateTimeInterface $createdAt): void
    {
        $package->forceFill(['created_at' => $createdAt])->saveQuietly();
    }

    // ---------- Pagination ----------

    public function test_destination_list_is_paginated_with_meta(): void
    {
        $this->getJson('/api/destinations?per_page=2&page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 4);
    }

    public function test_tour_package_list_is_paginated_with_meta(): void
    {
        $this->getJson('/api/tour-packages?per_page=3')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_requests_without_page_still_return_the_first_page(): void
    {
        $this->getJson('/api/destinations')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15);
    }

    // ---------- Destination filters ----------

    public function test_destinations_can_be_searched_by_free_text(): void
    {
        $this->getJson('/api/destinations?q=volcano')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bromo');
    }

    public function test_destinations_can_be_filtered_by_category_and_location(): void
    {
        $this->getJson('/api/destinations?category=gunung')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bromo')
            ->assertJsonPath('data.0.category', Destination::CATEGORY_GUNUNG);

        $this->getJson('/api/destinations?location=Yogyakarta')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Yogyakarta');
    }

    public function test_destinations_are_filtered_by_their_packages_price_and_duration(): void
    {
        $this->getJson('/api/destinations?min_price=4000000&max_price=6000000')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bali');

        $this->getJson('/api/destinations?duration=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bali');

        $this->getJson('/api/destinations?duration=4%2B')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Bali'])
            ->assertJsonFragment(['name' => 'Semarang']);
    }

    public function test_min_rating_uses_only_published_reviews(): void
    {
        $response = $this->getJson('/api/destinations?min_rating=4.4&sort=rating_desc')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertSame(['Bromo', 'Bali'], $names);
        // The hidden 1-star review must not pull Bali's 4.5 average down.
        $this->assertSame(4.5, $response->json('data.1.rating'));
    }

    // ---------- Tour package filters ----------

    public function test_tour_packages_support_each_filter(): void
    {
        $this->getJson('/api/tour-packages?q=beach')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/tour-packages?category=pantai')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Cheap Beach'])
            ->assertJsonFragment(['title' => 'Luxury Beach']);

        $this->getJson('/api/tour-packages?location=Malang')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Sunrise');

        $this->getJson('/api/tour-packages?min_price=600000&max_price=900000')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Culinary'])
            ->assertJsonFragment(['title' => 'Sunrise']);

        $this->getJson('/api/tour-packages?duration=2-3')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Sunrise'])
            ->assertJsonFragment(['title' => 'Heritage']);

        $this->getJson('/api/tour-packages?min_rating=4.5')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['title' => 'Sunrise'])
            ->assertJsonFragment(['title' => 'Cheap Beach'])
            ->assertJsonFragment(['title' => 'Luxury Beach']);
    }

    public function test_tour_package_filters_can_be_combined(): void
    {
        $this->getJson('/api/tour-packages?category=pantai&min_price=400000&max_price=600000&duration=1&sort=price_asc')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Cheap Beach');
    }

    // ---------- Sorts ----------

    public function test_tour_packages_can_be_sorted_by_price(): void
    {
        $asc = collect($this->getJson('/api/tour-packages?sort=price_asc')->json('data'))
            ->pluck('title')->all();

        $this->assertSame(
            ['Cheap Beach', 'Culinary', 'Sunrise', 'Heritage', 'Luxury Beach'],
            $asc
        );

        $desc = collect($this->getJson('/api/tour-packages?sort=price_desc')->json('data'))
            ->pluck('title')->all();

        $this->assertSame(array_reverse($asc), $desc);
    }

    public function test_tour_packages_can_be_sorted_by_rating_and_newest(): void
    {
        $byRating = collect($this->getJson('/api/tour-packages?sort=rating_desc')->json('data'))
            ->pluck('title')->all();

        $this->assertSame('Sunrise', $byRating[0]);
        $this->assertSame('Heritage', $byRating[count($byRating) - 1]);
        // Bali's packages both inherit the 4.5 published average.
        $this->assertContains('Cheap Beach', array_slice($byRating, 1, 2));
        $this->assertContains('Luxury Beach', array_slice($byRating, 1, 2));

        $newest = collect($this->getJson('/api/tour-packages?sort=newest')->json('data'))
            ->pluck('title')->all();

        $this->assertSame('Culinary', $newest[0]);
        $this->assertSame('Heritage', $newest[1]);
    }

    public function test_popular_sort_counts_only_successful_bookings(): void
    {
        $titles = collect($this->getJson('/api/tour-packages?sort=popular')->json('data'))
            ->pluck('title')->all();

        $this->assertSame('Sunrise', $titles[0]);
        $this->assertSame('Cheap Beach', $titles[1]);
        // Pending and failed payments for Cheap Beach were ignored, and no
        // package with zero successful bookings outranks it.
        $this->assertNotContains('Heritage', array_slice($titles, 0, 2));
    }

    public function test_destinations_can_be_sorted_by_price_rating_and_popularity(): void
    {
        $byPrice = collect($this->getJson('/api/destinations?sort=price_asc')->json('data'))
            ->pluck('name')->all();
        $this->assertSame('Bali', $byPrice[0]); // cheapest package = 500000
        $this->assertSame('Yogyakarta', $byPrice[count($byPrice) - 1]); // priciest package

        $byRating = collect($this->getJson('/api/destinations?sort=rating_desc')->json('data'))
            ->pluck('name')->all();
        $this->assertSame(['Bromo', 'Bali', 'Semarang', 'Yogyakarta'], $byRating);

        $popular = collect($this->getJson('/api/destinations?sort=popular')->json('data'))
            ->pluck('name')->all();
        $this->assertSame('Bromo', $popular[0]);
        $this->assertSame('Bali', $popular[1]);
    }

    // ---------- available_date ----------

    public function test_available_date_respects_capacity_closed_dates_and_released_payments(): void
    {
        $date = now()->addDays(20)->toDateString();

        // Cheap Beach (capacity 2) is fully held by a pending booking.
        $this->makeBooking(
            $this->packages['bali_cheap'],
            'pending',
            'pending',
            null,
            2,
            $date
        );

        // Sunrise is explicitly closed on that date.
        TourPackageDateAvailability::create([
            'tour_package_id' => $this->packages['bromo_sunrise']->id,
            'date' => $date,
            'is_closed' => true,
        ]);

        // Heritage gets a reduced capacity of 1 and is fully booked.
        TourPackageDateAvailability::create([
            'tour_package_id' => $this->packages['yogya_heritage']->id,
            'date' => $date,
            'capacity' => 1,
        ]);
        $this->makeBooking(
            $this->packages['yogya_heritage'],
            'paid',
            'confirmed',
            null,
            1,
            $date
        );

        // Culinary has a failed payment for its only two seats; the seats must
        // be released, so it stays available.
        $this->makeBooking(
            $this->packages['semarang_food'],
            'failed',
            'confirmed',
            null,
            2,
            $date
        );

        $titles = collect(
            $this->getJson('/api/tour-packages?available_date='.$date)->json('data')
        )->pluck('title')->all();

        $this->assertNotContains('Cheap Beach', $titles); // full
        $this->assertNotContains('Sunrise', $titles); // closed
        $this->assertNotContains('Heritage', $titles); // capacity 1, full
        $this->assertContains('Culinary', $titles); // failed payment released seats
        $this->assertContains('Luxury Beach', $titles); // default capacity, no holds
    }

    public function test_destination_available_date_requires_at_least_one_available_package(): void
    {
        $date = now()->addDays(21)->toDateString();

        // Close both Bali packages, leave the other destinations open.
        foreach ([$this->packages['bali_cheap'], $this->packages['bali_luxury']] as $package) {
            TourPackageDateAvailability::create([
                'tour_package_id' => $package->id,
                'date' => $date,
                'is_closed' => true,
            ]);
        }

        $names = collect(
            $this->getJson('/api/destinations?available_date='.$date)->json('data')
        )->pluck('name')->all();

        $this->assertNotContains('Bali', $names);
        $this->assertContains('Bromo', $names);
    }

    // ---------- Validation and legacy compatibility ----------

    public function test_per_page_is_capped_at_fifty(): void
    {
        $this->getJson('/api/destinations?per_page=50')->assertOk();
        $this->getJson('/api/destinations?per_page=51')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');

        $this->getJson('/api/tour-packages?per_page=999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_invalid_filter_parameters_are_rejected(): void
    {
        $cases = [
            '/api/destinations?sort=cheapest' => 'sort',
            '/api/destinations?duration=7' => 'duration',
            '/api/destinations?category=desert' => 'category',
            '/api/destinations?available_date=2026-13-40' => 'available_date',
            '/api/destinations?min_rating=9' => 'min_rating',
            '/api/destinations?page=0' => 'page',
            '/api/tour-packages?min_price=9000&max_price=1000' => 'max_price',
        ];

        foreach ($cases as $url => $field) {
            $this->getJson($url)
                ->assertStatus(422)
                ->assertJsonValidationErrors($field);
        }
    }

    public function test_legacy_search_country_and_destination_filters_still_work(): void
    {
        $this->getJson('/api/destinations?search=Bali')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/destinations?country=Indonesia')
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->getJson('/api/tour-packages?search=Beach')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/tour-packages?destination_id='.$this->bromo->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Sunrise');
    }

    public function test_catalog_queries_do_not_scale_with_row_count(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/tour-packages?per_page=1')->assertOk();
        $oneRow = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->getJson('/api/tour-packages?per_page=5')->assertOk();
        $fiveRows = count(DB::getQueryLog());

        DB::disableQueryLog();

        // Same number of queries for 1 and 5 rows: eager loading, not N+1.
        $this->assertSame($oneRow, $fiveRows);
    }
}
