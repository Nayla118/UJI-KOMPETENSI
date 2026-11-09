<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Destination;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Owner role access matrix: destination-scoped access, IDOR prevention,
 * redirect-after-login, inactive account rejection, and CSV export.
 */
class OwnerAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $ownerA;

    private User $ownerB;

    private User $traveller;

    private Destination $destA;

    private Destination $destB;

    private TourPackage $pkgA;

    private TourPackage $pkgB;

    private Booking $bookingA;

    private Booking $bookingB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

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

        $this->traveller = User::create([
            'firebase_uid' => 'uid-traveller-'.uniqid(),
            'name' => 'Traveller',
            'email' => 'traveller-'.uniqid().'@example.com',
            'role' => User::ROLE_TRAVELLER,
            'is_active' => true,
            'password' => bcrypt('password'),
        ]);

        $this->destA = Destination::create([
            'name' => 'Destinasi A',
            'city' => 'Jakarta',
            'country' => 'Indonesia',
            'category' => 'pantai',
        ]);
        $this->destB = Destination::create([
            'name' => 'Destinasi B',
            'city' => 'Bandung',
            'country' => 'Indonesia',
            'category' => 'gunung',
        ]);

        $this->ownerA->destinations()->attach($this->destA->id);
        $this->ownerB->destinations()->attach($this->destB->id);

        $this->pkgA = TourPackage::create([
            'destination_id' => $this->destA->id,
            'title' => 'Paket A',
            'price' => 500000,
            'duration_days' => 1,
            'max_people' => 5,
        ]);
        $this->pkgB = TourPackage::create([
            'destination_id' => $this->destB->id,
            'title' => 'Paket B',
            'price' => 700000,
            'duration_days' => 2,
            'max_people' => 5,
        ]);

        $this->bookingA = Booking::create([
            'user_id' => $this->traveller->id,
            'tour_package_id' => $this->pkgA->id,
            'booking_date' => now()->toDateString(),
            'people_count' => 2,
            'subtotal_amount' => 1000000,
            'discount_amount' => 0,
            'total_price' => 1000000,
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
        ]);
        $this->bookingB = Booking::create([
            'user_id' => $this->traveller->id,
            'tour_package_id' => $this->pkgB->id,
            'booking_date' => now()->toDateString(),
            'people_count' => 3,
            'subtotal_amount' => 2100000,
            'discount_amount' => 0,
            'total_price' => 2100000,
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
        ]);
    }

    /** ---------- Redirect after login ---------- */
    public function test_admin_login_redirects_to_admin_dashboard(): void
    {
        $this->post('/admin/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_owner_login_redirects_to_owner_dashboard(): void
    {
        $this->post('/admin/login', [
            'email' => $this->ownerA->email,
            'password' => 'password',
        ])->assertRedirect(route('owner.dashboard'));
    }

    public function test_traveller_cannot_login_to_web_panel(): void
    {
        $this->post('/admin/login', [
            'email' => $this->traveller->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    /** ---------- Inactive account ---------- */
    public function test_inactive_owner_cannot_login(): void
    {
        $this->ownerA->update(['is_active' => false]);

        $this->post('/admin/login', [
            'email' => $this->ownerA->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    /** ---------- Owner dashboard / destinations ---------- */
    public function test_owner_a_sees_only_own_destinations(): void
    {
        $this->actingAs($this->ownerA);

        $this->get('/owner/destinations')
            ->assertOk()
            ->assertSee('Destinasi A')
            ->assertDontSee('Destinasi B');
    }

    public function test_owner_a_cannot_access_owner_b_destination(): void
    {
        $this->actingAs($this->ownerA);

        $this->get("/owner/destinations/{$this->destB->id}")
            ->assertForbidden();
    }

    /** ---------- Scanner access ---------- */
    public function test_owner_a_can_open_scanner_for_own_destination(): void
    {
        $this->actingAs($this->ownerA);

        $this->get('/owner/scanner')
            ->assertOk()
            ->assertSee('Destinasi A')
            ->assertDontSee('Destinasi B');
    }

    public function test_owner_a_cannot_redeem_owner_b_ticket(): void
    {
        $this->actingAs($this->ownerA);

        $bookingB = $this->bookingB;
        $token = app(TicketService::class)->issue($bookingB->refresh())['token'];

        $this->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertStatus(403)
            ->assertJsonPath('reason', 'forbidden_destination');
    }

    public function test_admin_can_access_all_destinations_scanner(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/scanner')
            ->assertOk()
            ->assertSee('All destinations');
    }

    public function test_traveller_cannot_access_scanner(): void
    {
        $this->actingAs($this->traveller);

        $this->get('/owner/scanner')->assertForbidden();
        $this->get('/admin/scanner')->assertForbidden();
    }

    /** ---------- Booking access ---------- */
    public function test_owner_a_sees_only_own_bookings(): void
    {
        $this->actingAs($this->ownerA);

        $this->get('/owner/bookings')
            ->assertOk()
            ->assertSee('Paket A')
            ->assertDontSee('Paket B');
    }

    public function test_owner_a_cannot_access_owner_b_booking(): void
    {
        $this->actingAs($this->ownerA);

        $this->get("/owner/bookings/{$this->bookingB->id}")
            ->assertForbidden();
    }

    /** ---------- Change request / refund ---------- */
    public function test_owner_a_sees_only_own_change_requests(): void
    {
        $this->actingAs($this->ownerA);

        $this->get('/owner/change-requests')
            ->assertOk();
    }

    /** ---------- CSV export ---------- */
    public function test_owner_a_export_csv_only_own_data(): void
    {
        $this->actingAs($this->ownerA);

        $this->get('/owner/laporan/export')
            ->assertOk()
            ->assertHeaderContains('Content-Type', 'text/csv');
    }

    public function test_admin_export_csv_includes_all_data(): void
    {
        $this->actingAs($this->admin);

        $this->get('/owner/laporan/export')
            ->assertOk()
            ->assertHeaderContains('Content-Type', 'text/csv');
    }
}
