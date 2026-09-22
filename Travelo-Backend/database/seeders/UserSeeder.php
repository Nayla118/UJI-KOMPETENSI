<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin Travelo',
            'email' => 'admin@travelo.com',
            'password' => Hash::make('password123'),
            'firebase_uid' => 'admin-uid-001',
            'photo' => null,
        ]);
    }
}
