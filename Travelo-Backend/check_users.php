<?php
require 'vendor/autoload.php';

$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=travelo', 'root', '');
$users = $pdo->query('SELECT id, email, name FROM users')->fetchAll(PDO::FETCH_ASSOC);

echo "\n=== USERS IN DATABASE ===\n";
foreach ($users as $user) {
    echo "ID: {$user['id']}, Email: {$user['email']}, Name: {$user['name']}\n";
}
echo "\nTotal: " . count($users) . " users\n";
