<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Destination;
use App\Models\Payment;
use App\Models\TourPackage;
use App\Models\User;
use App\Services\TicketService;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Role access matrix for manager and gate_staff, destination isolation,
 * owner staff management, inactive account handling, gate-staff scan
 * payload, and the demo seeder environment gate.
 */
class ManagerGateStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $ownerA;

    private User $managerA;

    private User $gateStaffA;

    private User $ownerB;

    private User $traveller;

    private Destination $destA;

    private Destination $destB;

    private TourPackage $pkgA;

    private TourPackage $pkgB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser(User::ROLE_ADMIN, 'admin');
        $this->ownerA = $this->makeUser(User::ROLE_OWNER, 'owner-a', ['password' => bcrypt('password')]);
        $this->managerA = $this->makeUser(User::ROLE_MANAGER, 'manager-a', ['password' => bcrypt('password')]);
        $this->gateStaffA = $this->makeUser(User::ROLE_GATE_STAFF, 'gate-a', ['password' => bcrypt('password')]);
        $this->ownerB = $this->makeUser(User::ROLE_OWNER, 'owner-b', ['password' => bcrypt('password')]);
        $this->traveller = $this->makeUser(User::ROLE_TRAVELLER, 'traveller');

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
        $this->managerA->destinations()->attach($this->destA->id);
        $this->gateStaffA->destinations()->attach($this->destA->id);
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
    }

    private function makeUser(string $role, string $suffix, array $overrides = []): User
    {
        return User::create(array_merge([
            'firebase_uid' => 'uid-mg-'.$role.'-'.$suffix.'-'.uniqid(),
            'name' => ucfirst($role).' '.$suffix,
            'email' => $role.'-'.$suffix.'-'.uniqid().'@example.com',
            'role' => $role,
            'is_active' => true,
            'password' => bcrypt('password'),
        ], $overrides));
    }

    private function makePaidBooking(User $user, TourPackage $package): Booking
    {
        $booking = Booking::create([
            'user_id' => $user->id,
            'tour_package_id' => $package->id,
            'booking_date' => now()->toDateString(),
            'people_count' => 2,
            'subtotal_amount' => 1000000,
            'discount_amount' => 0,
            'total_price' => 1000000,
            'payment_status' => 'paid',
            'booking_status' => 'confirmed',
            'ticket_status' => 'unused',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'payment_status' => 'paid',
            'payment_method' => 'qris',
            'amount' => 1000000,
            'paid_at' => now(),
        ]);

        return $booking;
    }

    /** ---------- Role access matrix (200 vs 403) ---------- */
    public function test_manager_sees_own_portal_areas_and_not_forbidden_ones(): void
    {
        $this->actingAs($this->managerA);

        $this->get('/owner/scanner')->assertOk();
        $this->get('/owner/destinations')->assertOk();
        $this->get('/owner/tour-packages')->assertOk();
        $this->get('/owner/bookings')->assertOk();
        $this->get('/owner/change-requests')->assertOk();
        $this->get('/owner/payments')->assertOk();
        $this->get('/owner/laporan')->assertOk();
        $this->get('/owner/scanner/history')->assertOk();

        // Manager may not create/delete destinations/packages, manage promos,
        // manage staff, or enter the admin panel.
        $this->post('/owner/destinations')->assertForbidden();
        $this->post('/owner/tour-packages')->assertForbidden();
        $this->get('/owner/promos')->assertForbidden();
        $this->get('/owner/staff')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_gate_staff_sees_only_scanner_and_history(): void
    {
        $this->actingAs($this->gateStaffA);

        $this->get('/owner/scanner')->assertOk();
        $this->get('/owner/scanner/history')->assertOk();

        $this->get('/owner/destinations')->assertForbidden();
        $this->get('/owner/tour-packages')->assertForbidden();
        $this->get('/owner/bookings')->assertForbidden();
        $this->get('/owner/payments')->assertForbidden();
        $this->get('/owner/laporan')->assertForbidden();
        $this->get('/owner/staff')->assertForbidden();
        $this->get('/owner/promos')->assertForbidden();
        $this->get('/admin')->assertForbidden();
    }

    public function test_traveller_has_no_web_access(): void
    {
        $this->actingAs($this->traveller);

        $this->get('/owner/scanner')->assertForbidden();
        $this->get('/owner/destinations')->assertForbidden();
        $this->get('/admin')->assertForbidden();
    }

    /** ---------- Destination isolation ---------- */
    public function test_manager_cannot_see_or_edit_other_destination(): void
    {
        $this->actingAs($this->managerA);

        $this->get('/owner/destinations')
            ->assertOk()
            ->assertSee('Destinasi A')
            ->assertDontSee('Destinasi B');

        $this->get("/owner/destinations/{$this->destB->id}")->assertForbidden();
        $this->get("/owner/destinations/{$this->destB->id}/edit")->assertForbidden();
        $this->get("/owner/tour-packages/{$this->pkgB->id}")->assertForbidden();
        $this->get("/owner/tour-packages/{$this->pkgB->id}/edit")->assertForbidden();
    }

    public function test_gate_staff_cannot_redeem_other_destination_ticket(): void
    {
        $bookingB = $this->makePaidBooking($this->traveller, $this->pkgB);
        $token = app(TicketService::class)->issue($bookingB->refresh())['token'];

        $this->actingAs($this->gateStaffA);

        $this->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertStatus(403)
            ->assertJsonPath('reason', 'forbidden_destination');
    }

    /** ---------- Owner staff management ---------- */
    public function test_owner_can_manage_staff_for_own_destination_only(): void
    {
        $this->actingAs($this->ownerA);

        // Can create a manager for the owner's own destination.
        $this->post('/owner/staff', [
            'name' => 'Manager Baru',
            'email' => 'baru-manager@example.com',
            'role' => User::ROLE_MANAGER,
            'destinations' => [$this->destA->id],
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
        ])->assertRedirect(route('owner.staff.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'baru-manager@example.com',
            'role' => User::ROLE_MANAGER,
            'created_by' => $this->ownerA->id,
        ]);

        // Cannot create an admin or owner account: validation restricts the
        // role to manager/gate_staff, so the request bounces back with an
        // error and no user is created.
        $response = $this->post('/owner/staff', [
            'name' => 'Fake Admin',
            'email' => 'fake-admin@example.com',
            'role' => User::ROLE_ADMIN,
            'destinations' => [$this->destA->id],
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
        ]);
        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'fake-admin@example.com']);

        $response = $this->post('/owner/staff', [
            'name' => 'Fake Owner',
            'email' => 'fake-owner@example.com',
            'role' => User::ROLE_OWNER,
            'destinations' => [$this->destA->id],
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
        ]);
        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'fake-owner@example.com']);

        // Cannot assign a destination the owner does not own: validation
        // rejects the destination id, so no user is created.
        $response = $this->post('/owner/staff', [
            'name' => 'Cross Dest',
            'email' => 'cross-dest@example.com',
            'role' => User::ROLE_GATE_STAFF,
            'destinations' => [$this->destB->id],
            'password' => 'Secret1234',
            'password_confirmation' => 'Secret1234',
        ]);
        $response->assertSessionHasErrors('destinations.0');
        $this->assertDatabaseMissing('users', ['email' => 'cross-dest@example.com']);

        // Activity log records the creation.
        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Destination staff created',
            'causer_id' => $this->ownerA->id,
        ]);
    }

    /** ---------- Inactive account ---------- */
    public function test_inactive_user_cannot_login_web(): void
    {
        $this->managerA->update(['is_active' => false]);

        $this->post('/admin/login', [
            'email' => $this->managerA->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_token_is_rejected_on_api(): void
    {
        Sanctum::actingAs($this->managerA);
        $token = $this->managerA->createToken('api-test')->plainTextToken;

        $this->managerA->update(['is_active' => false]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    /** ---------- gate_staff scan payload + redeemed_by + double scan ---------- */
    public function test_gate_staff_scan_excludes_sensitive_fields_and_sets_redeemed_by(): void
    {
        $bookingA = $this->makePaidBooking($this->traveller, $this->pkgA);
        $token = app(TicketService::class)->issue($bookingA->refresh())['token'];

        $this->actingAs($this->gateStaffA);

        $response = $this->postJson('/admin/tickets/redeem', ['token' => $token]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingExact([
                'amount' => 1000000,
                'email' => $this->traveller->email,
                'phone' => null,
                'identity_number' => null,
            ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('valid', $data);
        $this->assertArrayHasKey('holder_name', $data);
        $this->assertArrayHasKey('package', $data);
        $this->assertArrayHasKey('visitor_count', $data);
        $this->assertArrayHasKey('visit_date', $data);
        $this->assertArrayNotHasKey('total_price', $data);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('phone', $data);

        $this->assertSame($this->gateStaffA->id, $bookingA->refresh()->ticket_used_by);

        $this->assertDatabaseHas('activity_logs', [
            'description' => 'Ticket redeemed',
            'causer_id' => $this->gateStaffA->id,
        ]);
    }

    public function test_gate_staff_double_scan_is_rejected(): void
    {
        $bookingA = $this->makePaidBooking($this->traveller, $this->pkgA);
        $token = app(TicketService::class)->issue($bookingA->refresh())['token'];

        $this->actingAs($this->gateStaffA);

        $this->postJson('/admin/tickets/redeem', ['token' => $token])->assertOk();

        $this->postJson('/admin/tickets/redeem', ['token' => $token])
            ->assertStatus(409)
            ->assertJsonPath('reason', 'used');
    }

    /** ---------- Demo seeder environment gate ---------- */
    public function test_demo_staff_seeder_runs_in_local_and_testing(): void
    {
        config(['app.env' => 'testing']);

        (new DemoStaffSeeder)->run();

        $this->assertDatabaseHas('users', ['email' => 'admin@travelo.com', 'role' => User::ROLE_ADMIN]);
        $this->assertDatabaseHas('users', ['email' => 'owner@travelo.com', 'role' => User::ROLE_OWNER]);
        $this->assertDatabaseHas('users', ['email' => 'manager@travelo.com', 'role' => User::ROLE_MANAGER]);
        $this->assertDatabaseHas('users', ['email' => 'gate@travelo.com', 'role' => User::ROLE_GATE_STAFF]);
        $this->assertDatabaseHas('users', ['email' => 'traveller@travelo.com', 'role' => User::ROLE_TRAVELLER]);
    }

    public function test_demo_staff_seeder_is_skipped_in_production(): void
    {
        $this->app->instance('env', 'production');

        User::whereIn('email', [
            'admin@travelo.com', 'owner@travelo.com', 'manager@travelo.com',
            'gate@travelo.com', 'traveller@travelo.com',
        ])->delete();

        (new DemoStaffSeeder)->run();

        $this->assertDatabaseMissing('users', ['email' => 'admin@travelo.com']);
        $this->assertDatabaseMissing('users', ['email' => 'manager@travelo.com']);
        $this->assertDatabaseMissing('users', ['email' => 'gate@travelo.com']);
    }
}
