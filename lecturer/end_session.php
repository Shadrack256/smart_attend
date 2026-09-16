<?php
require '../config/db.php';
require_role('lecturer');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$session_id = (int)($_POST['session_id'] ?? 0);

if (!$session_id) {
    echo json_encode(['success' => false, 'message' => 'Missing session ID']);
    exit;
}

// Verify ownership
$stmt = $pdo->prepare("
    SELECT s.id, s.is_active
    FROM sessions s
    JOIN courses c ON c.id = s.course_id
    WHERE s.id = ? AND c.lecturer_id = ?
");
$stmt->execute([$session_id, $_SESSION['user_id']]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['success' => false, 'message' => 'Session not found or not yours']);
    exit;
}

// Already ended?
if (!$session['is_active']) {
    echo json_encode(['success' => true, 'message' => 'Session was already ended']);
    exit;
}

// Run the full end-session logic (flip off + mark absentees)
$absent = end_session($pdo, $session_id);

if ($absent === -1) {
    echo json_encode(['success' => false, 'message' => 'Database error while ending session']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => "Session ended. {$absent} student(s) marked absent."
]);
exit;