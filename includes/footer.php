<?php
/**
 * Shared footer.
 *
 * Automatically renders a marketing-style footer on public pages
 * (landing, login, register) and a lightweight copyright line
 * on internal dashboards (admin, lecturer, student, profile).
 *
 * Options (set before requiring):
 *   $footerStyle   = 'on-dark'  → white text for dark/login pages
 *   $showDocsLink  = false      → hide the documentation links
 */

if (!isset($pdo)) return;

$__footer = app_settings($pdo);
if (($__footer['footer_enabled'] ?? '1') !== '1') return;

// Detect which kind of page we're on
$scriptPath  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
$currentPage = basename($_SERVER['PHP_SELF']);
$isInternal  = (bool)preg_match('#/(admin|lecturer|student)/#', $scriptPath);
$isProfile   = ($currentPage === 'profile.php');
$isAuth      = (bool)preg_match('#/(auth)/#', $scriptPath);
$isLanding   = ($currentPage === 'index.php' || $scriptPath === '/smart_attend/' || $scriptPath === '/smart_attend');

$__dark      = ($footerStyle ?? '') === 'on-dark';
$__showDocs  = $showDocsLink ?? true;
$__text      = trim($__footer['footer_text'] ?? '');
$__sysName   = $__footer['system_name']         ?? 'SmartAttend';
$__instName  = $__footer['institution_name']    ?? $__sysName;

// Project-root prefix for links
$projectBase = '/smart_attend';
$deepPath    = substr($scriptPath, strlen($projectBase));
$depth       = substr_count(trim($deepPath, '/'), '/');
$prefix      = $depth > 0 ? str_repeat('../', $depth) : '';

// ---------- Colour tokens ----------
if ($__dark) {
    $textColor = 'text-white/70';
    $heading   = 'text-white';
    $linkColor = 'text-white/70 hover:text-white';
    $divider   = 'border-white/10';
} else {
    $textColor = 'text-slate-500 dark:text-slate-400';
    $heading   = 'text-slate-900 dark:text-white';
    $linkColor = 'text-slate-500 dark:text-slate-400 hover:text-brand-600 dark:hover:text-brand-400';
    $divider   = 'border-slate-200 dark:border-slate-800';
}

// =========================================================================
// INTERNAL PAGES (admin, lecturer, student, profile) → slim footer only
// =========================================================================
if ($isInternal || $isProfile):
?>
    <div class="mt-12 pt-6 border-t <?= $divider ?>">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs <?= $textColor ?>">
            <div class="text-center sm:text-left">
                &copy; <?= date('Y') ?>
                <span class="font-medium <?= $heading ?>"><?= htmlspecialchars($__instName) ?></span>.
                All rights reserved.
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5">
                    <i data-lucide="code-2" class="w-3.5 h-3.5"></i>
                    PHP &amp; MySQL
                </span>
                <span class="opacity-40">·</span>
                <span class="inline-flex items-center gap-1.5">
                    <i data-lucide="heart" class="w-3.5 h-3.5 text-rose-500"></i>
                    <?= htmlspecialchars($__sysName) ?>
                </span>
            </div>
        </div>
    </div>

<?php
// =========================================================================
// AUTH PAGES (login, register) → simple centered copyright
// =========================================================================
elseif ($isAuth):
?>
    <div class="py-6 text-center text-xs <?= $textColor ?>">
        &copy; <?= date('Y') ?>
        <span class="font-medium"><?= htmlspecialchars($__instName) ?></span>.
        All rights reserved.
    </div>

<?php
// =========================================================================
// LANDING PAGE (index.php) → full marketing footer
// =========================================================================
else:
    // Institution contact fields
    $__instAddr  = trim($__footer['institution_address'] ?? '');
    $__instPhone = trim($__footer['institution_phone']   ?? '');
    $__instEmail = trim($__footer['institution_email']   ?? '');
    $__instWeb   = trim($__footer['institution_website'] ?? '');
    $__logo      = $__footer['system_logo']              ?? '';
?>

    <footer class="border-t <?= $divider ?>">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

            <!-- Top: brand + columns -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-12">

                <!-- Brand -->
                <div class="md:col-span-5 lg:col-span-4">
                    <div class="flex items-center gap-3 mb-4">
                        <?php if ($__logo): ?>
                            <img src="<?= $prefix ?>uploads/branding/<?= htmlspecialchars($__logo) ?>"
                                 class="w-10 h-10 rounded-xl object-cover" alt="">
                        <?php else: ?>
                            <div class="w-10 h-10 rounded-xl bg-brand-600 grid place-items-center text-white font-bold text-lg">
                                <?= strtoupper(substr($__instName, 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="font-semibold <?= $heading ?> leading-tight">
                                <?= htmlspecialchars($__instName) ?>
                            </div>
                            <div class="text-xs <?= $textColor ?>">
                                <?= htmlspecialchars($__sysName) ?>
                            </div>
                        </div>
                    </div>

                    <p class="text-sm <?= $textColor ?> leading-relaxed max-w-sm">
                        <?= htmlspecialchars($__text ?: 'A QR-and-GPS verified attendance system for modern classrooms.') ?>
                    </p>

                    <?php if ($__instAddr || $__instPhone || $__instEmail): ?>
                    <div class="mt-5 space-y-2 text-xs <?= $textColor ?>">
                        <?php if ($__instAddr): ?>
                            <div class="flex items-start gap-2">
                                <i data-lucide="map-pin" class="w-3.5 h-3.5 mt-0.5 shrink-0"></i>
                                <span><?= htmlspecialchars($__instAddr) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($__instPhone): ?>
                            <div class="flex items-center gap-2">
                                <i data-lucide="phone" class="w-3.5 h-3.5 shrink-0"></i>
                                <span><?= htmlspecialchars($__instPhone) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($__instEmail): ?>
                            <div class="flex items-center gap-2">
                                <i data-lucide="mail" class="w-3.5 h-3.5 shrink-0"></i>
                                <a href="mailto:<?= htmlspecialchars($__instEmail) ?>" class="<?= $linkColor ?>">
                                    <?= htmlspecialchars($__instEmail) ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <?php if ($__instWeb): ?>
                            <div class="flex items-center gap-2">
                                <i data-lucide="globe" class="w-3.5 h-3.5 shrink-0"></i>
                                <span><?= htmlspecialchars($__instWeb) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="hidden md:block md:col-span-1 lg:col-span-2"></div>

                <!-- Product -->
                <div class="md:col-span-3 lg:col-span-2">
                    <div class="text-xs font-semibold uppercase tracking-wider <?= $heading ?> mb-3">Product</div>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="<?= $prefix ?>#features" class="<?= $linkColor ?> transition">Features</a></li>
                        <li><a href="<?= $prefix ?>#how"      class="<?= $linkColor ?> transition">How it works</a></li>
                        <li><a href="<?= $prefix ?>#roles"    class="<?= $linkColor ?> transition">For whom</a></li>
                        <li><a href="<?= $prefix ?>#faq"      class="<?= $linkColor ?> transition">FAQ</a></li>
                    </ul>
                </div>

                <!-- Account -->
                <div class="md:col-span-3 lg:col-span-2">
                    <div class="text-xs font-semibold uppercase tracking-wider <?= $heading ?> mb-3">Account</div>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="<?= $prefix ?>auth/login.php"    class="<?= $linkColor ?> transition">Sign in</a></li>
                        <li><a href="<?= $prefix ?>auth/register.php" class="<?= $linkColor ?> transition">Register</a></li>
                        <li><a href="<?= $prefix ?>#demo"             class="<?= $linkColor ?> transition">Book a demo</a></li>
                    </ul>
                </div>

                <!-- Docs -->
                <?php if ($__showDocs): ?>
                <div class="md:col-span-3 lg:col-span-2">
                    <div class="text-xs font-semibold uppercase tracking-wider <?= $heading ?> mb-3">Documentation</div>
                    <ul class="space-y-2.5 text-sm">
                        <li>
                            <a href="<?= $prefix ?>docs/Smart_Attend_User_Manual.pdf" target="_blank" rel="noopener"
                               class="<?= $linkColor ?> transition inline-flex items-center gap-2">
                                <i data-lucide="book-open" class="w-3.5 h-3.5"></i> User Manual
                            </a>
                        </li>
                        <li>
                            <a href="<?= $prefix ?>docs/QUICK_START_Student.pdf" target="_blank" rel="noopener"
                               class="<?= $linkColor ?> transition inline-flex items-center gap-2">
                                <i data-lucide="graduation-cap" class="w-3.5 h-3.5"></i> For Students
                            </a>
                        </li>
                        <li>
                            <a href="<?= $prefix ?>docs/QUICK_START_Lecturer.pdf" target="_blank" rel="noopener"
                               class="<?= $linkColor ?> transition inline-flex items-center gap-2">
                                <i data-lucide="presentation" class="w-3.5 h-3.5"></i> For Lecturers
                            </a>
                        </li>
                        <li>
                            <a href="<?= $prefix ?>docs/QUICK_START_Admin.pdf" target="_blank" rel="noopener"
                               class="<?= $linkColor ?> transition inline-flex items-center gap-2">
                                <i data-lucide="shield" class="w-3.5 h-3.5"></i> For Admins
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>

            </div>

            <!-- Bottom row -->
            <div class="mt-10 pt-6 border-t <?= $divider ?> flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-xs <?= $textColor ?> text-center sm:text-left">
                    &copy; <?= date('Y') ?>
                    <span class="font-medium <?= $heading ?>"><?= htmlspecialchars($__instName) ?></span>.
                    All rights reserved.
                </div>
                <div class="flex items-center gap-3 text-xs <?= $textColor ?>">
                    <span>Built with</span>
                    <span class="inline-flex items-center gap-1.5">
                        <i data-lucide="code-2" class="w-3.5 h-3.5"></i> PHP &amp; MySQL
                    </span>
                    <span class="opacity-40">·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <i data-lucide="heart" class="w-3.5 h-3.5 text-rose-500"></i> <?= htmlspecialchars($__sysName) ?>
                    </span>
                </div>
            </div>

        </div>
    </footer>
<?php endif; ?>

<script>
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
</script>