<?php
/**
 * Smart Attend — Emergency Admin Password Reset
 *
 * USE ONCE, THEN DELETE THIS FILE.
 *
 * Access: /smart_attend/reset_admin.php?key=CHANGE_ME_12345
 */

// ==== 1. CHANGE THIS SECRET BEFORE USING ====
const RESET_KEY = 'admin123';
// ============================================

require __DIR__ . '/config/db.php';

// --- Gate: require the secret key ---
$providedKey = $_GET['key'] ?? '';
if (!hash_equals(RESET_KEY, $providedKey)) {
    http_response_code(404);
    exit('Not found');
}

$message = '';
$messageType = '';

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $action   = $_POST['action'] ?? 'reset';

    if ($action === 'list') {
        // Just show all admins
        $message = 'Listed admins below.';
        $messageType = 'info';
    } elseif ($email === '' || $password === '') {
        $message = 'Email and password are required.';
        $messageType = 'error';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $messageType = 'error';
    } else {
        try {
            // Verify the target user exists and is an admin
            $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? AND role = 'admin'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $message = "No admin account found with that email.";
                $messageType = 'error';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $upd  = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $upd->execute([$hash, $user['id']]);

                $message = "✅ Password updated for {$user['full_name']} ({$email}). You can now log in.";
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// --- Load all admin accounts for the dropdown ---
$admins = [];
try {
    $admins = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'admin' ORDER BY id")
                   ->fetchAll();
} catch (Exception $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password · Smart Attend</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = { theme: { extend: {
        colors: { brand: { 50:'#eef4ff',500:'#2f5bff',600:'#1a3ff0',700:'#152fd0' } },
        fontFamily: { sans: ['Inter','ui-sans-serif','system-ui','sans-serif'] },
    }}}
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', system-ui, sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen">

<div class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-lg">

        <!-- Warning banner -->
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <div class="font-semibold mb-0.5">⚠️ Emergency tool</div>
            Delete this file immediately after use. Anyone with the URL and secret can reset any admin password.
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-lg p-8">

            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-600 text-white font-bold text-xl mb-3">S</div>
                <h1 class="text-xl font-bold text-slate-900">Reset Admin Password</h1>
                <p class="text-sm text-slate-500 mt-1">Smart Attend recovery tool</p>
            </div>

            <?php if ($message): ?>
                <?php
                $styles = [
                    'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                    'error'   => 'border-rose-200 bg-rose-50 text-rose-800',
                    'info'    => 'border-sky-200 bg-sky-50 text-sky-800',
                ];
                $cls = $styles[$messageType] ?? $styles['info'];
                ?>
                <div class="mb-5 rounded-xl border px-4 py-3 text-sm <?= $cls ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($admins)): ?>
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 mb-5">
                    No admin accounts found in the database.
                    <a href="reset_admin.php?key=<?= urlencode(RESET_KEY) ?>&create=1" class="font-medium underline">Create one?</a>
                </div>

                <?php if (isset($_GET['create'])): ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="reset">
                        <input type="hidden" name="email" value="admin@smartattend.local">
                        <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            Will create: <strong>admin@smartattend.local</strong>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">New password</label>
                            <input name="password" type="password" required minlength="6"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirm password</label>
                            <input name="confirm" type="password" required minlength="6"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <button class="w-full rounded-lg bg-brand-600 text-white py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm">
                            Create admin account
                        </button>
                    </form>
                <?php endif; ?>

            <?php else: ?>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="reset">

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Admin account</label>
                        <select name="email" required
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <?php foreach ($admins as $a): ?>
                                <option value="<?= htmlspecialchars($a['email']) ?>">
                                    <?= htmlspecialchars($a['full_name']) ?> — <?= htmlspecialchars($a['email']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">New password</label>
                        <input name="password" type="password" required minlength="6"
                               placeholder="At least 6 characters"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirm new password</label>
                        <input name="confirm" type="password" required minlength="6"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </div>

                    <button class="w-full rounded-lg bg-brand-600 text-white py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                        Reset password
                    </button>
                </form>

                <div class="mt-6 pt-6 border-t border-slate-100 text-xs text-slate-500">
                    <div class="mb-2 font-medium text-slate-700">Existing admin accounts:</div>
                    <ul class="space-y-1">
                        <?php foreach ($admins as $a): ?>
                            <li class="font-mono"><?= htmlspecialchars($a['email']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            <?php endif; ?>

            <p class="mt-6 text-center text-xs text-slate-400">
                <a href="auth/login.php" class="hover:text-brand-600">← Back to login</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>