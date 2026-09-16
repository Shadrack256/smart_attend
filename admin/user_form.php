<?php
$pageTitle = 'User';
require __DIR__ . '/partials/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$edit = false;
$user = ['reg_number'=>'','staff_id'=>'','full_name'=>'','email'=>'','role'=>'student','photo'=>null];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) die("User not found");
    $edit = true;
}

$title = $edit ? 'Edit user' : 'New user';
$subtitle = 'Set basic details and (optionally) a profile photo.';
require __DIR__ . '/partials/page_header.php';
?>

<form method="POST" action="user_save.php" enctype="multipart/form-data" class="max-w-2xl">
    <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
    <input type="hidden" name="id" value="<?= $edit ? $user['id'] : '' ?>">

    <div class="bg-white rounded-xl border border-slate-200 shadow-card">
        <div class="p-6 space-y-5">

            <!-- Avatar -->
            <div class="flex items-center gap-5">
                <div class="shrink-0">
                    <?php if (!empty($user['photo'])): ?>
                        <img id="avatar-preview"
                             src="../uploads/avatars/<?= htmlspecialchars($user['photo']) ?>"
                             class="w-20 h-20 rounded-full object-cover border border-slate-200" alt="">
                    <?php else: ?>
                        <div id="avatar-preview"
                             class="w-20 h-20 rounded-full bg-brand-50 text-brand-700 grid place-items-center text-2xl font-semibold border border-slate-200">
                            <?= strtoupper(substr($user['full_name'] ?: '?', 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Profile photo</label>
                    <input type="file" name="photo" id="photo-input"
                           accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-sm text-slate-600
                                  file:mr-3 file:py-2 file:px-4
                                  file:rounded-lg file:border-0
                                  file:text-sm file:font-medium
                                  file:bg-brand-50 file:text-brand-700
                                  hover:file:bg-brand-100">
                    <p class="mt-1.5 text-xs text-slate-500">JPG, PNG, or WebP. Max 2 MB.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Full name</label>
                <input name="full_name" required value="<?= htmlspecialchars($user['full_name']) ?>"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Role</label>
                <select name="role" id="role"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <?php foreach (['student','lecturer','admin'] as $r): ?>
                        <option value="<?= $r ?>" <?= $user['role']===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="regRow">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Registration number</label>
                <input name="reg_number" value="<?= htmlspecialchars($user['reg_number'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div id="staffRow">
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Staff ID</label>
                <input name="staff_id" value="<?= htmlspecialchars($user['staff_id'] ?? '') ?>"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">
                    Password <?= $edit ? '<span class="text-slate-400 font-normal">(leave blank to keep current)</span>' : '' ?>
                </label>
                <input type="password" name="password" <?= $edit ? '' : 'required' ?> minlength="6"
                       class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3">
            <a href="users.php" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
            <button class="rounded-lg bg-brand-600 text-white px-4 py-2 text-sm font-semibold hover:bg-brand-700 shadow-sm">
                <?= $edit ? 'Save changes' : 'Create user' ?>
            </button>
        </div>
    </div>
</form>

<script>
    const role = document.getElementById('role');
    function toggleFields() {
        document.getElementById('regRow').style.display   = role.value === 'student'  ? 'block' : 'none';
        document.getElementById('staffRow').style.display = role.value === 'lecturer' ? 'block' : 'none';
    }
    role.addEventListener('change', toggleFields); toggleFields();

    // Live avatar preview
    document.getElementById('photo-input').addEventListener('change', e => {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            const old = document.getElementById('avatar-preview');
            const img = document.createElement('img');
            img.src = ev.target.result;
            img.id = 'avatar-preview';
            img.className = 'w-20 h-20 rounded-full object-cover border border-slate-200';
            old.replaceWith(img);
        };
        reader.readAsDataURL(file);
    });
</script>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>