<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Create a Firebase test account using the Admin SDK.
 *
 * Credentials and the password are read from the environment; never commit
 * either. The password is not printed.
 *
 * Usage:
 *   set TRAVELO_TEST_EMAIL=...
 *   set TRAVELO_TEST_PASSWORD=...
 *   php create_test_user.php
 */

require 'vendor/autoload.php';

use Kreait\Firebase\Factory;

$credentials = getenv('FIREBASE_CREDENTIALS') ?: __DIR__.'/storage/app/firebase-auth.json';
$email = getenv('TRAVELO_TEST_EMAIL');
$password = getenv('TRAVELO_TEST_PASSWORD');
$displayName = getenv('TRAVELO_TEST_NAME') ?: 'Travelo Test User';

if (!$email || !$password) {
    fwrite(STDERR, "Refusing to create a test account without TRAVELO_TEST_EMAIL and TRAVELO_TEST_PASSWORD.\n");
    exit(1);
}

if (!is_file($credentials)) {
    fwrite(STDERR, "Firebase service-account file not found: {$credentials}\n");
    exit(1);
}

$auth = (new Factory)->withServiceAccount($credentials)->createAuth();

try {
    $user = $auth->getUserByEmail($email);
    echo "User already exists.\n";
    echo "UID: {$user->uid}\n";
    echo "Email: {$user->email}\n";
} catch (\Throwable $e) {
    try {
        $created = $auth->createUser([
            'email' => $email,
            'password' => $password,
            'displayName' => $displayName,
            'emailVerified' => true,
        ]);

        echo "Firebase user created.\n";
        echo "UID: {$created->uid}\n";
        echo "Email: {$created->email}\n";
        echo "Display name: {$created->displayName}\n";
    } catch (\Throwable $createError) {
        fwrite(STDERR, "Could not create Firebase user: {$createError->getMessage()}\n");
        exit(1);
    }
}
