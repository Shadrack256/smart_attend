<?php
require '../config/db.php';
require_role('lecturer');

$session_id = (int)($_GET['session_id'] ?? 0);
$token   = bin2hex(random_bytes(16));
$expires = date('Y-m-d H:i:s', strtotime('+25 seconds'));

$stmt = $pdo->prepare("UPDATE sessions SET qr_token=?, token_expires_at=? WHERE id=?");
$stmt->execute([$token, $expires, $session_id]);

// Autoload Composer packages
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"><rect width="300" height="300" fill="#fff"/><text x="150" y="150" text-anchor="middle" font-family="sans-serif" font-size="14" fill="#333">Run: composer require endroid/qr-code</text></svg>';
    exit;
}
require $autoload;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

$payload = json_encode(['session_id' => $session_id, 'token' => $token]);

$qr = new QrCode($payload);
$writer = new SvgWriter();
$result = $writer->write($qr);

header('Content-Type: ' . $result->getMimeType());
echo $result->getString();
exit;