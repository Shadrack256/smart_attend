<?php
require '../config/db.php';
require_once '../includes/mailer.php';

$brand = app_settings($pdo);
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $message = 'Invalid request. Reload and try again.';
        $msgType = 'error';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $msgType = 'error';
        } else {
            // Light rate-limit: 1 request per 60s per session
            $now = time();
            if (!empty($_SESSION['pwd_reset_last']) && $now - $_SESSION['pwd_reset_last'] < 60) {
                $message = 'Please wait a minute before requesting another link.';
                $msgType = 'error';
            } else {
                // Look up the user
                $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // Always show the same message — never reveal whether an email exists
                if ($user) {
                    // Clean up expired tokens for this user
                    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND (expires_at < NOW() OR used_at IS NOT NULL)")
                        ->execute([$user['id']]);

                    // Generate a secure random token (32 bytes → 64 hex chars)
                    $rawToken  = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

                    $pdo->prepare("
                        INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address)
                        VALUES (?, ?, ?, ?)
                    ")->execute([
                        $user['id'],
                        $tokenHash,
                        $expiresAt,
                        $_SERVER['REMOTE_ADDR'] ?? null,
                    ]);

                    // Build the reset link
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host   = $_SERVER['HTTP_HOST'];
                    // project root URL — assumes the app is under /smart_attend/
                    $base   = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
                    $link   = $scheme . '://' . $host . $base . '/auth/reset_password.php?token=' . $rawToken;

                    $sysName = $brand['system_name'] ?? 'SmartAttend';

                    $subject = "Reset your {$sysName} password";
                    $bodyHtml = "
                        <div style='font-family:Inter,sans-serif;max-width:560px;margin:0 auto;padding:24px;color:#334155;'>
                            <h2 style='color:#0f172a;margin:0 0 12px;'>Password reset request</h2>
                            <p>Hi " . htmlspecialchars($user['full_name']) . ",</p>
                            <p>Someone requested a password reset for your {$sysName} account. If it was you, click the button below to set a new password.</p>
                            <p style='margin:24px 0;'>
                                <a href='" . htmlspecialchars($link) . "' style='display:inline-block;background:#1a3ff0;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;'>
                                    Reset my password
                                </a>
                            </p>
                            <p style='font-size:13px;color:#64748b;'>
                                This link expires in 60 minutes and can only be used once.<br>
                                If you did not request this, you can safely ignore this email — your password will not change.
                            </p>
                            <hr style='border:none;border-top:1px solid #e2e8f0;margin:24px 0;'>
                            <p style='font-size:12px;color:#94a3b8;'>
                                If the button does not work, copy and paste this URL into your browser:<br>
                                <span style='word-break:break-all;'>" . htmlspecialchars($link) . "</span>
                            </p>
                        </div>
                    ";

                    send_mail($user['email'], $user['full_name'], $subject, $bodyHtml);
                    $_SESSION['pwd_reset_last'] = $now;
                }

                // Constant response regardless of whether the email exists
                $message = 'If that email is registered, a reset link has been sent. Check your inbox (and spam folder).';
                $msgType = 'success';
            }
        }
    }
}

$pageTitle = 'Forgot password · ' . ($brand['system_name'] ?? 'SmartAttend');
require __DIR__ . '/../includes/head.php';

// Background image (same as login)
$bgFile    = $brand['login_bg'] ?? '';
$bgOverlay = (int)($brand['login_bg_overlay'] ?? 40);
$bgStyle   = $bgFile
    ? 'background-image:url(/smart_attend/uploads/branding/' . htmlspecialchars($bgFile) . ');background-size:cover;background-position:center;'
    : '';
?>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <div class="relative flex-1 flex items-center justify-center p-6" style="<?= $bgStyle ?>">
        <?php if ($bgFile): ?>
            <div class="absolute inset-0 pointer-events-none"
                 style="background: rgba(15,23,42, <?= $bgOverlay / 100 ?>)"></div>
        <?php endif; ?>

        <div class="relative w-full max-w-md">

            <div class="text-center mb-8">
                <?php if (!empty($brand['system_logo'])): ?>
                    <img src="../uploads/branding/<?= htmlspecialchars($brand['system_logo']) ?>"
                         class="inline-block w-14 h-14 rounded-2xl object-cover mb-4 shadow-lg" alt="">
                <?php else: ?>
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-600 text-white font-bold text-xl mb-4 shadow-lg">
                        <?= strtoupper(substr($brand['system_name'] ?? 'S', 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <h1 class="text-2xl font-bold <?= $bgFile ? 'text-white' : 'text-slate-900' ?>">Forgot password?</h1>
                <p class="text-sm mt-1 <?= $bgFile ? 'text-white/70' : 'text-slate-500' ?>">
                    We'll email you a link to reset it.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-card p-8">

                <?php if ($message): ?>
                    <div class="mb-5 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm
                        <?= $msgType === 'error'
                            ? 'border-rose-200 bg-rose-50 text-rose-800'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-800' ?>">
                        <i data-lucide="<?= $msgType === 'error' ? 'alert-circle' : 'check-circle' ?>" class="w-5 h-5 mt-0.5 shrink-0"></i>
                        <div><?= htmlspecialchars($message) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($msgType !== 'success'): ?>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
                        <input name="email" type="email" required autofocus
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>
                    <button class="w-full rounded-lg bg-brand-600 text-white py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                        Send reset link
                    </button>
                </form>
                <?php endif; ?>

                <p class="mt-6 text-center text-sm text-slate-500">
                    Remembered it?
                    <a href="login.php" class="font-medium text-brand-600 hover:text-brand-700">Sign in</a>
                </p>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/../includes/foot.php'; ?>
</body>
</html>