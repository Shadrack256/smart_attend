<?php
require_once __DIR__ . '/../config/db.php';
require_role('admin');

$courses = $pdo->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code")->fetchAll();

// ---------- Handle CSV upload + preview ----------
$course_id   = (int)($_POST['course_id'] ?? 0);
$preview     = [];      // each row: ['reg' => ..., 'student_id' => ..., 'name' => ..., 'status' => 'ready|already|notfound']
$totalRows   = 0;
$readyCount  = 0;
$alreadyCount= 0;
$notFoundCount = 0;
$parseError  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $course_id) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $parseError = 'Invalid request. Please reload and try again.';
    } elseif (empty($_FILES['csv']['tmp_name']) ||
              ($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $parseError = 'Please choose a CSV file to upload.';
    } elseif ($_FILES['csv']['size'] > 1024 * 1024) {
        $parseError = 'CSV file is too large (max 1 MB).';
    } else {
        // Confirm the course exists
        $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        if (!$stmt->fetch()) {
            $parseError = 'Selected course not found.';
        } else {
            // Read + parse the file
            $handle = fopen($_FILES['csv']['tmp_name'], 'r');
            if (!$handle) {
                $parseError = 'Could not read the uploaded file.';
            } else {
                $lineNo = 0;
                $seenRegs = [];   // dedupe within the file itself

                while (($row = fgetcsv($handle)) !== false) {
                    $lineNo++;

                    // Skip header row (line 1) — we assume any first-line value
                    if ($lineNo === 1) continue;

                    // Take the first non-empty cell
                    $reg = '';
                    foreach ($row as $cell) {
                        $cell = trim((string)$cell);
                        if ($cell !== '') { $reg = $cell; break; }
                    }
                    if ($reg === '') continue;

                    $totalRows++;
                    $regKey = strtolower($reg);

                    // Skip duplicates within the file
                    if (isset($seenRegs[$regKey])) {
                        $alreadyCount++;
                        $preview[] = [
                            'reg' => $reg,
                            'student_id' => null,
                            'name' => '—',
                            'status' => 'duplicate',
                            'note' => 'Duplicate of line ' . $seenRegs[$regKey] . ' in this file',
                        ];
                        continue;
                    }
                    $seenRegs[$regKey] = $lineNo;

                    // Look up the student
                    $stmt = $pdo->prepare("
                        SELECT id, full_name, reg_number
                        FROM users
                        WHERE role = 'student' AND LOWER(reg_number) = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$regKey]);
                    $student = $stmt->fetch();

                    if (!$student) {
                        $notFoundCount++;
                        $preview[] = [
                            'reg' => $reg,
                            'student_id' => null,
                            'name' => '—',
                            'status' => 'notfound',
                            'note' => 'No student with this registration number',
                        ];
                        continue;
                    }

                    // Already enrolled?
                    $stmt = $pdo->prepare("SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ?");
                    $stmt->execute([$student['id'], $course_id]);
                    if ($stmt->fetch()) {
                        $alreadyCount++;
                        $preview[] = [
                            'reg' => $reg,
                            'student_id' => $student['id'],
                            'name' => $student['full_name'],
                            'status' => 'already',
                            'note' => 'Already enrolled',
                        ];
                        continue;
                    }

                    $readyCount++;
                    $preview[] = [
                        'reg' => $reg,
                        'student_id' => $student['id'],
                        'name' => $student['full_name'],
                        'status' => 'ready',
                        'note' => 'Will be enrolled',
                    ];
                }
                fclose($handle);
            }
        }
    }
}

// ---------- Handle commit (final enrollment) ----------
$commitMessage = '';
$commitType    = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commit']) && $course_id) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $commitMessage = 'Invalid request.';
        $commitType = 'error';
    } else {
        // Re-parse the IDs that were marked "ready" during preview
        $idsToEnroll = array_filter(array_map('intval', $_POST['ready_ids'] ?? []));

        if (!$idsToEnroll) {
            $commitMessage = 'No students to enroll.';
            $commitType = 'info';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)");
                $inserted = 0;
                foreach ($idsToEnroll as $sid) {
                    $stmt->execute([$sid, $course_id]);
                    if ($stmt->rowCount() > 0) $inserted++;
                }
                $pdo->commit();

                $_SESSION['flash'] = [
                    'type' => 'success',
                    'msg'  => "Enrolled {$inserted} student(s) into the selected course."
                ];
                header("Location: enrollments.php?course_id={$course_id}");
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $commitMessage = 'Database error: ' . $e->getMessage();
                $commitType = 'error';
            }
        }
    }
}

// ---------- Download template ----------
if (isset($_GET['template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="enrollment_template.csv"');
    echo "reg_number\n";
    echo "2025/ITB/DAY/1790/G\n";
    echo "2025/ITB/DAY/0268/P\n";
    echo "2025/ITB/DAY/2303/G\n";
    exit;
}

$pageTitle = 'Bulk enrollment';
require __DIR__ . '/partials/admin_header.php';

$title = 'Bulk enrollment';
$subtitle = 'Upload a CSV of registration numbers to enroll a whole class at once.';
require __DIR__ . '/partials/page_header.php';
?>

<?php if ($commitMessage): ?>
    <div class="mb-5 rounded-xl border px-4 py-3 text-sm
        <?= $commitType === 'error'
            ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300'
            : 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/40 dark:bg-sky-500/10 dark:text-sky-300' ?>">
        <?= htmlspecialchars($commitMessage) ?>
    </div>
<?php endif; ?>

<!-- ============ UPLOAD FORM ============ -->
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card mb-6">
    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-semibold text-slate-900 dark:text-white">Upload a CSV</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                One registration number per line, with a header row. Matching is case-insensitive.
            </p>
        </div>
        <a href="bulk_enroll.php?template=1"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
            <i data-lucide="download" class="w-3.5 h-3.5"></i> Download template
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Course</label>
            <select name="course_id" required
                    class="w-full max-w-md rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">— Select a course —</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $course_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['course_code'] . ' — ' . $c['course_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">CSV file</label>
            <div id="drop-zone"
                 class="relative border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center hover:border-brand-400 dark:hover:border-brand-500 transition cursor-pointer">
                <input type="file" name="csv" id="csv-input" accept=".csv,text/csv"
                       class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                <div id="drop-content">
                    <i data-lucide="upload-cloud" class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600 mb-2"></i>
                    <div class="text-sm font-medium text-slate-700 dark:text-slate-200">
                        Click to browse, or drag a CSV here
                    </div>
                    <div class="text-xs text-slate-400 mt-1">Max 1 MB</div>
                </div>
                <div id="drop-filename" class="hidden text-sm font-medium text-brand-600 dark:text-brand-400"></div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-5 py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                <i data-lucide="eye" class="w-4 h-4"></i> Preview matches
            </button>
        </div>
    </form>
</div>

<?php if ($parseError): ?>
    <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-300">
        <?= htmlspecialchars($parseError) ?>
    </div>
<?php endif; ?>

<!-- ============ PREVIEW ============ -->
<?php if ($preview): ?>
<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden">

    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
        <h2 class="font-semibold text-slate-900 dark:text-white">Preview</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            <?= $totalRows ?> row(s) parsed ·
            <span class="text-emerald-600 dark:text-emerald-400"><?= $readyCount ?> ready</span> ·
            <span class="text-amber-600 dark:text-amber-400"><?= $alreadyCount ?> skipped</span> ·
            <span class="text-rose-600 dark:text-rose-400"><?= $notFoundCount ?> not found</span>
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 font-semibold">Reg no. (from CSV)</th>
                    <th class="px-5 py-3 font-semibold">Student name</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($preview as $row):
                    $statusMap = [
                        'ready'     => ['bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/40', 'Ready'],
                        'already'   => ['bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/40',         'Already enrolled'],
                        'notfound'  => ['bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/40',               'Not found'],
                        'duplicate' => ['bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',                 'Duplicate'],
                    ];
                    [$cls, $label] = $statusMap[$row['status']] ?? $statusMap['duplicate'];
                ?>
                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                    <td class="px-5 py-2.5 font-mono text-xs text-slate-700 dark:text-slate-300"><?= htmlspecialchars($row['reg']) ?></td>
                    <td class="px-5 py-2.5 text-slate-700 dark:text-slate-300"><?= htmlspecialchars($row['name']) ?></td>
                    <td class="px-5 py-2.5">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset <?= $cls ?>">
                            <?= $label ?>
                        </span>
                        <?php if (!empty($row['note'])): ?>
                            <span class="ml-2 text-xs text-slate-400 dark:text-slate-500"><?= htmlspecialchars($row['note']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Commit form -->
    <?php if ($readyCount > 0): ?>
    <form method="POST" class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 flex flex-wrap items-center justify-between gap-3">
        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <input type="hidden" name="commit" value="1">
        <?php foreach ($preview as $row):
            if ($row['status'] === 'ready' && $row['student_id']): ?>
            <input type="hidden" name="ready_ids[]" value="<?= (int)$row['student_id'] ?>">
        <?php endif; endforeach; ?>

        <div class="text-sm text-slate-600 dark:text-slate-300">
            Ready to enroll <strong class="text-slate-900 dark:text-white"><?= $readyCount ?></strong> student(s).
        </div>
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 text-white px-5 py-2.5 text-sm font-semibold hover:bg-emerald-700 shadow-sm transition">
            <i data-lucide="check" class="w-4 h-4"></i>
            Enroll <?= $readyCount ?> student<?= $readyCount === 1 ? '' : 's' ?>
        </button>
    </form>
    <?php else: ?>
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/50 text-sm text-slate-500 dark:text-slate-400">
        No students are ready to enroll. Fix the CSV and upload again.
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
    const input = document.getElementById('csv-input');
    if (input) {
        const fileName = document.getElementById('drop-filename');
        const dropContent = document.getElementById('drop-content');
        input.addEventListener('change', e => {
            const f = e.target.files[0];
            if (!f) return;
            dropContent.classList.add('hidden');
            fileName.classList.remove('hidden');
            fileName.textContent = '📄 ' + f.name + ' (' + Math.round(f.size / 1024) + ' KB)';
        });
    }
</script>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>