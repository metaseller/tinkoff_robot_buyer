<?php

$tinkoff_invest = require __DIR__ . '/tinkoff-invest.php';

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',

    'tinkoff_invest' => $tinkoff_invest,

    'telegram' => require __DIR__ . '/telegram.php'
];
