<?php
require '../config/db.php';
require_role('admin');

echo "<h2>Direct notify test</h2>";

// Simulate what the form posts
$_POST['course_id'] = 1;
$_POST['filter']    = 'atRisk';
$_POST['csrf']      = $_SESSION['csrf'];

echo "Session CSRF: " . htmlspecialchars($_SESSION['csrf']) . "<br>";
echo "POST CSRF:    " . htmlspecialchars($_POST['csrf']) . "<br>";
echo "Match: " . (hash_equals($_SESSION['csrf'], $_POST['csrf']) ? 'YES' : 'NO') . "<br><br>";

// Now include the actual handler
echo "Calling notify_low_attendance.php...<br><hr>";
include 'notify_low_attendance.php';