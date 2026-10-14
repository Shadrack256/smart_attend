<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_mail($toEmail, $toName, $subject, $bodyHtml) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log("PHPMailer not installed");
        return false;
    }
    require_once $autoload;

    $cfg = require __DIR__ . '/../config/mail.php';

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['username'];
        $mail->Password   = $cfg['password'];
        $mail->SMTPSecure = $cfg['encryption'];
        $mail->Port       = $cfg['port'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = strip_tags($bodyHtml);

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "<pre style='background:#fee;padding:12px;border:1px solid #f88;'>";
        echo "MAIL ERROR\n";
        echo "Message: " . htmlspecialchars($e->getMessage()) . "\n";
        echo "PHPMailer: " . htmlspecialchars($mail->ErrorInfo) . "\n";
        echo "</pre>";
        error_log("Mail to {$toEmail} failed: " . $mail->ErrorInfo);
        return false;
    }
}