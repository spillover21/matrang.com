<?php
// SMTP Configuration for Hostinger Email
return [
    'host' => 'smtp.hostinger.com',
    'port' => 587,
    'encryption' => 'tls', // или 'ssl' для порта 465
    'auth' => true,
    'username' => 'noreply@matrang.com', // ЗАМЕНИТЕ на ваш email
    'password' => 'YOUR_PASSWORD_HERE', // ЗАМЕНИТЕ на пароль от почты
    'from_email' => 'noreply@matrang.com',
    'from_name' => 'GREAT LEGACY BULLY',
    'reply_to' => 'greatlegacybully@gmail.com' // куда будут приходить ответы
];
