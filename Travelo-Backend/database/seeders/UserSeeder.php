<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('TRAVELO_ADMIN_EMAIL', 'admin@travelo.com');
        $password = env('TRAVELO_ADMIN_PASSWORD');

        if (!is_string($password) || strlen($password) < 12) {
            throw new RuntimeException(
                'Set TRAVELO_ADMIN_PASSWORD (minimum 12 characters) before running UserSeeder.'
            );
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('TRAVELO_ADMIN_NAME', 'Admin Travelo'),
                'password' => Hash::make($password),
                'firebase_uid' => env('TRAVELO_ADMIN_FIREBASE_UID', 'admin-uid-001'),
                'photo' => null,
                'email_verified_at' => now(),
            ]
        );
    }
}
