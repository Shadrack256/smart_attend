<?php
require_once __DIR__ . '/../config/db.php';
require_role('lecturer');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die("PDF library not installed. Run: composer require dompdf/dompdf");
}
require $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

$lecturer_id = (int)$_SESSION['user_id'];
$course_id   = (int)($_GET['course_id'] ?? 0);

$fromDate = trim($_GET['from'] ?? '');
$toDate   = trim($_GET['to']   ?? '');
$dateRe   = '/^\d{4}-\d{2}-\d{2}$/';
if ($fromDate !== '' && !preg_match($dateRe, $fromDate)) $fromDate = '';
if ($toDate   !== '' && !preg_match($dateRe, $toDate))   $toDate   = '';

if (!$course_id) die("No course selected");

// ---------- Verify the lecturer owns this course ----------
$stmt = $pdo->prepare("
    SELECT c.course_code, c.course_name, u.full_name AS lecturer_name
    FROM courses c
    LEFT JOIN users u ON u.id = c.lecturer_id
    WHERE c.id = ? AND c.lecturer_id = ?
");
$stmt->execute([$course_id, $lecturer_id]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(403);
    die("You are not assigned to this course.");
}

// ---------- Sessions in range ----------
$sql = "SELECT id, session_date, start_time, is_active
        FROM sessions WHERE course_id = ?";
$params = [$course_id];
if ($fromDate !== '') { $sql .= " AND session_date >= ?"; $params[] = $fromDate; }
if ($toDate   !== '') { $sql .= " AND session_date <= ?"; $params[] = $toDate; }
$sql .= " ORDER BY session_date ASC, start_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

// ---------- Roster ----------
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.reg_number
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    WHERE e.course_id = ?
    ORDER BY u.full_name
");
$stmt->execute([$course_id]);
$students = $stmt->fetchAll();

// ---------- Attendance lookup ----------
$attendance = [];
if ($students && $sessions) {
    $sessionIds = array_column($sessions, 'id');
    $in         = implode(',', array_fill(0, count($sessionIds), '?'));

    $stmt = $pdo->prepare("
        SELECT student_id, session_id, status
        FROM attendance
        WHERE session_id IN ($in)
    ");
    $stmt->execute($sessionIds);
    foreach ($stmt->fetchAll() as $row) {
        $attendance[$row['student_id']][$row['session_id']] = $row['status'];
    }
}

// ---------- Institution / branding ----------
$brand       = app_settings($pdo);
$instName    = $brand['institution_name']    ?? ($brand['system_name'] ?? 'Institution');
$instAddress = $brand['institution_address'] ?? '';
$instPhone   = $brand['institution_phone']   ?? '';
$instEmail   = $brand['institution_email']   ?? '';
$instWebsite = $brand['institution_website'] ?? '';
$reportTitle = $brand['report_title']        ?? 'Attendance Report';
$signerLabel = $brand['report_signer_name']  ?? 'Head of Department';
$sysName     = $brand['system_name']         ?? 'SmartAttend';
$sysLogo     = $brand['system_logo']         ?? '';

$logoData = '';
if ($sysLogo) {
    $p = __DIR__ . '/../uploads/branding/' . $sysLogo;
    if (is_file($p)) {
        $mime = mime_content_type($p) ?: 'image/png';
        $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p));
    }
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- Session headers ----------
$sessionHeaders = '';
$sessionCount   = count($sessions);
foreach ($sessions as $s) {
    $d = date('d', strtotime($s['session_date']));
    $m = date('M', strtotime($s['session_date']));
    $sessionHeaders .= '<th class="c sess-col">' . $d . '<br><span class="muted">' . $m . '</span></th>';
}

// ---------- Rows ----------
$rowsHtml = '';
$i = 0;
foreach ($students as $st) {
    $i++;
    $present = 0;
    $total   = count($sessions);

    $cells = '';
    foreach ($sessions as $s) {
        $status = $attendance[$st['id']][$s['id']] ?? null;
        if ($status === 'present')      { $cells .= '<td class="c mark-p">✓</td>'; $present++; }
        elseif ($status === 'late')     { $cells .= '<td class="c mark-l">L</td>'; $present++; }
        else                             { $cells .= '<td class="c mark-a">✗</td>'; }
    }

    $pct      = $total > 0 ? round($present / $total * 100) : 0;
    $pctColor = $pct >= 75 ? '#059669' : ($pct >= 50 ? '#d97706' : '#dc2626');

    $rowsHtml .= '
        <tr>
            <td class="c">' . $i . '</td>
            <td>' . e($st['full_name']) . '</td>
            <td class="mono">' . e($st['reg_number']) . '</td>'
            . $cells .
            '<td class="c b" style="color:' . $pctColor . '">' . $pct . '</td>
        </tr>';
}

if (!$students) {
    $rowsHtml = '<tr><td colspan="' . (4 + $sessionCount) . '" class="c empty">No students enrolled.</td></tr>';
} elseif (!$sessions) {
    $rowsHtml = '<tr><td colspan="4" class="c empty">No sessions held yet.</td></tr>';
}

// ---------- Date range label ----------
$dateRange = '—';
if ($sessions) {
    $first = $sessions[0]['session_date'];
    $last  = $sessions[count($sessions) - 1]['session_date'];
    $dateRange = date('d M Y', strtotime($first)) . ' – ' . date('d M Y', strtotime($last));
}
$generatedAt = date('d M Y \a\t H:i');

$orientation = $sessionCount > 12 ? 'landscape' : 'portrait';

// ---------- HTML ----------
$html = '
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    @page { margin: 28px 24px 40px 24px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.35; }

    .letterhead { width: 100%; border-bottom: 3px double #1e293b; padding-bottom: 10px; margin-bottom: 18px; }
    .letterhead table { width: 100%; border-collapse: collapse; }
    .letterhead td { vertical-align: middle; padding: 0; }
    .logo-cell { width: 84px; }
    .logo-cell img { width: 76px; height: 76px; object-fit: contain; }
    .logo-placeholder { width: 76px; height: 76px; line-height: 76px; text-align: center; background: #1a3ff0; color: #fff; font-size: 34pt; font-weight: bold; border-radius: 8px; }
    .name-cell h1 { font-size: 17pt; margin: 0 0 3px 0; text-transform: uppercase; letter-spacing: 1px; color: #0f172a; }
    .name-cell .contact { font-size: 8.5pt; color: #475569; margin: 0; }
    .name-cell .contact span { margin-right: 10px; }

    .report-title { text-align: center; margin: 14px 0 3px 0; font-size: 14pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #0f172a; }
    .report-subtitle { text-align: center; font-size: 9.5pt; color: #64748b; margin-bottom: 14px; }

    .meta { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
    .meta td { padding: 3px 8px; font-size: 9pt; vertical-align: top; }
    .meta .label { color: #64748b; width: 100px; }
    .meta .value { color: #0f172a; font-weight: bold; }

    table.register { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.register thead th { background: #1e293b; color: #fff; padding: 5px 3px; font-size: 8pt; font-weight: normal; text-align: left; border: 1px solid #1e293b; }
    table.register thead th.c { text-align: center; }
    table.register thead th.sess-col { width: 24px; line-height: 1.1; }
    table.register thead th .muted { font-size: 6.5pt; opacity: .8; }
    table.register tbody td { padding: 4px; border: 1px solid #cbd5e1; font-size: 8.5pt; }
    table.register tbody tr:nth-child(even) { background: #f8fafc; }
    table.register .c { text-align: center; }
    table.register .b { font-weight: bold; }
    table.register .mono { font-family: DejaVu Sans Mono, monospace; font-size: 7.5pt; }
    table.register .empty { color: #94a3b8; padding: 22px; font-style: italic; }

    .mark-p { color: #059669; font-weight: bold; font-size: 10pt; }
    .mark-l { color: #d97706; font-weight: bold; }
    .mark-a { color: #dc2626; }

    .legend { background: #f1f5f9; border-left: 4px solid #1a3ff0; padding: 8px 12px; margin-bottom: 20px; font-size: 8.5pt; }
    .legend b { color: #0f172a; }

    .signatures { width: 100%; margin-top: 26px; border-collapse: collapse; }
    .signatures td { width: 50%; padding: 0 20px 0 0; vertical-align: top; }
    .sig-line { border-bottom: 1px solid #475569; height: 30px; margin-bottom: 3px; }
    .sig-label { font-size: 8.5pt; color: #64748b; }
    .stamp-box { margin-top: 18px; border: 1px dashed #94a3b8; height: 70px; border-radius: 8px; text-align: center; line-height: 70px; color: #94a3b8; font-size: 8.5pt; }

    .footer { position: fixed; bottom: -26px; left: 0; right: 0; text-align: center; font-size: 7.5pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
</style>
</head>
<body>

<div class="letterhead">
    <table><tr>
        <td class="logo-cell">' .
            ($logoData
                ? '<img src="' . $logoData . '" alt="Logo">'
                : '<div class="logo-placeholder">' . e(strtoupper(substr($instName, 0, 1))) . '</div>')
        . '</td>
        <td class="name-cell">
            <h1>' . e($instName) . '</h1>
            <p class="contact">' .
                ($instAddress ? '<span>' . e($instAddress) . '</span>' : '') .
                ($instPhone   ? '<span>Tel: ' . e($instPhone) . '</span>' : '') .
                ($instEmail   ? '<span>Email: ' . e($instEmail) . '</span>' : '') .
                ($instWebsite ? '<span>' . e($instWebsite) . '</span>' : '') .
            '</p>
        </td>
    </tr></table>
</div>

<div class="report-title">' . e($reportTitle) . ' — Register</div>
<div class="report-subtitle">Generated by ' . e($sysName) . '</div>

<table class="meta">
    <tr>
        <td class="label">Course</td>
        <td class="value">' . e($course['course_code'] . ' — ' . $course['course_name']) . '</td>
        <td class="label">Sessions</td>
        <td class="value">' . $sessionCount . '</td>
    </tr>
    <tr>
        <td class="label">Lecturer</td>
        <td class="value">' . e($course['lecturer_name'] ?? '—') . '</td>
        <td class="label">Students</td>
        <td class="value">' . count($students) . '</td>
    </tr>
    <tr>
        <td class="label">Period</td>
        <td class="value">' . e($dateRange) . '</td>
        <td class="label">Generated</td>
        <td class="value">' . e($generatedAt) . '</td>
    </tr>
</table>

<table class="register">
    <thead><tr>
        <th class="c" style="width: 22px;">#</th>
        <th>Student</th>
        <th style="width: 100px;">Reg No.</th>'
        . $sessionHeaders .
        '<th class="c" style="width: 32px;">%</th>
    </tr></thead>
    <tbody>' . $rowsHtml . '</tbody>
</table>

<div class="legend">
    <b>Legend:</b> &nbsp; ✓ = Present &nbsp;·&nbsp; L = Late &nbsp;·&nbsp; ✗ = Absent.
    Percentage is (Present + Late) ÷ Sessions.
</div>

<table class="signatures">
    <tr>
        <td><div class="sig-line"></div><div class="sig-label">Lecturer (Name &amp; Signature)</div></td>
        <td><div class="sig-line"></div><div class="sig-label">' . e($signerLabel) . ' (Name &amp; Signature)</div></td>
    </tr>
</table>

<div class="stamp-box">Official Stamp</div>

<div class="footer">
    ' . e($instName) . ' · ' . e($sysName) . ' · ' . e($generatedAt) . '
</div>

</body></html>
';

// ---------- Render ----------
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', $orientation);
$dompdf->render();

$filename = 'register_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $course['course_code']);
if ($fromDate || $toDate) $filename .= '_' . ($fromDate ?: 'start') . '_to_' . ($toDate ?: 'end');
$filename .= '_' . date('Y-m-d') . '.pdf';

$dompdf->stream($filename, ['Attachment' => true]);
exit;