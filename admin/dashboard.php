<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);

$tanggalAwal = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalAwal)) {
    $tanggalAwal = date('Y-m-d');
}

$dashboardConfig = [
    'heading' => 'Dashboard Admin',
    'description' => 'Pusat kontrol data absensi sekolah.',
    'endpoint' => BASE_URL . '/admin/dashboard_data.php',
    'panelType' => 'quick_access',
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
            'icon' => 'fa-solid fa-paper-plane',
        ],
        [
            'key' => 'alpa',
            'label' => 'ALPA',
            'iconClass' => 'icon-red-soft',
            'icon' => 'fa-solid fa-xmark',
        ],
    ],
    'quickLinks' => [
        [
            'href' => BASE_URL . '/admin/scan_absensi.php',
            'iconClass' => 'icon-purple',
            'icon' => 'fa-solid fa-qrcode',
            'title' => 'Scan Absensi',
            'subtitle' => 'Mode scanner kamera',
        ],
        [
            'href' => BASE_URL . '/admin/data_siswa.php',
            'iconClass' => 'icon-blue',
            'icon' => 'fa-solid fa-user-graduate',
            'title' => 'Data Siswa',
            'subtitle' => 'Kelola database siswa',
        ],
        [
            'href' => BASE_URL . '/admin/data_guru.php',
            'iconClass' => 'icon-blue',
            'icon' => 'fa-solid fa-chalkboard-user',
            'title' => 'Data Guru',
            'subtitle' => 'Kelola database guru',
        ],
        [
            'href' => BASE_URL . '/admin/laporan.php',
            'iconClass' => 'icon-green',
            'icon' => 'fa-solid fa-file-lines',
            'title' => 'Laporan',
            'subtitle' => 'Export & rekap data',
        ],
        [
            'href' => BASE_URL . '/admin/kelola_absen.php',
            'iconClass' => 'icon-red',
            'icon' => 'fa-solid fa-calendar-xmark',
            'title' => 'Kelola Absen',
            'subtitle' => 'Jadwal & hari libur',
        ],
    ],
];

$pageTitle = 'Dashboard Admin';
include '../includes/header.php';
include '../includes/dashboard_template.php';
include '../includes/footer.php';
