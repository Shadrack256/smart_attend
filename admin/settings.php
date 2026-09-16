<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/avatar.php';
require_role('admin');

// ============================================================
// POST HANDLERS — must run BEFORE any HTML output
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die("Invalid request");
    }

    // ---------- Run backup ----------
    if (isset($_POST['run_backup'])) {
        $script = __DIR__ . '/../tools/backup_db.bat';
        if (!file_exists($script)) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Backup script not found. Expected at tools/backup_db.bat'];
        } else {
            // Launch in background so the request returns immediately
            pclose(popen('start /B "" "' . $script . '"', 'r'));
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Backup started in the background. Files will appear in /backups/ in a few seconds.'];
        }
        header("Location: settings.php");
        exit;
    }

    // ---------- Restore from backup ----------
    if (isset($_POST['restore_backup'])) {
        $file    = basename(trim($_POST['backup_file'] ?? ''));
        $confirm = trim($_POST['confirm_restore'] ?? '');

        $backupDir = realpath(__DIR__ . '/../backups/db');

        if (!$backupDir) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Backups folder not found at /backups/db/.'];
            header("Location: settings.php"); exit;
        }
        if ($file === '' || !preg_match('/^[A-Za-z0-9_\-\.]+\.sql$/', $file)) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Invalid backup filename.'];
            header("Location: settings.php"); exit;
        }
        if ($confirm !== 'RESTORE') {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'You must type RESTORE (all caps) to confirm.'];
            header("Location: settings.php"); exit;
        }

        $fullPath = $backupDir . DIRECTORY_SEPARATOR . $file;

        if (!is_file($fullPath)) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Backup file not found: ' . $file];
            header("Location: settings.php"); exit;
        }

        // Run the restore inline
        $mysql  = 'C:\xampp\mysql\bin\mysql.exe';
        $dbName = 'smart_attend';
        $dbUser = 'root';
        $dbPass = '';
        $pArg   = $dbPass !== '' ? '-p' . $dbPass : '';

        // 1) Drop + recreate database
        $dropCmd = sprintf(
            '"%s" -u %s %s -e "DROP DATABASE IF EXISTS %s; CREATE DATABASE %s CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"',
            $mysql, $dbUser, $pArg, $dbName, $dbName
        );
        @exec($dropCmd . ' 2>&1', $dropOut, $dropCode);

        if ($dropCode !== 0) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Failed to recreate the database. Check MySQL credentials in settings.php.'];
            header("Location: settings.php"); exit;
        }

        // 2) Import the backup
        $importCmd = sprintf(
            '"%s" -u %s %s %s < "%s"',
            $mysql, $dbUser, $pArg, $dbName, $fullPath
        );
        @exec($importCmd . ' 2>&1', $impOut, $impCode);

        if ($impCode !== 0) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Restore failed during import. The database may be empty — try restoring from another backup.'];
            header("Location: settings.php"); exit;
        }

        // 3) Success → log out (session may now be invalid)
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
        }
        session_destroy();

        // Start a fresh session just to carry the flash message
        session_start();
        session_regenerate_id(true);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Restore complete from ' . $file . '. Please log in again.'];
        header("Location: ../auth/login.php");
        exit;
    }

    // ---------- Normal settings save ----------
    // Geofence
    $geoEnabled = isset($_POST['geofence_enabled']) ? '1' : '0';
    $radius     = max(20, min(1000, (int)($_POST['default_radius_m'] ?? 100)));
    $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('geofence_enabled', ?)")->execute([$geoEnabled]);
    $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('default_radius_m', ?)")->execute([$radius]);

    // Branding text
    $systemName    = trim($_POST['system_name'] ?? '');
    $systemTagline = trim($_POST['system_tagline'] ?? '');
    if ($systemName === '') $systemName = 'SmartAttend';
    if (strlen($systemName) > 60) $systemName = substr($systemName, 0, 60);
    if (strlen($systemTagline) > 60) $systemTagline = substr($systemTagline, 0, 60);
    $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('system_name', ?)")->execute([$systemName]);
    $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('system_tagline', ?)")->execute([$systemTagline]);

    // Brand color
    $brandColor = strtolower(trim($_POST['brand_color'] ?? '#2f5bff'));
    if (!preg_match('/^#[0-9a-f]{6}$/', $brandColor)) $brandColor = '#2f5bff';
    $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('brand_color', ?)")->execute([$brandColor]);

    // Logo
    if (!empty($_FILES['logo']['tmp_name']) && ($_FILES['logo']['error'] ?? 0) === UPLOAD_ERR_OK) {
        $old = setting($pdo, 'system_logo', '');
        $new = handle_logo_upload($_FILES['logo']);
        if ($new === null) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Logo upload failed. Use JPG, PNG, WebP, or SVG under 2 MB.'];
            header("Location: settings.php"); exit;
        }
        $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('system_logo', ?)")->execute([$new]);
        if ($old && $old !== $new) delete_logo($old);
    }
    if (!empty($_POST['remove_logo'])) {
        $old = setting($pdo, 'system_logo', '');
        if ($old) delete_logo($old);
        $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('system_logo', '')")->execute();
    }

    // Favicon
    if (!empty($_FILES['favicon']['tmp_name']) && ($_FILES['favicon']['error'] ?? 0) === UPLOAD_ERR_OK) {
        $old = setting($pdo, 'favicon', '');
        $new = handle_favicon_upload($_FILES['favicon']);
        if ($new === null) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Favicon upload failed. Use ICO, PNG, or SVG under 512 KB.'];
            header("Location: settings.php"); exit;
        }
        $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('favicon', ?)")->execute([$new]);
        if ($old && $old !== $new) delete_favicon($old);
    }
    if (!empty($_POST['remove_favicon'])) {
        $old = setting($pdo, 'favicon', '');
        if ($old) delete_favicon($old);
        $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('favicon', '')")->execute();
    }

    // Login background
    if (!empty($_FILES['login_bg']['tmp_name']) && ($_FILES['login_bg']['error'] ?? 0) === UPLOAD_ERR_OK) {
        $old = setting($pdo, 'login_bg', '');
        $new = handle_login_bg_upload($_FILES['login_bg']);
        if ($new === null) {
            $_SESSION['flash'] = ['type'=>'error','msg'=>'Background upload failed. Use JPG, PNG, or WebP under 5 MB.'];
            header("Location: settings.php"); exit;
        }
        $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('login_bg', ?)")->execute([$new]);
        if ($old && $old !== $new) delete_login_bg($old);
    }
    if (!empty($_POST['remove_login_bg'])) {
        $old = setting($pdo, 'login_bg', '');
        if ($old) delete_login_bg($old);
        $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('login_bg', '')")->execute();
    }

    // Overlay
    $overlay = max(0, min(80, (int)($_POST['login_bg_overlay'] ?? 40)));
    $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('login_bg_overlay', ?)")->execute([$overlay]);

    // Footer
    $footerText    = trim($_POST['footer_text'] ?? '');
    $footerEnabled = isset($_POST['footer_enabled']) ? '1' : '0';
    if (strlen($footerText) > 200) $footerText = substr($footerText, 0, 200);
    $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('footer_text', ?)")->execute([$footerText]);
    $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('footer_enabled', ?)")->execute([$footerEnabled]);

    // Institution details
    foreach ([
        'institution_name'    => 120,
        'institution_address' => 200,
        'institution_phone'   => 60,
        'institution_email'   => 120,
        'institution_website' => 120,
        'report_title'        => 80,
        'report_signer_name'  => 80,
    ] as $key => $maxLen) {
        $val = trim($_POST[$key] ?? '');
        if (strlen($val) > $maxLen) $val = substr($val, 0, $maxLen);
        $pdo->prepare("REPLACE INTO settings (k,v) VALUES (?, ?)")->execute([$key, $val]);
    }

    $_SESSION['flash'] = ['type'=>'success','msg'=>'Settings saved.'];
    header("Location: settings.php");
    exit;
}

// ============================================================
// LOAD DATA FOR RENDERING
// ============================================================
$settings = app_settings($pdo);

// Available backups for the restore panel
$backupDir   = __DIR__ . '/../backups/db';
$backupFiles = [];
if (is_dir($backupDir)) {
    foreach (glob($backupDir . '/*.sql') as $f) {
        $backupFiles[] = [
            'name'  => basename($f),
            'size'  => filesize($f),
            'mtime' => filemtime($f),
        ];
    }
    usort($backupFiles, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
}
$fmtSize = function ($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1024 / 1024, 2) . ' MB';
};

$pageTitle = 'Settings';
require __DIR__ . '/partials/admin_header.php';

$title = 'System Settings';
$subtitle = 'Control global branding, behavior, and maintenance.';
require __DIR__ . '/partials/page_header.php';

require_once __DIR__ . '/../includes/ui.php';
flash_banner();
?>

<form method="POST" enctype="multipart/form-data" class="max-w-3xl space-y-6">

    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

    <!-- ============= BRANDING ============= -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Branding</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Appears in the sidebar, page titles, and login screen.</p>
        </div>

        <div class="p-6 space-y-5">

            <!-- Logo -->
            <div class="flex items-center gap-5">
                <div class="shrink-0 w-20 h-20 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 grid place-items-center overflow-hidden">
                    <?php if (!empty($settings['system_logo'])): ?>
                        <img id="logo-preview" src="../uploads/branding/<?= htmlspecialchars($settings['system_logo']) ?>"
                             class="w-full h-full object-cover" alt="">
                    <?php else: ?>
                        <div id="logo-preview" class="w-full h-full bg-brand-500 grid place-items-center text-white text-2xl font-bold">
                            <?= strtoupper(substr($settings['system_name'] ?? 'S', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Logo</label>
                    <input type="file" name="logo" id="logo-input"
                           accept="image/jpeg,image/png,image/webp,image/svg+xml"
                           class="block w-full text-sm text-slate-600 dark:text-slate-400
                                  file:mr-3 file:py-2 file:px-4
                                  file:rounded-lg file:border-0
                                  file:text-sm file:font-medium
                                  file:bg-brand-50 dark:file:bg-brand-500/15
                                  file:text-brand-700 dark:file:text-brand-400
                                  hover:file:bg-brand-100">
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">JPG, PNG, WebP, or SVG. Max 2 MB. Square images look best.</p>

                    <?php if (!empty($settings['system_logo'])): ?>
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-rose-600 dark:text-rose-400 cursor-pointer">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            Remove current logo
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">System name</label>
                <input name="system_name" maxlength="60" value="<?= htmlspecialchars($settings['system_name'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Tagline / subtitle</label>
                <input name="system_tagline" maxlength="60" value="<?= htmlspecialchars($settings['system_tagline'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Primary color</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="brand_color" id="brand_color"
                           value="<?= htmlspecialchars($settings['brand_color'] ?? '#2f5bff') ?>"
                           class="w-12 h-10 rounded-lg border border-slate-200 dark:border-slate-700 cursor-pointer">
                    <input type="text" id="brand_color_text"
                           value="<?= htmlspecialchars($settings['brand_color'] ?? '#2f5bff') ?>"
                           readonly
                           class="flex-1 max-w-[160px] rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-3 py-2 text-sm font-mono">
                </div>
            </div>

            <!-- Favicon -->
            <div class="flex items-center gap-5">
                <div class="shrink-0 w-20 h-20 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 grid place-items-center overflow-hidden">
                    <?php if (!empty($settings['favicon'])): ?>
                        <img id="favicon-preview" src="../uploads/branding/<?= htmlspecialchars($settings['favicon']) ?>"
                             class="w-12 h-12 object-contain" alt="">
                    <?php else: ?>
                        <img id="favicon-preview"
                             src="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%232f5bff'/><text x='50' y='68' font-size='60' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='bold'>S</text></svg>"
                             class="w-12 h-12 object-contain" alt="">
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Favicon</label>
                    <input type="file" name="favicon" id="favicon-input"
                           accept=".ico,.png,.svg,image/png,image/svg+xml,image/x-icon"
                           class="block w-full text-sm text-slate-600 dark:text-slate-400
                                  file:mr-3 file:py-2 file:px-4
                                  file:rounded-lg file:border-0
                                  file:text-sm file:font-medium
                                  file:bg-brand-50 dark:file:bg-brand-500/15
                                  file:text-brand-700 dark:file:text-brand-400
                                  hover:file:bg-brand-100">
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">ICO, PNG, or SVG. Max 512 KB.</p>
                    <?php if (!empty($settings['favicon'])): ?>
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-rose-600 dark:text-rose-400 cursor-pointer">
                            <input type="checkbox" name="remove_favicon" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            Remove current favicon
                        </label>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============= LOGIN BACKGROUND ============= -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Login background</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Shown behind the login and register forms.</p>
        </div>

        <div class="p-6 space-y-5">
            <div class="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800">
                <div class="relative h-44 grid place-items-center"
                     style="<?= !empty($settings['login_bg'])
                              ? 'background-image:url(../uploads/branding/' . htmlspecialchars($settings['login_bg']) . ');background-size:cover;background-position:center;'
                              : 'background:linear-gradient(135deg,#eef4ff,#dbe7ff);' ?>">
                    <div class="absolute inset-0" style="background:rgba(15,23,42,<?= ((int)($settings['login_bg_overlay'] ?? 40)) / 100 ?>)"></div>
                    <div class="relative bg-white/95 backdrop-blur-sm rounded-xl shadow-lg p-5 w-56">
                        <div class="h-2 w-16 bg-slate-300 rounded mb-3"></div>
                        <div class="h-3 w-24 bg-slate-800 rounded mb-2"></div>
                        <div class="h-2 w-20 bg-slate-300 rounded"></div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-5">
                <div class="shrink-0 w-20 h-20 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 grid place-items-center overflow-hidden">
                    <?php if (!empty($settings['login_bg'])): ?>
                        <img id="bg-preview" src="../uploads/branding/<?= htmlspecialchars($settings['login_bg']) ?>"
                             class="w-full h-full object-cover" alt="">
                    <?php else: ?>
                        <div id="bg-preview" class="w-full h-full grid place-items-center text-slate-400">
                            <i data-lucide="image" class="w-6 h-6"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Background image</label>
                    <input type="file" name="login_bg" id="bg-input"
                           accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-sm text-slate-600 dark:text-slate-400
                                  file:mr-3 file:py-2 file:px-4
                                  file:rounded-lg file:border-0
                                  file:text-sm file:font-medium
                                  file:bg-brand-50 dark:file:bg-brand-500/15
                                  file:text-brand-700 dark:file:text-brand-400
                                  hover:file:bg-brand-100">
                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">JPG, PNG, or WebP. Max 5 MB. Wide images (1920×1080) look best.</p>
                    <?php if (!empty($settings['login_bg'])): ?>
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-rose-600 dark:text-rose-400 cursor-pointer">
                            <input type="checkbox" name="remove_login_bg" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            Remove current background
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    Overlay darkness: <span id="overlay-value"><?= (int)($settings['login_bg_overlay'] ?? 40) ?></span>%
                </label>
                <input type="range" name="login_bg_overlay" id="overlay-input"
                       min="0" max="80" step="5"
                       value="<?= (int)($settings['login_bg_overlay'] ?? 40) ?>"
                       class="w-full accent-brand-600">
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Darkens the image so text stays readable. Try 30–50%.</p>
            </div>
        </div>
    </div>

    <!-- ============= FOOTER ============= -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Footer</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">The small line shown at the bottom of every page.</p>
        </div>

        <div class="p-6 space-y-5">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="footer_enabled" <?= ($settings['footer_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                       class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <div>
                    <div class="text-sm font-medium text-slate-900 dark:text-white">Show footer</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">When disabled, no footer text appears on any page.</div>
                </div>
            </label>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Footer text</label>
                <input type="text" name="footer_text" maxlength="200"
                       value="<?= htmlspecialchars($settings['footer_text'] ?? 'Powered by SmartAttend') ?>"
                       placeholder="e.g. © 2026 MyUniversity. All rights reserved."
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
        </div>
    </div>

    <!-- ============= INSTITUTION DETAILS ============= -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Institution details</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">These appear on the header of exported PDF reports.</p>
        </div>

        <div class="p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Institution name</label>
                <input name="institution_name" value="<?= htmlspecialchars($settings['institution_name'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Postal address</label>
                <input name="institution_address" value="<?= htmlspecialchars($settings['institution_address'] ?? '') ?>"
                       placeholder="e.g. P.O. Box 1234, Kampala, Uganda"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Phone</label>
                    <input name="institution_phone" value="<?= htmlspecialchars($settings['institution_phone'] ?? '') ?>"
                           placeholder="+256 700 000 000"
                           class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email</label>
                    <input name="institution_email" type="email" value="<?= htmlspecialchars($settings['institution_email'] ?? '') ?>"
                           placeholder="info@university.ac.ug"
                           class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Website</label>
                <input name="institution_website" value="<?= htmlspecialchars($settings['institution_website'] ?? '') ?>"
                       placeholder="www.university.ac.ug"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Report title</label>
                    <input name="report_title" value="<?= htmlspecialchars($settings['report_title'] ?? 'Attendance Report') ?>"
                           class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Signer label</label>
                    <input name="report_signer_name" value="<?= htmlspecialchars($settings['report_signer_name'] ?? 'Head of Department') ?>"
                           class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>
        </div>
    </div>

    <!-- ============= GEOFENCE ============= -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Attendance rules</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Control how attendance is captured.</p>
        </div>

        <div class="p-6 space-y-6">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="geofence_enabled" <?= ($settings['geofence_enabled'] ?? '1') === '1' ? 'checked' : '' ?>
                       class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                <div>
                    <div class="text-sm font-medium text-slate-900 dark:text-white">Enable geofencing</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        When enabled, students must be physically within the class radius to mark attendance.
                    </div>
                </div>
            </label>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Default geofence radius (meters)</label>
                <input type="number" name="default_radius_m" min="20" max="1000"
                       value="<?= (int)($settings['default_radius_m'] ?? 100) ?>"
                       class="w-full max-w-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Recommended: 50–150 m depending on building size and GPS accuracy.</p>
            </div>
        </div>
    </div>

    <!-- ============= DOCUMENTATION ============= -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Documentation</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                User guides and reference documents. Share these with your institution.
            </p>
        </div>

        <div class="p-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">

            <a href="../docs/USER_MANUAL.pdf" target="_blank" rel="noopener"
            class="group block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-brand-400 dark:hover:border-brand-500 hover:bg-brand-50/50 dark:hover:bg-brand-500/5 transition">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 rounded-lg bg-brand-50 dark:bg-brand-500/15 text-brand-600 dark:text-brand-400 grid place-items-center group-hover:bg-brand-600 group-hover:text-white transition">
                        <i data-lucide="book-open" class="w-4 h-4"></i>
                    </div>
                    <div class="font-semibold text-slate-900 dark:text-white text-sm">User Manual</div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Comprehensive reference for every role.</p>
            </a>

            <a href="../docs/QUICK_START_Student.pdf" target="_blank" rel="noopener"
            class="group block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-brand-400 dark:hover:border-brand-500 hover:bg-brand-50/50 dark:hover:bg-brand-500/5 transition">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 rounded-lg bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 grid place-items-center group-hover:bg-sky-600 group-hover:text-white transition">
                        <i data-lucide="graduation-cap" class="w-4 h-4"></i>
                    </div>
                    <div class="font-semibold text-slate-900 dark:text-white text-sm">Student Guide</div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">One-page quick-start for students.</p>
            </a>

            <a href="../docs/QUICK_START_Lecturer.pdf" target="_blank" rel="noopener"
            class="group block p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-brand-400 dark:hover:border-brand-500 hover:bg-brand-50/50 dark:hover:bg-brand-500/5 transition">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 grid place-items-center group-hover:bg-amber-600 group-hover:text-white transition">
                        <i data-lucide="presentation" class="w-4 h-4"></i>
                    </div>
                    <div class="font-semibold text-slate-900 dark:text-white text-sm">Lecturer Guide</div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Quick-start for lecturers.</p>
            </a>

        </div>
    </div>    

    <!-- ============= MAINTENANCE (BACKUP + RESTORE) ============= -->
    <div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-card">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-800">
            <h2 class="font-semibold text-slate-900 dark:text-white">Maintenance</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Database backup and restore. Use with care.</p>
        </div>

        <div class="p-6 space-y-6">

            <!-- Backup -->
            <div>
                <div class="text-sm font-medium text-slate-900 dark:text-white mb-2">Create a backup</div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                    Dumps the entire database and zips the uploads folder into
                    <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">/backups/</code>.
                </p>
                <button type="submit" name="run_backup" value="1"
                        class="inline-flex items-center gap-2 rounded-lg bg-slate-900 dark:bg-white text-white dark:text-slate-900 px-4 py-2.5 text-sm font-semibold hover:bg-slate-800 dark:hover:bg-slate-100 transition">
                    <i data-lucide="database" class="w-4 h-4"></i> Run backup now
                </button>
            </div>

            <!-- Restore -->
            <div class="pt-6 border-t border-slate-100 dark:border-slate-800">
                <div class="text-sm font-medium text-slate-900 dark:text-white mb-2">Restore from backup</div>

                <?php if (!$backupFiles): ?>
                    <div class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10 px-4 py-3 text-xs text-amber-800 dark:text-amber-300">
                        No backups found. Click "Run backup now" first, then wait a few seconds and refresh this page.
                    </div>
                <?php else: ?>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">
                        This will <strong class="text-rose-600 dark:text-rose-400">completely replace</strong>
                        the current database with the selected backup. All current data is lost.
                    </p>

                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Choose a backup</label>
                    <select name="backup_file" id="backup-select"
                            class="w-full max-w-md rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <?php foreach ($backupFiles as $bf): ?>
                            <option value="<?= htmlspecialchars($bf['name']) ?>">
                                <?= htmlspecialchars($bf['name']) ?>
                                · <?= $fmtSize($bf['size']) ?>
                                · <?= date('d M Y H:i', $bf['mtime']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="mt-4">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            Type <code class="bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded font-mono text-xs">RESTORE</code> to confirm
                        </label>
                        <input type="text" name="confirm_restore" id="confirm-input"
                               placeholder="RESTORE" autocomplete="off"
                               class="w-full max-w-md rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-rose-500">
                    </div>

                    <button type="submit" name="restore_backup" value="1" id="restore-btn" disabled
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-rose-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-rose-700 transition disabled:opacity-40 disabled:cursor-not-allowed">
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i> Restore this backup
                    </button>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- ============= SAVE ============= -->
    <div class="flex justify-end">
        <button class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-5 py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
            <i data-lucide="save" class="w-4 h-4"></i> Save settings
        </button>
    </div>
</form>

<script>
    // ---------- Live preview: logo ----------
    const logoInput = document.getElementById('logo-input');
    if (logoInput) {
        logoInput.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = ev => {
                const old = document.getElementById('logo-preview');
                const img = document.createElement('img');
                img.src = ev.target.result;
                img.id = 'logo-preview';
                img.className = 'w-full h-full object-cover';
                old.replaceWith(img);
            };
            reader.readAsDataURL(file);
        });
    }

    // ---------- Live preview: favicon ----------
    const faviconInput = document.getElementById('favicon-input');
    if (faviconInput) {
        faviconInput.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = ev => {
                document.getElementById('favicon-preview').src = ev.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    // ---------- Live preview: login background ----------
    const bgInput = document.getElementById('bg-input');
    if (bgInput) {
        bgInput.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = ev => {
                const el = document.getElementById('bg-preview');
                const img = document.createElement('img');
                img.src = ev.target.result;
                img.id = 'bg-preview';
                img.className = 'w-full h-full object-cover';
                el.replaceWith(img);
            };
            reader.readAsDataURL(file);
        });
    }

    // ---------- Live preview: color picker ----------
    const colorInput = document.getElementById('brand_color');
    const colorText  = document.getElementById('brand_color_text');
    if (colorInput && colorText) {
        colorInput.addEventListener('input', e => { colorText.value = e.target.value; });
    }

    // ---------- Live preview: overlay slider ----------
    const overlayInput = document.getElementById('overlay-input');
    const overlayValue = document.getElementById('overlay-value');
    if (overlayInput && overlayValue) {
        overlayInput.addEventListener('input', e => { overlayValue.textContent = e.target.value; });
    }

    // ---------- Restore confirm gate ----------
    const confirmInput = document.getElementById('confirm-input');
    const restoreBtn   = document.getElementById('restore-btn');
    if (confirmInput && restoreBtn) {
        confirmInput.addEventListener('input', e => {
            restoreBtn.disabled = e.target.value !== 'RESTORE';
        });
    }
</script>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>