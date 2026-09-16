<?php
require_once __DIR__ . '/../config/db.php';
require_role('student');

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die("PDF library not installed. Run: composer require dompdf/dompdf");
}
require $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

$student_id = (int)$_SESSION['user_id'];
$course_id  = (int)($_GET['course_id'] ?? 0);

if (!$course_id) die("No course selected.");

// ---------- Verify the student is enrolled ----------
$stmt = $pdo->prepare("
    SELECT c.course_code, c.course_name, u.full_name AS lecturer_name
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    LEFT JOIN users u ON u.id = c.lecturer_id
    WHERE e.student_id = ? AND e.course_id = ?
");
$stmt->execute([$student_id, $course_id]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(403);
    die("You are not enrolled in this course.");
}

// ---------- Load the student ----------
$stmt = $pdo->prepare("SELECT full_name, reg_number, email FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// ---------- Load the session-by-session record ----------
$stmt = $pdo->prepare("
    SELECT
        s.id            AS session_id,
        s.session_date,
        s.start_time,
        s.is_active,
        a.status,
        a.marked_at,
        a.distance_m
    FROM sessions s
    LEFT JOIN attendance a
           ON a.session_id = s.id
          AND a.student_id = ?
    WHERE s.course_id = ?
    ORDER BY s.session_date ASC, s.start_time ASC
");
$stmt->execute([$student_id, $course_id]);
$sessions = $stmt->fetchAll();

// ---------- Compute summary ----------
$present = 0;
$late    = 0;
$absent  = 0;
$total   = count($sessions);

foreach ($sessions as $s) {
    if ($s['status'] === 'present')      $present++;
    elseif ($s['status'] === 'late')     $late++;
    elseif ($s['status'] === 'absent')   $absent++;
    else                                  $absent++; // no record = absent
}

$attended = $present + $late;
$pct      = $total > 0 ? round($attended / $total * 100, 1) : 0;

// ---------- Institution / branding ----------
$brand       = app_settings($pdo);
$instName    = $brand['institution_name']    ?? ($brand['system_name'] ?? 'Institution');
$instAddress = $brand['institution_address'] ?? '';
$instPhone   = $brand['institution_phone']   ?? '';
$instEmail   = $brand['institution_email']   ?? '';
$instWebsite = $brand['institution_website'] ?? '';
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

// ---------- Helpers ----------
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- Build session rows ----------
$rowsHtml = '';
$i = 0;

foreach ($sessions as $s) {
    $i++;
    $date = date('d M Y', strtotime($s['session_date']));

    // Determine the status: prefer the DB record, else "Absent" (no record)
    if ($s['status'] === 'present') {
        $statusLabel = 'Present';
        $statusClass = 'status-present';
    } elseif ($s['status'] === 'late') {
        $statusLabel = 'Late';
        $statusClass = 'status-late';
    } elseif ($s['status'] === 'absent') {
        $statusLabel = 'Absent';
        $statusClass = 'status-absent';
    } else {
        $statusLabel = 'Absent';
        $statusClass = 'status-absent';
    }

    $time = $s['marked_at'] ? date('H:i', strtotime($s['marked_at'])) : '—';

    // Notes column: distance if present, "waiting" if the session is live and unmarked
    $notes = '';
    if ($s['distance_m'] !== null) {
        $notes = (int)$s['distance_m'] . ' m';
    } elseif ($s['is_active']) {
        $notes = 'Session live';
    }

    $rowsHtml .= '
        <tr>
            <td class="c">' . $i . '</td>
            <td>' . e($date) . '</td>
            <td class="c ' . $statusClass . '">' . e($statusLabel) . '</td>
            <td class="c mono">' . e($time) . '</td>
            <td class="c mono">' . e($notes ?: '—') . '</td>
        </tr>';
}

if (!$sessions) {
    $rowsHtml = '<tr><td colspan="5" class="c empty">No sessions have been held for this course yet.</td></tr>';
}

// ---------- Percentage color ----------
$pctColor = $pct >= 75 ? '#059669' : ($pct >= 50 ? '#d97706' : '#dc2626');

$generatedAt = date('d M Y \a\t H:i');

// ---------- HTML ----------
$html = '
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    @page { margin: 30px 40px 50px 40px; }

    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10pt;
        color: #1e293b;
        line-height: 1.4;
    }

    /* Letterhead */
    .letterhead {
        width: 100%;
        border-bottom: 3px double #1e293b;
        padding-bottom: 12px;
        margin-bottom: 20px;
    }
    .letterhead table { width: 100%; border-collapse: collapse; }
    .letterhead td { vertical-align: middle; padding: 0; }
    .logo-cell { width: 90px; }
    .logo-cell img { width: 80px; height: 80px; object-fit: contain; }
    .logo-placeholder {
        width: 80px; height: 80px; line-height: 80px;
        text-align: center; background: #1a3ff0; color: #fff;
        font-size: 36pt; font-weight: bold;
        border-radius: 8px;
    }
    .name-cell h1 {
        font-size: 18pt;
        margin: 0 0 4px 0;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #0f172a;
    }
    .name-cell .contact { font-size: 9pt; color: #475569; margin: 0; }
    .name-cell .contact span { margin-right: 12px; }

    /* Title */
    .report-title {
        text-align: center;
        margin: 20px 0 4px 0;
        font-size: 15pt;
        font-weight: bold;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #0f172a;
    }
    .report-subtitle {
        text-align: center;
        font-size: 10pt;
        color: #64748b;
        margin-bottom: 20px;
    }

    /* Meta grid */
    .meta {
        width: 100%;
        margin-bottom: 16px;
        border-collapse: collapse;
    }
    .meta td {
        padding: 5px 8px;
        font-size: 9.5pt;
        vertical-align: top;
    }
    .meta .label { color: #64748b; width: 110px; }
    .meta .value { color: #0f172a; font-weight: bold; }

    /* Summary box */
    .summary-box {
        background: #f1f5f9;
        border-left: 4px solid #1a3ff0;
        padding: 12px 16px;
        margin-bottom: 20px;
    }
    .summary-box table { width: 100%; border-collapse: collapse; }
    .summary-box td { padding: 4px 8px; font-size: 10pt; vertical-align: middle; }
    .summary-box .num { font-size: 14pt; font-weight: bold; text-align: center; }

    /* Sessions table */
    table.data {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    table.data thead th {
        background: #1e293b;
        color: #fff;
        padding: 7px 6px;
        font-size: 9pt;
        text-align: left;
        border: 1px solid #1e293b;
    }
    table.data thead th.c { text-align: center; }
    table.data tbody td {
        padding: 6px;
        border: 1px solid #cbd5e1;
        font-size: 9pt;
    }
    table.data tbody tr:nth-child(even) { background: #f8fafc; }
    table.data .c { text-align: center; }
    table.data .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8.5pt; }
    table.data .empty { color: #94a3b8; padding: 24px; font-style: italic; }

    .status-present { color: #059669; font-weight: bold; }
    .status-late    { color: #d97706; font-weight: bold; }
    .status-absent  { color: #dc2626; font-weight: bold; }

    /* Signatures */
    .signatures {
        width: 100%;
        margin-top: 30px;
        border-collapse: collapse;
    }
    .signatures td {
        width: 50%;
        padding: 0 20px 0 0;
        vertical-align: top;
    }
    .sig-line {
        border-bottom: 1px solid #475569;
        height: 32px;
        margin-bottom: 4px;
    }
    .sig-label { font-size: 9pt; color: #64748b; }
    .stamp-box {
        margin-top: 20px;
        border: 1px dashed #94a3b8;
        height: 80px;
        border-radius: 8px;
        text-align: center;
        line-height: 80px;
        color: #94a3b8;
        font-size: 9pt;
    }

    /* Footer */
    .footer {
        position: fixed;
        bottom: -30px;
        left: 0;
        right: 0;
        text-align: center;
        font-size: 8pt;
        color: #94a3b8;
        border-top: 1px solid #e2e8f0;
        padding-top: 6px;
    }
</style>
</head>
<body>

<!-- LETTERHEAD -->
<div class="letterhead">
    <table>
        <tr>
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
        </tr>
    </table>
</div>

<!-- TITLE -->
<div class="report-title">My Attendance Record</div>
<div class="report-subtitle">' . e($course['course_code']) . ' · Generated by ' . e($sysName) . '</div>

<!-- META -->
<table class="meta">
    <tr>
        <td class="label">Student</td>
        <td class="value">' . e($student['full_name']) . '</td>
        <td class="label">Reg Number</td>
        <td class="value mono">' . e($student['reg_number']) . '</td>
    </tr>
    <tr>
        <td class="label">Course</td>
        <td class="value">' . e($course['course_code'] . ' — ' . $course['course_name']) . '</td>
        <td class="label">Lecturer</td>
        <td class="value">' . e($course['lecturer_name'] ?? '—') . '</td>
    </tr>
    <tr>
        <td class="label">Generated</td>
        <td class="value">' . e($generatedAt) . '</td>
        <td class="label"></td>
        <td class="value"></td>
    </tr>
</table>

<!-- SUMMARY -->
<div class="summary-box">
    <table>
        <tr>
            <td style="width:22%; text-align:center;">
                <div class="num" style="color:#059669;">' . $present . '</div>
                <div style="font-size:9pt; color:#64748b;">Present</div>
            </td>
            <td style="width:22%; text-align:center;">
                <div class="num" style="color:#d97706;">' . $late . '</div>
                <div style="font-size:9pt; color:#64748b;">Late</div>
            </td>
            <td style="width:22%; text-align:center;">
                <div class="num" style="color:#dc2626;">' . $absent . '</div>
                <div style="font-size:9pt; color:#64748b;">Absent</div>
            </td>
            <td style="width:22%; text-align:center;">
                <div class="num">' . $total . '</div>
                <div style="font-size:9pt; color:#64748b;">Sessions</div>
            </td>
            <td style="width:22%; text-align:center;">
                <div class="num" style="color:' . $pctColor . ';">' . $pct . '%</div>
                <div style="font-size:9pt; color:#64748b;">Attendance</div>
            </td>
        </tr>
    </table>
</div>

<!-- SESSIONS TABLE -->
<table class="data">
    <thead>
        <tr>
            <th class="c" style="width: 30px;">#</th>
            <th style="width: 130px;">Session date</th>
            <th class="c" style="width: 100px;">Status</th>
            <th class="c" style="width: 90px;">Time</th>
            <th class="c" style="width: 90px;">Distance</th>
        </tr>
    </thead>
    <tbody>
        ' . $rowsHtml . '
    </tbody>
</table>

<!-- SIGNATURES -->
<table class="signatures">
    <tr>
        <td>
            <div class="sig-line"></div>
            <div class="sig-label">Student (Name &amp; Signature)</div>
        </td>
        <td>
            <div class="sig-line"></div>
            <div class="sig-label">' . e($signerLabel) . ' (Name &amp; Signature)</div>
        </td>
    </tr>
</table>

<div class="stamp-box">Official Stamp</div>

<!-- FOOTER -->
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
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'my_attendance_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $course['course_code']) . '_' . date('Y-m-d') . '.pdf';

$dompdf->stream($filename, ['Attachment' => true]);
exit;