<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "GD loaded: " . (extension_loaded('gd') ? 'YES' : 'NO') . "<br>";
echo "Autoload exists: " . (file_exists(__DIR__ . '/vendor/autoload.php') ? 'YES' : 'NO') . "<br>";

require __DIR__ . '/vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$qr = new QrCode('https://example.com');
$writer = new PngWriter();
$result = $writer->write($qr);

echo "MIME type: " . $result->getMimeType() . "<br>";
echo "QR generated: YES<br><br>";
echo '<img src="data:' . $result->getMimeType() . ';base64,' . base64_encode($result->getString()) . '" alt="QR">';