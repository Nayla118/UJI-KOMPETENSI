<?php
// Script untuk create Firebase user via PHP SDK

require 'vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

// Initialize Firebase
$factory = (new Factory)
    ->withServiceAccount('config/firebase.json');

$auth = $factory->createAuth();

// Test user credentials
$email = 'test@travelo.com';
$password = 'password123';
$displayName = 'Test User';

try {
    // Check if user already exists
    $user = $auth->getUserByEmail($email);
    echo "✅ User already exists:\n";
    echo "UID: {$user['uid']}\n";
    echo "Email: {$user['email']}\n";
} catch (\Exception $e) {
    // User doesn't exist, create new
    echo "Creating new Firebase user...\n";
    
    $userProperties = [
        'email' => $email,
        'password' => $password,
        'displayName' => $displayName,
        'emailVerified' => true,
    ];
    
    $createdUser = $auth->createUser($userProperties);
    
    echo "✅ Firebase user created successfully!\n";
    echo "UID: {$createdUser['uid']}\n";
    echo "Email: {$createdUser['email']}\n";
    echo "Display Name: {$createdUser['displayName']}\n";
}

echo "\n========================================\n";
echo "TEST CREDENTIALS:\n";
echo "========================================\n";
echo "Email: $email\n";
echo "Password: $password\n";
echo "========================================\n";
echo "\nUse these credentials to login in the app!\n";
