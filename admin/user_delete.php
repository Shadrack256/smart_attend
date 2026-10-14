<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    die("Invalid request");
}
$id = (int)$_POST['id'];
if ($id === (int)$_SESSION['user_id']) die("You cannot delete yourself.");

$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
$_SESSION['flash'] = ['type'=>'success','msg'=>'User deleted.'];
header("Location: users.php");