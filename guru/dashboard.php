<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru']);

$tanggalAwal = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal)) {
    $tanggalAwal = date('Y-m-d');
}

$dashboardConfig = [
    'heading' => 'Dashboard Guru',
    'description' => 'Ringkasan aktivitas siswa.',
    'endpoint' => BASE_URL . '/guru/dashboard_data.php',
    'panelType' => 'scan',
    'showKelasFilter' => true,
    'showHolidayBanner' => true,
    'initialDate' => $tanggalAwal,
    'initialDateLabel' => formatTanggalIndonesia($tanggalAwal),
    'chartTitle' => 'Statistik Kehadiran Hari Ini',
    'statsCards' => [
        [
            'key' => 'totalSiswa',
            'label' => 'TOTAL SISWA',
            'iconClass' => 'icon-purple-soft',
            'icon' => 'fa-solid fa-user-graduate',
        ],
        [
            'key' => 'sakit',
            'label' => 'SAKIT',
            'iconClass' => 'icon-yellow-soft',
            'icon' => 'fa-solid fa-bed',
        ],
        [
            'key' => 'izin',
            'label' => 'IZIN',
            'iconClass' => 'icon-blue-soft',
            'icon' => 'fa-solid fa-clipboard-check',
        ],
        [
            'key' => 'alpa',
            'label' => 'ALPA',
            'iconClass' => 'icon-red-soft',
            'icon' => 'fa-solid fa-circle-xmark',
        ],
    ],
];

$pageTitle = 'Dashboard Guru';
include '../includes/header.php';
include '../includes/dashboard_template.php';
include '../includes/footer.php';