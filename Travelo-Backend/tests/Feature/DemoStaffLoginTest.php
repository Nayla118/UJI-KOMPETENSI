<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Web login regressions for the demo accounts created by DemoStaffSeeder.
 *
 * The database used by tests is `:memory:` (see phpunit.xml), so these tests
 * never touch the development database.sqlite. DemoStaffSeeder only runs when
 * APP_ENV is `local` or `testing`, which is satisfied in the test environment.
 */
class DemoStaffLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoStaffSeeder::class);
    }

    public function test_admin_can_login_via_web_and_is_redirected_to_dashboard(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'admin@travelo.com',
            'password' => 'admin123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isAdmin());
    }

    public function test_owner_can_login_via_web_and_is_redirected_to_owner_dashboard(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'owner@travelo.com',
            'password' => 'owner123',
        ]);

        $response->assertRedirect(route('owner.dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isOwner());
    }

    public function test_manager_can_login_via_web_and_is_redirected_to_owner_dashboard(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'manager@travelo.com',
            'password' => 'manager123',
        ]);

        $response->assertRedirect(route('owner.dashboard'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isManager());
    }

    public function test_gate_staff_can_login_via_web_and_is_redirected_to_scanner(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'gate@travelo.com',
            'password' => 'gate123',
        ]);

        $response->assertRedirect(route('owner.scanner'));
        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isGateStaff());
    }

    public function test_traveller_cannot_login_via_web_firebase_only(): void
    {
        $response = $this->post('/admin/login', [
            'email' => 'traveller@travelo.com',
            'password' => 'traveller123',
        ]);

        // Traveller is not a portal user, so the controller rejects the web
        // session even though the password is correct.
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->post('/admin/login', [
            'email' => 'admin@travelo.com',
            'password' => 'wrongpassword',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $admin = User::where('email', 'admin@travelo.com')->first();
        $admin->update(['is_active' => false]);

        $this->post('/admin/login', [
            'email' => 'admin@travelo.com',
            'password' => 'admin123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
