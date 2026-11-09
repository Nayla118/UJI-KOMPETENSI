<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo accounts for development / local testing only.
 *
 * This seeder MUST only run when APP_ENV is `local` or `testing`. In any
 * other environment it skips and prints a warning so demo credentials never
 * leak into production. Passwords here are intentionally short because they
 * are demo-only; the 12-character rule still applies to the admin account
 * created by UserSeeder via TRAVELO_ADMIN_PASSWORD.
 */
class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            fwrite(STDERR, "DemoStaffSeeder dilewati: bukan environment local/testing.\n");

            return;
        }

        $accounts = [
            [
                'email' => 'admin@travelo.com',
                'password' => 'admin123',
                'name' => 'Admin Travelo',
                'role' => User::ROLE_ADMIN,
            ],
            [
                'email' => 'owner@travelo.com',
                'password' => 'owner123',
                'name' => 'Owner Destinasi',
                'role' => User::ROLE_OWNER,
            ],
            [
                'email' => 'manager@travelo.com',
                'password' => 'manager123',
                'name' => 'Manager Destinasi',
                'role' => User::ROLE_MANAGER,
            ],
            [
                'email' => 'gate@travelo.com',
                'password' => 'gate123',
                'name' => 'Gate Staff',
                'role' => User::ROLE_GATE_STAFF,
            ],
            [
                'email' => 'traveller@travelo.com',
                'password' => 'traveller123',
                'name' => 'Traveller Demo',
                'role' => User::ROLE_TRAVELLER,
            ],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => Hash::make($account['password']),
                    'role' => $account['role'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        // Owner, manager and gate_staff share one demo destination so they can
        // scan/manage within the same scope.
        $destination = Destination::first();

        if ($destination) {
            foreach ([User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_GATE_STAFF] as $role) {
                $staff = User::where('role', $role)
                    ->where('email', 'like', match ($role) {
                        User::ROLE_OWNER => 'owner@travelo.com',
                        User::ROLE_MANAGER => 'manager@travelo.com',
                        default => 'gate@travelo.com',
                    })
                    ->first();

                if ($staff) {
                    $staff->destinations()->syncWithoutDetaching([$destination->id]);
                }
            }
        }
    }
}
