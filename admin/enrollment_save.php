<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) die("Invalid");

$course_id = (int)$_POST['course_id'];
$ids = array_map('intval', $_POST['student_ids'] ?? []);

if ($ids) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?,?)");
    foreach ($ids as $sid) $stmt->execute([$sid, $course_id]);
    $_SESSION['flash'] = ['type'=>'success','msg'=>count($ids).' student(s) enrolled.'];
} else {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'No students selected.'];
}
header("Location: enrollments.php?course_id=$course_id");