<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/ui.php';
require_role('admin');

$brand = app_settings($pdo);
$current = basename($_SERVER['PHP_SELF']);

$navItems = [
    ['dashboard.php',   'Dashboard',   'layout-dashboard'],
    ['users.php',       'Users',       'users'],
    ['courses.php',     'Courses',     'book-open'],
    ['enrollments.php', 'Enrollments', 'user-plus'],
    ['timetables.php',  'Timetables',  'calendar-days'],
    ['bulk_enroll.php', 'Bulk enroll', 'upload'],
    ['build_docs.php',  'Docs',        'file-text'],
    ['reports.php',     'Reports',     'bar-chart-3'],
    ['settings.php',    'Settings',    'settings'],
];

// Load current user's photo for sidebar
$__stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
$__stmt->execute([$_SESSION['user_id']]);
$__myPhoto = $__stmt->fetchColumn();

$pageTitle = $pageTitle ?? 'Admin';
require __DIR__ . '/../../includes/head.php';
?>
<div class="min-h-screen flex flex-col md:flex-row transition-colors">

    <!-- Sidebar -->
    <aside class="md:w-64 md:shrink-0 bg-slate-900 text-slate-300 md:min-h-screen dark:bg-slate-950 dark:border-r dark:border-slate-800">
        <div class="flex md:flex-col items-center md:items-stretch gap-3 p-4 md:p-6 border-b border-slate-800">
            <a href="dashboard.php" class="flex items-center gap-3">
                <?php if (!empty($brand['system_logo'])): ?>
                    <img src="../uploads/branding/<?= htmlspecialchars($brand['system_logo']) ?>"
                         class="w-9 h-9 rounded-xl object-cover" alt="">
                <?php else: ?>
                    <div class="w-9 h-9 rounded-xl bg-brand-500 grid place-items-center text-white font-bold">
                        <?= strtoupper(substr($brand['system_name'] ?? 'S', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="hidden md:block">
                    <div class="text-white font-semibold leading-tight">
                        <?= htmlspecialchars($brand['system_name'] ?? 'SmartAttend') ?>
                    </div>
                    <div class="text-xs text-slate-400">Admin Console</div>
                </div>
            </a>
        </div>

        <nav class="flex md:flex-col overflow-x-auto md:overflow-visible no-scrollbar px-2 md:px-3 py-2 md:py-4 gap-1">
            <?php foreach ($navItems as [$href, $label, $icon]): ?>
                <?php $active = $current === $href; ?>
                <a href="<?= $href ?>"
                   class="flex items-center gap-3 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition
                          <?= $active
                              ? 'bg-brand-600 text-white shadow-sm'
                              : 'text-slate-300 hover:bg-slate-800 hover:text-white' ?>">
                    <i data-lucide="<?= $icon ?>" class="w-4 h-4"></i>
                    <span><?= $label ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="hidden md:block mt-auto p-4 border-t border-slate-800">
            <a href="../profile.php"
               class="flex items-center gap-3 p-2 -m-2 rounded-lg hover:bg-slate-800 transition group">
                <?php if ($__myPhoto): ?>
                    <img src="../uploads/avatars/<?= htmlspecialchars($__myPhoto) ?>"
                         class="w-9 h-9 rounded-full object-cover ring-2 ring-transparent group-hover:ring-brand-500 transition" alt="">
                <?php else: ?>
                    <div class="w-9 h-9 rounded-full bg-slate-700 grid place-items-center text-sm font-semibold text-white ring-2 ring-transparent group-hover:ring-brand-500 transition">
                        <?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="min-w-0">
                    <div class="text-sm text-white truncate"><?= htmlspecialchars($_SESSION['name']) ?></div>
                    <div class="text-xs text-slate-400 group-hover:text-brand-400 transition">View profile</div>
                </div>
            </a>
            <a href="../auth/logout.php"
               class="mt-3 flex items-center justify-center gap-2 w-full rounded-lg bg-slate-800 hover:bg-rose-600 text-white text-sm py-2 transition">
                <i data-lucide="log-out" class="w-4 h-4"></i> Sign out
            </a>
        </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 min-w-0">

        <!-- Top bar (mobile + theme toggle) -->
        <div class="flex items-center justify-between bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 py-3">
            <div class="md:hidden font-semibold text-slate-900 dark:text-white">
                <?= htmlspecialchars($brand['system_name'] ?? 'SmartAttend') ?> · Admin
            </div>

            <div class="flex items-center gap-2 ml-auto">
                <!-- Theme toggle -->
                <button id="theme-toggle" type="button"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                        aria-label="Toggle theme" title="Toggle theme">
                    <i data-lucide="sun"  class="w-4 h-4 hidden dark:block"></i>
                    <i data-lucide="moon" class="w-4 h-4 block dark:hidden"></i>
                </button>

                <a href="../profile.php" class="md:hidden">
                    <?php if ($__myPhoto): ?>
                        <img src="../uploads/avatars/<?= htmlspecialchars($__myPhoto) ?>" class="w-8 h-8 rounded-full object-cover" alt="">
                    <?php else: ?>
                        <div class="w-8 h-8 rounded-full bg-brand-500 grid place-items-center text-xs font-semibold text-white">
                            <?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </a>
                <a href="../auth/logout.php" class="md:hidden text-xs text-rose-600 dark:text-rose-400">Logout</a>
            </div>
        </div>

        <main class="p-4 sm:p-6 lg:p-6 w-full">
            <?php flash_banner(); ?>