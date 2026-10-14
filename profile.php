<?php
require __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/avatar.php';

// Any logged-in user can access this page
if (!is_logged_in()) {
    header("Location: auth/login.php");
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$role     = $_SESSION['role'];
$message  = '';
$msgType  = 'info';

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $message = 'Invalid request.';
        $msgType = 'error';
    } elseif (empty($_FILES['photo']['tmp_name']) ||
              ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $message = 'Please choose a photo to upload.';
        $msgType = 'error';
    } else {
        // Get old photo filename for cleanup
        $stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $oldPhoto = $stmt->fetchColumn() ?: null;

        $newPhoto = handle_avatar_upload($_FILES['photo'], $userId);

        if ($newPhoto === null) {
            $message = 'Upload failed. Use a JPG, PNG, or WebP under 2 MB.';
            $msgType = 'error';
        } else {
            $pdo->prepare("UPDATE users SET photo = ? WHERE id = ?")
                ->execute([$newPhoto, $userId]);

            if ($oldPhoto) delete_avatar($oldPhoto);

            $message = 'Profile photo updated.';
            $msgType = 'success';
        }
    }
}

// Load current user
$stmt = $pdo->prepare("SELECT full_name, email, photo, role, reg_number, staff_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: auth/logout.php");
    exit;
}

$pageTitle = 'My Profile';
require __DIR__ . '/includes/head.php';

// Where should "Back" go?
$backUrl = $role === 'student' ? 'student/dashboard.php'
         : ($role === 'lecturer' ? 'lecturer/dashboard.php'
         : 'admin/dashboard.php');
?>
<div class="max-w-2xl mx-auto p-4 sm:p-6 lg:p-8">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">My profile</h1>
            <p class="text-sm text-slate-500 mt-1">Manage your account details and photo.</p>
        </div>
        <a href="<?= $backUrl ?>"
           class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
        </a>
    </div>

    <?php if ($message): ?>
        <?php
        $styles = [
            'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            'error'   => 'border-rose-200 bg-rose-50 text-rose-800',
            'info'    => 'border-sky-200 bg-sky-50 text-sky-800',
        ];
        ?>
        <div class="mb-5 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm <?= $styles[$msgType] ?>">
            <i data-lucide="<?= $msgType === 'success' ? 'check-circle' : ($msgType === 'error' ? 'alert-circle' : 'info') ?>" class="w-5 h-5 mt-0.5 shrink-0"></i>
            <div><?= htmlspecialchars($message) ?></div>
        </div>
    <?php endif; ?>

    <!-- Identity card -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-card overflow-hidden">
        <div class="p-6 border-b border-slate-100">
            <div class="flex items-center gap-5">
                <div class="shrink-0">
                    <?php if (!empty($user['photo'])): ?>
                        <img src="uploads/avatars/<?= htmlspecialchars($user['photo']) ?>"
                             class="w-24 h-24 rounded-full object-cover border-2 border-white shadow-md" alt="">
                    <?php else: ?>
                        <div class="w-24 h-24 rounded-full bg-brand-50 text-brand-700 grid place-items-center text-3xl font-semibold border-2 border-white shadow-md">
                            <?= strtoupper(substr($user['full_name'] ?: '?', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="min-w-0">
                    <div class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="text-sm text-slate-500 mt-0.5"><?= htmlspecialchars($user['email']) ?></div>
                    <div class="mt-2">
                        <?php if ($role === 'student' && $user['reg_number']): ?>
                            <span class="inline-flex items-center rounded-md bg-sky-100 text-sky-700 px-2 py-0.5 text-xs font-medium font-mono">
                                <?= htmlspecialchars($user['reg_number']) ?>
                            </span>
                        <?php elseif ($role === 'lecturer' && $user['staff_id']): ?>
                            <span class="inline-flex items-center rounded-md bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-medium font-mono">
                                <?= htmlspecialchars($user['staff_id']) ?>
                            </span>
                        <?php endif; ?>
                        <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset <?php
                            echo $role === 'admin' ? 'bg-rose-100 text-rose-700 ring-rose-200'
                               : ($role === 'lecturer' ? 'bg-amber-100 text-amber-800 ring-amber-200'
                               : 'bg-sky-100 text-sky-700 ring-sky-200');
                        ?>">
                            <?= ucfirst($role) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload form -->
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Change profile photo</label>
                <div id="drop-zone"
                     class="relative border-2 border-dashed border-slate-200 rounded-xl p-8 text-center hover:border-brand-400 transition cursor-pointer">
                    <input type="file" name="photo" id="photo-input"
                           accept="image/jpeg,image/png,image/webp"
                           class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">

                    <div id="drop-content">
                        <i data-lucide="upload-cloud" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                        <div class="text-sm font-medium text-slate-700">Click to browse, or drop a photo here</div>
                        <div class="text-xs text-slate-400 mt-1">JPG, PNG, or WebP — max 2 MB</div>
                    </div>

                    <img id="preview-img" class="hidden mx-auto rounded-xl max-h-40 object-cover" alt="">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <a href="<?= $backUrl ?>"
                   class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Cancel
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-semibold hover:bg-brand-700 shadow-sm">
                    <i data-lucide="check" class="w-4 h-4"></i> Save photo
                </button>
            </div>
        </form>
    </div>

</div>

<script>
    const input   = document.getElementById('photo-input');
    const preview = document.getElementById('preview-img');
    const content = document.getElementById('drop-content');
    const drop    = document.getElementById('drop-zone');

    function showPreview(file) {
        if (!file || !file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            content.classList.add('hidden');
        };
        reader.readAsDataURL(file);
    }

    input.addEventListener('change', e => showPreview(e.target.files[0]));

    // Drag and drop
    drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('border-brand-400', 'bg-brand-50'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('border-brand-400', 'bg-brand-50'));
    drop.addEventListener('drop', e => {
        e.preventDefault();
        drop.classList.remove('border-brand-400', 'bg-brand-50');
        if (e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            showPreview(e.dataTransfer.files[0]);
        }
    });
</script>

<?php require __DIR__ . '/includes/foot.php'; ?>