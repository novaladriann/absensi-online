<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

$scheme = 'http';

if (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
) {
    $scheme = 'https';
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = '/absensi-online';

define('BASE_URL', $scheme . '://' . $host . $basePath);
define('APP_NAME', 'E-Absensi');
?>