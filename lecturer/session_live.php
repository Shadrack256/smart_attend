<?php
require '../config/db.php';
require_role('lecturer');

$session_id = (int)($_GET['session_id'] ?? 0);

// Load the session + course (verify ownership)
$stmt = $pdo->prepare("
    SELECT s.*, c.course_code, c.course_name, c.id AS course_id
    FROM sessions s
    JOIN courses c ON c.id = s.course_id
    WHERE s.id = ? AND c.lecturer_id = ?
");
$stmt->execute([$session_id, $_SESSION['user_id']]);
$session = $stmt->fetch();

if (!$session) die("Session not found or not yours.");

/**
 * Build the roster: every enrolled student, joined with their attendance row
 * for this session (if any).
 */
function fetch_roster($pdo, $session_id, $course_id) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.reg_number,
               a.status, a.marked_at, a.distance_m
        FROM enrollments e
        JOIN users u ON u.id = e.student_id
        LEFT JOIN attendance a
               ON a.student_id = u.id AND a.session_id = ?
        WHERE e.course_id = ?
        ORDER BY u.full_name
    ");
    $stmt->execute([$session_id, $course_id]);
    return $stmt->fetchAll();
}

$roster = fetch_roster($pdo, $session_id, $session['course_id']);

// Count by status
$present = $late = $absent = $waiting = 0;
foreach ($roster as $r) {
    switch ($r['status']) {
        case 'present': $present++; break;
        case 'late':    $late++;    break;
        case 'absent':  $absent++;  break;
        default:        $waiting++; break;
    }
}

$pageTitle = 'Live Attendance · ' . $session['course_code'];
require __DIR__ . '/../includes/head.php';
?>
<div class="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">

    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <div class="text-xs text-slate-400">
                <?= htmlspecialchars($session['course_code']) ?> ·
                <?= htmlspecialchars(substr($session['start_time'], 0, 16)) ?>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Live attendance</h1>
            <p class="text-sm text-slate-500 mt-1" id="status-line">
                <?= $session['is_active'] ? 'Session is live — updates every 5 seconds' : 'Session ended' ?>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($session['is_active']): ?>
                <a href="display_qr.php?session_id=<?= $session_id ?>"
                   class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-semibold hover:bg-brand-700">
                    <i data-lucide="qr-code" class="w-4 h-4"></i> Show QR
                </a>
            <?php endif; ?>
            <a href="dashboard.php"
               class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6" id="summary">
        <div class="bg-white rounded-xl border border-slate-200 shadow-card p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Present</div>
            <div class="text-2xl font-bold text-emerald-700 tabular-nums" id="count-present"><?= $present ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-card p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Late</div>
            <div class="text-2xl font-bold text-amber-700 tabular-nums" id="count-late"><?= $late ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-card p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Absent</div>
            <div class="text-2xl font-bold text-rose-700 tabular-nums" id="count-absent"><?= $absent ?></div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-card p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Waiting</div>
            <div class="text-2xl font-bold text-slate-500 tabular-nums" id="count-waiting"><?= $waiting ?></div>
        </div>
    </div>

    <!-- Roster table -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Student</th>
                        <th class="px-5 py-3 font-semibold">Reg no.</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3 font-semibold">Time</th>
                        <th class="px-5 py-3 font-semibold text-right">Distance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="roster">
                    <?php foreach ($roster as $r):
                        $status = $r['status'] ?? 'waiting';
                        $badge = [
                            'present' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                            'late'    => 'bg-amber-100 text-amber-800 ring-amber-200',
                            'absent'  => 'bg-rose-100 text-rose-700 ring-rose-200',
                            'waiting' => 'bg-slate-100 text-slate-600 ring-slate-200',
                        ][$status];
                        $label = ucfirst($status);
                        $icon  = [
                            'present' => 'check-circle-2',
                            'late'    => 'clock',
                            'absent'  => 'x-circle',
                            'waiting' => 'user',
                        ][$status];
                    ?>
                    <tr class="hover:bg-slate-50/70" data-student-id="<?= $r['id'] ?>">
                        <td class="px-5 py-3 font-medium text-slate-900"><?= htmlspecialchars($r['full_name']) ?></td>
                        <td class="px-5 py-3 text-slate-600 font-mono text-xs"><?= htmlspecialchars($r['reg_number'] ?? '—') ?></td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset <?= $badge ?>">
                                <i data-lucide="<?= $icon ?>" class="w-3 h-3"></i>
                                <?= $label ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-slate-500 text-xs whitespace-nowrap">
                            <?= $r['marked_at'] ? htmlspecialchars(substr($r['marked_at'], 11, 8)) : '—' ?>
                        </td>
                        <td class="px-5 py-3 text-right text-slate-500 tabular-nums text-xs">
                            <?= $r['distance_m'] !== null ? $r['distance_m'].' m' : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$roster): ?>
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-400">No students enrolled in this course.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const SESSION_ID = <?= $session_id ?>;
    const isActive   = <?= $session['is_active'] ? 'true' : 'false' ?>;

    async function refresh() {
        try {
            const res = await fetch('session_roster.php?session_id=' + SESSION_ID);
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success) return;

            // Update counters
            document.getElementById('count-present').textContent = data.counts.present;
            document.getElementById('count-late').textContent    = data.counts.late;
            document.getElementById('count-absent').textContent  = data.counts.absent;
            document.getElementById('count-waiting').textContent = data.counts.waiting;

            // Update roster rows in place
            const rosterEl = document.getElementById('roster');
            const badgeClass = {
                present: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                late:    'bg-amber-100 text-amber-800 ring-amber-200',
                absent:  'bg-rose-100 text-rose-700 ring-rose-200',
                waiting: 'bg-slate-100 text-slate-600 ring-slate-200',
            };

            data.roster.forEach(r => {
                const row = rosterEl.querySelector(`tr[data-student-id="${r.id}"]`);
                if (!row) return;
                const cells = row.children;
                const status = r.status || 'waiting';

                cells[2].innerHTML = `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${badgeClass[status]}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
                cells[3].textContent = r.marked_at ? r.marked_at.substr(11, 8) : '—';
                cells[4].textContent = r.distance_m !== null ? r.distance_m + ' m' : '—';
            });

            // If the session just ended, stop polling
            if (!data.active && isActive) {
                document.getElementById('status-line').textContent = 'Session ended';
                clearInterval(timer);
            }
        } catch (e) {
            // network hiccup — ignore and try again next cycle
        }
    }

    // Only poll while the session is live
    let timer = null;
    if (isActive) {
        timer = setInterval(refresh, 5000);
        // Fire one immediately so the page feels responsive
        refresh();
    }
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php require __DIR__ . '/../includes/foot.php'; ?>