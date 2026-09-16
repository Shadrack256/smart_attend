<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('admin');

// ---------- Validate request ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    die("Invalid request");
}

$course_id = (int)($_POST['course_id'] ?? 0);
$filter    = $_POST['filter'] ?? 'all';

if (!$course_id) die("No course selected");

// ---------- Course + required threshold ----------
$stmt = $pdo->prepare("
    SELECT course_code, course_name, required_attendance
    FROM courses WHERE id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) die("Course not found");

$courseRequired = (int)($course['required_attendance'] ?? 75);

// ---------- Filter interpretation ----------
$threshold = null;
if ($filter === 'below_course')  $threshold = $courseRequired;
if ($filter === 'below_50')      $threshold = 50;
if ($filter === 'below_25')      $threshold = 25;

if ($threshold === null) die("No threshold selected");

// ---------- Load students ----------
$stmt = $pdo->prepare("
    SELECT u.full_name, u.email, u.reg_number,
           COUNT(DISTINCT s.id) AS total_sessions,
           SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) AS attended_count
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    LEFT JOIN sessions s ON s.course_id = e.course_id
    LEFT JOIN attendance a
           ON a.session_id = s.id AND a.student_id = u.id
    WHERE e.course_id = ?
    GROUP BY u.id, u.full_name, u.email, u.reg_number
");
$stmt->execute([$course_id]);
$all = $stmt->fetchAll();

// ---------- Send emails ----------
$sent   = 0;
$failed = 0;
$skipped = 0;

foreach ($all as $r) {
    $total = (int)$r['total_sessions'];
    if ($total === 0) { $skipped++; continue; }

    $pct = round(((int)$r['attended_count'] / $total) * 100, 1);
    if ($pct >= $threshold) { $skipped++; continue; }

    $subject = "Attendance warning — {$course['course_code']} (below {$threshold}%)";

    $body = "
        <div style='font-family:Inter,sans-serif;max-width:560px;margin:0 auto;padding:24px;color:#334155;'>
            <h2 style='color:#0f172a;margin:0 0 12px;'>Attendance warning</h2>
            <p>Dear " . htmlspecialchars($r['full_name']) . ",</p>
            <p>Your attendance in <strong>" . htmlspecialchars($course['course_code'] . ' — ' . $course['course_name']) . "</strong>
               is currently <strong style='color:#dc2626;'>" . $pct . "%</strong>.</p>
            <p>This course requires a minimum of <strong>" . $threshold . "%</strong>.</p>
            <p>Please speak with your lecturer as soon as possible to discuss your attendance.</p>
            <hr style='border:none;border-top:1px solid #e2e8f0;margin:24px 0;'>
            <p style='font-size:12px;color:#94a3b8;'>Sent automatically by Smart Attend.</p>
        </div>
    ";

    if (send_mail($r['email'], $r['full_name'], $subject, $body)) {
        $sent++;
    } else {
        $failed++;
    }
}

$_SESSION['flash'] = [
    'type' => $failed > 0 ? 'error' : 'success',
    'msg'  => "Sent {$sent} email(s)." .
              ($skipped > 0 ? " {$skipped} skipped (no sessions or above threshold)." : '') .
              ($failed > 0 ? " {$failed} failed — check error log." : '')
];

header("Location: reports.php?course_id={$course_id}&filter=" . urlencode($filter));
exit;