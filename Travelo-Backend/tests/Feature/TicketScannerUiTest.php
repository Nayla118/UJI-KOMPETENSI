<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The scanner is an operational screen for admins and gate staff only.
 *
 * Travellers must never be able to load it, and gate staff must be sent to
 * the scanner after login instead of the admin-only dashboard.
 */
class TicketScannerUiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $suffix = ''): User
    {
        return User::create([
            'firebase_uid' => 'uid-scanner-'.$suffix.'-'.uniqid(),
            'name' => 'Scanner User '.$suffix,
            'email' => 'scanner-'.$suffix.'-'.uniqid().'@example.com',
            'role' => $role,
            'password' => bcrypt('synthetic-password'),
        ]);
    }

    public function test_guest_is_redirected_to_the_admin_login(): void
    {
        // Laravel's auth middleware uses the named `login` route, which is a
        // short redirect to the actual /admin/login form.
        $this->get('/admin/scanner')
            ->assertRedirect(route('login'));
    }

    public function test_traveller_cannot_open_the_scanner(): void
    {
        $traveller = $this->makeUser(User::ROLE_TRAVELLER, 'traveller');

        $this->actingAs($traveller)
            ->get('/admin/scanner')
            ->assertForbidden();
    }

    public function test_staff_can_open_the_scanner_for_assigned_destinations(): void
    {
        $destination = Destination::create([
            'name' => 'Bali',
            'city' => 'Denpasar',
            'country' => 'Indonesia',
        ]);

        $staff = $this->makeUser(User::ROLE_STAFF, 'staff');
        $staff->staffDestinations()->attach($destination->id);

        $this->actingAs($staff)
            ->get('/admin/scanner')
            ->assertOk()
            ->assertSee('Camera scanner')
            ->assertSee('Camera starts automatically')
            ->assertSee('Take QR photo')
            ->assertSee('Scan QR image')
            ->assertSee('Not today')
            ->assertSee('Manual fallback')
            ->assertSee('id="scanner-result"', false)
            ->assertSee('aria-labelledby="scanner-result-title"', false)
            ->assertSee('id="scanner-result-next"', false)
            ->assertSee('Bali')
            ->assertDontSee('Customers');
    }

    public function test_staff_without_destinations_sees_an_explicit_warning(): void
    {
        $staff = $this->makeUser(User::ROLE_STAFF, 'unassigned');

        $this->actingAs($staff)
            ->get('/admin/scanner')
            ->assertOk()
            ->assertSee('No destinations assigned');
    }

    public function test_admin_can_open_the_scanner_for_all_destinations(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN, 'admin');

        $this->actingAs($admin)
            ->get('/admin/scanner')
            ->assertOk()
            ->assertSee('All destinations');
    }

    public function test_staff_login_lands_on_the_scanner_not_the_admin_dashboard(): void
    {
        $staff = $this->makeUser(User::ROLE_STAFF, 'login');

        $this->post('/admin/login', [
            'email' => $staff->email,
            'password' => 'synthetic-password',
        ])->assertRedirect(route('admin.scanner'));

        $this->assertAuthenticatedAs($staff);
    }

    public function test_admin_login_still_lands_on_the_dashboard(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN, 'login-admin');

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'synthetic-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }
}
