<?php
require '../config/db.php';
require_role('lecturer');
require '../includes/ui.php';

$lecturer_id = $_SESSION['user_id'];
$brand = app_settings($pdo);

// ---------- Load lecturer's photo ----------
$stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
$stmt->execute([$lecturer_id]);
$myPhoto = $stmt->fetchColumn();

// ---------- Active session ----------
$stmt = $pdo->prepare("
    SELECT s.id, s.start_time, s.radius_m, c.course_code, c.course_name, c.id AS course_id
    FROM sessions s
    JOIN courses c ON c.id = s.course_id
    WHERE c.lecturer_id = ? AND s.is_active = 1
    ORDER BY s.start_time DESC LIMIT 1
");
$stmt->execute([$lecturer_id]);
$activeSession = $stmt->fetch();

// ---------- Active roster ----------
$activeRoster = [];
$activeCounts = ['present' => 0, 'late' => 0, 'absent' => 0, 'waiting' => 0];
if ($activeSession) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.reg_number, u.photo,
               a.status, a.marked_at, a.distance_m
        FROM enrollments e
        JOIN users u ON u.id = e.student_id
        LEFT JOIN attendance a ON a.student_id = u.id AND a.session_id = ?
        WHERE e.course_id = ?
        ORDER BY
            CASE
                WHEN a.status = 'present' THEN 1
                WHEN a.status = 'late'    THEN 2
                WHEN a.status = 'absent'  THEN 3
                ELSE 4
            END,
            u.full_name
    ");
    $stmt->execute([$activeSession['id'], $activeSession['course_id']]);
    $activeRoster = $stmt->fetchAll();
    foreach ($activeRoster as $r) {
        $status = $r['status'] ?? 'waiting';
        $activeCounts[$status] = ($activeCounts[$status] ?? 0) + 1;
    }
}

// ---------- My courses (with image) ----------
$stmt = $pdo->prepare("
    SELECT c.id, c.course_code, c.course_name, c.required_attendance, c.image,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students
    FROM courses c
    WHERE c.lecturer_id = ?
    ORDER BY c.course_code
");
$stmt->execute([$lecturer_id]);
$courses = $stmt->fetchAll();

// ---------- Recent sessions ----------
$stmt = $pdo->prepare("
    SELECT s.id, s.session_date, s.start_time, s.is_active, c.course_code,
           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
           SUM(CASE WHEN a.status = 'late'    THEN 1 ELSE 0 END) AS late_count,
           SUM(CASE WHEN a.status = 'absent'  THEN 1 ELSE 0 END) AS absent_count
    FROM sessions s
    JOIN courses c ON c.id = s.course_id
    LEFT JOIN attendance a ON a.session_id = s.id
    WHERE c.lecturer_id = ?
    GROUP BY s.id, s.session_date, s.start_time, s.is_active, c.course_code
    ORDER BY s.start_time DESC
    LIMIT 10
");
$stmt->execute([$lecturer_id]);
$sessions = $stmt->fetchAll();

$pageTitle = 'Lecturer Dashboard';
require __DIR__ . '/../includes/head.php';
?>
<div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">

    <!-- ============= HEADER ============= -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
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
                    Hi, <?= htmlspecialchars($_SESSION['name']) ?>
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Lecturer dashboard · <span class="text-brand-600 dark:text-brand-400">View profile</span>
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

            <a href="start_session.php"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                <i data-lucide="play" class="w-4 h-4"></i> Start session
            </a>
            <a href="../auth/logout.php"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                Sign out
            </a>
        </div>
    </div>

    <?php flash_banner(); ?>

    <!-- ============= LIVE SESSION WIDGET ============= -->
    <?php if ($activeSession): ?>
    <div class="bg-slate-900 text-white rounded-2xl border border-slate-800 shadow-card p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
            <div>
                <div class="flex items-center gap-2 text-emerald-400 text-xs uppercase tracking-wide mb-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    Live session
                </div>
                <h2 class="text-xl font-bold">
                    <span class="font-mono text-brand-400"><?= htmlspecialchars($activeSession['course_code']) ?></span>
                    · <?= htmlspecialchars($activeSession['course_name']) ?>
                </h2>
                <p class="text-slate-400 text-sm mt-1">
                    Started <?= htmlspecialchars(substr($activeSession['start_time'], 11, 5)) ?>
                    · radius <?= (int)$activeSession['radius_m'] ?> m
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="display_qr.php?session_id=<?= $activeSession['id'] ?>"
                   class="inline-flex items-center gap-2 rounded-lg bg-white text-slate-900 px-4 py-2 text-sm font-semibold hover:bg-slate-100">
                    <i data-lucide="qr-code" class="w-4 h-4"></i> Show QR
                </a>
                <a href="session_live.php?session_id=<?= $activeSession['id'] ?>"
                   class="inline-flex items-center gap-2 rounded-lg border border-slate-600 hover:bg-slate-700 text-white px-4 py-2 text-sm font-medium">
                    <i data-lucide="activity" class="w-4 h-4"></i> Full view
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
            <div class="bg-slate-800 rounded-xl px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">Present</div>
                <div class="text-2xl font-bold text-emerald-400 tabular-nums" id="count-present"><?= $activeCounts['present'] ?></div>
            </div>
            <div class="bg-slate-800 rounded-xl px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">Late</div>
                <div class="text-2xl font-bold text-amber-400 tabular-nums" id="count-late"><?= $activeCounts['late'] ?></div>
            </div>
            <div class="bg-slate-800 rounded-xl px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">Waiting</div>
                <div class="text-2xl font-bold text-slate-300 tabular-nums" id="count-waiting"><?= $activeCounts['waiting'] ?></div>
            </div>
            <div class="bg-slate-800 rounded-xl px-4 py-3">
                <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">Total</div>
                <div class="text-2xl font-bold text-white tabular-nums"><?= count($activeRoster) ?></div>
            </div>
        </div>

        <div class="bg-slate-800 rounded-xl overflow-hidden max-h-72 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/60 text-slate-400 text-left text-xs uppercase tracking-wide sticky top-0">
                    <tr>
                        <th class="px-4 py-2 font-semibold">Student</th>
                        <th class="px-4 py-2 font-semibold">Reg no.</th>
                        <th class="px-4 py-2 font-semibold">Status</th>
                        <th class="px-4 py-2 font-semibold text-right">Distance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700" id="live-roster">
                    <?php foreach ($activeRoster as $r):
                        $status = $r['status'] ?? 'waiting';
                        $badge = [
                            'present' => 'bg-emerald-500/20 text-emerald-300 ring-emerald-500/40',
                            'late'    => 'bg-amber-500/20 text-amber-300 ring-amber-500/40',
                            'absent'  => 'bg-rose-500/20 text-rose-300 ring-rose-500/40',
                            'waiting' => 'bg-slate-700 text-slate-400 ring-slate-600',
                        ][$status];
                    ?>
                    <tr data-student-id="<?= $r['id'] ?>" class="hover:bg-slate-700/50">
                        <td class="px-4 py-2 font-medium text-white">
                            <div class="flex items-center gap-2">
                                <?php if (!empty($r['photo'])): ?>
                                    <img src="../uploads/avatars/<?= htmlspecialchars($r['photo']) ?>" class="w-6 h-6 rounded-full object-cover" alt="">
                                <?php else: ?>
                                    <div class="w-6 h-6 rounded-full bg-slate-600 text-white grid place-items-center text-[10px] font-semibold">
                                        <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <?= htmlspecialchars($r['full_name']) ?>
                            </div>
                        </td>
                        <td class="px-4 py-2 text-slate-400 font-mono text-xs"><?= htmlspecialchars($r['reg_number'] ?? '—') ?></td>
                        <td class="px-4 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset <?= $badge ?>">
                                <?= ucfirst($status) ?>
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right text-slate-400 tabular-nums text-xs">
                            <?= $r['distance_m'] !== null ? $r['distance_m'].' m' : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============= MY COURSES ============= -->
    <div class="mb-6">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-white mb-3">My courses</h2>

        <?php if (!$courses): ?>
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card px-5 py-10 text-center text-slate-400 text-sm">
                You have no courses assigned yet. Contact an administrator.
            </div>
        <?php else: ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($courses as $c):
                    $req = (int)($c['required_attendance'] ?? 75);
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
                            <span class="text-xs text-slate-400"><?= (int)$c['students'] ?> students</span>
                        </div>

                        <div class="text-sm font-medium text-slate-900 dark:text-white mb-1">
                            <?= htmlspecialchars($c['course_name']) ?>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                            Required: <strong class="text-slate-700 dark:text-slate-300"><?= $req ?>%</strong>
                        </div>

                        <div class="grid grid-cols-3 gap-2 mt-auto">
                            <a href="export_register_pdf.php?course_id=<?= $c['id'] ?>" target="_blank"
                               class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Register
                            </a>

                            <a href="manage_students.php?course_id=<?= $c['id'] ?>"
                               class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                <i data-lucide="users" class="w-3.5 h-3.5"></i> Students
                            </a>

                            <a href="start_session.php?course_id=<?= $c['id'] ?>"
                               class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-brand-600 text-white px-2 py-2 text-xs font-semibold hover:bg-brand-700 transition">
                                <i data-lucide="play" class="w-3.5 h-3.5"></i> Start
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============= WEEKLY TIMETABLE ============= -->
    <?php
    $stmt = $pdo->prepare("
        SELECT t.*, c.id AS course_id, c.course_code, c.course_name
        FROM timetables t
        JOIN courses c ON c.id = t.course_id
        WHERE c.lecturer_id = ?
        ORDER BY t.day_of_week, t.start_time
    ");
    $stmt->execute([$lecturer_id]);
    $timetableSlots = $stmt->fetchAll();
    $timetableTitle = 'My teaching timetable';

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

    <!-- ============= RECENT SESSIONS ============= -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden mt-6">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Recent sessions</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 text-left text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Date</th>
                        <th class="px-5 py-3 font-semibold">Course</th>
                        <th class="px-5 py-3 font-semibold text-center">Present</th>
                        <th class="px-5 py-3 font-semibold text-center">Late</th>
                        <th class="px-5 py-3 font-semibold text-center">Absent</th>
                        <th class="px-5 py-3 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php if (!$sessions): ?>
                        <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">No sessions yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($sessions as $s):
                        $present = (int)$s['present_count'];
                        $late    = (int)$s['late_count'];
                        $absent  = (int)$s['absent_count'];
                    ?>
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                        <td class="px-5 py-3 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                            <?= htmlspecialchars(substr($s['start_time'], 0, 16)) ?>
                            <?php if ($s['is_active']): ?>
                                <span class="ml-2 inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> live
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center rounded-md bg-brand-50 dark:bg-brand-500/15 text-brand-700 dark:text-brand-400 px-2 py-0.5 text-xs font-semibold font-mono">
                                <?= htmlspecialchars($s['course_code']) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center tabular-nums text-emerald-700 dark:text-emerald-400 font-medium"><?= $present ?></td>
                        <td class="px-5 py-3 text-center tabular-nums text-amber-700 dark:text-amber-400 font-medium"><?= $late ?></td>
                        <td class="px-5 py-3 text-center tabular-nums <?= $absent > 0 ? 'text-rose-700 dark:text-rose-400 font-medium' : 'text-slate-400' ?>"><?= $absent ?></td>
                        <td class="px-5 py-3 text-right">
                            <a href="session_live.php?session_id=<?= $s['id'] ?>"
                               class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-brand-600 dark:text-brand-400 hover:bg-brand-50 dark:hover:bg-brand-500/10">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php if ($activeSession): ?>
<script>
    const ACTIVE_SESSION_ID = <?= (int)$activeSession['id'] ?>;

    async function refreshLive() {
        try {
            const res = await fetch('session_roster.php?session_id=' + ACTIVE_SESSION_ID);
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success) return;

            document.getElementById('count-present').textContent = data.counts.present;
            document.getElementById('count-late').textContent    = data.counts.late;
            document.getElementById('count-waiting').textContent = data.counts.waiting;

            const rosterEl = document.getElementById('live-roster');
            const badgeClass = {
                present: 'bg-emerald-500/20 text-emerald-300 ring-emerald-500/40',
                late:    'bg-amber-500/20 text-amber-300 ring-amber-500/40',
                absent:  'bg-rose-500/20 text-rose-300 ring-rose-500/40',
                waiting: 'bg-slate-700 text-slate-400 ring-slate-600',
            };

            data.roster.forEach(r => {
                const row = rosterEl.querySelector('tr[data-student-id="' + r.id + '"]');
                if (!row) return;
                const cells = row.children;
                const status = r.status || 'waiting';
                cells[2].innerHTML = '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ' + badgeClass[status] + '">' + (status.charAt(0).toUpperCase() + status.slice(1)) + '</span>';
                cells[3].textContent = r.distance_m !== null ? r.distance_m + ' m' : '—';
            });

            if (!data.active) location.reload();
        } catch (e) {}
    }

    setInterval(refreshLive, 5000);
    refreshLive();
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/foot.php'; ?>