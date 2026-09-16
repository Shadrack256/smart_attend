<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');

// Autoload Dompdf
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    die("Dompdf is not installed. Run: composer require dompdf/dompdf");
}
require $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// ---------- Inputs ----------
$course_id = (int)($_GET['course_id'] ?? 0);
$filter    = $_GET['filter'] ?? 'all';

$fromDate = trim($_GET['from'] ?? '');
$toDate   = trim($_GET['to']   ?? '');
$dateRe   = '/^\d{4}-\d{2}-\d{2}$/';
if ($fromDate !== '' && !preg_match($dateRe, $fromDate)) $fromDate = '';
if ($toDate   !== '' && !preg_match($dateRe, $toDate))   $toDate   = '';

if (!$course_id) die("No course selected");

// ---------- Course + lecturer + required threshold ----------
$stmt = $pdo->prepare("
    SELECT c.course_code, c.course_name, c.required_attendance, u.full_name AS lecturer_name
    FROM courses c
    LEFT JOIN users u ON u.id = c.lecturer_id
    WHERE c.id = ?
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

// ---------- Students + attendance ----------
$sql = "
    SELECT
        u.full_name,
        u.reg_number,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN a.status = 'late'    THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN a.status = 'absent'  THEN 1 ELSE 0 END) AS absent_count,
        COUNT(DISTINCT s.id) AS total_sessions,
        SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) AS attended_count,
        MIN(s.session_date) AS first_date,
        MAX(s.session_date) AS last_date
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
    GROUP BY u.id, u.full_name, u.reg_number
    ORDER BY u.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Threshold filter
if ($threshold !== null) {
    $rows = array_values(array_filter($rows, function ($r) use ($threshold) {
        $total = (int)$r['total_sessions'];
        if ($total === 0) return false;
        return ((int)$r['attended_count'] / $total) * 100 < $threshold;
    }));
}

// Total sessions in the same range
$sql = "SELECT COUNT(*) FROM sessions WHERE course_id = ?";
$params = [$course_id];
if ($fromDate !== '') { $sql .= " AND session_date >= ?"; $params[] = $fromDate; }
if ($toDate   !== '') { $sql .= " AND session_date <= ?"; $params[] = $toDate; }
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$totalSessions = (int)$stmt->fetchColumn();

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

// ---------- Derived labels ----------
$dateRange = '—';
if ($rows) {
    $first = min(array_column($rows, 'first_date'));
    $last  = max(array_column($rows, 'last_date'));
    if ($first && $last) {
        $dateRange = date('d M Y', strtotime($first)) . ' – ' . date('d M Y', strtotime($last));
    }
}
$generatedAt = date('d M Y \a\t H:i');

$filterLabel = 'All students';
if ($threshold !== null) {
    $filterLabel = "Below {$threshold}%";
    if ($filter === 'below_course') $filterLabel .= " (course requirement)";
}
if ($fromDate || $toDate) {
    $rangeParts = [];
    if ($fromDate) $rangeParts[] = 'from ' . date('d M Y', strtotime($fromDate));
    if ($toDate)   $rangeParts[] = 'to '   . date('d M Y', strtotime($toDate));
    $filterLabel .= ' · ' . implode(' ', $rangeParts);
}

// ---------- Helpers ----------
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- Rows ----------
$rowsHtml = '';
$i = 0;
foreach ($rows as $r) {
    $i++;
    $total    = (int)$r['total_sessions'];
    $present  = (int)$r['present_count'];
    $late     = (int)$r['late_count'];
    $absent   = (int)$r['absent_count'];
    $attended = (int)$r['attended_count'];
    $pct      = $total > 0 ? round($attended / $total * 100, 1) : 0;

    // Color relative to course requirement
    $warnBand = max(0, $courseRequired - 10);
    if ($pct >= $courseRequired) {
        $pctColor = '#059669';
    } elseif ($pct >= $warnBand) {
        $pctColor = '#d97706';
    } else {
        $pctColor = '#dc2626';
    }

    $rowsHtml .= '
        <tr>
            <td class="c">' . $i . '</td>
            <td>' . e($r['full_name']) . '</td>
            <td class="mono">' . e($r['reg_number']) . '</td>
            <td class="c">' . $present . '</td>
            <td class="c">' . $late . '</td>
            <td class="c">' . $absent . '</td>
            <td class="c">' . $total . '</td>
            <td class="c b" style="color:' . $pctColor . '">' . $pct . '%</td>
        </tr>';
}

if (!$rows) {
    $rowsHtml = '<tr><td colspan="8" class="c empty">No records match the selected criteria.</td></tr>';
}

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
    table.data .b { font-weight: bold; }
    table.data .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8.5pt; }
    table.data .empty { color: #94a3b8; padding: 24px; font-style: italic; }

    .summary {
        background: #f1f5f9;
        border-left: 4px solid #1a3ff0;
        padding: 10px 14px;
        margin-bottom: 24px;
        font-size: 9.5pt;
    }
    .summary b { color: #0f172a; }

    .signatures {
        width: 100%;
        margin-top: 40px;
        border-collapse: collapse;
    }
    .signatures td {
        width: 50%;
        padding: 0 20px 0 0;
        vertical-align: top;
    }
    .sig-line {
        border-bottom: 1px solid #475569;
        height: 36px;
        margin-bottom: 4px;
    }
    .sig-label { font-size: 9pt; color: #64748b; }
    .stamp-box {
        margin-top: 24px;
        border: 1px dashed #94a3b8;
        height: 80px;
        border-radius: 8px;
        text-align: center;
        line-height: 80px;
        color: #94a3b8;
        font-size: 9pt;
    }

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

<div class="report-title">' . e($reportTitle) . '</div>
<div class="report-subtitle">Generated by ' . e($sysName) . '</div>

<table class="meta">
    <tr>
        <td class="label">Course</td>
        <td class="value">' . e($course['course_code'] . ' — ' . $course['course_name']) . '</td>
        <td class="label">Sessions</td>
        <td class="value">' . $totalSessions . '</td>
    </tr>
    <tr>
        <td class="label">Lecturer</td>
        <td class="value">' . e($course['lecturer_name'] ?? '—') . '</td>
        <td class="label">Required</td>
        <td class="value">' . $courseRequired . '%</td>
    </tr>
    <tr>
        <td class="label">Students</td>
        <td class="value">' . count($rows) . '</td>
        <td class="label">Filter</td>
        <td class="value">' . e($filterLabel) . '</td>
    </tr>
    <tr>
        <td class="label">Period</td>
        <td class="value">' . e($dateRange) . '</td>
        <td class="label">Generated</td>
        <td class="value">' . e($generatedAt) . '</td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th class="c" style="width: 30px;">#</th>
            <th>Student</th>
            <th style="width: 110px;">Reg No.</th>
            <th class="c" style="width: 40px;">P</th>
            <th class="c" style="width: 40px;">L</th>
            <th class="c" style="width: 40px;">A</th>
            <th class="c" style="width: 45px;">Total</th>
            <th class="c" style="width: 60px;">Att. %</th>
        </tr>
    </thead>
    <tbody>
        ' . $rowsHtml . '
    </tbody>
</table>

<div class="summary">
    <b>Legend:</b> &nbsp;
    P = Present &nbsp;·&nbsp;
    L = Late &nbsp;·&nbsp;
    A = Absent &nbsp;·&nbsp;
    Att. % = (Present + Late) ÷ Total sessions. &nbsp;·&nbsp;
    This course requires <b>' . $courseRequired . '%</b>.
</div>

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

$filename = 'attendance_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $course['course_code']);
if ($threshold !== null) $filename .= '_below' . $threshold;
if ($fromDate || $toDate) $filename .= '_' . ($fromDate ?: 'start') . '_to_' . ($toDate ?: 'end');
$filename .= '_' . date('Y-m-d') . '.pdf';

$dompdf->stream($filename, ['Attachment' => true]);
exit;