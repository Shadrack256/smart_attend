<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'shadrackssebagereka2002@gmail.com';   // ← your Gmail
    $mail->Password   = 'hfaezfpihqmhboti';            // ← App Password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('shadrackssebagereka2002@gmail.com', 'Smart Attend');
    $mail->addAddress('shadrackssebagereka2002@gmail.com', 'Me');

    $mail->isHTML(true);
    $mail->Subject = 'Standalone PHPMailer test';
    $mail->Body    = '<h1>Direct SMTP test</h1>';

    $mail->send();
    echo "SUCCESS: Email sent";
} catch (Exception $e) {
    echo "FAILED: " . $mail->ErrorInfo;
}