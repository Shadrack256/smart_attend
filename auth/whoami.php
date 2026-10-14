<?php
require '../config/db.php';
header('Content-Type: application/json');
echo json_encode([
    'loggedIn' => is_logged_in(),
    'role' => $_SESSION['role'] ?? null,
]);