<?php
header('Content-Type: text/plain');

require __DIR__ . '/vendor/autoload.php';

echo "Autoload: OK\n";
echo "PHPMailer class exists: " . (class_exists('PHPMailer\\PHPMailer\\PHPMailer') ? 'YES' : 'NO') . "\n";
echo "OpenSSL extension: " . (extension_loaded('openssl') ? 'YES' : 'NO') . "\n";

$cfg = require __DIR__ . '/config/mail.php';
echo "\nConfig:\n";
print_r($cfg);

// Test connection manually
$fp = @fsockopen($cfg['host'], $cfg['port'], $errno, $errstr, 3);
if ($fp) {
    echo "\nSMTP connection to {$cfg['host']}:{$cfg['port']}: OK\n";
    fclose($fp);
} else {
    echo "\nSMTP connection FAILED: {$errstr} ({$errno})\n";
}