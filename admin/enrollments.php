<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');

$course_id = (int)($_GET['course_id'] ?? ($_POST['course_id'] ?? 0));
$courses   = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code")->fetchAll();

// ==================================================================
// POST HANDLERS
// ==================================================================

// ---------- A) Manual enrollment (checkbox form) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_enroll'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid request.'];
        header("Location: enrollments.php?course_id={$course_id}");
        exit;
    }
    $ids = array_map('intval', $_POST['student_ids'] ?? []);
    $ids = array_filter($ids);

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
    header("Location: enrollments.php?course_id={$course_id}");
    exit;
}

// ---------- B) Bulk CSV upload — preview ----------
$csvPreview   = [];
$csvStats     = ['ready'=>0, 'already'=>0, 'notfound'=>0, 'duplicate'=>0, 'total'=>0];
$csvError     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csv_preview'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $csvError = 'Invalid request.';
    } elseif (!$course_id) {
        $csvError = 'Please select a course first.';
    } elseif (empty($_FILES['csv']['tmp_name']) ||
              ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $csvError = 'Please choose a CSV file.';
    } elseif ($_FILES['csv']['size'] > 1024 * 1024) {
        $csvError = 'CSV file is too large (max 1 MB).';
    } else {
        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        if (!$handle) {
            $csvError = 'Could not read the uploaded file.';
        } else {
            $lineNo = 0;
            $seen   = [];
            while (($row = fgetcsv($handle)) !== false) {
                $lineNo++;
                if ($lineNo === 1) continue; // skip header

                $reg = '';
                foreach ($row as $cell) {
                    $cell = trim((string)$cell);
                    if ($cell !== '') { $reg = $cell; break; }
                }
                if ($reg === '') continue;

                $csvStats['total']++;
                $key = strtolower($reg);

                if (isset($seen[$key])) {
                    $csvStats['duplicate']++;
                    $csvPreview[] = [
                        'reg'=>$reg, 'id'=>null, 'name'=>'—',
                        'status'=>'duplicate',
                        'note'=>'Duplicate of line ' . $seen[$key],
                    ];
                    continue;
                }
                $seen[$key] = $lineNo;

                $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role='student' AND LOWER(reg_number) = ? LIMIT 1");
                $stmt->execute([$key]);
                $student = $stmt->fetch();

                if (!$student) {
                    $csvStats['notfound']++;
                    $csvPreview[] = [
                        'reg'=>$reg, 'id'=>null, 'name'=>'—',
                        'status'=>'notfound', 'note'=>'No student with this reg number',
                    ];
                    continue;
                }

                $stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id=? AND course_id=?");
                $stmt->execute([$student['id'], $course_id]);
                if ($stmt->fetch()) {
                    $csvStats['already']++;
                    $csvPreview[] = [
                        'reg'=>$reg, 'id'=>$student['id'], 'name'=>$student['full_name'],
                        'status'=>'already', 'note'=>'Already enrolled',
                    ];
                } else {
                    $csvStats['ready']++;
                    $csvPreview[] = [
                        'reg'=>$reg, 'id'=>$student['id'], 'name'=>$student['full_name'],
                        'status'=>'ready', 'note'=>'Will be enrolled',
                    ];
                }
            }
            fclose($handle);
        }
    }
}

// ---------- C) Bulk CSV — commit ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csv_commit'])) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid request.'];
        header("Location: enrollments.php?course_id={$course_id}");
        exit;
    }
    $ids = array_filter(array_map('intval', $_POST['ready_ids'] ?? []));
    if (!$ids) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'No students to enroll.'];
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
            $added = 0;
            foreach ($ids as $sid) {
                $stmt->execute([$sid, $course_id]);
                if ($stmt->rowCount() > 0) $added++;
            }
            $pdo->commit();
            $_SESSION['flash'] = ['type'=>'success','msg'=>"Bulk-enrolled {$added} student(s)."];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Error: ' . $e->getMessage()];
        }
    }
    header("Location: enrollments.php?course_id={$course_id}");
    exit;
}

// ---------- D) Download CSV template ----------
if (isset($_GET['template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="enrollment_template.csv"');
    echo "reg_number\n2025/ITB/DAY/1790/G\n2025/ITB/DAY/0268/P\n2025/ITB/DAY/2303/G\n";
    exit;
}

// ==================================================================
// LOAD DATA
// ==================================================================

$course = null;
$enrolled = [];
$available = [];

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
            SELECT u.id, u.full_name, u.reg_number, u.email, u.photo
            FROM enrollments e
            JOIN users u ON u.id = e.student_id
            WHERE e.course_id = ?
            ORDER BY u.full_name
        ");
        $stmt->execute([$course_id]);
        $enrolled = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT id, full_name, reg_number, email, photo
            FROM users
            WHERE role='student'
              AND id NOT IN (SELECT student_id FROM enrollments WHERE course_id = ?)
            ORDER BY full_name
        ");
        $stmt->execute([$course_id]);
        $available = $stmt->fetchAll();
    }
}

$pageTitle = 'Enrollments';
require __DIR__ . '/partials/admin_header.php';

$title = 'Enrollments';
$subtitle = 'Manage who\'s in a course — manually or via CSV.';
require __DIR__ . '/partials/page_header.php';

// Flash
require_once __DIR__ . '/../includes/ui.php';
flash_banner();
?>

<!-- ============ COURSE PICKER ============ -->
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
        Select a course above to manage enrollments.
    </div>

<?php else: ?>

<!-- ============ STATS + BULK UPLOAD TRIGGER ============ -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-5">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Enrolled</div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white tabular-nums"><?= count($enrolled) ?></div>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-5">
        <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Available to enroll</div>
        <div class="text-2xl font-bold text-slate-900 dark:text-white tabular-nums"><?= count($available) ?></div>
    </div>

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card p-5 flex items-center justify-between">
        <div>
            <div class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-1">Bulk enroll</div>
            <div class="text-sm font-medium text-slate-700 dark:text-slate-300">Upload a CSV</div>
        </div>
        <button type="button" onclick="document.getElementById('bulk-panel').classList.toggle('hidden')"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-3.5 py-2 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
            <i data-lucide="upload" class="w-4 h-4"></i> Upload
        </button>
    </div>
</div>

<!-- ============ BULK CSV PANEL ============ -->
<div id="bulk-panel" class="<?= $csvPreview || $csvError ? '' : 'hidden' ?> bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card mb-6">

    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold text-slate-900 dark:text-white">Bulk enroll via CSV</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">One registration number per line, with a header row.</p>
        </div>
        <a href="enrollments.php?course_id=<?= $course_id ?>&template=1"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
            <i data-lucide="download" class="w-3.5 h-3.5"></i> Download template
        </a>
    </div>

    <!-- Upload form -->
    <form method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <input type="hidden" name="csv_preview" value="1">

        <div id="drop-zone"
             class="relative border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl p-6 text-center hover:border-brand-400 dark:hover:border-brand-500 transition cursor-pointer">
            <input type="file" name="csv" id="csv-input" accept=".csv,text/csv"
                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
            <div id="drop-content">
                <i data-lucide="upload-cloud" class="w-9 h-9 mx-auto text-slate-300 dark:text-slate-600 mb-2"></i>
                <div class="text-sm font-medium text-slate-700 dark:text-slate-200">Click to browse, or drag a CSV here</div>
                <div class="text-xs text-slate-400 mt-1">Max 1 MB</div>
            </div>
            <div id="drop-filename" class="hidden text-sm font-medium text-brand-600 dark:text-brand-400"></div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                <i data-lucide="eye" class="w-4 h-4"></i> Preview matches
            </button>
        </div>
    </form>

    <?php if ($csvError): ?>
        <div class="mx-5 mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300">
            <?= htmlspecialchars($csvError) ?>
        </div>
    <?php endif; ?>

    <!-- Preview table -->
    <?php if ($csvPreview): ?>
        <div class="px-5 py-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 text-xs text-slate-600 dark:text-slate-400">
            <?= $csvStats['total'] ?> row(s) parsed ·
            <span class="text-emerald-600 dark:text-emerald-400"><?= $csvStats['ready'] ?> ready</span> ·
            <span class="text-amber-600 dark:text-amber-400"><?= $csvStats['already'] ?> skipped</span> ·
            <span class="text-rose-600 dark:text-rose-400"><?= $csvStats['notfound'] ?> not found</span> ·
            <span class="text-slate-500"><?= $csvStats['duplicate'] ?> duplicate</span>
        </div>

        <div class="overflow-x-auto max-h-[400px]">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 text-left text-xs uppercase tracking-wide sticky top-0">
                    <tr>
                        <th class="px-5 py-2.5 font-semibold">Reg no. (CSV)</th>
                        <th class="px-5 py-2.5 font-semibold">Student name</th>
                        <th class="px-5 py-2.5 font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <?php foreach ($csvPreview as $row):
                        $map = [
                            'ready'     => ['bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/40', 'Ready'],
                            'already'   => ['bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/40',         'Already enrolled'],
                            'notfound'  => ['bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/40',               'Not found'],
                            'duplicate' => ['bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',                 'Duplicate'],
                        ];
                        [$cls, $label] = $map[$row['status']] ?? $map['duplicate'];
                    ?>
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                        <td class="px-5 py-2 font-mono text-xs text-slate-700 dark:text-slate-300"><?= htmlspecialchars($row['reg']) ?></td>
                        <td class="px-5 py-2 text-slate-700 dark:text-slate-300"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="px-5 py-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset <?= $cls ?>"><?= $label ?></span>
                            <?php if ($row['note']): ?>
                                <span class="ml-2 text-xs text-slate-400 dark:text-slate-500"><?= htmlspecialchars($row['note']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($csvStats['ready'] > 0): ?>
        <form method="POST" class="px-5 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 flex flex-wrap items-center justify-between gap-3">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
            <input type="hidden" name="course_id" value="<?= $course_id ?>">
            <input type="hidden" name="csv_commit" value="1">
            <?php foreach ($csvPreview as $row):
                if ($row['status'] === 'ready' && $row['id']): ?>
                <input type="hidden" name="ready_ids[]" value="<?= (int)$row['id'] ?>">
            <?php endif; endforeach; ?>

            <div class="text-sm text-slate-600 dark:text-slate-300">
                Ready to enroll <strong class="text-slate-900 dark:text-white"><?= $csvStats['ready'] ?></strong> student(s).
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-700 shadow-sm transition">
                <i data-lucide="check" class="w-4 h-4"></i> Enroll <?= $csvStats['ready'] ?> now
            </button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ============ TWO-COLUMN LAYOUT ============ -->
<div class="grid lg:grid-cols-5 gap-6">

    <!-- ============ ENROLLED LIST (left, wider) ============ -->
    <div class="lg:col-span-3">
        <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden">

            <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-slate-900 dark:text-white">Enrolled students</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><?= count($enrolled) ?> on this course</p>
                </div>
                <span class="inline-flex items-center rounded-md bg-brand-50 dark:bg-brand-500/15 text-brand-700 dark:text-brand-400 px-2 py-1 text-xs font-semibold font-mono">
                    <?= htmlspecialchars($course['course_code']) ?>
                </span>
            </div>

            <!-- Live search -->
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

            <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-[600px] overflow-y-auto" id="roster-list">
                <?php if (!$enrolled): ?>
                    <div class="px-5 py-12 text-center text-slate-400 text-sm">
                        No students enrolled yet. Add some from the right, or use bulk CSV upload.
                    </div>
                <?php endif; ?>

                <?php foreach ($enrolled as $s): ?>
                <div class="px-5 py-3 flex items-center justify-between hover:bg-slate-50/70 dark:hover:bg-slate-800/50 roster-row"
                     data-search="<?= htmlspecialchars(strtolower($s['full_name'] . ' ' . $s['reg_number'] . ' ' . $s['email'])) ?>">
                    <div class="flex items-center gap-3 min-w-0">
                        <?php if (!empty($s['photo'])): ?>
                            <img src="../uploads/avatars/<?= htmlspecialchars($s['photo']) ?>"
                                 class="w-9 h-9 rounded-full object-cover shrink-0" alt="">
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
                    <form method="POST" action="enrollment_delete.php"
                          onsubmit="return confirm('Remove <?= htmlspecialchars($s['full_name']) ?> from this course?');">
                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                        <input type="hidden" name="course_id" value="<?= $course_id ?>">
                        <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
                        <button class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i> Remove
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>

                <!-- No-match message (hidden by default) -->
                <div id="roster-empty" class="hidden px-5 py-10 text-center text-slate-400 text-sm">
                    No matching students.
                </div>
            </div>
        </div>
    </div>

    <!-- ============ ADD STUDENTS (right, narrower) ============ -->
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

            <form method="POST" id="manual-form">
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                <input type="hidden" name="course_id" value="<?= $course_id ?>">
                <input type="hidden" name="manual_enroll" value="1">

                <div class="px-5 py-3 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input type="checkbox" id="checkAll" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        Select all
                    </label>
                    <span class="text-xs text-slate-400" id="selected-count">0 selected</span>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800 max-h-[400px] overflow-y-auto">
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
                    <button type="submit"
                            class="w-full rounded-lg bg-brand-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed"
                            id="enroll-btn" disabled>
                        Enroll selected
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ============ SCRIPTS ============ -->
<script>
    // ---------- Select-all + counter ----------
    const checkAll = document.getElementById('checkAll');
    const checks   = document.querySelectorAll('.student-check');
    const counter  = document.getElementById('selected-count');
    const enrollBtn = document.getElementById('enroll-btn');

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

    // ---------- Roster live search ----------
    const searchInput = document.getElementById('roster-search');
    if (searchInput) {
        searchInput.addEventListener('input', e => {
            const q = e.target.value.toLowerCase().trim();
            let visible = 0;
            document.querySelectorAll('.roster-row').forEach(row => {
                const match = row.dataset.search.includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            const emptyMsg = document.getElementById('roster-empty');
            if (emptyMsg) emptyMsg.classList.toggle('hidden', visible > 0);
        });
    }

    // ---------- CSV drop zone ----------
    const csvInput = document.getElementById('csv-input');
    if (csvInput) {
        const fileName = document.getElementById('drop-filename');
        const dropContent = document.getElementById('drop-content');
        csvInput.addEventListener('change', e => {
            const f = e.target.files[0];
            if (!f) return;
            dropContent.classList.add('hidden');
            fileName.classList.remove('hidden');
            fileName.textContent = '📄 ' + f.name + ' (' + Math.round(f.size / 1024) + ' KB)';
        });
    }
</script>

<?php endif; ?>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>