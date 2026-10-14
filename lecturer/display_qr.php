<?php
require '../config/db.php';
require_role('lecturer');

$session_id = (int)($_GET['session_id'] ?? 0);

// Load the session + course
$stmt = $pdo->prepare("
    SELECT s.*, c.course_code, c.course_name
    FROM sessions s
    JOIN courses c ON c.id = s.course_id
    WHERE s.id = ? AND c.lecturer_id = ?
");
$stmt->execute([$session_id, $_SESSION['user_id']]);
$session = $stmt->fetch();

if (!$session) die("Session not found or not yours.");

// If the session is already closed, show a friendly message
$alreadyEnded = !$session['is_active'];

$pageTitle = 'Live QR · ' . $session['course_code'];
require __DIR__ . '/../includes/head.php';
?>
<div class="min-h-screen bg-slate-900 text-white p-6">
    <div class="max-w-4xl mx-auto">

        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="text-slate-400 text-sm">Live session · <?= htmlspecialchars($session['course_code']) ?></div>
                <h1 class="text-2xl font-bold mt-1"><?= htmlspecialchars($session['course_name']) ?></h1>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <?php if ($alreadyEnded): ?>
                    <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                    <span class="text-slate-400">Ended</span>
                <?php else: ?>
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-emerald-400">Live</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($alreadyEnded): ?>
            <div class="bg-slate-800 rounded-2xl p-8 text-center">
                <i data-lucide="check-circle-2" class="w-12 h-12 mx-auto mb-4 text-emerald-400"></i>
                <h2 class="text-xl font-semibold mb-2">Session ended</h2>
                <p class="text-slate-400 text-sm mb-6">This session is closed. Students can no longer scan.</p>
                <div class="flex justify-center gap-3">
                    <a href="session_live.php?session_id=<?= $session_id ?>"
                       class="inline-flex items-center gap-2 rounded-lg bg-white text-slate-900 px-4 py-2 text-sm font-semibold hover:bg-slate-100">
                        <i data-lucide="activity" class="w-4 h-4"></i> View attendance
                    </a>
                    <a href="dashboard.php"
                       class="inline-flex items-center gap-2 rounded-lg border border-slate-600 hover:bg-slate-700 text-white px-4 py-2 text-sm font-medium">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to dashboard
                    </a>
                </div>
            </div>

        <?php else: ?>

        <div class="grid md:grid-cols-2 gap-8 items-center bg-slate-800 rounded-2xl p-8">

            <div class="bg-white rounded-2xl p-6 flex items-center justify-center">
                <img id="qr" src="generate_token.php?session_id=<?= $session_id ?>"
                     class="w-full max-w-[320px]" alt="QR">
            </div>

            <div>
                <h2 class="text-xl font-semibold mb-3">Scan to mark attendance</h2>
                <p class="text-slate-400 text-sm mb-6">
                    The QR refreshes every 20 seconds. Students must be within range.
                </p>

                <div class="text-xs uppercase tracking-wide text-slate-500 mb-2">Refresh in</div>
                <div id="countdown" class="text-4xl font-bold tabular-nums mb-8">20</div>

                <div class="flex flex-wrap gap-3">
                    <a href="session_live.php?session_id=<?= $session_id ?>"
                       class="inline-flex items-center gap-2 rounded-lg bg-white text-slate-900 px-4 py-2 text-sm font-semibold hover:bg-slate-100">
                        <i data-lucide="activity" class="w-4 h-4"></i> View live attendance
                    </a>
                    <button type="button" onclick="endSession()"
                            class="inline-flex items-center gap-2 rounded-lg bg-rose-600 hover:bg-rose-700 text-white px-4 py-2 text-sm font-semibold">
                        <i data-lucide="square" class="w-4 h-4"></i> End session
                    </button>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<script>
    const SESSION_ID = <?= $session_id ?>;
    const isActive   = <?= $alreadyEnded ? 'false' : 'true' ?>;

    // QR refresh countdown — only run if the session is live
    if (isActive) {
        let n = 20;
        const cd = document.getElementById('countdown');
        const qr = document.getElementById('qr');

        setInterval(() => {
            n--;
            if (n <= 0) {
                qr.src = 'generate_token.php?session_id=' + SESSION_ID + '&t=' + Date.now();
                n = 20;
            }
            cd.textContent = n;
        }, 1000);

        // Poll every 5 seconds to detect if the session was ended elsewhere
        // (e.g., lecturer clicked End on another tab, or auto-closed)
        setInterval(() => {
            fetch('session_status.php?session_id=' + SESSION_ID)
                .then(r => r.json())
                .then(d => { if (!d.active) location.reload(); })
                .catch(() => {});
        }, 5000);
    }

    function endSession() {
        if (!confirm('End this session? Students will no longer be able to scan.')) return;

        fetch('end_session.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'session_id=' + SESSION_ID
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert('Could not end session: ' + d.message);
            }
        })
        .catch(() => alert('Network error'));
    }
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php require __DIR__ . '/../includes/foot.php'; ?>