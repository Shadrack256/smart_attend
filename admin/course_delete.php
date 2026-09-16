<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) die("Invalid");
$pdo->prepare("DELETE FROM courses WHERE id=?")->execute([(int)$_POST['id']]);
$_SESSION['flash'] = ['type'=>'success','msg'=>'Course deleted.'];
header("Location: courses.php");