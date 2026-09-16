<?php
require '../config/db.php';
require_role('lecturer');

$courses = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE lecturer_id=?");
$courses->execute([$_SESSION['user_id']]);
$courses = $courses->fetchAll();
?>
<!DOCTYPE html>
<html><head><title>Start Session</title></head><body>
<h1>Start a Session</h1>

<form method="POST" action="start_session.php" id="form">
    <label>Course
        <select name="course_id" required>
            <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code'].' '.$c['course_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Geofence radius (meters)
        <input type="number" name="radius_m" value="100" min="20" max="1000">
    </label>

    <input type="hidden" name="latitude"  id="latitude">
    <input type="hidden" name="longitude" id="longitude">

    <p id="loc-status">📍 Requesting your location...</p>
    <button type="submit" id="submitBtn" disabled>Start Session</button>
</form>

<script>
function getLocation() {
    if (!navigator.geolocation) {
        document.getElementById('loc-status').textContent = '❌ Geolocation not supported.';
        return;
    }
    navigator.geolocation.getCurrentPosition(
        pos => {
            document.getElementById('latitude').value  = pos.coords.latitude.toFixed(7);
            document.getElementById('longitude').value = pos.coords.longitude.toFixed(7);
            document.getElementById('loc-status').textContent =
                `✅ Location captured: ${pos.coords.latitude.toFixed(5)}, ${pos.coords.longitude.toFixed(5)} (±${Math.round(pos.coords.accuracy)}m)`;
            document.getElementById('submitBtn').disabled = false;
        },
        err => {
            document.getElementById('loc-status').textContent = '❌ ' + err.message;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}
getLocation();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
</body></html>