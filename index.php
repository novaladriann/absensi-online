<?php
require_once 'config/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$role = strtolower(trim($_SESSION['role'] ?? ''));

switch ($role) {
    case 'admin':
        header("Location: admin/dashboard.php");
        exit;

    case 'guru':
        header("Location: guru/dashboard.php");
        exit;

    case 'siswa':
        header("Location: siswa/dashboard.php");
        exit;

    default:
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
}
?>