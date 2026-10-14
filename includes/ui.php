<?php
function flash_banner() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $type = $f['type'] ?? 'info';
        $styles = [
            'success' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'error'   => 'bg-rose-50 text-rose-800 border-rose-200',
            'info'    => 'bg-sky-50 text-sky-800 border-sky-200',
        ];
        $cls = $styles[$type] ?? $styles['info'];
        $icon = $type === 'success' ? 'check-circle'
              : ($type === 'error' ? 'alert-circle' : 'info');
        echo '<div class="mb-6 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm ' . $cls . '">'
           . '<i data-lucide="' . $icon . '" class="w-5 h-5 mt-0.5 shrink-0"></i>'
           . '<div>' . htmlspecialchars($f['msg']) . '</div></div>';
    }
}

function role_badge($role) {
    $map = [
        'student'  => 'bg-sky-100 text-sky-700 ring-sky-200',
        'lecturer' => 'bg-amber-100 text-amber-800 ring-amber-200',
        'admin'    => 'bg-rose-100 text-rose-700 ring-rose-200',
    ];
    $cls = $map[$role] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
    return '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ' . $cls . '">'
         . htmlspecialchars(ucfirst($role)) . '</span>';
}

function status_badge($status) {
    $map = [
        'present' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
        'late'    => 'bg-amber-100 text-amber-800 ring-amber-200',
        'absent'  => 'bg-rose-100 text-rose-700 ring-rose-200',
    ];
    $cls = $map[$status] ?? 'bg-slate-100 text-slate-700 ring-slate-200';
    return '<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ' . $cls . '">'
         . htmlspecialchars(ucfirst($status)) . '</span>';
}