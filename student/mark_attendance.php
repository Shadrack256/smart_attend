<?php
require '../config/db.php';
require_role('student');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$student_id = $_SESSION['user_id'];

// Auto-close sessions older than 2 hours
$pdo->prepare("UPDATE sessions SET is_active=0 WHERE is_active=1 AND start_time < NOW() - INTERVAL 2 HOUR")
    ->execute();

// --- 1. Validate session & token (existing logic) ---
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE id=? AND is_active=1");
$stmt->execute([$data['session_id'] ?? 0]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['success'=>false,'message'=>'Session not found']); exit;
}
if ($session['qr_token'] !== ($data['token'] ?? '')) {
    echo json_encode(['success'=>false,'message'=>'QR expired. Scan again.']); exit;
}
if (strtotime($session['token_expires_at']) < time() - 10) {
    echo json_encode(['success'=>false,'message'=>'QR code expired']); exit;
}

// --- 2. Enrollment check ---
$stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=?");
$stmt->execute([$student_id, $session['course_id']]);
if (!$stmt->fetch()) {
    echo json_encode(['success'=>false,'message'=>'You are not enrolled in this course']); exit;
}

// --- 3. Duplicate check ---
$stmt = $pdo->prepare("SELECT 1 FROM attendance WHERE session_id=? AND student_id=?");
$stmt->execute([$session['id'], $student_id]);
if ($stmt->fetch()) {
    echo json_encode(['success'=>false,'message'=>'Attendance already marked']); exit;
}

// --- 4. Geofence enforcement ---
$geoEnabled = $pdo->query("SELECT v FROM settings WHERE k='geofence_enabled'")->fetchColumn() === '1';

$distance_m = null;
if ($geoEnabled) {
    // Session must have coordinates
    if ($session['latitude'] === null || $session['longitude'] === null) {
        echo json_encode(['success'=>false,'message'=>'This session has no geofence set. Contact your lecturer.']); exit;
    }
    // Client must have sent coordinates
    if (!isset($data['lat'], $data['lng']) ||
        !is_numeric($data['lat']) || !is_numeric($data['lng'])) {
        echo json_encode(['success'=>false,'message'=>'Location required to mark attendance']); exit;
    }

    $studentLat = (float)$data['lat'];
    $studentLng = (float)$data['lng'];
    $accuracy   = (float)($data['accuracy'] ?? 0);

    // Reject obviously bogus coordinates
    if (abs($studentLat) > 90 || abs($studentLng) > 180 ||
        ($studentLat === 0.0 && $studentLng === 0.0)) {
        echo json_encode(['success'=>false,'message'=>'Invalid location data']); exit;
    }

    $distance_m = (int)round(haversine_m(
        (float)$session['latitude'],  (float)$session['longitude'],
        $studentLat,                  $studentLng
    ));

    // Add the GPS accuracy as a safety buffer.
    // If a phone reports ±30 m accuracy, we allow radius + accuracy.
    // Cap the buffer so students can't fake "accuracy: 500".
    $buffer = min($accuracy, 50);
    $allowed = $session['radius_m'] + $buffer;

    if ($distance_m > $allowed) {
        echo json_encode([
            'success'  => false,
            'message'  => "You are {$distance_m} m from the class (allowed: {$allowed} m).",
            'distance' => $distance_m
        ]);
        exit;
    }
}

// --- 5. Determine status ---
$status = (time() - strtotime($session['start_time']) > 600) ? 'late' : 'present';

// --- 6. Insert with coordinates ---
$stmt = $pdo->prepare("
    INSERT INTO attendance (session_id, student_id, status, scan_lat, scan_lng, distance_m)
    VALUES (?,?,?,?,?,?)
");
$stmt->execute([
    $session['id'],
    $student_id,
    $status,
    $data['lat'] ?? null,
    $data['lng'] ?? null,
    $distance_m
]);

$msg = "Attendance marked as $status!";
if ($distance_m !== null) $msg .= " ({$distance_m} m from class center)";

echo json_encode(['success'=>true,'message'=>$msg,'distance'=>$distance_m]);