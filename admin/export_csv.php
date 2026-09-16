<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');

// ---------- Inputs ----------
$course_id = (int)($_GET['course_id'] ?? 0);
$filter    = $_GET['filter'] ?? 'all';

$fromDate = trim($_GET['from'] ?? '');
$toDate   = trim($_GET['to']   ?? '');
$dateRe   = '/^\d{4}-\d{2}-\d{2}$/';
if ($fromDate !== '' && !preg_match($dateRe, $fromDate)) $fromDate = '';
if ($toDate   !== '' && !preg_match($dateRe, $toDate))   $toDate   = '';

if (!$course_id) {
    http_response_code(400);
    die("No course selected");
}

// ---------- Course info + required threshold ----------
$stmt = $pdo->prepare("
    SELECT c.course_code, c.course_name, c.required_attendance, u.full_name AS lecturer_name
    FROM courses c
    LEFT JOIN users u ON u.id = c.lecturer_id
    WHERE c.id = ?
");
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) {
    http_response_code(404);
    die("Course not found");
}

$courseRequired = (int)($course['required_attendance'] ?? 75);

// ---------- Filter interpretation ----------
$threshold = null;
if ($filter === 'below_course')  $threshold = $courseRequired;
if ($filter === 'below_50')      $threshold = 50;
if ($filter === 'below_25')      $threshold = 25;

// ---------- Report rows ----------
$sql = "
    SELECT
        u.full_name,
        u.reg_number,
        u.email,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN a.status = 'late'    THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN a.status = 'absent'  THEN 1 ELSE 0 END) AS absent_count,
        COUNT(DISTINCT s.id) AS total_sessions,
        SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) AS attended_count
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    LEFT JOIN sessions s
           ON s.course_id = e.course_id
          AND 1=1";

$params = [];
if ($fromDate !== '') { $sql .= " AND s.session_date >= ?"; $params[] = $fromDate; }
if ($toDate   !== '') { $sql .= " AND s.session_date <= ?"; $params[] = $toDate; }

$sql .= "
    LEFT JOIN attendance a
           ON a.session_id = s.id AND a.student_id = u.id
    WHERE e.course_id = ?";
$params[] = $course_id;

$sql .= "
    GROUP BY u.id, u.full_name, u.reg_number, u.email
    ORDER BY u.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Apply threshold filter
if ($threshold !== null) {
    $rows = array_values(array_filter($rows, function ($r) use ($threshold) {
        $total = (int)$r['total_sessions'];
        if ($total === 0) return false;
        return ((int)$r['attended_count'] / $total) * 100 < $threshold;
    }));
}

// Session count for the same range
$sql = "SELECT COUNT(*) FROM sessions WHERE course_id = ?";
$params = [$course_id];
if ($fromDate !== '') { $sql .= " AND session_date >= ?"; $params[] = $fromDate; }
if ($toDate   !== '') { $sql .= " AND session_date <= ?"; $params[] = $toDate; }
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$totalSessions = (int)$stmt->fetchColumn();

// ---------- Filename ----------
$safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $course['course_code']);
$filename = 'attendance_' . $safeCode;
if ($threshold !== null) $filename .= '_below' . $threshold;
if ($fromDate !== '' || $toDate !== '') {
    $filename .= '_' . ($fromDate ?: 'start') . '_to_' . ($toDate ?: 'end');
}
$filename .= '_' . date('Y-m-d') . '.csv';

// ---------- Stream ----------
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM for Excel

// Metadata
fputcsv($out, ['Course', $course['course_code'] . ' — ' . $course['course_name']]);
fputcsv($out, ['Lecturer', $course['lecturer_name'] ?? '—']);
fputcsv($out, ['Required attendance', $courseRequired . '%']);
fputcsv($out, ['Sessions in range', $totalSessions]);
fputcsv($out, ['Period',
    ($fromDate ? 'From ' . date('d M Y', strtotime($fromDate)) : '') .
    (($fromDate && $toDate) ? ' ' : '') .
    ($toDate ? 'To ' . date('d M Y', strtotime($toDate)) : '') ?: 'All sessions'
]);
fputcsv($out, ['Filter',
    $threshold !== null
        ? "Below {$threshold}%" . ($filter === 'below_course' ? ' (course requirement)' : '')
        : 'All students'
]);
fputcsv($out, ['Generated', date('d M Y H:i')]);
fputcsv($out, ['Students listed', count($rows)]);
fputcsv($out, []);

// Header row
fputcsv($out, [
    '#',
    'Student',
    'Reg Number',
    'Email',
    'Present',
    'Late',
    'Absent',
    'Total Sessions',
    'Attended',
    'Attendance %',
    'Status',
]);

// Data rows
$i = 0;
foreach ($rows as $r) {
    $i++;
    $total    = (int)$r['total_sessions'];
    $present  = (int)$r['present_count'];
    $late     = (int)$r['late_count'];
    $absent   = (int)$r['absent_count'];
    $attended = (int)$r['attended_count'];
    $pct      = $total > 0 ? round($attended / $total * 100, 1) : 0;

    if ($pct >= $courseRequired)         $status = 'OK';
    elseif ($pct >= max(0, $courseRequired - 10)) $status = 'Warning';
    else                                 $status = 'At risk';

    fputcsv($out, [
        $i,
        $r['full_name'],
        $r['reg_number'],
        $r['email'],
        $present,
        $late,
        $absent,
        $total,
        $attended,
        $pct . '%',
        $status,
    ]);
}

// Summary
if ($rows) {
    $sumPresent  = array_sum(array_column($rows, 'present_count'));
    $sumLate     = array_sum(array_column($rows, 'late_count'));
    $sumAbsent   = array_sum(array_column($rows, 'absent_count'));
    $sumAttended = array_sum(array_column($rows, 'attended_count'));
    $sumPossible = $totalSessions * count($rows);
    $avgPct      = $sumPossible > 0 ? round($sumAttended / $sumPossible * 100, 1) : 0;

    fputcsv($out, []);
    fputcsv($out, ['Totals (all students)']);
    fputcsv($out, ['Present',  $sumPresent]);
    fputcsv($out, ['Late',     $sumLate]);
    fputcsv($out, ['Absent',   $sumAbsent]);
    fputcsv($out, ['Attended', $sumAttended]);
    fputcsv($out, ['Average %', $avgPct . '%']);
}

fclose($out);
exit;