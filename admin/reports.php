<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');

// ---------- Inputs ----------
$courses   = $pdo->query("SELECT id, course_code, course_name, required_attendance FROM courses ORDER BY course_code")->fetchAll();
$course_id = (int)($_GET['course_id'] ?? 0);

$filter = $_GET['filter'] ?? 'all';

// Course-specific required threshold (used for filtering and coloring)
$courseRequired = 75;
if ($course_id) {
    $stmt = $pdo->prepare("SELECT required_attendance FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $courseRequired = (int)($stmt->fetchColumn() ?: 75);
}

// Interpret the filter
$threshold = null;
if ($filter === 'below_course')     $threshold = $courseRequired;
elseif ($filter === 'below_50')     $threshold = 50;
elseif ($filter === 'below_25')     $threshold = 25;

$fromDate = trim($_GET['from'] ?? '');
$toDate   = trim($_GET['to']   ?? '');
$dateRe   = '/^\d{4}-\d{2}-\d{2}$/';
if ($fromDate !== '' && !preg_match($dateRe, $fromDate)) $fromDate = '';
if ($toDate   !== '' && !preg_match($dateRe, $toDate))   $toDate   = '';

// ---------- Load report rows ----------
$rows = [];
$sessionInfo = ['count' => 0, 'first' => null, 'last' => null];

if ($course_id) {
    $sql = "
        SELECT
            u.id,
            u.full_name,
            u.reg_number,
            u.email,
            COUNT(DISTINCT s.id) AS total_sessions,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'late'    THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN a.status = 'absent'  THEN 1 ELSE 0 END) AS absent_count,
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

    // Apply attendance filter
    if ($threshold !== null) {
        $rows = array_values(array_filter($rows, function ($r) use ($threshold) {
            $t = (int)$r['total_sessions'];
            if ($t === 0) return false;
            return ((int)$r['attended_count'] / $t) * 100 < $threshold;
        }));
    }

    // Session count + period
    $sql = "SELECT COUNT(*) AS n, MIN(session_date) AS first_d, MAX(session_date) AS last_d
            FROM sessions WHERE course_id = ?";
    $params = [$course_id];
    if ($fromDate !== '') { $sql .= " AND session_date >= ?"; $params[] = $fromDate; }
    if ($toDate   !== '') { $sql .= " AND session_date <= ?"; $params[] = $toDate; }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $info = $stmt->fetch();

    $sessionInfo = [
        'count' => $info ? (int)$info['n']  : 0,
        'first' => $info ? $info['first_d'] : null,
        'last'  => $info ? $info['last_d']  : null,
    ];
}

$pageTitle = 'Reports';
require __DIR__ . '/partials/admin_header.php';

$title = 'Attendance Reports';
$subtitle = 'Per-student attendance and register exports.';
require __DIR__ . '/partials/page_header.php';

require_once __DIR__ . '/../includes/ui.php';
flash_banner();

// Preserve filters in export URLs
$exportQS = http_build_query(array_filter([
    'course_id' => $course_id,
    'filter'    => $filter,
    'from'      => $fromDate,
    'to'        => $toDate,
]));
?>

<!-- ============= FILTERS ============= -->
<form method="GET" class="mb-6 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">

        <div class="lg:col-span-2">
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Course</label>
            <select name="course_id" onchange="this.form.submit()"
                    class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">— Select a course —</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $course_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['course_code'] . ' — ' . $c['course_name']) ?>
                        (<?= (int)($c['required_attendance'] ?? 75) ?>%)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Show</label>
            <select name="filter"
                    class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="all"          <?= $filter === 'all'          ? 'selected' : '' ?>>All students</option>
                <option value="below_course" <?= $filter === 'below_course' ? 'selected' : '' ?>>
                    Below <?= $course_id ? $courseRequired . '%' : 'course requirement' ?>
                </option>
                <option value="below_50"     <?= $filter === 'above_90'     ? 'selected' : '' ?>>Above 90%</option>
                <option value="below_50"     <?= $filter === 'above_75'     ? 'selected' : '' ?>>above 75%</option>
                <option value="below_50"     <?= $filter === 'below_75'     ? 'selected' : '' ?>>Below 75%</option>
                <option value="below_50"     <?= $filter === 'above_50'     ? 'selected' : '' ?>>above 50%</option>
                <option value="below_50"     <?= $filter === 'below_50'     ? 'selected' : '' ?>>Below 50%</option>
                <option value="below_25"     <?= $filter === 'below_25'     ? 'selected' : '' ?>>Below 25%</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">From</label>
            <input type="date" name="from" value="<?= htmlspecialchars($fromDate) ?>"
                   class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">To</label>
            <input type="date" name="to" value="<?= htmlspecialchars($toDate) ?>"
                   class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>

        <div class="flex gap-2 lg:col-span-5">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                <i data-lucide="filter" class="w-4 h-4"></i> Apply
            </button>
            <a href="reports.php"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                Reset
            </a>
        </div>
    </div>

    <?php if ($course_id): ?>
        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
            This course requires
            <strong class="text-slate-700 dark:text-slate-300"><?= $courseRequired ?>%</strong>
            attendance. Colors below reflect this threshold.
        </p>
    <?php endif; ?>
</form>

<!-- ============= EXPORT ACTIONS ============= -->
<?php if ($course_id && $rows): ?>
<div class="flex flex-wrap gap-3 mb-6">

    <a href="export_csv.php?<?= $exportQS ?>"
       class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
        <i data-lucide="download" class="w-4 h-4"></i> Export CSV
    </a>

    <a href="export_pdf.php?<?= $exportQS ?>" target="_blank"
       class="inline-flex items-center gap-2 rounded-lg bg-rose-600 text-white px-4 py-2.5 text-sm font-medium hover:bg-rose-700 shadow-sm transition">
        <i data-lucide="file-text" class="w-4 h-4"></i> Export PDF (summary)
    </a>

    <a href="export_register_pdf.php?<?= $exportQS ?>" target="_blank"
       class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 text-white px-4 py-2.5 text-sm font-medium hover:bg-emerald-700 shadow-sm transition">
        <i data-lucide="table" class="w-4 h-4"></i> Export Register
    </a>

    <?php if ($threshold !== null): ?>
    <form method="POST" action="notify_low_attendance.php" class="inline"
          onsubmit="return confirm('Send warning email to all <?= count($rows) ?> student(s) listed?');">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
        <button class="inline-flex items-center gap-2 rounded-lg bg-amber-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-amber-700 transition">
            <i data-lucide="mail" class="w-4 h-4"></i>
            Notify <?= count($rows) ?> student(s)
        </button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ============= RESULTS ============= -->
<?php if ($course_id && $rows): ?>

<div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
    <div>
        <strong class="text-slate-900 dark:text-white"><?= count($rows) ?></strong> student(s)
        <?php if ($threshold !== null): ?>
            with attendance below
            <strong class="text-slate-700 dark:text-slate-300"><?= $threshold ?>%</strong>
            <?php if ($filter === 'below_course'): ?>
                (this course's requirement)
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div>·</div>
    <div>
        <strong class="text-slate-900 dark:text-white"><?= (int)$sessionInfo['count'] ?></strong> session(s) in range
        <?php if ($sessionInfo['first'] && $sessionInfo['last']): ?>
            (<?= date('d M Y', strtotime($sessionInfo['first'])) ?> – <?= date('d M Y', strtotime($sessionInfo['last'])) ?>)
        <?php endif; ?>
    </div>
</div>

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 font-semibold">Student</th>
                    <th class="px-5 py-3 font-semibold">Reg no.</th>
                    <th class="px-5 py-3 font-semibold text-center">Present</th>
                    <th class="px-5 py-3 font-semibold text-center">Late</th>
                    <th class="px-5 py-3 font-semibold text-center">Absent</th>
                    <th class="px-5 py-3 font-semibold text-center">Total</th>
                    <th class="px-5 py-3 font-semibold">Attendance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php
            $courseThreshold = $courseRequired;
            $warnBand = max(0, $courseThreshold - 10);   // within 10 points below = amber
            foreach ($rows as $r):
                $total    = (int)$r['total_sessions'];
                $present  = (int)$r['present_count'];
                $late     = (int)$r['late_count'];
                $absent   = (int)$r['absent_count'];
                $attended = (int)$r['attended_count'];

                $pct = $total > 0 ? round($attended / $total * 100, 1) : 0;

                // Color relative to the course's own requirement
                if ($pct >= $courseThreshold) {
                    $bar = 'bg-emerald-500';
                    $txt = 'text-emerald-700 dark:text-emerald-400';
                } elseif ($pct >= $warnBand) {
                    $bar = 'bg-amber-500';
                    $txt = 'text-amber-700 dark:text-amber-400';
                } else {
                    $bar = 'bg-rose-500';
                    $txt = 'text-rose-700 dark:text-rose-400';
                }

                $absentCls = $absent === 0
                    ? 'text-slate-400'
                    : ($absent >= 3 ? 'text-rose-700 dark:text-rose-400 font-semibold' : 'text-amber-700 dark:text-amber-400 font-medium');
            ?>
                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                    <td class="px-5 py-3 font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($r['full_name']) ?></td>
                    <td class="px-5 py-3 text-slate-600 dark:text-slate-400 font-mono text-xs"><?= htmlspecialchars($r['reg_number']) ?></td>
                    <td class="px-5 py-3 text-center tabular-nums text-emerald-700 dark:text-emerald-400"><?= $present ?></td>
                    <td class="px-5 py-3 text-center tabular-nums text-amber-700 dark:text-amber-400"><?= $late ?></td>
                    <td class="px-5 py-3 text-center tabular-nums <?= $absentCls ?>"><?= $absent ?></td>
                    <td class="px-5 py-3 text-center tabular-nums text-slate-500 dark:text-slate-400"><?= $total ?></td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-32 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full <?= $bar ?>" style="width: <?= $pct ?>%"></div>
                            </div>
                            <span class="text-sm font-semibold tabular-nums <?= $txt ?>"><?= $pct ?>%</span>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($course_id): ?>

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-12 text-center text-slate-400">
        <?php if ($threshold !== null): ?>
            No students match this filter. 🎉
        <?php elseif ($fromDate || $toDate): ?>
            No sessions found in the selected date range.
        <?php else: ?>
            No enrollment data for this course yet.
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-12 text-center text-slate-400">
        Select a course above to see attendance data.
    </div>

<?php endif; ?>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>