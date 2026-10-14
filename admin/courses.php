<?php
$pageTitle = 'Courses';
require __DIR__ . '/partials/admin_header.php';

$courses = $pdo->query("
    SELECT c.*, u.full_name AS lecturer_name,
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) AS students
    FROM courses c
    LEFT JOIN users u ON u.id = c.lecturer_id
    ORDER BY c.course_code
")->fetchAll();

$title    = 'Courses';
$subtitle = count($courses) . ' course' . (count($courses) === 1 ? '' : 's') . ' available';
$action   = ['label' => 'New course', 'href' => 'course_form.php', 'icon' => 'plus'];
require __DIR__ . '/partials/page_header.php';
?>

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-950 text-slate-500 dark:text-slate-400 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 font-semibold">Course</th>
                    <th class="px-5 py-3 font-semibold">Lecturer</th>
                    <th class="px-5 py-3 font-semibold text-center">Required</th>
                    <th class="px-5 py-3 font-semibold text-center">Students</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if (!$courses): ?>
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-400">
                            No courses yet. Click <strong>New course</strong> to create the first one.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($courses as $c):
                    $required = (int)($c['required_attendance'] ?? 75);
                    if ($required >= 90) {
                        $badgeCls = 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/40';
                    } elseif ($required >= 75) {
                        $badgeCls = 'bg-amber-100 text-amber-800 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/40';
                    } else {
                        $badgeCls = 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/40';
                    }
                ?>
                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/50">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($c['image'])): ?>
                                <img src="../uploads/courses/<?= htmlspecialchars($c['image']) ?>"
                                     class="w-12 h-12 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0"
                                     alt="">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 grid place-items-center shrink-0 text-slate-400">
                                    <i data-lucide="book-open" class="w-5 h-5"></i>
                                </div>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-md bg-brand-50 dark:bg-brand-500/15 text-brand-700 dark:text-brand-400 px-2 py-1 text-xs font-semibold font-mono">
                                        <?= htmlspecialchars($c['course_code']) ?>
                                    </span>
                                </div>
                                <div class="font-medium text-slate-900 dark:text-white mt-1 truncate">
                                    <?= htmlspecialchars($c['course_name']) ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <?php if ($c['lecturer_name']): ?>
                            <span class="text-slate-700 dark:text-slate-300"><?= htmlspecialchars($c['lecturer_name']) ?></span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10 rounded-md px-2 py-0.5">
                                <i data-lucide="alert-circle" class="w-3 h-3"></i> Unassigned
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset tabular-nums <?= $badgeCls ?>">
                            <?= $required ?>%
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center tabular-nums text-slate-700 dark:text-slate-300">
                        <?= (int)$c['students'] ?>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="inline-flex items-center gap-1">
                            <a href="enrollments.php?course_id=<?= $c['id'] ?>"
                               class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Enroll
                            </a>
                            <a href="reports.php?course_id=<?= $c['id'] ?>"
                               class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                <i data-lucide="bar-chart-3" class="w-3.5 h-3.5"></i> Reports
                            </a>
                            <a href="course_form.php?id=<?= $c['id'] ?>"
                               class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
                            </a>
                            <form method="POST" action="course_delete.php" class="inline"
                                  onsubmit="return confirm('Delete course and all its sessions? This cannot be undone.');">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                <button class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>