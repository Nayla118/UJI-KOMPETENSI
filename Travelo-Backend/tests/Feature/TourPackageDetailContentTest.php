<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\TourPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Travel-agent detail content: API payload, itinerary ordering, gallery,
 * owner scoping, and content-based upload validation.
 */
class TourPackageDetailContentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $ownerA;

    private User $ownerB;

    private Destination $destination;

    private Destination $otherDestination;

    private TourPackage $package;

    private TourPackage $otherPackage;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::create([
            'firebase_uid' => 'uid-admin-'.uniqid(),
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@example.com',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->ownerA = User::create([
            'firebase_uid' => 'uid-owner-a-'.uniqid(),
            'name' => 'Owner A',
            'email' => 'ownera-'.uniqid().'@example.com',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->ownerB = User::create([
            'firebase_uid' => 'uid-owner-b-'.uniqid(),
            'name' => 'Owner B',
            'email' => 'ownerb-'.uniqid().'@example.com',
            'role' => User::ROLE_OWNER,
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->destination = Destination::create([
            'name' => 'Bromo',
            'city' => 'Probolinggo',
            'country' => 'Indonesia',
            'category' => 'gunung',
            'latitude' => -7.9425,
            'longitude' => 112.9530,
        ]);

        $this->otherDestination = Destination::create([
            'name' => 'Ubud',
            'city' => 'Gianyar',
            'country' => 'Indonesia',
            'category' => 'budaya',
        ]);

        $this->ownerA->destinations()->attach($this->destination->id);
        $this->ownerB->destinations()->attach($this->otherDestination->id);

        $this->package = TourPackage::create([
            'destination_id' => $this->destination->id,
            'title' => 'Bromo Sunrise Tour',
            'description' => 'Sunrise over the caldera.',
            'price' => 800000,
            'duration_days' => 2,
            'max_people' => 10,
            'inclusions' => ['Jeep 4x4', 'Licensed guide'],
            'exclusions' => ['Personal expenses'],
            'meeting_point' => 'Juanda Airport Terminal 1',
            'meeting_latitude' => -7.3795,
            'meeting_longitude' => 112.7870,
            'terms' => 'Bring a warm jacket.',
        ]);

        $this->otherPackage = TourPackage::create([
            'destination_id' => $this->otherDestination->id,
            'title' => 'Ubud Culture Tour',
            'price' => 600000,
            'duration_days' => 1,
            'max_people' => 8,
        ]);
    }

    public function test_api_show_returns_detail_content_and_ordered_itinerary(): void
    {
        // Insert deliberately out of day order; sort_order is authoritative.
        $this->package->itineraryDays()->create([
            'day_number' => 2,
            'title' => 'Second day',
            'description' => 'Crater and sea of sand.',
            'sort_order' => 1,
        ]);
        $this->package->itineraryDays()->create([
            'day_number' => 1,
            'title' => 'First day',
            'description' => 'Midnight pickup.',
            'sort_order' => 0,
        ]);

        $this->package->images()->create(['path' => 'tour-packages/gallery/a.jpg', 'caption' => 'Sunrise', 'sort_order' => 0]);
        $this->package->images()->create(['path' => 'tour-packages/gallery/b.jpg', 'caption' => 'Crater', 'sort_order' => 1]);

        $response = $this->getJson('/api/tour-packages/'.$this->package->id)
            ->assertOk()
            ->assertJsonPath('data.inclusions', ['Jeep 4x4', 'Licensed guide'])
            ->assertJsonPath('data.exclusions', ['Personal expenses'])
            ->assertJsonPath('data.meeting_point', 'Juanda Airport Terminal 1')
            ->assertJsonPath('data.meeting_latitude', -7.3795)
            ->assertJsonPath('data.meeting_longitude', 112.787)
            ->assertJsonPath('data.terms', 'Bring a warm jacket.')
            ->assertJsonPath('data.itinerary.0.day_number', 1)
            ->assertJsonPath('data.itinerary.0.title', 'First day')
            ->assertJsonPath('data.itinerary.1.day_number', 2)
            ->assertJsonPath('data.itinerary.1.title', 'Second day')
            ->assertJsonPath('data.images.0.caption', 'Sunrise')
            ->assertJsonPath('data.images.1.caption', 'Crater')
            ->assertJsonPath('data.destination.latitude', -7.9425)
            ->assertJsonPath('data.destination.longitude', 112.953);

        $this->assertStringContainsString('storage/tour-packages/gallery/a.jpg', $response->json('data.images.0.url'));
    }

    public function test_api_destination_show_returns_coordinates_and_gallery(): void
    {
        $this->destination->images()->create([
            'path' => 'destinations/bromo-1.jpg',
            'caption' => 'Bromo sunrise',
            'sort_order' => 0,
        ]);

        $this->getJson('/api/destinations/'.$this->destination->id)
            ->assertOk()
            ->assertJsonPath('data.latitude', -7.9425)
            ->assertJsonPath('data.longitude', 112.953)
            ->assertJsonPath('data.images.0.caption', 'Bromo sunrise')
            ->assertJsonPath('data.images.0.path', 'destinations/bromo-1.jpg');
    }

    public function test_owner_can_update_own_package_with_itinerary_and_gallery(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->put(route('owner.tour-packages.update', $this->package), [
            'destination_id' => $this->destination->id,
            'title' => 'Bromo Sunrise Tour Updated',
            'description' => 'Updated description.',
            'price' => 900000,
            'duration_days' => 2,
            'max_people' => 9,
            'inclusions' => "Jeep 4x4\nBreakfast",
            'exclusions' => "Insurance\nPersonal expenses",
            'meeting_point' => 'Hotel lobby',
            'meeting_latitude' => -7.2,
            'meeting_longitude' => 112.7,
            'terms' => 'Updated terms.',
            'itinerary' => [
                ['day_number' => 1, 'title' => 'Pickup', 'description' => 'Midnight pickup.'],
                ['day_number' => 2, 'title' => 'Sunrise', 'description' => 'Penanjakan.'],
            ],
            'images' => [UploadedFile::fake()->image('gallery.jpg')],
        ]);

        $response->assertRedirect(route('owner.tour-packages.index'))
            ->assertSessionHasNoErrors();

        $this->package->refresh();
        $this->assertSame('Bromo Sunrise Tour Updated', $this->package->title);
        $this->assertSame(['Jeep 4x4', 'Breakfast'], $this->package->inclusions);
        $this->assertSame(['Insurance', 'Personal expenses'], $this->package->exclusions);
        $this->assertSame('Hotel lobby', $this->package->meeting_point);

        $this->assertSame(
            ['Pickup', 'Sunrise'],
            $this->package->itineraryDays()->orderBy('sort_order')->pluck('title')->all()
        );
        $this->assertSame(2, $this->package->itineraryDays()->count());
        $this->assertSame(1, $this->package->images()->count());

        $storedPath = $this->package->images()->first()->path;
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_owner_can_view_own_package_detail_page(): void
    {
        $this->package->itineraryDays()->create([
            'day_number' => 1,
            'title' => 'Pickup',
            'description' => 'Midnight pickup.',
            'sort_order' => 0,
        ]);

        $this->actingAs($this->ownerA)
            ->get(route('owner.tour-packages.show', $this->package))
            ->assertOk()
            ->assertSee('Bromo Sunrise Tour')
            ->assertSee('Pickup')
            ->assertSee('Juanda Airport Terminal 1');
    }

    public function test_owner_cannot_view_another_owners_package_detail_page(): void
    {
        $this->actingAs($this->ownerA)
            ->get(route('owner.tour-packages.show', $this->otherPackage))
            ->assertForbidden();
    }

    public function test_owner_cannot_update_another_owners_package(): void
    {
        $this->actingAs($this->ownerA);

        $this->put(route('owner.tour-packages.update', $this->otherPackage), [
            'destination_id' => $this->otherDestination->id,
            'title' => 'Hijacked title',
            'price' => 1,
            'duration_days' => 1,
            'max_people' => 1,
        ])->assertForbidden();

        $this->assertSame('Ubud Culture Tour', $this->otherPackage->fresh()->title);
    }

    public function test_owner_cannot_switch_package_to_a_destination_they_do_not_own(): void
    {
        $this->actingAs($this->ownerA);

        $this->put(route('owner.tour-packages.update', $this->package), [
            'destination_id' => $this->otherDestination->id,
            'title' => 'Moved package',
            'price' => 100000,
            'duration_days' => 1,
            'max_people' => 4,
        ])->assertSessionHasErrors('destination_id');

        $this->assertSame($this->destination->id, $this->package->fresh()->destination_id);
    }

    public function test_content_based_upload_validation_rejects_a_non_image(): void
    {
        $this->actingAs($this->admin);

        // A raw UploadedFile (not the test fake, which reports a MIME type
        // from the filename) so the validator has to sniff the real bytes.
        $path = tempnam(sys_get_temp_dir(), 'travelo');
        file_put_contents($path, 'this is not an image');
        $fake = new \Illuminate\Http\UploadedFile($path, 'evil.jpg', 'image/jpeg', null, true);

        $this->post(route('admin.tour-packages.store'), [
            'destination_id' => $this->destination->id,
            'title' => 'Invalid upload package',
            'price' => 100000,
            'duration_days' => 1,
            'max_people' => 4,
            'images' => [$fake],
        ])->assertSessionHasErrors('images.0');

        $this->assertDatabaseMissing('tour_packages', ['title' => 'Invalid upload package']);
    }

    public function test_admin_can_upload_multiple_gallery_images_and_cover(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.tour-packages.store'), [
            'destination_id' => $this->destination->id,
            'title' => 'Gallery package',
            'price' => 250000,
            'duration_days' => 1,
            'max_people' => 6,
            'meeting_point' => 'Main gate',
            'inclusions' => "Ticket\nGuide",
            'itinerary' => [
                ['day_number' => 1, 'title' => 'Arrival', 'description' => 'Meet the guide.'],
            ],
            'image' => UploadedFile::fake()->image('cover.jpg'),
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.png'),
            ],
        ])->assertSessionHasNoErrors();

        $created = TourPackage::where('title', 'Gallery package')->firstOrFail();

        $this->assertSame(['Ticket', 'Guide'], $created->inclusions);
        $this->assertSame(2, $created->images()->count());
        $this->assertNotNull($created->image);
        Storage::disk('public')->assertExists($created->image);

        foreach ($created->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_removing_a_gallery_image_deletes_the_file(): void
    {
        $this->actingAs($this->admin);

        Storage::disk('public')->put('tour-packages/gallery/old.jpg', 'x');
        $image = $this->package->images()->create([
            'path' => 'tour-packages/gallery/old.jpg',
            'sort_order' => 0,
        ]);

        $this->put(route('admin.tour-packages.update', $this->package), [
            'destination_id' => $this->destination->id,
            'title' => $this->package->title,
            'price' => 800000,
            'duration_days' => 2,
            'max_people' => 10,
            'remove_images' => [$image->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('tour_package_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing('tour-packages/gallery/old.jpg');
    }

    public function test_demo_seeder_populates_itinerary_and_gallery_for_three_packages(): void
    {
        // Build the demo catalog, then layer the detail content on top exactly
        // as DatabaseSeeder does.
        $this->seed(\Database\Seeders\DestinationSeeder::class);
        $this->seed(\Database\Seeders\TourPackageSeeder::class);
        $this->seed(\Database\Seeders\TourPackageContentSeeder::class);

        $packages = TourPackage::whereIn('title', [
            'Bromo Sunrise Tour 1 Hari',
            'Ubud Culture & Nature 1 Hari',
            'Open Trip Komodo 3 Hari 2 Malam',
        ])->get();

        $this->assertCount(3, $packages);

        foreach ($packages as $package) {
            $this->assertGreaterThanOrEqual(3, $package->itineraryDays()->count(), $package->title);
            $this->assertGreaterThanOrEqual(2, $package->images()->count(), $package->title);
            $this->assertNotEmpty($package->inclusions, $package->title);
            $this->assertNotEmpty($package->exclusions, $package->title);
            $this->assertNotNull($package->meeting_point, $package->title);
        }
    }
}
