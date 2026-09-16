<?php
$pageTitle = 'Users';
require __DIR__ . '/partials/admin_header.php';

$q    = trim($_GET['q'] ?? '');
$role = $_GET['role'] ?? '';

$sql = "SELECT id, reg_number, staff_id, full_name, email, photo, role, created_at FROM users WHERE 1=1";
$params = [];
if ($q !== '') {
    $sql .= " AND (full_name LIKE ? OR email LIKE ? OR reg_number LIKE ? OR staff_id LIKE ?)";
    $like = "%$q%";
    array_push($params, $like, $like, $like, $like);
}
if (in_array($role, ['student','lecturer','admin'], true)) {
    $sql .= " AND role = ?"; $params[] = $role;
}
$sql .= " ORDER BY role, full_name";
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$users = $stmt->fetchAll();

require __DIR__ . '/partials/page_header.php';
$title = 'Users';
$subtitle = count($users) . ' user' . (count($users) === 1 ? '' : 's') . ' found';
$action = ['label'=>'New user', 'href'=>'user_form.php', 'icon'=>'user-plus'];
require __DIR__ . '/partials/page_header.php';
?>

<!-- Filters -->
<form method="GET" class="mb-4 flex flex-wrap gap-3">
    <div class="relative flex-1 min-w-[220px]">
        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
        <input name="q" value="<?= htmlspecialchars($q) ?>"
               placeholder="Search name, email, reg no..."
               class="w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2.5 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500">
    </div>
    <select name="role"
            class="rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        <option value="">All roles</option>
        <?php foreach (['student','lecturer','admin'] as $r): ?>
            <option value="<?= $r ?>" <?= $role===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="rounded-lg bg-slate-900 text-white px-4 py-2.5 text-sm font-medium hover:bg-slate-800 transition">
        Filter
    </button>
    <?php if ($q !== '' || $role !== ''): ?>
        <a href="users.php" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
            Reset
        </a>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="bg-white rounded-xl border border-slate-200 shadow-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-5 py-3 font-semibold">Name</th>
                    <th class="px-5 py-3 font-semibold">Reg / Staff ID</th>
                    <th class="px-5 py-3 font-semibold">Email</th>
                    <th class="px-5 py-3 font-semibold">Role</th>
                    <th class="px-5 py-3 font-semibold">Created</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (!$users): ?>
                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">No users match your filters.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-slate-50/70">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-brand-50 text-brand-700 grid place-items-center text-xs font-semibold">
                                <?php if (!empty($u['photo'])): ?>
                                    <img src="../uploads/avatars/<?= htmlspecialchars($u['photo']) ?>"
                                        class="w-8 h-8 rounded-full object-cover" alt="">
                                <?php else: ?>
                                    <div class="w-8 h-8 rounded-full bg-brand-50 text-brand-700 grid place-items-center text-xs font-semibold">
                                        <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="font-medium text-slate-900"><?= htmlspecialchars($u['full_name']) ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-slate-600 font-mono text-xs">
                        <?= htmlspecialchars($u['reg_number'] ?? $u['staff_id'] ?? '—') ?>
                    </td>
                    <td class="px-5 py-3 text-slate-600"><?= htmlspecialchars($u['email']) ?></td>
                    <td class="px-5 py-3"><?= role_badge($u['role']) ?></td>
                    <td class="px-5 py-3 text-slate-500 text-xs whitespace-nowrap">
                        <?= htmlspecialchars(substr($u['created_at'], 0, 10)) ?>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <div class="inline-flex items-center gap-1">
                            <a href="user_form.php?id=<?= $u['id'] ?>"
                               class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 transition">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
                            </a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" action="user_delete.php" class="inline"
                                  onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                <button class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 transition">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/partials/admin_footer.php'; ?>