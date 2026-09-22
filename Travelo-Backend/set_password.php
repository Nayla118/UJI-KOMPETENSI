<?php
require 'vendor/autoload.php';

use Illuminate\Support\Facades\Hash;

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Set password for aisyahnayla828@gmail.com
$email = 'aisyahnayla828@gmail.com';
$password = 'password123'; // Password untuk test login
$hashedPassword = Hash::make($password);

// Update database
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=travelo', 'root', '');
$stmt = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
$stmt->execute([$hashedPassword, $email]);

echo "\n✅ PASSWORD SET SUCCESSFULLY\n";
echo "Email: $email\n";
echo "Password: $password\n";
echo "Hash: $hashedPassword\n";
