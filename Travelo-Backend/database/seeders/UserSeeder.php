<?php

namespace Database\Seeders;

use App\Models\Destination;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Admin account (web panel full access) ----
        $adminEmail = env('TRAVELO_ADMIN_EMAIL', 'admin@travelo.com');
        $adminPassword = env('TRAVELO_ADMIN_PASSWORD');

        if (! is_string($adminPassword) || strlen($adminPassword) < 12) {
            throw new RuntimeException(
                'Set TRAVELO_ADMIN_PASSWORD (minimum 12 characters) before running UserSeeder.'
            );
        }

        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => env('TRAVELO_ADMIN_NAME', 'Admin Travelo'),
                'password' => Hash::make($adminPassword),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                'firebase_uid' => env('TRAVELO_ADMIN_FIREBASE_UID', 'admin-uid-001'),
                'email_verified_at' => now(),
            ]
        );

        // ---- Owner account (destination-scoped access) ----
        $ownerEmail = env('TRAVELO_OWNER_EMAIL', 'owner@travelo.com');
        $ownerPassword = env('TRAVELO_OWNER_PASSWORD');

        if (! is_string($ownerPassword) || strlen($ownerPassword) < 12) {
            throw new RuntimeException(
                'Set TRAVELO_OWNER_PASSWORD (minimum 12 characters) before running UserSeeder.'
            );
        }

        $owner = User::updateOrCreate(
            ['email' => $ownerEmail],
            [
                'name' => env('TRAVELO_OWNER_NAME', 'Owner Destinasi'),
                'password' => Hash::make($ownerPassword),
                'role' => User::ROLE_OWNER,
                'is_active' => true,
                'firebase_uid' => env('TRAVELO_OWNER_FIREBASE_UID', 'owner-uid-001'),
                'email_verified_at' => now(),
            ]
        );

        // Assign the first destination to the owner so they can scan/manage it.
        $firstDestination = Destination::first();
        if ($firstDestination) {
            $owner->destinations()->syncWithoutDetaching([$firstDestination->id]);
        }
    }
}
