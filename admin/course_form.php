<?php
$pageTitle = 'Course';
require __DIR__ . '/partials/admin_header.php';
require_once __DIR__ . '/../includes/avatar.php';

$id = (int)($_GET['id'] ?? 0);
$edit = false;

// Default shape for a new course
$course = [
    'course_code'         => '',
    'course_name'         => '',
    'required_attendance' => (int)setting($pdo, 'default_required_attendance', 75),
    'lecturer_id'         => '',
    'image'               => null,
];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) die("Course not found");
    $course = $row;
    $edit = true;
}

// Lecturer list for the dropdown
$lecturers = $pdo->query("
    SELECT id, full_name
    FROM users
    WHERE role = 'lecturer'
    ORDER BY full_name
")->fetchAll();

// Attendance threshold options
$thresholdOptions = [10, 20, 30, 40, 50, 60, 70, 75, 80, 85, 90, 95, 100];
$currentThreshold = (int)($course['required_attendance'] ?? 75);

$title    = $edit ? 'Edit course' : 'New course';
$subtitle = 'Provide the course details, assign a lecturer, and set the required attendance percentage.';
require __DIR__ . '/partials/page_header.php';
?>

<form method="POST" action="course_save.php" enctype="multipart/form-data" class="max-w-2xl">
    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
    <input type="hidden" name="id" value="<?= $edit ? $course['id'] : '' ?>">

    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card">
        <div class="p-6 space-y-5">

            <!-- ---------- Course image ---------- -->
            <div class="flex items-center gap-5">
                <div class="shrink-0 w-24 h-24 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 grid place-items-center overflow-hidden">
                    <?php if (!empty($course['image'])): ?>
                        <img id="image-preview" src="../uploads/courses/<?= htmlspecialchars($course['image']) ?>"
                             class="w-full h-full object-cover" alt="">
                    <?php else: ?>
                        <div id="image-preview" class="w-full h-full grid place-items-center text-slate-400">
                            <i data-lucide="image" class="w-8 h-8"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                        Course image <span class="text-slate-400">(optional)</span>
                    </label>
                    <input type="file" name="image" id="image-input"
                           accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-sm text-slate-600 dark:text-slate-400
                                  file:mr-3 file:py-2 file:px-4
                                  file:rounded-lg file:border-0
                                  file:text-sm file:font-medium
                                  file:bg-brand-50 dark:file:bg-brand-500/15
                                  file:text-brand-700 dark:file:text-brand-400
                                  hover:file:bg-brand-100">
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                        JPG, PNG, or WebP. Max 3 MB. Square or 16:9 images look best.
                    </p>

                    <?php if (!empty($course['image'])): ?>
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-rose-600 dark:text-rose-400 cursor-pointer">
                            <input type="checkbox" name="remove_image" value="1"
                                   class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            Remove current image
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ---------- Course code ---------- -->
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Course code</label>
                <input name="course_code" required maxlength="30"
                       value="<?= htmlspecialchars($course['course_code']) ?>"
                       placeholder="e.g. CSC101"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                    Short unique code (max 30 chars). Example: CSC101, BSM 2102.
                </p>
            </div>

            <!-- ---------- Course name ---------- -->
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Course name</label>
                <input name="course_name" required maxlength="150"
                       value="<?= htmlspecialchars($course['course_name']) ?>"
                       placeholder="e.g. Introduction to Computer Science"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                    Full course title as it appears on reports and registers.
                </p>
            </div>

            <!-- ---------- Required attendance ---------- -->
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    Required attendance percentage
                </label>
                <select name="required_attendance"
                        class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <?php foreach ($thresholdOptions as $t): ?>
                        <option value="<?= $t ?>" <?= $currentThreshold === $t ? 'selected' : '' ?>>
                            <?= $t ?>%
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                    Students below this percentage are flagged as at-risk on reports and in email warnings.
                </p>
            </div>

            <!-- ---------- Lecturer ---------- -->
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Lecturer</label>
                <select name="lecturer_id"
                        class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($lecturers as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= (int)$course['lecturer_id'] === (int)$l['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                    The lecturer who will run sessions and manage students for this course.
                </p>
            </div>

        </div>

        <!-- ---------- Actions ---------- -->
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-end gap-3">
            <a href="courses.php"
               class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                Cancel
            </a>
            <button class="rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                <?= $edit ? 'Save changes' : 'Create course' ?>
            </button>
        </div>
    </div>
</form>

<script>
    // Live image preview
    const imgInput = document.getElementById('image-input');
    if (imgInput) {
        imgInput.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = ev => {
                const old = document.getElementById('image-preview');
                const img = document.createElement('img');
                img.src = ev.target.result;
                img.id = 'image-preview';
                img.className = 'w-full h-full object-cover';
                old.replaceWith(img);
            };
            reader.readAsDataURL(file);
        });
    }
</script>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>