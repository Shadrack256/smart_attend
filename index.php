<?php
require __DIR__ . '/config/db.php';

$brand = app_settings($pdo);

if (is_logged_in()) {
    $redirect = [
        'student'  => 'student/dashboard.php',
        'lecturer' => 'lecturer/dashboard.php',
        'admin'    => 'admin/dashboard.php',
    ][$_SESSION['role']] ?? 'auth/login.php';
    header("Location: $redirect");
    exit;
}

try {
    $stats = [
        'students'  => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
        'lecturers' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='lecturer'")->fetchColumn(),
        'courses'   => (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
        'sessions'  => (int)$pdo->query("SELECT COUNT(*) FROM sessions")->fetchColumn(),
        'records'   => (int)$pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn(),
    ];
} catch (Exception $e) {
    $stats = ['students'=>0,'lecturers'=>0,'courses'=>0,'sessions'=>0,'records'=>0];
}

$pageTitle = $brand['system_name'] ?? 'Smart Attend';
require __DIR__ . '/includes/head.php';
?>

<!-- ============ NAVBAR ============ -->
<header class="sticky top-0 z-40 bg-white/80 dark:bg-slate-950/80 backdrop-blur border-b border-slate-200 dark:border-slate-800">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">

        <a href="index.php" class="flex items-center gap-2.5">
            <?php if (!empty($brand['system_logo'])): ?>
                <img src="uploads/branding/<?= htmlspecialchars($brand['system_logo']) ?>"
                     class="w-9 h-9 rounded-xl object-cover" alt="">
            <?php else: ?>
                <div class="w-9 h-9 rounded-xl bg-brand-600 grid place-items-center text-white font-bold">
                    <?= strtoupper(substr($brand['system_name'] ?? 'S', 0, 1)) ?>
                </div>
            <?php endif; ?>
            <span class="font-bold text-slate-900 dark:text-white">
                <?= htmlspecialchars($brand['system_name'] ?? 'SmartAttend') ?>
            </span>
        </a>

        <div class="hidden md:flex items-center gap-8">
            <a href="#features" class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white">Features</a>
            <a href="#how"      class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white">How it works</a>
            <a href="#roles"    class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white">For whom</a>
            <a href="#faq"      class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white">FAQ</a>
            <a href="#demo"     class="text-sm font-medium text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white">Demo</a>
        </div>

        <div class="flex items-center gap-2">
            <button id="theme-toggle" type="button"
                    class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    aria-label="Toggle theme" title="Toggle dark mode">
                <i data-lucide="sun" class="w-5 h-5 hidden dark:block"></i>
                <i data-lucide="moon" class="w-5 h-5 block dark:hidden"></i>
            </button>

            <a href="auth/login.php"
               class="hidden sm:inline-flex items-center rounded-lg px-3.5 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                Sign in
            </a>
            <a href="auth/register.php"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-3.5 py-2 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                Get started
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>
    </nav>
</header>

<!-- ============ HERO ============ -->
<section class="relative overflow-hidden bg-white dark:bg-slate-950">
    <div class="absolute inset-0 -z-10">
        <div class="absolute top-0 -left-24 w-96 h-96 rounded-full bg-brand-200/40 dark:bg-brand-500/10 blur-3xl"></div>
        <div class="absolute top-32 -right-24 w-96 h-96 rounded-full bg-violet-200/40 dark:bg-violet-500/10 blur-3xl"></div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
        <div class="grid lg:grid-cols-12 gap-12 items-center">

            <div class="lg:col-span-7">
                <div class="inline-flex items-center gap-2 rounded-full border border-brand-200 dark:border-brand-500/40 bg-brand-50 dark:bg-brand-500/15 px-3 py-1 text-xs font-medium text-brand-700 dark:text-brand-200 mb-6">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-500 dark:bg-brand-400 animate-pulse"></span>
                    QR + GPS verified attendance
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-slate-900 dark:text-white leading-[1.05]">
                    Attendance that
                    <span class="bg-gradient-to-r from-brand-600 to-violet-600 dark:from-brand-400 dark:to-violet-400 bg-clip-text text-transparent">
                        marks itself.
                    </span>
                </h1>

                <p class="mt-6 text-lg text-slate-600 dark:text-slate-300 max-w-xl">
                    <?= htmlspecialchars($brand['system_name'] ?? 'Smart Attend') ?> replaces paper sign-in
                    sheets with a secure QR-and-location system. Lecturers display a rotating QR code;
                    students scan from their phones; attendance is captured instantly — verified by
                    location, time, and identity.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="auth/register.php"
                       class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-5 py-3 text-sm font-semibold hover:bg-brand-700 shadow-sm transition">
                        Create an account
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                    <a href="auth/login.php"
                       class="inline-flex items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-5 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        Sign in
                    </a>
                </div>

                <div class="mt-10 grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-2xl">
                    <?php
                    $quick = [
                        ['label'=>'Students',  'value'=>$stats['students'],  'icon'=>'graduation-cap'],
                        ['label'=>'Lecturers', 'value'=>$stats['lecturers'], 'icon'=>'presentation'],
                        ['label'=>'Courses',   'value'=>$stats['courses'],   'icon'=>'book-open'],
                        ['label'=>'Records',   'value'=>$stats['records'],   'icon'=>'check-circle-2'],
                    ];
                    foreach ($quick as $s): ?>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-3">
                        <div class="flex items-center gap-2 text-slate-400 dark:text-slate-500 text-xs uppercase tracking-wide mb-1">
                            <i data-lucide="<?= $s['icon'] ?>" class="w-3.5 h-3.5"></i>
                            <?= $s['label'] ?>
                        </div>
                        <div class="text-xl font-bold text-slate-900 dark:text-white tabular-nums"><?= (int)$s['value'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="lg:col-span-5">
                <div class="relative">
                    <div class="absolute -inset-4 bg-gradient-to-tr from-brand-500/20 to-violet-500/20 rounded-3xl blur-2xl"></div>
                    <div class="relative bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-xl p-6">

                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <div class="text-xs text-slate-400 dark:text-slate-500">Live session</div>
                                <div class="font-semibold text-slate-900 dark:text-white">CSC101 · Intro to CS</div>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Live
                            </div>
                        </div>

                        <div class="aspect-square max-w-[240px] mx-auto rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 grid place-items-center mb-4">
                            <div class="grid grid-cols-7 gap-1">
                                <?php for ($i = 0; $i < 49; $i++):
                                    $on = ($i * 7 + $i * 3) % 5 < 2; ?>
                                    <div class="w-3 h-3 rounded-[2px] <?= $on ? 'bg-slate-900 dark:bg-white' : 'bg-slate-200 dark:bg-slate-700' ?>"></div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <div class="text-center text-xs text-slate-400 dark:text-slate-500 mb-4">
                            Refreshes every 20 seconds
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-300 text-sm">
                                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                <span class="font-medium">Jane Doe</span>
                                <span class="ml-auto text-xs">12 m away</span>
                            </div>
                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-300 text-sm">
                                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                <span class="font-medium">John Smith</span>
                                <span class="ml-auto text-xs">27 m away</span>
                            </div>
                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 text-sm">
                                <i data-lucide="user" class="w-4 h-4"></i>
                                <span class="font-medium">Waiting…</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ TRUST STRIP ============ -->
<section class="border-y border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <?php
            $pillars = [
                ['icon'=>'shield-check', 'text'=>'Location-verified scans'],
                ['icon'=>'refresh-cw',   'text'=>'Rotating QR tokens'],
                ['icon'=>'smartphone',   'text'=>'Works on any phone'],
                ['icon'=>'bar-chart-3',  'text'=>'Live attendance reports'],
            ];
            foreach ($pillars as $p): ?>
            <div class="flex items-center justify-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <i data-lucide="<?= $p['icon'] ?>" class="w-4 h-4 text-brand-600 dark:text-brand-400"></i>
                <?= $p['text'] ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ PURPOSE ============ -->
<section id="about" class="py-20 lg:py-24 bg-slate-50 dark:bg-slate-950">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14">
            <div class="text-sm font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wider mb-3">Our purpose</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 dark:text-white">
                Why <?= htmlspecialchars($brand['system_name'] ?? 'Smart Attend') ?> exists
            </h2>
        </div>

        <div class="grid md:grid-cols-2 gap-8">
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-card p-8">
                <div class="w-12 h-12 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 grid place-items-center mb-5">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-3">The problem</h3>
                <ul class="text-sm text-slate-600 dark:text-slate-300 space-y-2.5">
                    <li class="flex gap-2"><i data-lucide="x" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>Paper sign-in sheets get lost, forged, or filled in by a friend</li>
                    <li class="flex gap-2"><i data-lucide="x" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>Lecturers waste 5–10 minutes per class calling names</li>
                    <li class="flex gap-2"><i data-lucide="x" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>No reliable record for exams, funding, or accreditation</li>
                    <li class="flex gap-2"><i data-lucide="x" class="w-4 h-4 text-rose-500 shrink-0 mt-0.5"></i>Students who skip class leave no trace</li>
                </ul>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-card p-8">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 grid place-items-center mb-5">
                    <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-3">The solution</h3>
                <ul class="text-sm text-slate-600 dark:text-slate-300 space-y-2.5">
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Lecturers show a rotating QR code — new one every 20 seconds</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Students scan with their phone; the server verifies they're in the room</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Attendance is recorded instantly — no paperwork, no proxies</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Admins see live percentages, export reports, and email at-risk students</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ============ FEATURES ============ -->
<section id="features" class="py-20 lg:py-28 bg-white dark:bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center mb-16">
            <div class="text-sm font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wider mb-3">Features</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 dark:text-white">
                Everything you need to run attendance
            </h2>
            <p class="mt-4 text-slate-600 dark:text-slate-400">
                Built for real classrooms — no more paper sheets, no more proxy sign-ins.
            </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            $features = [
                ['qr-code',          'Rotating QR codes',      'A fresh QR every 20 seconds makes screenshots useless.'],
                ['map-pin',          'GPS geofencing',         'Students must be physically inside the room radius to scan.'],
                ['shield-check',     'Anti-proxy protection',  'Server-side distance calculation — client data is never trusted.'],
                ['users',            'Multi-role accounts',    'Students, lecturers, and admins each get their own dashboard.'],
                ['chart-line',       'Attendance percentages', 'Every student sees their attendance per course in real time.'],
                ['file-spreadsheet', 'CSV & report exports',   'Download per-course reports whenever you need them.'],
                ['mail',             'Warning emails',         'Students below a threshold get an automatic email from the system.'],
                ['clock',            'Auto absent-marking',    'Sessions close automatically; non-scanners are marked absent.'],
                ['palette',          'Fully brandable',        'Custom logo, colors, favicon, footer — make it your own.'],
            ];
            foreach ($features as [$icon, $title, $desc]): ?>
            <div class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-6 shadow-card hover:shadow-lg transition">
                <div class="w-11 h-11 rounded-xl bg-brand-50 dark:bg-brand-500/15 text-brand-600 dark:text-brand-400 grid place-items-center mb-4 group-hover:bg-brand-600 group-hover:text-white transition">
                    <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
                </div>
                <h3 class="font-semibold text-slate-900 dark:text-white mb-1.5"><?= $title ?></h3>
                <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed"><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ HOW IT WORKS ============ -->
<section id="how" class="py-20 lg:py-28 bg-slate-900 text-white dark:bg-slate-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center mb-16">
            <div class="text-sm font-semibold text-brand-400 uppercase tracking-wider mb-3">How it works</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Three simple steps</h2>
            <p class="mt-4 text-slate-400">
                From the lecturer's first click to the student's confirmed attendance — in seconds.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <?php
            $steps = [
                ['01', 'Lecturer starts the session',
                       'One click on the dashboard records the time and location, and generates a live QR code.'],
                ['02', 'Students scan the code',
                       "The camera reads the QR, the phone's location is captured, and the server verifies everything."],
                ['03', 'Attendance is auto-captured',
                       'The record is saved with status, timestamp, and distance. Reports update instantly.'],
            ];
            foreach ($steps as [$num, $title, $desc]): ?>
            <div class="relative">
                <div class="text-5xl font-bold text-brand-500/30 mb-4 tabular-nums"><?= $num ?></div>
                <h3 class="text-xl font-semibold mb-2"><?= $title ?></h3>
                <p class="text-slate-400 leading-relaxed"><?= $desc ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ ROLES ============ -->
<section id="roles" class="py-20 lg:py-28 bg-white dark:bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center mb-16">
            <div class="text-sm font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wider mb-3">Built for everyone</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 dark:text-white">Who uses it</h2>
            <p class="mt-4 text-slate-600 dark:text-slate-400">
                Each role sees only what they need — nothing more, nothing less.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-6">

            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-7 shadow-card flex flex-col">
                <div class="w-12 h-12 rounded-xl bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 grid place-items-center mb-5">
                    <i data-lucide="graduation-cap" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">For Students</h3>
                <ul class="text-sm text-slate-600 dark:text-slate-300 space-y-2.5 mb-6 flex-1">
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>See all enrolled courses</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Track attendance percentages live</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Scan QR to mark yourself present</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Receive email warnings when below threshold</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Upload a profile photo</li>
                </ul>
                <a href="auth/register.php"
                   class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                    Register as student
                </a>
            </div>

            <div class="relative rounded-2xl border-2 border-brand-500 bg-white dark:bg-slate-950 p-7 shadow-lg flex flex-col">
                <span class="absolute -top-3 left-7 inline-flex items-center gap-1 rounded-full bg-brand-600 text-white px-2.5 py-0.5 text-xs font-semibold">
                    <i data-lucide="star" class="w-3 h-3"></i> Popular
                </span>
                <div class="w-12 h-12 rounded-xl bg-brand-50 dark:bg-brand-500/15 text-brand-600 dark:text-brand-400 grid place-items-center mb-5">
                    <i data-lucide="presentation" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">For Lecturers</h3>
                <ul class="text-sm text-slate-600 dark:text-slate-300 space-y-2.5 mb-6 flex-1">
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Start sessions in one click</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Auto-rotating QR display</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Live "who's in" roster</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Absent marking on session end</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Session history and reports</li>
                </ul>
                <a href="auth/login.php"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-600 text-white px-4 py-2.5 text-sm font-semibold hover:bg-brand-700 transition">
                    Sign in
                </a>
            </div>

            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-7 shadow-card flex flex-col">
                <div class="w-12 h-12 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 grid place-items-center mb-5">
                    <i data-lucide="shield" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white mb-2">For Admins</h3>
                <ul class="text-sm text-slate-600 dark:text-slate-300 space-y-2.5 mb-6 flex-1">
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Manage all users and roles</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Create courses and assign lecturers</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Bulk-enroll students in courses</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Filter reports by attendance %</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Notify at-risk students by email</li>
                    <li class="flex gap-2"><i data-lucide="check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>Brand the system with a logo and colors</li>
                </ul>
                <a href="auth/login.php"
                   class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                    Admin sign in
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============ SCREENSHOTS ============ -->
<section id="screenshots" class="py-20 lg:py-24 bg-slate-50 dark:bg-slate-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl mx-auto text-center mb-14">
            <div class="text-sm font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wider mb-3">Screenshots</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 dark:text-white">
                A look inside
            </h2>
            <p class="mt-4 text-slate-600 dark:text-slate-400">
                Clean, focused screens for every role — nothing to learn, nothing to configure.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            <?php
            $screens = [
                ['label'=>'Lecturer · Live QR', 'desc'=>'Start a session and display a rotating QR code.',             'file'=>'screenshot-lecturer.png', 'icon'=>'qr-code',    'grad'=>'from-brand-500 to-brand-700'],
                ['label'=>'Student · Scan',     'desc'=>'Point the camera, mark yourself present in one tap.',         'file'=>'screenshot-student.png',  'icon'=>'smartphone', 'grad'=>'from-emerald-500 to-emerald-700'],
                ['label'=>'Admin · Reports',    'desc'=>'Filter by attendance %, export, and email at-risk students.', 'file'=>'screenshot-admin.png',    'icon'=>'bar-chart-3','grad'=>'from-violet-500 to-violet-700'],
            ];
            foreach ($screens as $s):
                $imgPath  = 'uploads/branding/' . $s['file'];
                $hasImage = file_exists(__DIR__ . '/' . $imgPath);
            ?>
            <div class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-card overflow-hidden hover:shadow-lg transition">
                <?php if ($hasImage): ?>
                    <img src="<?= htmlspecialchars($imgPath) ?>" alt="<?= htmlspecialchars($s['label']) ?>"
                         class="aspect-[4/3] w-full object-cover">
                <?php else: ?>
                    <div class="relative aspect-[4/3] bg-gradient-to-br <?= $s['grad'] ?> grid place-items-center">
                        <i data-lucide="<?= $s['icon'] ?>" class="w-16 h-16 text-white/90"></i>
                    </div>
                <?php endif; ?>
                <div class="p-5">
                    <div class="text-xs font-mono uppercase tracking-wide text-brand-600 dark:text-brand-400 mb-1"><?= $s['label'] ?></div>
                    <div class="text-sm text-slate-700 dark:text-slate-300"><?= $s['desc'] ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ FAQ ============ -->
<section id="faq" class="py-20 lg:py-24 bg-white dark:bg-slate-900">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-14">
            <div class="text-sm font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wider mb-3">FAQ</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 dark:text-white">
                Common questions
            </h2>
        </div>

        <?php
        $faqs = [
            ['How does the system prevent students from sharing QR codes?',
             'The QR code refreshes every 20 seconds and only a student who is physically inside the class radius can scan it successfully.'],
            ["What if a student's phone doesn't have GPS?",
             'Every modern smartphone has GPS built in. If location services are disabled, the student sees a clear error and can enable them in one tap.'],
            ['Do students need to install an app?',
             'No. Smart Attend runs entirely in the browser. Students open the URL, log in, and scan.'],
            ['Can I brand it as my own institution?',
             'Yes. Administrators can set the system name, tagline, logo, primary color, favicon, footer text, and login background — all from Settings.'],
            ['How are absent students recorded?',
             'When a lecturer ends a session, every enrolled student who never scanned is automatically marked absent.'],
            ['Can I email students who are falling behind?',
             'Yes. On the Reports page, filter by attendance percentage, then click Notify. Each at-risk student gets a personalized warning email.'],
            ['Is my data secure?',
             'Passwords are hashed with bcrypt, all queries use prepared statements, file uploads are sandboxed, and sessions are secured.'],
            ['Do I need special hosting?',
             'Any standard PHP and MySQL host works. HTTPS is required on the live site so phones can access the camera and location APIs.'],
        ];
        ?>

        <div class="space-y-3">
            <?php foreach ($faqs as [$q, $a]): ?>
            <details class="group bg-white dark:bg-slate-950 rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-card">
                <summary class="flex items-center justify-between cursor-pointer list-none px-5 py-4 hover:bg-slate-50 dark:hover:bg-slate-900 transition">
                    <span class="font-medium text-slate-900 dark:text-white pr-4"><?= htmlspecialchars($q) ?></span>
                    <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400 shrink-0 transition-transform group-open:rotate-180"></i>
                </summary>
                <div class="px-5 pb-5 text-sm text-slate-600 dark:text-slate-400 leading-relaxed border-t border-slate-100 dark:border-slate-800 pt-4">
                    <?= htmlspecialchars($a) ?>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ DEMO FORM ============ -->
<section id="demo" class="py-20 lg:py-24 bg-slate-50 dark:bg-slate-950">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <div class="text-sm font-semibold text-brand-600 dark:text-brand-400 uppercase tracking-wider mb-3">Get in touch</div>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 dark:text-white">Book a demo</h2>
            <p class="mt-4 text-slate-600 dark:text-slate-400">
                Want a walkthrough or considering Smart Attend for your institution? Send us a note.
            </p>
        </div>

        <form id="demo-form" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-card p-6 sm:p-8">
            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Your name</label>
                    <input name="name" required
                           class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Email</label>
                    <input name="email" type="email" required
                           class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>

            <div class="mt-5">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">School / organization <span class="text-slate-400">(optional)</span></label>
                <input name="organization"
                       class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div class="mt-5">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Message</label>
                <textarea name="message" required rows="5"
                          placeholder="Tell us about your class size, current attendance process, and what you'd like to see…"
                          class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
            </div>

            <div id="demo-result" class="mt-5 hidden"></div>

            <div class="mt-6 flex items-center justify-end">
                <button type="submit" id="demo-submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-5 py-2.5 text-sm font-semibold hover:bg-brand-700 shadow-sm disabled:opacity-50 transition">
                    <i data-lucide="send" class="w-4 h-4"></i> Send request
                </button>
            </div>
        </form>
    </div>
</section>

<!-- ============ CTA ============ -->
<section class="py-16 bg-white dark:bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-600 to-violet-600 px-8 py-14 sm:px-14 sm:py-16 text-white">
            <div class="absolute inset-0 opacity-10"
                 style="background-image: radial-gradient(circle at 20% 20%, white 1px, transparent 1px); background-size: 24px 24px;"></div>

            <div class="relative grid lg:grid-cols-2 gap-8 items-center">
                <div>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">
                        Ready to modernize attendance?
                    </h2>
                    <p class="mt-4 text-brand-100 max-w-lg">
                        Create an account in under a minute and start running paperless, verified sessions today.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3 lg:justify-end">
                    <a href="auth/register.php"
                       class="inline-flex items-center gap-2 rounded-lg bg-white text-brand-700 px-5 py-3 text-sm font-semibold hover:bg-slate-100 shadow-sm transition">
                        Get started
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>
                    <a href="auth/login.php"
                       class="inline-flex items-center gap-2 rounded-lg border border-white/30 text-white px-5 py-3 text-sm font-semibold hover:bg-white/10 transition">
                        Sign in
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ FOOTER ============ -->
<footer class="border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid md:grid-cols-4 gap-8">

            <div class="md:col-span-2">
                <div class="flex items-center gap-2.5 mb-3">
                    <?php if (!empty($brand['system_logo'])): ?>
                        <img src="uploads/branding/<?= htmlspecialchars($brand['system_logo']) ?>"
                             class="w-9 h-9 rounded-xl object-cover" alt="">
                    <?php else: ?>
                        <div class="w-9 h-9 rounded-xl bg-brand-600 grid place-items-center text-white font-bold">
                            <?= strtoupper(substr($brand['system_name'] ?? 'S', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <span class="font-bold text-slate-900 dark:text-white">
                        <?= htmlspecialchars($brand['system_name'] ?? 'SmartAttend') ?>
                    </span>
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 max-w-sm">
                    <?= htmlspecialchars($brand['system_tagline'] ?? 'A QR-and-GPS verified attendance system for modern classrooms.') ?>
                </p>
            </div>

            <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Product</div>
                <ul class="space-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <li><a href="#features" class="hover:text-brand-600 dark:hover:text-brand-400">Features</a></li>
                    <li><a href="#how"      class="hover:text-brand-600 dark:hover:text-brand-400">How it works</a></li>
                    <li><a href="#roles"    class="hover:text-brand-600 dark:hover:text-brand-400">For whom</a></li>
                    <li><a href="#faq"      class="hover:text-brand-600 dark:hover:text-brand-400">FAQ</a></li>
                </ul>
            </div>

            <div>
                <div class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Account</div>
                <ul class="space-y-2 text-sm text-slate-500 dark:text-slate-400">
                    <li><a href="auth/login.php"    class="hover:text-brand-600 dark:hover:text-brand-400">Sign in</a></li>
                    <li><a href="auth/register.php" class="hover:text-brand-600 dark:hover:text-brand-400">Register</a></li>
                    <li><a href="#demo"             class="hover:text-brand-600 dark:hover:text-brand-400">Book a demo</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-10 pt-6 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400 dark:text-slate-500">
            <div>
                <?php if (($brand['footer_enabled'] ?? '1') === '1' && trim($brand['footer_text'] ?? '') !== ''): ?>
                    <?= htmlspecialchars($brand['footer_text']) ?>
                <?php else: ?>
                    © <?= date('Y') ?> <?= htmlspecialchars($brand['system_name'] ?? 'SmartAttend') ?>.
                <?php endif; ?>
            </div>
            <div>Built with PHP &amp; MySQL</div>
        </div>
    </div>
</footer>

<!-- ============ PAGE SCRIPTS ============ -->
<script>
(function () {
    'use strict';

    // ---------- Theme toggle ----------
    // Use event delegation so the handler works even if the button is
    // replaced by another script later. Also safe if the button isn't
    // in the DOM yet at parse time.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('#theme-toggle');
        if (!btn) return;

        const root = document.documentElement;

        // Optional: enable a short transition animation
        root.classList.add('theme-transition');

        const isDark = root.classList.toggle('dark');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch (err) {}

        // Rebuild lucide icons so sun/moon swap correctly
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

        // Remove the transition class after the animation completes
        setTimeout(function () {
            root.classList.remove('theme-transition');
        }, 250);
    });

    // ---------- Demo form ----------
    const form = document.getElementById('demo-form');
    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('demo-submit');
            const out = document.getElementById('demo-result');
            if (!btn || !out) return;

            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Sending…';
            if (window.lucide) window.lucide.createIcons();

            try {
                const res  = await fetch('demo_request.php', { method: 'POST', body: new FormData(form) });
                const data = await res.json();

                out.className = 'mt-5 rounded-xl border px-4 py-3 text-sm ' + (data.success
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    : 'border-rose-200 bg-rose-50 text-rose-800');
                out.textContent = data.message;
                out.classList.remove('hidden');
                if (data.success) form.reset();
            } catch (err) {
                out.className = 'mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800';
                out.textContent = 'Network error. Please try again.';
                out.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Send request';
                if (window.lucide) window.lucide.createIcons();
            }
        });
    }
})();
</script>

<?php require __DIR__ . '/includes/foot.php'; ?>