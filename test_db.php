<?php
require __DIR__ . '/config/db.php';
echo "Connected to: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "<br>";
echo "Users in table: " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();