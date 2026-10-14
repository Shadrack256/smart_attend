<?php
require '../config/db.php';
require_role('lecturer');
require '../includes/ui.php';

$lecturer_id = $_SESSION['user_id'];

// If the form was submitted, create the session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = (int)$_POST['course_id'];
    $lat       = $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
    $lng       = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    $radius    = (int)($_POST['radius_m'] ?? 1000);

    // Verify the lecturer owns this course
    $check = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND lecturer_id = ?");
    $check->execute([$course_id, $lecturer_id]);
    if (!$check->fetch()) {
        die("Course not found or not yours.");
    }

    $stmt = $pdo->prepare("
        INSERT INTO sessions (course_id, session_date, start_time, is_active, latitude, longitude, radius_m)
        VALUES (?, CURDATE(), NOW(), 1, ?, ?, ?)
    ");
    $stmt->execute([$course_id, $lat, $lng, $radius]);
    $session_id = $pdo->lastInsertId();

    header("Location: display_qr.php?session_id=$session_id");
    exit;
}

// Load this lecturer's courses
$stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE lecturer_id = ? ORDER BY course_code");
$stmt->execute([$lecturer_id]);
$courses = $stmt->fetchAll();

$preselected = (int)($_GET['course_id'] ?? 0);

$pageTitle = 'Start Session';
require __DIR__ . '/../includes/head.php';
?>
<div class="max-w-2xl mx-auto p-4 sm:p-6 lg:p-8">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Start a session</h1>
            <p class="text-sm text-slate-500 mt-1">Your location will be captured for geofencing.</p>
        </div>
        <a href="dashboard.php"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
        </a>
    </div>

    <?php if (!$courses): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            You have no courses assigned. Contact an administrator.
        </div>
    <?php else: ?>

    <form method="POST" id="form">
        <div class="bg-white rounded-xl border border-slate-200 shadow-card">
            <div class="p-6 space-y-5">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Course</label>
                    <select name="course_id" required
                            class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $preselected==$c['id']?'selected':'' ?>>
                                <?= htmlspecialchars($c['course_code'] . ' — ' . $c['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Geofence radius (meters)</label>
                    <input type="number" name="radius_m" value="100" min="20" max="1000"
                           class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>

                <input type="hidden" name="latitude"  id="latitude">
                <input type="hidden" name="longitude" id="longitude">

                <div id="loc-status" class="flex items-start gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                    <i data-lucide="map-pin" class="w-5 h-5 shrink-0 mt-0.5"></i>
                    <div>Requesting your location…</div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="dashboard.php" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" id="submitBtn" disabled
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-semibold hover:bg-brand-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="play" class="w-4 h-4"></i> Start session
                </button>
            </div>
        </div>
    </form>

    <script>
    const status  = document.getElementById('loc-status');
    const submit  = document.getElementById('submitBtn');

    function locate() {
        if (!navigator.geolocation) {
            status.innerHTML = '<div>❌ Geolocation not supported.</div>';
            return;
        }
        navigator.geolocation.getCurrentPosition(
            pos => {
                document.getElementById('latitude').value  = pos.coords.latitude.toFixed(7);
                document.getElementById('longitude').value = pos.coords.longitude.toFixed(7);
                status.className = 'flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800';
                status.innerHTML = `<div>✅ Location captured: ${pos.coords.latitude.toFixed(5)}, ${pos.coords.longitude.toFixed(5)} (±${Math.round(pos.coords.accuracy)} m)</div>`;
                submit.disabled = false;
            },
            err => {
                status.className = 'flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800';
                status.innerHTML = `<div>❌ ${err.message}</div>`;
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }
    locate();
    </script>

    <?php endif; ?>

    <?php require __DIR__ . '/../includes/footer.php'; ?>
</div>
<?php require __DIR__ . '/../includes/foot.php'; ?>