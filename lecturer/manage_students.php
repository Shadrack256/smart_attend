<?php
require_once __DIR__ . '/../config/db.php';
require_role('lecturer');

$lecturer_id = (int)$_SESSION['user_id'];
$course_id   = (int)($_GET['course_id'] ?? ($_POST['course_id'] ?? 0));

if (!$course_id) die("No course selected.");

// ---------- Verify the lecturer owns this course ----------
$stmt = $pdo->prepare("
    SELECT id, course_code, course_name
    FROM courses
    WHERE id = ? AND lecturer_id = ?
");
$stmt->execute([$course_id, $lecturer_id]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(403);
    die("You are not assigned to this course.");
}

// ==================================================================
// POST HANDLERS
// ==================================================================

// ---------- Enroll selected students ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid request.'];
        header("Location: manage_students.php?course_id={$course_id}");
        exit;
    }

    $ids = array_filter(array_map('intval', $_POST['student_ids'] ?? []));

    if (!$ids) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'No students selected.'];
    } else {
        $stmt = $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
        $added = 0;
        foreach ($ids as $sid) {
            $stmt->execute([$sid, $course_id]);
            if ($stmt->rowCount() > 0) $added++;
        }
        $_SESSION['flash'] = ['type'=>'success','msg'=>"Enrolled {$added} student(s)."];
    }
    header("Location: manage_students.php?course_id={$course_id}");
    exit;
}

// ---------- Remove a student ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid request.'];
        header("Location: manage_students.php?course_id={$course_id}");
        exit;
    }

    $sid = (int)$_POST['remove_id'];

    // Safety: never remove a student who has attendance for this course
    $stmt = $pdo->prepare("
        SELECT 1 FROM attendance a
        JOIN sessions s ON s.id = a.session_id
        WHERE s.course_id = ? AND a.student_id = ?
        LIMIT 1
    ");
    $stmt->execute([$course_id, $sid]);
    if ($stmt->fetch()) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Cannot remove: this student has attendance records for this course.'];
    } else {
        $pdo->prepare("DELETE FROM enrollments WHERE student_id = ? AND course_id = ?")
            ->execute([$sid, $course_id]);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Student removed from course.'];
    }
    header("Location: manage_students.php?course_id={$course_id}");
    exit;
}

// ==================================================================
// LOAD DATA
// ==================================================================

// Enrolled students
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.reg_number, u.email, u.photo
    FROM enrollments e
    JOIN users u ON u.id = e.student_id
    WHERE e.course_id = ?
    ORDER BY u.full_name
");
$stmt->execute([$course_id]);
$enrolled = $stmt->fetchAll();

// Available students (not already enrolled)
$stmt = $pdo->prepare("
    SELECT id, full_name, reg_number, email, photo
    FROM users
    WHERE role='student'
      AND id NOT IN (SELECT student_id FROM enrollments WHERE course_id = ?)
    ORDER BY full_name
");
$stmt->execute([$course_id]);
$available = $stmt->fetchAll();

$pageTitle = 'Manage students';
require __DIR__ . '/../includes/head.php';

require_once __DIR__ . '/../includes/ui.php';
?>
<div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <div class="text-xs text-slate-400 dark:text-slate-500 mb-1">
                <a href="dashboard.php" class="hover:text-brand-600 dark:hover:text-brand-400">← Back to dashboard</a>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                Manage students
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                <span class="font-mono font-semibold text-brand-600 dark:text-brand-400">
                    <?= htmlspecialchars($course['course_code']) ?>
                </span>
                · <?= htmlspecialchars($course['course_name']) ?>
            </p>
        </div>
        <a href="export_register_pdf.php?course_id=<?= $course_id ?>" target="_blank"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
            <i data-lucide="file-text" class="w-4 h-4"></i> Download register
        </a>
    </div>

    <?php flash_banner(); ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Enrolled</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white tabular-nums"><?= count($enrolled) ?></div>
        </div>
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Available</div>
            <div class="text-2xl font-bold text-slate-900 dark:text-white tabular-nums"><?= count($available) ?></div>
        </div>
    </div>

    <div class="grid lg:grid-cols-5 gap-6">

        <!-- ============ ENROLLED (left) ============ -->
        <div class="lg:col-span-3">
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                    <h2 class="font-semibold text-slate-900 dark:text-white">Enrolled students</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><?= count($enrolled) ?> on this course</p>
                </div>

                <?php if ($enrolled): ?>
                <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                        <input type="text" id="roster-search"
                               placeholder="Search enrolled students…"
                               class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>
                </div>
                <?php endif; ?>

                <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-[560px] overflow-y-auto" id="roster-list">
                    <?php if (!$enrolled): ?>
                        <div class="px-5 py-12 text-center text-slate-400 text-sm">
                            No students enrolled yet.
                        </div>
                    <?php endif; ?>

                    <?php foreach ($enrolled as $s): ?>
                    <div class="px-5 py-3 flex items-center justify-between hover:bg-slate-50/70 dark:hover:bg-slate-800/50 roster-row"
                         data-search="<?= htmlspecialchars(strtolower($s['full_name'] . ' ' . $s['reg_number'] . ' ' . $s['email'])) ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <?php if (!empty($s['photo'])): ?>
                                <img src="../uploads/avatars/<?= htmlspecialchars($s['photo']) ?>" class="w-9 h-9 rounded-full object-cover shrink-0" alt="">
                            <?php else: ?>
                                <div class="w-9 h-9 rounded-full bg-brand-50 dark:bg-brand-500/15 text-brand-700 dark:text-brand-400 grid place-items-center text-xs font-semibold shrink-0">
                                    <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-slate-900 dark:text-white truncate"><?= htmlspecialchars($s['full_name']) ?></div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono"><?= htmlspecialchars($s['reg_number']) ?></div>
                            </div>
                        </div>
                        <form method="POST" onsubmit="return confirm('Remove <?= htmlspecialchars($s['full_name']) ?> from this course?');">
                            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                            <input type="hidden" name="course_id" value="<?= $course_id ?>">
                            <input type="hidden" name="remove_id" value="<?= $s['id'] ?>">
                            <button class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition">
                                <i data-lucide="x" class="w-3.5 h-3.5"></i> Remove
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>

                    <div id="roster-empty" class="hidden px-5 py-10 text-center text-slate-400 text-sm">
                        No matching students.
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ AVAILABLE (right) ============ -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800">
                    <h2 class="font-semibold text-slate-900 dark:text-white">Add students</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><?= count($available) ?> available</p>
                </div>

                <?php if (!$available): ?>
                    <div class="px-5 py-12 text-center text-slate-400 text-sm">
                        All students are already enrolled.
                    </div>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                    <input type="hidden" name="course_id" value="<?= $course_id ?>">
                    <input type="hidden" name="enroll" value="1">

                    <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                            <input type="checkbox" id="checkAll" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            Select all
                        </label>
                        <span class="text-xs text-slate-400" id="selected-count">0 selected</span>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-[440px] overflow-y-auto">
                        <?php foreach ($available as $s): ?>
                        <label class="flex items-center gap-3 px-5 py-2.5 hover:bg-slate-50/70 dark:hover:bg-slate-800/50 cursor-pointer">
                            <input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="student-check rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <?php if (!empty($s['photo'])): ?>
                                <img src="../uploads/avatars/<?= htmlspecialchars($s['photo']) ?>" class="w-7 h-7 rounded-full object-cover" alt="">
                            <?php else: ?>
                                <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 grid place-items-center text-[10px] font-semibold">
                                    <?= strtoupper(substr($s['full_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm text-slate-900 dark:text-white truncate"><?= htmlspecialchars($s['full_name']) ?></div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono"><?= htmlspecialchars($s['reg_number']) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="submit" id="enroll-btn" disabled
                                class="w-full rounded-lg bg-brand-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Enroll selected
                        </button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Select-all + counter
    const checkAll    = document.getElementById('checkAll');
    const checks      = document.querySelectorAll('.student-check');
    const counter     = document.getElementById('selected-count');
    const enrollBtn   = document.getElementById('enroll-btn');

    function updateCount() {
        const n = document.querySelectorAll('.student-check:checked').length;
        if (counter) counter.textContent = n + ' selected';
        if (enrollBtn) enrollBtn.disabled = n === 0;
    }

    if (checkAll) {
        checkAll.addEventListener('change', e => {
            checks.forEach(cb => cb.checked = e.target.checked);
            updateCount();
        });
    }
    checks.forEach(cb => cb.addEventListener('change', updateCount));

    // Roster search
    const search = document.getElementById('roster-search');
    if (search) {
        search.addEventListener('input', e => {
            const q = e.target.value.toLowerCase().trim();
            let visible = 0;
            document.querySelectorAll('.roster-row').forEach(row => {
                const match = row.dataset.search.includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('roster-empty').classList.toggle('hidden', visible > 0);
        });
    }
</script>

<?php require __DIR__ . '/../includes/foot.php'; ?>