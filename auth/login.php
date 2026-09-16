<?php
require '../config/db.php';

$brand = app_settings($pdo);

// ---------- Handle login ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['full_name'];
        session_regenerate_id(true);

        $redirect = [
            'student'  => 'student/dashboard.php',
            'lecturer' => 'lecturer/dashboard.php',
            'admin'    => 'admin/dashboard.php',
        ][$user['role']] ?? 'index.php';

        header("Location: ../$redirect");
        exit;
    }
    $error = "Invalid email or password.";
}

// ---------- Background image ----------
$bgFile    = $brand['login_bg'] ?? '';
$bgOverlay = (int)($brand['login_bg_overlay'] ?? 40);
$bgStyle   = '';
if ($bgFile) {
    $bgStyle = 'background-image:url(/smart_attend/uploads/branding/' . htmlspecialchars($bgFile) . ');'
             . 'background-size:cover;background-position:center;background-repeat:no-repeat;';
}

$pageTitle = 'Sign in · ' . ($brand['system_name'] ?? 'SmartAttend');
require __DIR__ . '/../includes/head.php';
?>

<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <!-- Main area — centers the login card, holds the background -->
    <div class="relative flex-1 flex items-center justify-center p-6" style="<?= $bgStyle ?>">

        <!-- Overlay -->
        <?php if ($bgFile): ?>
            <div class="absolute inset-0 pointer-events-none"
                 style="background: rgba(15,23,42, <?= $bgOverlay / 100 ?>)"></div>
        <?php endif; ?>

        <!-- Login card -->
        <div class="relative w-full max-w-md">

            <!-- Header: logo + welcome -->
            <div class="text-center mb-8">
                <?php if (!empty($brand['system_logo'])): ?>
                    <img src="../uploads/branding/<?= htmlspecialchars($brand['system_logo']) ?>"
                         class="inline-block w-14 h-14 rounded-2xl object-cover mb-4 shadow-lg" alt="">
                <?php else: ?>
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-brand-600 text-white font-bold text-xl mb-4 shadow-lg">
                        <?= strtoupper(substr($brand['system_name'] ?? 'S', 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <h1 class="text-2xl font-bold <?= $bgFile ? 'text-white' : 'text-slate-900' ?>">Welcome back</h1>
                <p class="text-sm mt-1 <?= $bgFile ? 'text-white/70' : 'text-slate-500' ?>">
                    Sign in to <?= htmlspecialchars($brand['system_name'] ?? 'SmartAttend') ?>
                </p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-card p-8">

                <?php if (!empty($error)): ?>
                    <div class="mb-5 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <i data-lucide="alert-circle" class="w-5 h-5 mt-0.5 shrink-0"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                        <input name="email" type="email" required autofocus
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-sm font-medium text-slate-700">Password</label>
                            <a href="forgot_password.php" class="text-xs font-medium text-brand-600 hover:text-brand-700">
                                Forgot password?
                            </a>
                        </div>
                        <input name="password" type="password" required
                            class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
                    </div>

                    <button class="w-full rounded-lg bg-brand-600 text-white py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                        Sign in
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    Don't have an account?
                    <a href="register.php" class="font-medium text-brand-600 hover:text-brand-700">Create one</a>
                </p>
            </div>

            <!-- Back to home -->
            <div class="text-center mt-6">
                <a href="../index.php"
                   class="text-xs <?= $bgFile ? 'text-white/70 hover:text-white' : 'text-slate-500 hover:text-slate-700' ?>">
                    ← Back to home
                </a>
            </div>
        </div>
    </div>

    <!-- Footer sits at the bottom of the page, below the flex area -->
    <?php if (($brand['footer_enabled'] ?? '1') === '1' && trim($brand['footer_text'] ?? '') !== ''): ?>
        <div class="py-4 text-center text-xs <?= $bgFile ? 'text-white/60' : 'text-slate-400' ?>">
            <?= htmlspecialchars($brand['footer_text']) ?>
        </div>
    <?php endif; ?>

<?php require __DIR__ . '/../includes/foot.php'; ?>
</body>
</html>