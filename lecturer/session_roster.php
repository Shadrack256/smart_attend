<?php
require '../config/db.php';
require_role('lecturer');

header('Content-Type: application/json');

$session_id = (int)($_GET['session_id'] ?? 0);

// Verify ownership + load course id
$stmt = $pdo->prepare("
    SELECT s.is_active, s.course_id
    FROM sessions s
    JOIN courses c ON c.id = s.course_id
    WHERE s.id = ? AND c.lecturer_id = ?
");
$stmt->execute([$session_id, $_SESSION['user_id']]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['success' => false]);
    exit;
}

// Roster — same query as the page
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.reg_number,
           a.status, a.marked_at, a.distance_m
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    LEFT JOIN attendance a
           ON a.student_id = u.id AND a.session_id = ?
    WHERE e.course_id = ?
    ORDER BY u.full_name
");
$stmt->execute([$session_id, $session['course_id']]);
$rows = $stmt->fetchAll();

$counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'waiting' => 0];
$roster = [];

foreach ($rows as $r) {
    $status = $r['status'] ?? 'waiting';
    $counts[$status] = ($counts[$status] ?? 0);

    $roster[] = [
        'id'         => (int)$r['id'],
        'full_name'  => $r['full_name'],
        'reg_number' => $r['reg_number'],
        'status'     => $r['status'],                     // null if waiting
        'marked_at'  => $r['marked_at'],                  // null if waiting
        'distance_m' => $r['distance_m'] !== null ? (int)$r['distance_m'] : null,
    ];
}

echo json_encode([
    'success' => true,
    'active'  => (bool)$session['is_active'],
    'counts'  => $counts,
    'roster'  => $roster,
]);