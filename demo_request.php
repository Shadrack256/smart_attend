<?php
require __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

// Sanitize input
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$org     = trim($_POST['organization'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in name, email, and message.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

// Very light rate limiting by session
if (!empty($_SESSION['demo_last_sent']) && time() - $_SESSION['demo_last_sent'] < 60) {
    echo json_encode(['success' => false, 'message' => 'Please wait a minute before sending another request.']);
    exit;
}

// Find recipient: setting first, else first admin
$recipient = trim(setting($pdo, 'demo_contact_email', ''));
if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
    $recipient = $pdo->query("SELECT email FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();
}
if (!$recipient) {
    echo json_encode(['success' => false, 'message' => 'No recipient configured. Please try again later.']);
    exit;
}

$brand    = app_settings($pdo);
$sysName  = $brand['system_name'] ?? 'SmartAttend';

// Build the email body
$safe = fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$body = "
<div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:24px;color:#334155;'>
    <h2 style='color:#0f172a;margin:0 0 12px;'>New demo request</h2>
    <p style='margin:0 0 16px;'>Someone has requested a demo of <strong>{$safe($sysName)}</strong>.</p>

    <table style='width:100%;border-collapse:collapse;font-size:14px;'>
        <tr><td style='padding:8px 0;color:#64748b;width:120px;'>Name</td><td style='padding:8px 0;'><strong>{$safe($name)}</strong></td></tr>
        <tr><td style='padding:8px 0;color:#64748b;'>Email</td><td style='padding:8px 0;'><a href='mailto:{$safe($email)}'>{$safe($email)}</a></td></tr>
        <tr><td style='padding:8px 0;color:#64748b;'>Organization</td><td style='padding:8px 0;'>{$safe($org ?: '—')}</td></tr>
    </table>

    <h3 style='margin:20px 0 8px;font-size:15px;'>Message</h3>
    <div style='background:#f8fafc;border-left:3px solid #2f5bff;padding:12px 16px;border-radius:6px;'>
        " . nl2br($safe($message)) . "
    </div>

    <p style='margin:24px 0 0;font-size:12px;color:#94a3b8;'>Sent from the {$safe($sysName)} landing page.</p>
</div>
";

$subject = "Demo request from {$name}";

if (send_mail($recipient, 'Administrator', $subject, $body)) {
    $_SESSION['demo_last_sent'] = time();
    echo json_encode(['success' => true, 'message' => 'Thanks! We\'ll be in touch soon.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not send the message. Please try again later.']);
}
exit;