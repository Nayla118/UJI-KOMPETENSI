<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Set a local (admin) password without hardcoding it in the repository.
 *
 * Firebase app accounts authenticate through Firebase, but the admin web
 * login uses a local password hash.
 *
 * Usage:
 *   set TRAVELO_TARGET_EMAIL=admin@travelo.com
 *   set TRAVELO_NEW_PASSWORD=...
 *   php set_password.php
 */

require 'vendor/autoload.php';

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = trim((string) getenv('TRAVELO_TARGET_EMAIL'));
$password = (string) getenv('TRAVELO_NEW_PASSWORD');

if ($email === '' || $password === '') {
    fwrite(STDERR, "Set TRAVELO_TARGET_EMAIL and TRAVELO_NEW_PASSWORD before running this script.\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "TRAVELO_TARGET_EMAIL must be a valid email address.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "TRAVELO_NEW_PASSWORD must be at least 12 characters.\n");
    exit(1);
}

$user = User::where('email', $email)->first();

if (!$user) {
    fwrite(STDERR, "No local user found for {$email}.\n");
    exit(1);
}

$user->forceFill(['password' => Hash::make($password)])->save();

echo "Local password updated for {$email}. The password was not printed.\n";
