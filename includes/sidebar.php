<?php
function menuActive($keyword) {
    return strpos($_SERVER['REQUEST_URI'], $keyword) !== false ? 'active' : '';
}

$role = $_SESSION['role'] ?? '';
$nama = $_SESSION['nama'] ?? 'User';
$inisial = strtoupper(substr($nama, 0, 1));
?>

<div class="sidebar">
    <div>
        <div class="brand-box">
            <div class="brand-logo"><i class="fa-solid fa-qrcode"></i></div>
            <div class="brand-text">
                <div class="brand-title">E-ABSENSI</div>
                <div class="brand-sub">SCHOOL SYSTEM</div>
            </div>
        </div>

        <div class="user-panel">
            <div class="user-avatar"><?= $inisial; ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($nama); ?></div>
                <div class="user-role"><?= htmlspecialchars($role); ?></div>
            </div>
        </div>

        <div class="sidebar-menu">
            <?php if ($role === 'admin'): ?>
                <a href="<?= BASE_URL; ?>/admin/dashboard.php" class="<?= menuActive('/admin/dashboard.php'); ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= BASE_URL; ?>/admin/siswa.php" class="<?= menuActive('/admin/siswa.php'); ?>">
                    <i class="fa-solid fa-user-graduate"></i>
                    <span>Data Siswa</span>
                </a>
                <a href="<?= BASE_URL; ?>/admin/guru.php" class="<?= menuActive('/admin/guru.php'); ?>">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>Data Guru</span>
                </a>
                <a href="<?= BASE_URL; ?>/admin/laporan.php" class="<?= menuActive('/admin/laporan.php'); ?>">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>Laporan</span>
                </a>
                <a href="<?= BASE_URL; ?>/admin/absensi.php" class="<?= menuActive('/admin/absensi.php'); ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Kelola Absen</span>
                </a>
                <a href="<?= BASE_URL; ?>/admin/scan.php" class="<?= menuActive('/admin/scan.php'); ?>">
                    <i class="fa-solid fa-qrcode"></i>
                    <span>Scan Absensi</span>
                </a>

            <?php elseif ($role === 'guru'): ?>
                <a href="<?= BASE_URL; ?>/guru/dashboard.php" class="<?= menuActive('/guru/dashboard.php'); ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= BASE_URL; ?>/guru/monitoring.php" class="<?= (menuActive('/guru/monitoring.php') || menuActive('/guru/monitoring_kelas.php')) ? 'active' : ''; ?>">
                    <i class="fa-solid fa-eye"></i>
                    <span>Monitoring</span>
                </a>
                <a href="<?= BASE_URL; ?>/guru/scan.php" class="<?= menuActive('/guru/scan.php'); ?>">
                    <i class="fa-solid fa-qrcode"></i>
                    <span>Scan Absensi</span>
                </a>

            <?php elseif ($role === 'siswa'): ?>
                <a href="<?= BASE_URL; ?>/siswa/dashboard.php" class="<?= menuActive('/siswa/dashboard.php'); ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= BASE_URL; ?>/siswa/kartu_digital.php" class="<?= menuActive('/siswa/kartu_digital.php'); ?>">
                    <i class="fa-solid fa-id-card"></i>
                    <span>Kartu Saya</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="sidebar-logout">
        <a href="<?= BASE_URL; ?>/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Keluar Aplikasi</span>
        </a>
    </div>
</div>