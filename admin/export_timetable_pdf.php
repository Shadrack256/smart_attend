<?php
require_once __DIR__ . '/../config/db.php';

// ---------- Authentication ----------
// Allow admin, lecturer, and student — anything else redirects to login
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'lecturer', 'student'], true)) {
    header("Location: ../auth/login.php");
    exit;
}

$role       = $_SESSION['role'];
$user_id    = (int)$_SESSION['user_id'];
$course_id  = (int)($_GET['course_id'] ?? 0);

// ==================================================================
// ROLE-SPECIFIC DATA GATHERING
// ==================================================================
$slots       = [];
$course      = null;
$student     = null;
$reportKind  = 'course';    // 'course' | 'week'

if ($role === 'student') {
    // ---------- STUDENT: entire week across all enrolled courses ----------
    $reportKind = 'week';

    // Student info
    $stmt = $pdo->prepare("SELECT full_name, reg_number FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    if (!$student) die("Student not found.");

    // All timetable slots for the student's enrolled courses
    $stmt = $pdo->prepare("
        SELECT t.*, c.course_code, c.course_name
        FROM timetables t
        JOIN courses c ON c.id = t.course_id
        JOIN enrollments e ON e.course_id = c.id
        WHERE e.student_id = ?
        ORDER BY t.day_of_week, t.start_time
    ");
    $stmt->execute([$user_id]);
    $slots = $stmt->fetchAll();

} else {
    // ---------- ADMIN or LECTURER: single course ----------
    $reportKind = 'course';

    if (!$course_id) die("No course selected.");

    // Load course + lecturer
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name AS lecturer_name
        FROM courses c
        LEFT JOIN users u ON u.id = c.lecturer_id
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    if (!$course) die("Course not found.");

    // Ownership check for lecturers
    if ($role === 'lecturer') {
        $stmt = $pdo->prepare("SELECT 1 FROM courses WHERE id = ? AND lecturer_id = ?");
        $stmt->execute([$course_id, $user_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            die("You are not assigned to this course.");
        }
    }

    // Load slots for the course
    $stmt = $pdo->prepare("
        SELECT * FROM timetables
        WHERE course_id = ?
        ORDER BY day_of_week, start_time
    ");
    $stmt->execute([$course_id]);
    $slots = $stmt->fetchAll();
}

// ==================================================================
// INSTITUTION / BRANDING
// ==================================================================
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

// ==================================================================
// HELPERS
// ==================================================================
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$dayNames = [1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 7=>'Sunday'];

$byDay = [];
for ($d = 1; $d <= 7; $d++) $byDay[$d] = [];
foreach ($slots as $s) {
    $byDay[(int)$s['day_of_week']][] = $s;
}

// ==================================================================
// BUILD THE TABLE ROWS
// ==================================================================
$rowsHtml = '';
$i = 0;
foreach ($slots as $s) {
    $i++;
    $day   = $dayNames[(int)$s['day_of_week']] ?? '—';
    $start = substr($s['start_time'], 0, 5);
    $end   = substr($s['end_time'],   0, 5);

    // For student reports, show course code in a separate column
    $courseCell = '';
    if ($reportKind === 'week') {
        $courseCell = '<td class="mono">' . e($s['course_code'] ?? '') . '</td>';
    }

    $rowsHtml .= '
        <tr>
            <td class="c">' . $i . '</td>
            <td>' . e($day) . '</td>
            <td class="c mono">' . e($start) . ' – ' . e($end) . '</td>'
            . $courseCell .
            '<td>' . e($s['room'] ?? '—') . '</td>
            <td>' . e($s['notes'] ?? '') . '</td>
        </tr>';
}

if (!$slots) {
    $colspan = $reportKind === 'week' ? 6 : 5;
    $rowsHtml = '<tr><td colspan="' . $colspan . '" class="c empty">No classes have been scheduled yet.</td></tr>';
}

// ==================================================================
// BUILD THE WEEKLY GRID
// ==================================================================
$gridHtml = '<table class="weekgrid"><tr>';
foreach ($dayNames as $dayNum => $dayLabel) {
    $gridHtml .= '<th>' . e($dayLabel) . '</th>';
}
$gridHtml .= '</tr><tr>';

foreach ($dayNames as $dayNum => $dayLabel) {
    $gridHtml .= '<td>';
    if (empty($byDay[$dayNum])) {
        $gridHtml .= '<div class="empty-cell">—</div>';
    } else {
        foreach ($byDay[$dayNum] as $s) {
            $start = substr($s['start_time'], 0, 5);
            $end   = substr($s['end_time'],   0, 5);

            $gridHtml .= '<div class="cell">';
            $gridHtml .= '<div class="cell-time">' . e($start) . '–' . e($end) . '</div>';
            if ($reportKind === 'week' && !empty($s['course_code'])) {
                $gridHtml .= '<div class="cell-course">' . e($s['course_code']) . '</div>';
            }
            if (!empty($s['room'])) {
                $gridHtml .= '<div class="cell-room">' . e($s['room']) . '</div>';
            }
            if (!empty($s['notes'])) {
                $gridHtml .= '<div class="cell-notes">' . e($s['notes']) . '</div>';
            }
            $gridHtml .= '</div>';
        }
    }
    $gridHtml .= '</td>';
}
$gridHtml .= '</tr></table>';

$slotCount   = count($slots);
$generatedAt = date('d M Y \a\t H:i');

// ==================================================================
// PAGE TITLE + META BLOCK — depends on the role
// ==================================================================
if ($reportKind === 'week') {
    $pageTitle    = 'Weekly Timetable';
    $pageSubtitle = 'Generated for ' . e($student['full_name']);

    $metaBlock = '
        <tr>
            <td class="label">Student</td>
            <td class="value">' . e($student['full_name']) . '</td>
            <td class="label">Reg Number</td>
            <td class="value mono">' . e($student['reg_number']) . '</td>
        </tr>
        <tr>
            <td class="label">Slots</td>
            <td class="value">' . $slotCount . ' per week</td>
            <td class="label">Generated</td>
            <td class="value">' . e($generatedAt) . '</td>
        </tr>
    ';
} else {
    $pageTitle    = 'Weekly Timetable';
    $pageSubtitle = 'Course schedule';

    $metaBlock = '
        <tr>
            <td class="label">Course</td>
            <td class="value">' . e($course['course_code'] . ' — ' . $course['course_name']) . '</td>
            <td class="label">Slots</td>
            <td class="value">' . $slotCount . ' per week</td>
        </tr>
        <tr>
            <td class="label">Lecturer</td>
            <td class="value">' . e($course['lecturer_name'] ?? '—') . '</td>
            <td class="label">Required</td>
            <td class="value">' . (int)($course['required_attendance'] ?? 75) . '% attendance</td>
        </tr>
        <tr>
            <td class="label">Generated</td>
            <td class="value">' . e($generatedAt) . '</td>
            <td class="label"></td>
            <td class="value"></td>
        </tr>
    ';
}

// ==================================================================
// LOAD DOMPDF
// ==================================================================
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die("Dompdf is not installed. Run: composer require dompdf/dompdf");
}
require $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// ==================================================================
// HTML
// ==================================================================
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

    .meta {
        width: 100%;
        margin-bottom: 16px;
        border-collapse: collapse;
    }
    .meta td {
        padding: 4px 8px;
        font-size: 9.5pt;
        vertical-align: top;
    }
    .meta .label { color: #64748b; width: 110px; }
    .meta .value { color: #0f172a; font-weight: bold; }
    .meta .mono { font-family: DejaVu Sans Mono, monospace; font-size: 9pt; }

    h2.section {
        font-size: 11pt;
        color: #0f172a;
        margin: 22px 0 8px 0;
        padding-bottom: 4px;
        border-bottom: 1px solid #cbd5e1;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    /* Detail table */
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

    /* Weekly grid */
    table.weekgrid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        table-layout: fixed;
    }
    table.weekgrid th {
        background: #1e293b;
        color: #fff;
        padding: 6px 3px;
        font-size: 8pt;
        font-weight: normal;
        text-align: center;
        border: 1px solid #1e293b;
    }
    table.weekgrid td {
        border: 1px solid #cbd5e1;
        padding: 4px;
        vertical-align: top;
        height: 70px;
        background: #ffffff;
    }
    table.weekgrid .empty-cell {
        color: #cbd5e1;
        text-align: center;
        padding-top: 18px;
        font-size: 9pt;
    }
    table.weekgrid .cell {
        background: #eef4ff;
        border-left: 3px solid #1a3ff0;
        padding: 4px 5px;
        margin-bottom: 3px;
        border-radius: 3px;
    }
    table.weekgrid .cell-time {
        font-size: 7.5pt;
        font-weight: bold;
        color: #0f172a;
        line-height: 1.2;
    }
    table.weekgrid .cell-course {
        font-size: 7pt;
        font-weight: bold;
        color: #1a3ff0;
        font-family: DejaVu Sans Mono, monospace;
        margin-top: 2px;
    }
    table.weekgrid .cell-room {
        font-size: 7pt;
        color: #475569;
        margin-top: 2px;
    }
    table.weekgrid .cell-notes {
        font-size: 6.5pt;
        color: #64748b;
        font-style: italic;
        margin-top: 2px;
    }

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

<div class="report-title">' . e($pageTitle) . '</div>
<div class="report-subtitle">' . e($pageSubtitle) . ' · Generated by ' . e($sysName) . '</div>

<table class="meta">
    ' . $metaBlock . '
</table>

<h2 class="section">Schedule detail</h2>

<table class="data">
    <thead>
        <tr>
            <th class="c" style="width: 30px;">#</th>
            <th style="width: 100px;">Day</th>
            <th class="c" style="width: 130px;">Time</th>'
            . ($reportKind === 'week' ? '<th style="width: 90px;">Course</th>' : '') .
            '<th style="width: 90px;">Room</th>
            <th>Notes</th>
        </tr>
    </thead>
    <tbody>
        ' . $rowsHtml . '
    </tbody>
</table>

<h2 class="section">Weekly grid</h2>

' . $gridHtml . '

<table class="signatures">
    <tr>
        <td>
            <div class="sig-line"></div>
            <div class="sig-label">' . ($reportKind === 'week' ? 'Student (Name &amp; Signature)' : 'Prepared by (Name &amp; Signature)') . '</div>
        </td>
        <td>
            <div class="sig-line"></div>
            <div class="sig-label">' . e($signerLabel) . ' (Name &amp; Signature)</div>
        </td>
    </tr>
</table>

<div class="stamp-box">Official Stamp</div>

<div class="footer">
    ' . e($instName) . ' · ' . e($sysName) . ' · ' . e($generatedAt) . '
</div>

</body></html>
';

// ==================================================================
// RENDER
// ==================================================================
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ---------- Filename ----------
if ($reportKind === 'week') {
    $safeReg = preg_replace('/[^A-Za-z0-9_-]/', '_', $student['reg_number'] ?? 'student');
    $filename = 'my_week_timetable_' . $safeReg . '_' . date('Y-m-d') . '.pdf';
} else {
    $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $course['course_code']);
    $filename = 'timetable_' . $safeCode . '_' . date('Y-m-d') . '.pdf';
}

$dompdf->stream($filename, ['Attachment' => true]);
exit;