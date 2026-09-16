<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');

$courses   = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code")->fetchAll();
$course_id = (int)($_GET['course_id'] ?? ($_POST['course_id'] ?? 0));

// ==================================================================
// POST HANDLERS
// ==================================================================

// ---------- Add a slot ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_slot'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid request.'];
        header("Location: timetables.php?course_id={$course_id}");
        exit;
    }

    $day       = (int)($_POST['day_of_week'] ?? 0);
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime   = trim($_POST['end_time'] ?? '');
    $room      = trim($_POST['room'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    // ---- Basic validation ----
    $errors = [];
    if ($day < 1 || $day > 7)                        $errors[] = 'Pick a valid day.';
    if (!preg_match('/^\d{2}:\d{2}$/', $startTime))  $errors[] = 'Invalid start time.';
    if (!preg_match('/^\d{2}:\d{2}$/', $endTime))    $errors[] = 'Invalid end time.';
    if ($startTime >= $endTime)                      $errors[] = 'End time must be after start time.';
    if ($room !== '' && strlen($room) > 80)          $errors[] = 'Room name too long.';
    if ($notes !== '' && strlen($notes) > 255)       $errors[] = 'Notes too long.';

    // ---- Overlap detection ----
    if (!$errors) {
        $startDb = $startTime . ':00';
        $endDb   = $endTime   . ':00';

        // 1) Room conflict
        if ($room !== '') {
            $stmt = $pdo->prepare("
                SELECT t.start_time, t.end_time, c.course_code, c.course_name
                FROM timetables t
                JOIN courses c ON c.id = t.course_id
                WHERE t.day_of_week = ?
                  AND LOWER(t.room) = LOWER(?)
                  AND t.start_time < ?
                  AND t.end_time   > ?
                LIMIT 1
            ");
            $stmt->execute([$day, $room, $endDb, $startDb]);
            $conflict = $stmt->fetch();

            if ($conflict) {
                $errors[] = sprintf(
                    'Room "%s" is already booked %s–%s on this day by %s (%s).',
                    $room,
                    substr($conflict['start_time'], 0, 5),
                    substr($conflict['end_time'], 0, 5),
                    $conflict['course_code'],
                    $conflict['course_name']
                );
            }
        }

        // 2) Lecturer conflict
        $stmt = $pdo->prepare("SELECT lecturer_id FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $lecturer_id = $stmt->fetchColumn();

        if ($lecturer_id) {
            $stmt = $pdo->prepare("
                SELECT t.start_time, t.end_time, c.course_code, c.course_name
                FROM timetables t
                JOIN courses c ON c.id = t.course_id
                WHERE t.day_of_week = ?
                  AND c.lecturer_id = ?
                  AND c.id <> ?
                  AND t.start_time < ?
                  AND t.end_time   > ?
                LIMIT 1
            ");
            $stmt->execute([$day, $lecturer_id, $course_id, $endDb, $startDb]);
            $conflict = $stmt->fetch();

            if ($conflict) {
                $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmt->execute([$lecturer_id]);
                $lecturerName = $stmt->fetchColumn();

                $errors[] = sprintf(
                    'Lecturer %s is already teaching %s (%s) from %s to %s on this day.',
                    $lecturerName,
                    $conflict['course_code'],
                    $conflict['course_name'],
                    substr($conflict['start_time'], 0, 5),
                    substr($conflict['end_time'], 0, 5)
                );
            }
        }
    }

    // ---- Save or reject ----
    if ($errors) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>implode(' ', $errors)];
    } else {
        $pdo->prepare("
            INSERT INTO timetables (course_id, day_of_week, start_time, end_time, room, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([
            $course_id,
            $day,
            $startTime . ':00',
            $endTime   . ':00',
            $room ?: null,
            $notes ?: null,
        ]);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Class added to the timetable.'];
    }

    header("Location: timetables.php?course_id={$course_id}");
    exit;
}

// ---------- Delete a slot ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_slot'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid request.'];
        header("Location: timetables.php?course_id={$course_id}");
        exit;
    }

    $slotId = (int)$_POST['slot_id'];
    $pdo->prepare("DELETE FROM timetables WHERE id = ? AND course_id = ?")
        ->execute([$slotId, $course_id]);

    $_SESSION['flash'] = ['type'=>'success','msg'=>'Class removed.'];
    header("Location: timetables.php?course_id={$course_id}");
    exit;
}

// ==================================================================
// LOAD DATA
// ==================================================================
$course = null;
$slots  = [];
$byDay  = [];
$dayNames = [1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 7=>'Sunday'];

if ($course_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name AS lecturer_name
        FROM courses c
        LEFT JOIN users u ON u.id = c.lecturer_id
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if ($course) {
        $stmt = $pdo->prepare("
            SELECT * FROM timetables
            WHERE course_id = ?
            ORDER BY day_of_week, start_time
        ");
        $stmt->execute([$course_id]);
        $slots = $stmt->fetchAll();

        for ($d = 1; $d <= 7; $d++) $byDay[$d] = [];
        foreach ($slots as $s) {
            $byDay[(int)$s['day_of_week']][] = $s;
        }
    }
}

// ---------- Detect any existing conflicts (for grid highlighting) ----------
$conflictIds = [];
if ($course) {
    // Room conflicts — for this course's slots against anything else in the same room
    $stmt = $pdo->prepare("
        SELECT t1.id AS id1, t2.id AS id2
        FROM timetables t1
        JOIN timetables t2
          ON t1.id < t2.id
         AND t1.day_of_week = t2.day_of_week
         AND t1.room IS NOT NULL AND t2.room IS NOT NULL
         AND LOWER(t1.room) = LOWER(t2.room)
         AND t1.start_time < t2.end_time
         AND t1.end_time   > t2.start_time
        WHERE t1.course_id = ? OR t2.course_id = ?
    ");
    $stmt->execute([$course_id, $course_id]);
    foreach ($stmt->fetchAll() as $c) {
        $conflictIds[(int)$c['id1']] = true;
        $conflictIds[(int)$c['id2']] = true;
    }

    // Lecturer conflicts — same lecturer, overlapping time, same day, different course
    $stmt = $pdo->prepare("
        SELECT t1.id AS id1, t2.id AS id2
        FROM timetables t1
        JOIN courses c1 ON c1.id = t1.course_id
        JOIN timetables t2
          ON t1.id < t2.id
         AND t1.day_of_week = t2.day_of_week
         AND t1.start_time < t2.end_time
         AND t1.end_time   > t2.start_time
        JOIN courses c2 ON c2.id = t2.course_id
        WHERE c1.lecturer_id IS NOT NULL
          AND c1.lecturer_id = c2.lecturer_id
          AND c1.id <> c2.id
          AND (t1.course_id = ? OR t2.course_id = ?)
    ");
    $stmt->execute([$course_id, $course_id]);
    foreach ($stmt->fetchAll() as $c) {
        $conflictIds[(int)$c['id1']] = true;
        $conflictIds[(int)$c['id2']] = true;
    }
}

// ==================================================================
// RENDER
// ==================================================================
$pageTitle = 'Timetables';
require __DIR__ . '/partials/admin_header.php';

$title = 'Timetables';
$subtitle = 'Design the weekly schedule for each course.';
require __DIR__ . '/partials/page_header.php';

require_once __DIR__ . '/../includes/ui.php';
flash_banner();
?>

<!-- ============= COURSE PICKER ============= -->
<form method="GET" class="mb-6">
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Course</label>
    <select name="course_id" onchange="this.form.submit()"
            class="w-full max-w-md rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        <option value="">— Select a course —</option>
        <?php foreach ($courses as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $course_id == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['course_code'] . ' — ' . $c['course_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (!$course_id || !$course): ?>
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-12 text-center text-slate-400">
        Select a course above to design its timetable.
    </div>
<?php else: ?>

<!-- ============= ADD SLOT FORM ============= -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card mb-6">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
        <h2 class="font-semibold text-slate-900 dark:text-white">Add a class slot</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Room and lecturer conflicts are checked automatically.
        </p>
    </div>

    <form method="POST" class="p-5">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input type="hidden" name="add_slot" value="1">

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">

            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Day</label>
                <select name="day_of_week" required
                        class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <?php foreach ($dayNames as $n => $label): ?>
                        <option value="<?= $n ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Start time</label>
                <input type="time" name="start_time" required
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">End time</label>
                <input type="time" name="end_time" required
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Room <span class="text-slate-400">(optional)</span></label>
                <input type="text" name="room" maxlength="80" placeholder="e.g. LH-1"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1">Notes <span class="text-slate-400">(optional)</span></label>
                <input type="text" name="notes" maxlength="255" placeholder="e.g. Group A only"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

        </div>

        <div class="mt-4 flex justify-end">
            <button class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                <i data-lucide="plus" class="w-4 h-4"></i> Add to timetable
            </button>
        </div>
    </form>
</div>

<!-- ============= WEEKLY GRID ============= -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold text-slate-900 dark:text-white">Weekly timetable</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                <?= count($slots) ?> class slot<?= count($slots) === 1 ? '' : 's' ?> per week
                <?php if ($course['lecturer_name']): ?>
                    · Lecturer: <?= htmlspecialchars($course['lecturer_name']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-md bg-brand-50 dark:bg-brand-500/15 text-brand-700 dark:text-brand-400 px-2 py-1 text-xs font-semibold font-mono">
                <?= htmlspecialchars($course['course_code']) ?>
            </span>
            <?php if ($slots): ?>
                <a href="export_timetable_pdf.php?course_id=<?= $course_id ?>" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 rounded-lg bg-rose-600 text-white px-3.5 py-2 text-xs font-medium hover:bg-rose-700 shadow-sm transition">
                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i> Export PDF
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$slots): ?>
        <div class="p-12 text-center text-slate-400 text-sm">
            No classes yet. Use the form above to add the first slot.
        </div>
    <?php else: ?>

        <?php if ($conflictIds): ?>
            <div class="px-5 py-3 bg-amber-50 dark:bg-amber-500/10 border-b border-amber-200 dark:border-amber-500/40 text-sm text-amber-800 dark:text-amber-300 flex items-start gap-2">
                <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 shrink-0"></i>
                <div>
                    <strong>Existing conflicts detected.</strong>
                    Some slots on this timetable overlap in room or lecturer.
                    Conflicting slots are highlighted in red below.
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-7 divide-y md:divide-y-0 md:divide-x divide-slate-100 dark:divide-slate-800">

            <?php foreach ($dayNames as $dayNum => $dayLabel):
                $daySlots = $byDay[$dayNum];
            ?>
            <div class="p-4">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">
                    <?= $dayLabel ?>
                </div>

                <?php if (!$daySlots): ?>
                    <div class="text-xs text-slate-300 dark:text-slate-600 italic">—</div>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($daySlots as $s):
                            $start = substr($s['start_time'], 0, 5);
                            $end   = substr($s['end_time'],   0, 5);
                            $isConflict = isset($conflictIds[(int)$s['id']]);

                            $cardCls = $isConflict
                                ? 'border-rose-300 dark:border-rose-500/50 bg-rose-50 dark:bg-rose-500/10'
                                : 'border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950';
                        ?>
                        <div class="rounded-lg border <?= $cardCls ?> p-3">
                            <div class="text-sm font-semibold text-slate-900 dark:text-white tabular-nums flex items-center gap-1">
                                <?php if ($isConflict): ?>
                                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-rose-600 dark:text-rose-400"></i>
                                <?php endif; ?>
                                <?= htmlspecialchars($start) ?> – <?= htmlspecialchars($end) ?>
                            </div>
                            <?php if ($s['room']): ?>
                                <div class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 inline-flex items-center gap-1">
                                    <i data-lucide="map-pin" class="w-3 h-3"></i>
                                    <?= htmlspecialchars($s['room']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($s['notes']): ?>
                                <div class="text-xs text-slate-500 dark:text-slate-500 mt-1 italic">
                                    <?= htmlspecialchars($s['notes']) ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" class="mt-2" onsubmit="return confirm('Remove this class slot?');">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                <input type="hidden" name="delete_slot" value="1">
                                <input type="hidden" name="slot_id" value="<?= $s['id'] ?>">
                                <input type="hidden" name="course_id" value="<?= $course_id ?>">
                                <button class="text-xs text-rose-600 dark:text-rose-400 hover:underline inline-flex items-center gap-1">
                                    <i data-lucide="trash-2" class="w-3 h-3"></i> Remove
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>