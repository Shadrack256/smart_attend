<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) die("Invalid");

$course_id  = (int)$_POST['course_id'];
$student_id = (int)$_POST['student_id'];

$pdo->prepare("DELETE FROM enrollments WHERE course_id=? AND student_id=?")
    ->execute([$course_id, $student_id]);

$_SESSION['flash'] = ['type'=>'success','msg'=>'Student removed from course.'];
header("Location: enrollments.php?course_id=$course_id");