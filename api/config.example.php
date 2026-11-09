<?php
// Copia questo file in config.php e inserisci i valori reali (config.php e' in .gitignore)
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_USER', 'user@example.com');
define('SMTP_PASS', 'CHANGE_ME');
define('SMTP_PORT', 587);         // 587 (TLS) o 465 (SSL)
define('SMTP_SECURE', 'tls');     // 'tls' o 'ssl'
define('MAIL_FROM', 'noreply@example.com');
define('MAIL_FROM_NAME', 'MoneyMate');

// Chiave per firmare i JWT (genera con: php -r "echo bin2hex(random_bytes(32));")
define('JWT_SECRET', 'CHANGE_ME');
