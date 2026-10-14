<?php
require '../config/db.php';
require_role('student');
require '../includes/ui.php';

$student_id = $_SESSION['user_id'];
$brand = app_settings($pdo);

// ---------- Load current student's photo ----------
$stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$myPhoto = $stmt->fetchColumn();

// ---------- Load courses with required threshold, image, and attendance ----------
$stmt = $pdo->prepare("
    SELECT c.id AS course_id, c.course_code, c.course_name,
           c.required_attendance, c.image,
           COUNT(DISTINCT s.id) AS total_sessions,
           SUM(CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END) AS attended
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    LEFT JOIN sessions s ON s.course_id = c.id
    LEFT JOIN attendance a ON a.session_id = s.id AND a.student_id = e.student_id
    WHERE e.student_id = ?
    GROUP BY c.id, c.course_code, c.course_name, c.required_attendance, c.image
    ORDER BY c.course_code
");
$stmt->execute([$student_id]);
$courses = $stmt->fetchAll();

$pageTitle = 'My Dashboard';
require __DIR__ . '/../includes/head.php';
?>
<div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">

    <!-- ============= HEADER ============= -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8">
        <a href="../profile.php" class="flex items-center gap-4 group">
            <?php if ($myPhoto): ?>
                <img src="../uploads/avatars/<?= htmlspecialchars($myPhoto) ?>"
                     class="w-12 h-12 rounded-full object-cover ring-2 ring-transparent group-hover:ring-brand-500 transition" alt="">
            <?php else: ?>
                <div class="w-12 h-12 rounded-full bg-brand-50 dark:bg-brand-500/15 text-brand-700 dark:text-brand-400 grid place-items-center text-lg font-semibold ring-2 ring-transparent group-hover:ring-brand-500 transition">
                    <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white group-hover:text-brand-700 dark:group-hover:text-brand-400 transition">
                    Hi, <?= htmlspecialchars($_SESSION['name']) ?> 👋
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Your attendance overview · <span class="text-brand-600 dark:text-brand-400">View profile</span>
                </p>
            </div>
        </a>

        <div class="flex items-center gap-2">
            <button id="theme-toggle" type="button"
                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    aria-label="Toggle theme">
                <i data-lucide="sun"  class="w-4 h-4 hidden dark:block"></i>
                <i data-lucide="moon" class="w-4 h-4 block dark:hidden"></i>
            </button>

            <a href="scan.php"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                <i data-lucide="qr-code" class="w-4 h-4"></i> Scan QR
            </a>
            <a href="../auth/logout.php"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                Sign out
            </a>
        </div>
    </div>

    <?php flash_banner(); ?>

    <!-- ============= COURSE CARDS ============= -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php if (!$courses): ?>
            <div class="col-span-full bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-12 text-center text-slate-400">
                You are not enrolled in any courses yet.
            </div>
        <?php endif; ?>

        <?php foreach ($courses as $c):
            $total = (int)$c['total_sessions'];
            $attended = (int)$c['attended'];
            $pct = $total > 0 ? round($attended / $total * 100, 1) : 0;
            $req = (int)($c['required_attendance'] ?? 75);
            $warnBand = max(0, $req - 10);

            if ($pct >= $req) {
                $bar  = 'bg-emerald-500';
                $text = 'text-emerald-700 dark:text-emerald-400';
                $statusLabel = 'On track';
            } elseif ($pct >= $warnBand) {
                $bar  = 'bg-amber-500';
                $text = 'text-amber-700 dark:text-amber-400';
                $statusLabel = 'Warning';
            } else {
                $bar  = 'bg-rose-500';
                $text = 'text-rose-700 dark:text-rose-400';
                $statusLabel = 'At risk';
            }
        ?>
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden flex flex-col">

            <!-- Course image -->
            <?php if (!empty($c['image'])): ?>
                <img src="../uploads/courses/<?= htmlspecialchars($c['image']) ?>"
                     class="w-full h-32 object-cover"
                     alt="<?= htmlspecialchars($c['course_name']) ?>">
            <?php else: ?>
                <div class="w-full h-32 bg-gradient-to-br from-brand-500 to-violet-600 grid place-items-center">
                    <i data-lucide="book-open" class="w-10 h-10 text-white/90"></i>
                </div>
            <?php endif; ?>

            <!-- Card body -->
            <div class="p-5 flex flex-col flex-1">

                <div class="flex items-center justify-between mb-3">
                    <span class="inline-flex items-center rounded-md bg-brand-50 dark:bg-brand-500/15 text-brand-700 dark:text-brand-400 px-2 py-1 text-xs font-semibold font-mono">
                        <?= htmlspecialchars($c['course_code']) ?>
                    </span>
                    <span class="text-xs text-slate-400"><?= $attended ?>/<?= $total ?> sessions</span>
                </div>

                <div class="text-sm font-medium text-slate-900 dark:text-white mb-1">
                    <?= htmlspecialchars($c['course_name']) ?>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    Required: <strong class="text-slate-700 dark:text-slate-300"><?= $req ?>%</strong>
                    <span class="mx-1">·</span>
                    <span class="<?= $text ?> font-medium"><?= $statusLabel ?></span>
                </div>

                <div class="flex items-center gap-3 mb-4 mt-auto">
                    <div class="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                        <div class="h-full <?= $bar ?>" style="width: <?= $pct ?>%"></div>
                    </div>
                    <span class="text-sm font-semibold tabular-nums <?= $text ?>"><?= $pct ?>%</span>
                </div>

                <a href="download_report.php?course_id=<?= $c['course_id'] ?>"
                   target="_blank"
                   class="inline-flex items-center gap-2 text-xs font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 transition">
                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                    Download my report (PDF)
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ============= WEEKLY TIMETABLE ============= -->
    <?php
    $stmt = $pdo->prepare("
        SELECT t.*, c.id AS course_id, c.course_code, c.course_name
        FROM timetables t
        JOIN courses c ON c.id = t.course_id
        JOIN enrollments e ON e.course_id = c.id
        WHERE e.student_id = ?
        ORDER BY t.day_of_week, t.start_time
    ");
    $stmt->execute([$student_id]);
    $timetableSlots = $stmt->fetchAll();
    $timetableTitle = 'My weekly timetable';

    // Courses with slots (for PDF exports)
    $timetableCourses = [];
    foreach ($timetableSlots as $slot) {
        if (!isset($timetableCourses[$slot['course_id']])) {
            $timetableCourses[$slot['course_id']] = [
                'code' => $slot['course_code'],
                'name' => $slot['course_name'],
            ];
        }
    }

    require __DIR__ . '/../includes/timetable_widget.php';
    ?>

    <?php if (!empty($timetableCourses)): ?>
    <div class="mt-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-5">
        <div class="flex items-center gap-2 mb-3">
            <i data-lucide="calendar-days" class="w-4 h-4 text-brand-600 dark:text-brand-400"></i>
            <h3 class="font-semibold text-slate-900 dark:text-white text-sm">
                Download a printable timetable
            </h3>
        </div>

        <div class="flex flex-wrap gap-2">
            <?php foreach ($timetableCourses as $cid => $cInfo): ?>
                <a href="export_timetable_pdf.php?course_id=<?= $cid ?>" target="_blank"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3.5 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                    <span class="font-mono font-semibold"><?= htmlspecialchars($cInfo['code']) ?></span>
                    <span class="text-slate-400">PDF</span>
                </a>
            <?php endforeach; ?>
        </div>

        <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
            Each file contains the full weekly schedule for that course, with the institution letterhead.
        </p>
    </div>
    <?php endif; ?>

</div>
<?php require __DIR__ . '/../includes/foot.php'; ?>