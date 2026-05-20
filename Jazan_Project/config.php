<?php
// Central configuration
// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'if0_41747985_jazan_db');

// App
define('APP_TIMEZONE', 'Asia/Riyadh');

// Session settings
define('SESSION_COOKIE_LIFETIME', 0); // until browser close
define('SESSION_COOKIE_SECURE', false); // set true if using HTTPS
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Lax');

// Pagination
define('SCHOOLS_PER_PAGE', 10);

// Other
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php-error.log');
date_default_timezone_set(APP_TIMEZONE);

// SMTP (for PHPMailer)
// Fill these with your SMTP provider details when ready.
// SMTP (for PHPMailer)
define('SMTP_HOST', 'sandbox.smtp.mailtrap.io'); 
define('SMTP_PORT', 2525);                       
define('SMTP_USER', '5ba9c2887d0684'); // 👈 اليوزر الحقيقي الخاص بك
define('SMTP_PASS', '6f77e2fd6e5991'); // 👈 الباسورد الحقيقي الشغال بالملي
define('SMTP_FROM_EMAIL', 'test@jazanschools.com');
define('SMTP_FROM_NAME', 'Jazan App Beta');
?>