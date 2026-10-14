<?php

ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ------------------------------------------------------------------
// Supabase (PostgreSQL) Connection Settings
// ------------------------------------------------------------------
$host = getenv('DB_HOST') ?: 'aws-0-eu-west-1.pooler.supabase.com'; 
$port = getenv('DB_PORT') ?: '5432';
$db   = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres.jayhqcbwyehgnthnobvsu'; 

// ⚠️ Replace this with your actual password, or set it in Render Env Vars
$pass = getenv('DB_PASS') ?: 'Shadrack@4xs29ptm'; 

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

try {
    // PostgreSQL DSN string. Note the 'sslmode=require' which Supabase needs.
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, 
    ]);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// ... [Keep the rest of your db.php exactly the same as the previous message] ...
