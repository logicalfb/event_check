<?php
// config.php — Configuration centrale de l'application

define('DB_HOST', 'localhost');
define('DB_NAME', 'event_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');

// Détection automatique du domaine (fonctionne en local et en production)
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$baseUrl  = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
define('BASE_URL', rtrim($baseUrl, '/'));
?>