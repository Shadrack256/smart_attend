<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/avatar.php';
require_role('admin');

// ---------- Validate request ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: courses.php");
    exit;
}
if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    die("Invalid request");
}

// ---------- Read inputs ----------
$id          = (int)($_POST['id'] ?? 0);
$course_code = trim($_POST['course_code'] ?? '');
$course_name = trim($_POST['course_name'] ?? '');
$lecturer_id = ($_POST['lecturer_id'] ?? '') !== '' ? (int)$_POST['lecturer_id'] : null;

// Whitelist the required attendance value
$allowedThresholds  = [10, 20, 30, 40, 50, 60, 70, 75, 80, 85, 90, 95, 100];
$requiredAttendance = (int)($_POST['required_attendance'] ?? 75);
if (!in_array($requiredAttendance, $allowedThresholds, true)) {
    $requiredAttendance = 75;
}

// ---------- Basic validation ----------
$redirectBack = $id ? "course_form.php?id={$id}" : "course_form.php";

if ($course_code === '' || $course_name === '') {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Course code and name are required.'];
    header("Location: {$redirectBack}");
    exit;
}
if (strlen($course_code) > 30) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Course code must be 30 characters or fewer.'];
    header("Location: {$redirectBack}");
    exit;
}
if (strlen($course_name) > 150) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Course name must be 150 characters or fewer.'];
    header("Location: {$redirectBack}");
    exit;
}

// Verify the lecturer exists and has the right role
if ($lecturer_id !== null) {
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? AND role = 'lecturer'");
    $stmt->execute([$lecturer_id]);
    if (!$stmt->fetch()) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Selected lecturer not found.'];
        header("Location: {$redirectBack}");
        exit;
    }
}

// ---------- Course image upload ----------
$newImage = null;      // filename if a new one was uploaded
$oldImage = null;      // previous filename (for cleanup)
$removeImage = !empty($_POST['remove_image']);

if ($id) {
    $stmt = $pdo->prepare("SELECT image FROM courses WHERE id = ?");
    $stmt->execute([$id]);
    $oldImage = $stmt->fetchColumn() ?: null;
}

if (!empty($_FILES['image']['tmp_name']) &&
    ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {

    $newImage = handle_course_image_upload($_FILES['image']);
    if ($newImage === null) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Image upload failed. Use JPG, PNG, or WebP under 3 MB.'];
        header("Location: {$redirectBack}");
        exit;
    }
}

// ---------- Save ----------
try {
    if ($id) {
        // ---------- UPDATE ----------
        if ($newImage !== null) {
            // New image uploaded → replace old
            $pdo->prepare("
                UPDATE courses
                SET course_code = ?, course_name = ?, required_attendance = ?, lecturer_id = ?, image = ?
                WHERE id = ?
            ")->execute([$course_code, $course_name, $requiredAttendance, $lecturer_id, $newImage, $id]);

            if ($oldImage) delete_course_image($oldImage);

        } elseif ($removeImage) {
            // Image explicitly removed
            $pdo->prepare("
                UPDATE courses
                SET course_code = ?, course_name = ?, required_attendance = ?, lecturer_id = ?, image = NULL
                WHERE id = ?
            ")->execute([$course_code, $course_name, $requiredAttendance, $lecturer_id, $id]);

            if ($oldImage) delete_course_image($oldImage);

        } else {
            // No image change — just update the other fields
            $pdo->prepare("
                UPDATE courses
                SET course_code = ?, course_name = ?, required_attendance = ?, lecturer_id = ?
                WHERE id = ?
            ")->execute([$course_code, $course_name, $requiredAttendance, $lecturer_id, $id]);
        }

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Course updated.'];
    } else {
        // ---------- INSERT ----------
        $pdo->prepare("
            INSERT INTO courses (course_code, course_name, required_attendance, lecturer_id, image)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$course_code, $course_name, $requiredAttendance, $lecturer_id, $newImage]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Course created.'];
    }
} catch (PDOException $e) {
    // 23000 = unique constraint violation (duplicate course code)
    if ($e->getCode() === '23000') {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'That course code is already in use.'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
    }
    header("Location: {$redirectBack}");
    exit;
}

header("Location: courses.php");
exit;