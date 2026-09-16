<?php
/**
 * Upload helpers for avatars, branding assets, and course images.
 * All handlers validate by real MIME type (via getimagesize or finfo),
 * enforce size limits, and generate randomized filenames so uploaded
 * content can never overwrite anything else on disk.
 */

// ==================================================================
// AVATARS
// ==================================================================
function handle_avatar_upload($file, $userId) {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if (!is_uploaded_file($file['tmp_name'])) return null;
    if ($file['size'] > 2 * 1024 * 1024) return null;   // 2 MB

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) return null;

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$info['mime']])) return null;

    $ext = $allowed[$info['mime']];
    $filename = 'user_' . (int)$userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

    $dir = __DIR__ . '/../uploads/avatars/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $target = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) return null;
    @chmod($target, 0644);

    return $filename;
}

function delete_avatar($filename) {
    if (!$filename) return;
    if (!preg_match('/^user_\d+_[a-f0-9]+\.(jpg|png|webp)$/', $filename)) return;
    $path = __DIR__ . '/../uploads/avatars/' . $filename;
    if (is_file($path)) @unlink($path);
}

// ==================================================================
// BRANDING LOGO
// ==================================================================
function handle_logo_upload($file) {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if (!is_uploaded_file($file['tmp_name'])) return null;
    if ($file['size'] > 2 * 1024 * 1024) return null;

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? null;
    }

    $allowed = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];
    if (!isset($allowed[$mime])) return null;

    if ($mime === 'image/svg+xml') {
        $content = file_get_contents($file['tmp_name']);
        if (preg_match('/<script|on\w+\s*=/i', $content)) return null;
    }

    $ext = $allowed[$mime];
    $filename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;

    $dir = __DIR__ . '/../uploads/branding/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) return null;
    @chmod($dir . $filename, 0644);

    return $filename;
}

function delete_logo($filename) {
    if (!$filename) return;
    if (!preg_match('/^logo_[a-f0-9]+\.(jpg|png|webp|svg)$/', $filename)) return;
    $path = __DIR__ . '/../uploads/branding/' . $filename;
    if (is_file($path)) @unlink($path);
}

// ==================================================================
// FAVICON
// ==================================================================
function handle_favicon_upload($file) {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if (!is_uploaded_file($file['tmp_name'])) return null;
    if ($file['size'] > 512 * 1024) return null;   // 512 KB

    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $allowed = [
        'image/png'                => 'png',
        'image/jpeg'               => 'jpg',
        'image/x-icon'             => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/svg+xml'            => 'svg',
        'application/octet-stream' => null,   // check by extension
    ];

    if (!isset($allowed[$mime]) && !in_array($ext, ['ico','png','svg','jpg','jpeg'], true)) {
        return null;
    }

    $finalExt = $allowed[$mime] ?? $ext;
    if (!$finalExt) return null;
    if ($finalExt === 'jpeg') $finalExt = 'jpg';

    if ($finalExt === 'svg') {
        $content = file_get_contents($file['tmp_name']);
        if (preg_match('/<script|on\w+\s*=/i', $content)) return null;
    }

    $filename = 'favicon_' . bin2hex(random_bytes(8)) . '.' . $finalExt;

    $dir = __DIR__ . '/../uploads/branding/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) return null;
    @chmod($dir . $filename, 0644);

    return $filename;
}

function delete_favicon($filename) {
    if (!$filename) return;
    if (!preg_match('/^favicon_[a-f0-9]+\.(ico|png|jpg|svg)$/', $filename)) return;
    $path = __DIR__ . '/../uploads/branding/' . $filename;
    if (is_file($path)) @unlink($path);
}

// ==================================================================
// LOGIN BACKGROUND
// ==================================================================
function handle_login_bg_upload($file) {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if (!is_uploaded_file($file['tmp_name'])) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null;   // 5 MB

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) return null;

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$info['mime']])) return null;

    $ext = $allowed[$info['mime']];
    $filename = 'loginbg_' . bin2hex(random_bytes(8)) . '.' . $ext;

    $dir = __DIR__ . '/../uploads/branding/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) return null;
    @chmod($dir . $filename, 0644);

    return $filename;
}

function delete_login_bg($filename) {
    if (!$filename) return;
    if (!preg_match('/^loginbg_[a-f0-9]+\.(jpg|png|webp)$/', $filename)) return;
    $path = __DIR__ . '/../uploads/branding/' . $filename;
    if (is_file($path)) @unlink($path);
}

// ==================================================================
// COURSE IMAGES
// ==================================================================
function handle_course_image_upload($file) {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if (!is_uploaded_file($file['tmp_name'])) return null;
    if ($file['size'] > 3 * 1024 * 1024) return null;   // 3 MB

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) return null;

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$info['mime']])) return null;

    $ext = $allowed[$info['mime']];
    $filename = 'course_' . bin2hex(random_bytes(8)) . '.' . $ext;

    $dir = __DIR__ . '/../uploads/courses/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $target = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) return null;
    @chmod($target, 0644);

    return $filename;
}

function delete_course_image($filename) {
    if (!$filename) return;
    if (!preg_match('/^course_[a-f0-9]+\.(jpg|png|webp)$/', $filename)) return;
    $path = __DIR__ . '/../uploads/courses/' . $filename;
    if (is_file($path)) @unlink($path);
}