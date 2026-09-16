<?php
/**
 * Reusable weekly timetable widget.
 * Expects: $pdo, and $timetableSlots (array of rows from timetables table).
 * Optionally: $timetableTitle (string).
 */
if (!isset($timetableSlots)) {
    $timetableSlots = [];
}

$__dayNames = [1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday', 6=>'Saturday', 7=>'Sunday'];
$__byDay = [];
for ($d = 1; $d <= 7; $d++) $__byDay[$d] = [];
foreach ($timetableSlots as $s) {
    $__byDay[(int)$s['day_of_week']][] = $s;
}
?>

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <h2 class="font-semibold text-slate-900 dark:text-white">
            <?= htmlspecialchars($timetableTitle ?? 'Weekly timetable') ?>
        </h2>
        <span class="text-xs text-slate-500 dark:text-slate-400">
            <?= count($timetableSlots) ?> class<?= count($timetableSlots) === 1 ? '' : 'es' ?> per week
        </span>
    </div>

    <?php if (!$timetableSlots): ?>
        <div class="p-10 text-center text-slate-400 text-sm">
            No classes scheduled yet.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-7 divide-y md:divide-y-0 md:divide-x divide-slate-100 dark:divide-slate-800">
            <?php foreach ($__dayNames as $dayNum => $dayLabel):
                $daySlots = $__byDay[$dayNum];
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
                            $end   = substr($s['end_time'], 0, 5);
                        ?>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 p-3">
                            <div class="text-sm font-semibold text-slate-900 dark:text-white tabular-nums">
                                <?= htmlspecialchars($start) ?> – <?= htmlspecialchars($end) ?>
                            </div>
                            <?php if (!empty($s['course_code'])): ?>
                                <div class="text-xs font-mono text-brand-600 dark:text-brand-400 mt-0.5">
                                    <?= htmlspecialchars($s['course_code']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($s['course_name']) && empty($hideCourseName)): ?>
                                <div class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                                    <?= htmlspecialchars($s['course_name']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($s['room']): ?>
                                <div class="text-xs text-slate-500 dark:text-slate-500 mt-1 inline-flex items-center gap-1">
                                    <i data-lucide="map-pin" class="w-3 h-3"></i>
                                    <?= htmlspecialchars($s['room']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($s['notes']): ?>
                                <div class="text-xs text-slate-500 dark:text-slate-500 mt-1 italic">
                                    <?= htmlspecialchars($s['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>