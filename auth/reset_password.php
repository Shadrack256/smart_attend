<?php
require '../config/db.php';

$brand = app_settings($pdo);

$rawToken = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$message = '';
$msgType = '';

// Look up the reset row (only if we have a token)
$reset = null;
$user  = null;

if ($rawToken !== '' && preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
    $tokenHash = hash('sha256', $rawToken);

    $stmt = $pdo->prepare("
        SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at,
               u.full_name, u.email
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ?
        ORDER BY pr.id DESC
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    $reset = $stmt->fetch();

    if ($reset) {
        if ($reset['used_at'] !== null) {
            $message = 'This reset link has already been used. Please request a new one.';
            $msgType = 'error';
            $reset = null;
        } elseif (strtotime($reset['expires_at']) < time()) {
            $message = 'This reset link has expired. Please request a new one.';
            $msgType = 'error';
            $reset = null;
        } else {
            $user = ['id' => $reset['user_id'], 'full_name' => $reset['full_name'], 'email' => $reset['email']];
        }
    } else {
        $message = 'Invalid or expired reset link.';
        $msgType = 'error';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = 'Invalid token.';
    $msgType = 'error';
} else {
    $message = 'No reset token provided.';
    $msgType = 'error';
}

// Handle the new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $message = 'Invalid request.';
        $msgType = 'error';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm']  ?? '';

        if (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters.';
            $msgType = 'error';
        } elseif ($password !== $confirm) {
            $message = 'Passwords do not match.';
            $msgType = 'error';
        } else {
            try {
                $pdo->beginTransaction();

                // Update password
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                    ->execute([$hash, $user['id']]);

                // Mark the token as used
                $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
                    ->execute([$reset['id']]);

                // Invalidate any other outstanding tokens for this user
                $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
                    ->execute([$user['id']]);

                $pdo->commit();

                // Log the user in
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role']    = $pdo->query("SELECT role FROM users WHERE id = " . (int)$user['id'])->fetchColumn();
                $_SESSION['name']    = $user['full_name'];
                session_regenerate_id(true);

                $_SESSION['flash'] = ['type'=>'success','msg'=>'Your password has been reset. Welcome back!'];

                $redirect = [
                    'student'  => 'student/dashboard.php',
                    'lecturer' => 'lecturer/dashboard.php',
                    'admin'    => 'admin/dashboard.php',
                ][$_SESSION['role']] ?? 'index.php';

                header("Location: ../$redirect");
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = 'Something went wrong. Please try again.';
                $msgType = 'error';
            }
        }
    }
}

$pageTitle = 'Reset password · ' . ($brand['system_name'] ?? 'SmartAttend');
require __DIR__ . '/../includes/head.php';

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
                <h1 class="text-2xl font-bold <?= $bgFile ? 'text-white' : 'text-slate-900' ?>">Set a new password</h1>
                <?php if ($user): ?>
                    <p class="text-sm mt-1 <?= $bgFile ? 'text-white/70' : 'text-slate-500' ?>">
                        For <?= htmlspecialchars($user['email']) ?>
                    </p>
                <?php endif; ?>
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

                <?php if ($user && $msgType !== 'error'): ?>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken) ?>">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">New password</label>
                        <input name="password" type="password" required minlength="6" autofocus
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                        <p class="text-xs text-slate-500 mt-1.5">At least 6 characters.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirm new password</label>
                        <input name="confirm" type="password" required minlength="6"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>

                    <button class="w-full rounded-lg bg-brand-600 text-white py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                        Reset password
                    </button>
                </form>
                <?php else: ?>
                    <div class="text-center">
                        <a href="forgot_password.php"
                           class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-600 text-white px-5 py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                            Request a new link
                        </a>
                    </div>
                <?php endif; ?>

                <p class="mt-6 text-center text-sm text-slate-500">
                    <a href="login.php" class="font-medium text-brand-600 hover:text-brand-700">← Back to sign in</a>
                </p>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/../includes/foot.php'; ?>
</body>
</html>