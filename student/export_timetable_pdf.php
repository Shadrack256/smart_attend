<?php
require_once __DIR__ . '/../config/db.php';
require_role('student');

// ---------- Autoload Dompdf ----------
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die("Dompdf is not installed. Run: composer require dompdf/dompdf");
}
require $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// ---------- Inputs ----------
$student_id = (int)$_SESSION['user_id'];
$course_id  = (int)($_GET['course_id'] ?? 0);

if (!$course_id) {
    http_response_code(400);
    die("No course selected");
}

// ---------- Verify the student is enrolled in this course ----------
$stmt = $pdo->prepare("
    SELECT c.course_code, c.course_name, c.required_attendance,
           u.full_name AS lecturer_name
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

// ---------- Load the timetable slots ----------
$stmt = $pdo->prepare("
    SELECT day_of_week, start_time, end_time, room, notes
    FROM timetables
    WHERE course_id = ?
    ORDER BY day_of_week, start_time
");
$stmt->execute([$course_id]);
$slots = $stmt->fetchAll();

// Group slots by day
$byDay = [];
for ($d = 1; $d <= 7; $d++) $byDay[$d] = [];
foreach ($slots as $s) {
    $byDay[(int)$s['day_of_week']][] = $s;
}

$dayNames = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday',
];

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

// Embed logo as data URI
$logoData = '';
if ($sysLogo) {
    $p = __DIR__ . '/../uploads/branding/' . $sysLogo;
    if (is_file($p)) {
        $mime = mime_content_type($p) ?: 'image/png';
        $logoData = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p));
    }
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$generatedAt = date('d M Y \a\t H:i');

// ---------- Build the grid ----------
$gridHtml = '';

if (!$slots) {
    $gridHtml = '<div class="empty">No classes are scheduled for this course yet.</div>';
} else {
    foreach ($dayNames as $dayNum => $dayLabel) {
        $daySlots = $byDay[$dayNum];
        if (!$daySlots) continue;   // Skip empty days for a compact document

        $gridHtml .= '<div class="day-block">';
        $gridHtml .= '<div class="day-heading">' . e($dayLabel) . '</div>';

        foreach ($daySlots as $s) {
            $start = substr($s['start_time'], 0, 5);
            $end   = substr($s['end_time'], 0, 5);

            $gridHtml .= '<div class="slot-row">';
            $gridHtml .= '<div class="slot-time">' . e($start) . ' – ' . e($end) . '</div>';
            $gridHtml .= '<div class="slot-details">';

            if ($s['room']) {
                $gridHtml .= '<span class="room"><strong>Room:</strong> ' . e($s['room']) . '</span>';
            }
            if ($s['notes']) {
                $gridHtml .= '<span class="notes">' . e($s['notes']) . '</span>';
            }
            if (!$s['room'] && !$s['notes']) {
                $gridHtml .= '<span class="muted">—</span>';
            }

            $gridHtml .= '</div>';
            $gridHtml .= '</div>';
        }

        $gridHtml .= '</div>';
    }
}

// ---------- Summary calculations ----------
$totalSlots = count($slots);
$totalHours = 0;
foreach ($slots as $s) {
    $totalHours += max(0, (strtotime($s['end_time']) - strtotime($s['start_time'])) / 3600);
}
$totalHours = round($totalHours, 1);

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
        line-height: 1.45;
    }

    /* ---------- LETTERHEAD ---------- */
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

    /* ---------- TITLE ---------- */
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

    /* ---------- META ---------- */
    .meta {
        width: 100%;
        margin-bottom: 18px;
        border-collapse: collapse;
    }
    .meta td {
        padding: 5px 8px;
        font-size: 9.5pt;
        vertical-align: top;
    }
    .meta .label { color: #64748b; width: 110px; }
    .meta .value { color: #0f172a; font-weight: bold; }

    /* ---------- SCHEDULE ---------- */
    .day-block {
        margin-bottom: 14px;
        page-break-inside: avoid;
    }
    .day-heading {
        background: #1e293b;
        color: #fff;
        padding: 6px 12px;
        font-size: 11pt;
        font-weight: bold;
        letter-spacing: 1px;
        border-radius: 4px 4px 0 0;
        text-transform: uppercase;
    }
    .slot-row {
        display: table;
        width: 100%;
        border: 1px solid #cbd5e1;
        border-top: none;
        padding: 8px 12px;
        page-break-inside: avoid;
    }
    .slot-time {
        display: table-cell;
        width: 140px;
        font-family: DejaVu Sans Mono, monospace;
        font-size: 10pt;
        font-weight: bold;
        color: #0f172a;
        vertical-align: middle;
    }
    .slot-details {
        display: table-cell;
        font-size: 9.5pt;
        color: #334155;
        vertical-align: middle;
    }
    .slot-details .room {
        margin-right: 16px;
        color: #334155;
    }
    .slot-details .notes {
        font-style: italic;
        color: #64748b;
    }
    .slot-details .muted { color: #94a3b8; }

    .empty {
        text-align: center;
        padding: 30px;
        color: #94a3b8;
        font-style: italic;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
    }

    /* ---------- SUMMARY ---------- */
    .summary {
        background: #f1f5f9;
        border-left: 4px solid #1a3ff0;
        padding: 10px 14px;
        margin-top: 18px;
        font-size: 9.5pt;
    }
    .summary b { color: #0f172a; }

    /* ---------- SIGNATURES ---------- */
    .signatures {
        width: 100%;
        margin-top: 36px;
        border-collapse: collapse;
        page-break-inside: avoid;
    }
    .signatures td {
        width: 50%;
        padding: 0 20px 0 0;
        vertical-align: top;
    }
    .sig-line {
        border-bottom: 1px solid #475569;
        height: 34px;
        margin-bottom: 4px;
    }
    .sig-label { font-size: 9pt; color: #64748b; }
    .stamp-box {
        margin-top: 22px;
        border: 1px dashed #94a3b8;
        height: 78px;
        border-radius: 8px;
        text-align: center;
        line-height: 78px;
        color: #94a3b8;
        font-size: 9pt;
    }

    /* ---------- FOOTER ---------- */
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
<div class="report-title">Course Timetable</div>
<div class="report-subtitle">Generated by ' . e($sysName) . '</div>

<!-- META -->
<table class="meta">
    <tr>
        <td class="label">Course</td>
        <td class="value">' . e($course['course_code'] . ' — ' . $course['course_name']) . '</td>
        <td class="label">Lecturer</td>
        <td class="value">' . e($course['lecturer_name'] ?? '—') . '</td>
    </tr>
    <tr>
        <td class="label">Classes per week</td>
        <td class="value">' . $totalSlots . '</td>
        <td class="label">Contact hours</td>
        <td class="value">' . $totalHours . ' hours</td>
    </tr>
    <tr>
        <td class="label">Required attendance</td>
        <td class="value">' . (int)$course['required_attendance'] . '%</td>
        <td class="label">Generated</td>
        <td class="value">' . e($generatedAt) . '</td>
    </tr>
</table>

<!-- SCHEDULE -->
' . $gridHtml . '

<!-- SUMMARY -->
<div class="summary">
    <b>Total:</b> ' . $totalSlots . ' scheduled class' . ($totalSlots === 1 ? '' : 'es') . ' per week
    · approximately <b>' . $totalHours . ' contact hour' . ($totalHours == 1 ? '' : 's') . '</b>.
</div>

<!-- SIGNATURES -->
<table class="signatures">
    <tr>
        <td>
            <div class="sig-line"></div>
            <div class="sig-label">Prepared by (Name &amp; Signature)</div>
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

$filename = 'timetable_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $course['course_code']) . '_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;