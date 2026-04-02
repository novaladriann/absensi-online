<?php
require_once __DIR__ . '/config.php';

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'absensi_online';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('Koneksi database gagal: ' . $conn->connect_error);
}
?>