<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/avatar.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    die("Invalid request");
}

$id         = (int)($_POST['id'] ?? 0);
$full_name  = trim($_POST['full_name']);
$email      = trim($_POST['email']);
$role       = $_POST['role'];
$reg_number = $role === 'student'  ? trim($_POST['reg_number'])  : null;
$staff_id   = $role === 'lecturer' ? trim($_POST['staff_id'])    : null;
$password   = $_POST['password'] ?? '';

if (!in_array($role, ['student','lecturer','admin'], true)) die("Bad role");

// Handle avatar upload (only if a file was provided)
$newPhoto = null;
$oldPhoto = null;

if (!empty($_FILES['photo']['tmp_name']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    if ($id) {
        $stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $oldPhoto = $stmt->fetchColumn() ?: null;
    }
    $newPhoto = handle_avatar_upload($_FILES['photo'], $id ?: 0);
    if ($newPhoto === null) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Image upload failed. Use JPG/PNG/WebP under 2 MB.'];
        header("Location: " . ($id ? "user_form.php?id=$id" : "user_form.php"));
        exit;
    }
}

try {
    if ($id) {
        // UPDATE
        $sql = "UPDATE users SET full_name=?, email=?, role=?, reg_number=?, staff_id=?";
        $params = [$full_name, $email, $role, $reg_number, $staff_id];

        if ($password !== '') {
            $sql .= ", password=?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($newPhoto !== null) {
            $sql .= ", photo=?";
            $params[] = $newPhoto;
        }

        $sql .= " WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);

        // Delete the old avatar file if replaced
        if ($newPhoto !== null && $oldPhoto) {
            delete_avatar($oldPhoto);
        }

        $_SESSION['flash'] = ['type'=>'success','msg'=>'User updated.'];
    } else {
        // INSERT
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (full_name,email,role,reg_number,staff_id,password,photo)
                       VALUES (?,?,?,?,?,?,?)")
            ->execute([$full_name,$email,$role,$reg_number,$staff_id,$hash,$newPhoto]);

        // If photo was uploaded before the user existed, rename it to include the new ID
        if ($newPhoto && strpos($newPhoto, 'user_0_') === 0) {
            $newId = (int)$pdo->lastInsertId();
            $renamed = preg_replace('/^user_0_/', "user_{$newId}_", $newPhoto);
            if (@rename(__DIR__.'/../uploads/avatars/'.$newPhoto, __DIR__.'/../uploads/avatars/'.$renamed)) {
                $pdo->prepare("UPDATE users SET photo=? WHERE id=?")->execute([$renamed, $newId]);
            }
        }

        $_SESSION['flash'] = ['type'=>'success','msg'=>'User created.'];
    }
} catch (PDOException $e) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Error: ' . $e->getMessage()];
}

header("Location: users.php");