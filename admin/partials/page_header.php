<?php
// Provide safe defaults so this file never emits warnings
$title    = $title    ?? '';
$subtitle = $subtitle ?? '';
$action   = $action   ?? null;
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            <?= htmlspecialchars($title) ?>
        </h1>
        <?php if ($subtitle !== ''): ?>
            <p class="mt-1 text-sm text-slate-500"><?= htmlspecialchars($subtitle) ?></p>
        <?php endif; ?>
    </div>

    <?php if (is_array($action) && !empty($action['label']) && !empty($action['href'])): ?>
        <a href="<?= htmlspecialchars($action['href']) ?>"
           class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 transition">
            <?php if (!empty($action['icon'])): ?>
                <i data-lucide="<?= htmlspecialchars($action['icon']) ?>" class="w-4 h-4"></i>
            <?php endif; ?>
            <?= htmlspecialchars($action['label']) ?>
        </a>
    <?php endif; ?>
</div>