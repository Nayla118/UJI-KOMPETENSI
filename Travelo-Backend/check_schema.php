<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=travelo', 'root', '');
$columns = $pdo->query('DESCRIBE users')->fetchAll(PDO::FETCH_ASSOC);

echo "\n=== USERS TABLE STRUCTURE ===\n";
foreach ($columns as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ')\n';
}
