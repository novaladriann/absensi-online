<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);

$scanConfig = [
    'pageTitle'  => 'Scan Absensi',
    'scanTitle'  => 'Scan Absensi Admin',
    'scanSub'    => 'Arahkan kamera ke kartu QR siswa',
    'backUrl'    => BASE_URL . '/admin/dashboard.php',
    'backLabel'  => 'Kembali ke Dashboard',
    'processUrl' => BASE_URL . '/scan/process.php',
    'infoUrl'    => BASE_URL . '/scan/info.php',
    'infoTitle'  => 'Ringkasan Hari Ini',
];

include '../includes/scan_template.php';