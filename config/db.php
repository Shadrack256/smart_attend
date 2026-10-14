<?php

ini_set('session.gc_maxlifetime', 1800);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$host = 'localhost';
$db   = 'smart_attend';
$user = 'root';
$pass = '';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage());
}

// ------------------------------------------------------------------
// Auto-close sessions older than 3 hours AND mark non-scanners absent.
// Mirrors what end_session() does when a lecturer clicks "End session".
// ------------------------------------------------------------------
try {
    $expired = $pdo->query("
        SELECT id FROM sessions
        WHERE is_active = 1 AND start_time < NOW() - INTERVAL 3 HOUR
    ")->fetchAll(PDO::FETCH_COLUMN);

    if ($expired) {
        $pdo->beginTransaction();

        $upd = $pdo->prepare("
            UPDATE sessions
            SET is_active = 0,
                end_time  = NOW(),
                qr_token  = NULL,
                token_expires_at = NULL
            WHERE id = ?
        ");

        $ins = $pdo->prepare("
            INSERT IGNORE INTO attendance (session_id, student_id, status, marked_at)
            SELECT ?, e.student_id, 'absent', NOW()
            FROM enrollments e
            JOIN sessions s ON s.course_id = e.course_id
            WHERE s.id = ?
        ");

        foreach ($expired as $sid) {
            $upd->execute([$sid]);
            $ins->execute([$sid, $sid]);
        }

        $pdo->commit();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Silently ignore — never crash a page over this.
}

function is_logged_in() { return isset($_SESSION['user_id']); }

function require_role($role) {
    if (!is_logged_in() || $_SESSION['role'] !== $role) {
        // Portable redirect: works regardless of install folder.
        header("Location: ../auth/login.php");
        exit;
    }
}

/**
 * Haversine distance between two lat/lng points in meters.
 */
function haversine_m($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000;
    $φ1 = deg2rad($lat1);
    $φ2 = deg2rad($lat2);
    $Δφ = deg2rad($lat2 - $lat1);
    $Δλ = deg2rad($lng2 - $lng1);
    $a = sin($Δφ/2)**2 + cos($φ1) * cos($φ2) * sin($Δλ/2)**2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function throttle($key, $max = 5, $window = 60) {
    $now = time();
    $_SESSION['throttle'][$key] = array_filter(
        $_SESSION['throttle'][$key] ?? [],
        fn($t) => $t > $now - $window
    );
    if (count($_SESSION['throttle'][$key]) >= $max) {
        http_response_code(429);
        die(json_encode(['success'=>false,'message'=>'Too many requests. Wait a moment.']));
    }
    $_SESSION['throttle'][$key][] = $now;
}

/**
 * Load all app settings into a static cache.
 */
function app_settings($pdo) {
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        $rows = $pdo->query("SELECT k, v FROM settings")->fetchAll();
        $cache = [];
        foreach ($rows as $r) $cache[$r['k']] = $r['v'];
    } catch (Exception $e) {
        $cache = [];
    }

    $cache += [
        'system_name'         => 'SmartAttend',
        'system_tagline'      => 'Admin Console',
        'system_logo'         => '',
        'brand_color'         => '#2f5bff',
        'favicon'             => '',
        'footer_text'         => 'Powered by SmartAttend',
        'footer_enabled'      => '1',
        'login_bg'            => '',
        'login_bg_overlay'    => '40',
        'geofence_enabled'    => '1',
        'default_radius_m'    => '100',
        'demo_contact_email'  => '',
        'institution_name'    => 'Your University Name',
        'institution_address' => 'P.O. Box 1234, Kampala, Uganda',
        'institution_phone'   => '+256 700 000 000',
        'institution_email'   => 'info@youruniversity.ac.ug',
        'institution_website' => 'www.youruniversity.ac.ug',
        'report_signer_name'  => 'Head of Department',
        'report_title'        => 'Attendance Report',
    ];

    return $cache;
}

function setting($pdo, $key, $default = null) {
    $s = app_settings($pdo);
    return $s[$key] ?? $default;
}

/**
 * Ends a session and marks all enrolled students who never scanned as "absent".
 * Returns the number of absences recorded (or -1 on error).
 */
function end_session($pdo, $session_id) {
    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            UPDATE sessions
            SET is_active = 0,
                end_time  = NOW(),
                qr_token  = NULL,
                token_expires_at = NULL
            WHERE id = ?
        ")->execute([$session_id]);

        $pdo->prepare("
            INSERT IGNORE INTO attendance (session_id, student_id, status, marked_at)
            SELECT ?, e.student_id, 'absent', NOW()
            FROM enrollments e
            JOIN sessions s ON s.course_id = e.course_id
            WHERE s.id = ?
        ")->execute([$session_id, $session_id]);

        $count = $pdo->prepare("
            SELECT COUNT(*) FROM attendance
            WHERE session_id = ? AND status = 'absent'
        ");
        $count->execute([$session_id]);
        $absent = (int)$count->fetchColumn();

        $pdo->commit();
        return $absent;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("end_session failed: " . $e->getMessage());
        return -1;
    }
}

/**
 * Given a hex color (e.g. "#2f5bff"), return a Tailwind-compatible
 * palette by mixing with white and black at fixed ratios.
 */
function brand_palette($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        $hex = '2f5bff';
    }

    [$r, $g, $b] = [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];

    $mixWhite = function($t) use ($r, $g, $b) {
        return sprintf('#%02x%02x%02x',
            (int)round($r + (255 - $r) * $t),
            (int)round($g + (255 - $g) * $t),
            (int)round($b + (255 - $b) * $t));
    };
    $mixBlack = function($t) use ($r, $g, $b) {
        return sprintf('#%02x%02x%02x',
            (int)round($r * (1 - $t)),
            (int)round($g * (1 - $t)),
            (int)round($b * (1 - $t)));
    };

    return [
        '50'  => $mixWhite(0.95),
        '100' => $mixWhite(0.88),
        '200' => $mixWhite(0.75),
        '300' => $mixWhite(0.55),
        '400' => $mixWhite(0.30),
        '500' => sprintf('#%02x%02x%02x', $r, $g, $b),
        '600' => $mixBlack(0.10),
        '700' => $mixBlack(0.25),
        '800' => $mixBlack(0.40),
        '900' => $mixBlack(0.55),
    ];
}