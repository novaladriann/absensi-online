<?php
$tanggalIndonesia = date('l, d F Y');

$hariMap = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

$bulanMap = [
    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];

$tanggalIndonesia = strtr($tanggalIndonesia, $hariMap);
$tanggalIndonesia = strtr($tanggalIndonesia, $bulanMap);

$pageTitle = $pageTitle ?? 'Dashboard';
?>

<div class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle Menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar-title"><?= $pageTitle; ?></div>
    </div>

    <div class="topbar-right">
        <div class="topbar-label">Hari Ini</div>
        <div class="topbar-date"><?= $tanggalIndonesia; ?></div>
    </div>
</div>