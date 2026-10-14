<?php
$pageTitle = 'Dashboard';
require __DIR__ . '/partials/admin_header.php';

$stats = [
    ['label'=>'Students',   'value'=>$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),   'icon'=>'graduation-cap', 'color'=>'brand'],
    ['label'=>'Lecturers',  'value'=>$pdo->query("SELECT COUNT(*) FROM users WHERE role='lecturer'")->fetchColumn(),  'icon'=>'presentation',   'color'=>'amber'],
    ['label'=>'Courses',    'value'=>$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),                    'icon'=>'book-open',      'color'=>'emerald'],
    ['label'=>'Sessions',   'value'=>$pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn(),                   'icon'=>'calendar',       'color'=>'violet'],
    ['label'=>'Records',    'value'=>$pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn(),                 'icon'=>'check-circle-2', 'color'=>'rose'],
];

$recent = $pdo->query("
    SELECT a.marked_at, u.full_name, u.reg_number, c.course_code, a.status, a.distance_m
    FROM attendance a
    JOIN users u ON u.id = a.student_id
    JOIN sessions s ON s.id = a.session_id
    JOIN courses c ON c.id = s.course_id
    ORDER BY a.marked_at DESC LIMIT 10
")->fetchAll();
?>

<!-- Header -->
<div class="mb-6">
    <h1 class="text-xl font-bold tracking-tight text-slate-900">Dashboard</h1>
    <p class="mt-0.5 text-xs text-slate-500">Overview of attendance activity across the system.</p>
</div>

<!-- Stat cards — 5 across, tight -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
    <?php foreach ($stats as $s):
        $colorMap = [
            'brand'   => 'bg-brand-50 text-brand-700',
            'amber'   => 'bg-amber-50 text-amber-700',
            'emerald' => 'bg-emerald-50 text-emerald-700',
            'violet'  => 'bg-violet-50 text-violet-700',
            'rose'    => 'bg-rose-50 text-rose-700',
        ];
        $cls = $colorMap[$s['color']] ?? 'bg-slate-100 text-slate-700';
    ?>
    <div class="bg-white rounded-lg border border-slate-200 shadow-card p-4">
        <div class="w-8 h-8 rounded-md grid place-items-center <?= $cls ?> mb-2.5">
            <i data-lucide="<?= $s['icon'] ?>" class="w-4 h-4"></i>
        </div>
        <div class="text-xl font-bold text-slate-900 tabular-nums"><?= (int)$s['value'] ?></div>
        <div class="text-[10px] uppercase tracking-wider text-slate-500 mt-0.5"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent activity -->
<div class="bg-white rounded-lg border border-slate-200 shadow-card overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900">Recent Attendance Activity</h2>
        <a href="reports.php" class="text-xs text-brand-600 hover:text-brand-700 font-medium">View reports →</a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 text-slate-500 text-left text-[10px] uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Time</th>
                    <th class="px-4 py-2.5 font-semibold">Student</th>
                    <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Course</th>
                    <th class="px-4 py-2.5 font-semibold">Status</th>
                    <th class="px-4 py-2.5 font-semibold text-right whitespace-nowrap">Distance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (!$recent): ?>
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No attendance recorded yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($recent as $r): ?>
                <tr class="hover:bg-slate-50/70">
                    <td class="px-4 py-2.5 text-slate-500 whitespace-nowrap tabular-nums"><?= htmlspecialchars($r['marked_at']) ?></td>
                    <td class="px-4 py-2.5">
                        <div class="font-medium text-slate-900 whitespace-nowrap"><?= htmlspecialchars($r['full_name']) ?></div>
                        <div class="text-[10px] text-slate-500 font-mono"><?= htmlspecialchars($r['reg_number']) ?></div>
                    </td>
                    <td class="px-4 py-2.5">
                        <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-700 font-mono">
                            <?= htmlspecialchars($r['course_code']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2.5">
                        <?php
                        $badgeCls = [
                            'present' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
                            'late'    => 'bg-amber-100 text-amber-800 ring-amber-200',
                            'absent'  => 'bg-rose-100 text-rose-700 ring-rose-200',
                        ][$r['status']] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
                        ?>
                        <span class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium ring-1 ring-inset <?= $badgeCls ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-right text-slate-500 tabular-nums">
                        <?= $r['distance_m'] !== null ? $r['distance_m'].' m' : '—' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<?php require __DIR__ . '/partials/admin_footer.php'; ?>