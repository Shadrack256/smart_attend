<?php
require '../config/db.php';
require_role('lecturer');

header('Content-Type: application/json');

$session_id = (int)($_GET['session_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT s.is_active
    FROM sessions s
    JOIN courses c ON c.id = s.course_id
    WHERE s.id = ? AND c.lecturer_id = ?
");
$stmt->execute([$session_id, $_SESSION['user_id']]);
$session = $stmt->fetch();

echo json_encode([
    'active' => $session ? (bool)$session['is_active'] : false
]);