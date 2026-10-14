<?php
require '../config/db.php';
require_role('student');
$pageTitle = 'Scan QR';
require __DIR__ . '/../includes/head.php';
?>
<div class="max-w-2xl mx-auto p-4 sm:p-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Scan QR code</h1>
            <p class="text-sm text-slate-500 mt-1">Point your camera at the lecturer's screen.</p>
        </div>
        <a href="dashboard.php"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
        </a>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-card p-5">
        <div id="loc-status" class="mb-4 flex items-center gap-2 text-sm text-slate-500">
            <i data-lucide="map-pin" class="w-4 h-4"></i>
            <span>Requesting location…</span>
        </div>

        <div id="reader" class="rounded-lg overflow-hidden"></div>

        <div id="result" class="mt-4"></div>
    </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
let studentCoords = null;
let coordsReady = false;

function locate() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) return reject(new Error("Geolocation not supported"));
        navigator.geolocation.getCurrentPosition(
            pos => {
                studentCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy };
                document.getElementById('loc-status').innerHTML =
                    `<i data-lucide="map-pin" class="w-4 h-4 text-emerald-600"></i>
                     <span class="text-emerald-700">Location ready (±${Math.round(pos.coords.accuracy)} m)</span>`;
                if (window.lucide) lucide.createIcons();
                coordsReady = true;
                resolve(studentCoords);
            },
            err => {
                document.getElementById('loc-status').innerHTML =
                    `<i data-lucide="map-pin-off" class="w-4 h-4 text-rose-600"></i>
                     <span class="text-rose-700">${err.message}</span>`;
                if (window.lucide) lucide.createIcons();
                reject(err);
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
        );
    });
}
locate().catch(() => {});

const scanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
scanner.render(async (decodedText) => {
    if (!coordsReady) {
        try { await locate(); } catch {
            document.getElementById('result').innerHTML =
                `<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Location required.</div>`;
            return;
        }
    }

    let payload;
    try { payload = JSON.parse(decodedText); }
    catch { document.getElementById('result').innerHTML =
        `<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">Invalid QR code.</div>`; return; }

    payload.lat = studentCoords.lat;
    payload.lng = studentCoords.lng;
    payload.accuracy = studentCoords.accuracy;

    fetch('mark_attendance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        const ok = data.success;
        document.getElementById('result').innerHTML =
            `<div class="rounded-xl border px-4 py-3 text-sm ${ok
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-rose-200 bg-rose-50 text-rose-800'}">
                ${data.message}
             </div>`;
        if (ok) scanner.clear();
    });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<?php require __DIR__ . '/../includes/foot.php'; ?>