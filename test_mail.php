<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/mailer.php';

$ok = send_mail(
    'shadrackssebagereka2002@gmail.com',   // ← your real Gmail
    'Shadrack',
    'Smart Attend send_mail() test',
    '<h1>send_mail() works</h1><p>The wrapper function delivers too.</p>'
);

var_dump($ok);