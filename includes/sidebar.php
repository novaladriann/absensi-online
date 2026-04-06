<?php
/**
 * Cek apakah halaman saat ini aktif
 * Menggunakan basename() agar tidak bergantung pada path lengkap URL
 *
 * @param string|array $keywords  nama file atau array nama file
 */
function menuActive(string|array $keywords): string {
    // Ambil hanya nama file dari URL, tanpa query string
    $currentFile = basename(strtok($_SERVER['REQUEST_URI'], '?'));

    foreach ((array)$keywords as $keyword) {
        if ($currentFile === $keyword) {
            return 'active';
        }
    }
    return '';
}

$role    = $_SESSION['role'] ?? '';
$nama    = $_SESSION['nama'] ?? 'User';
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
            <div class="user-avatar"><?= $inisial ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($nama) ?></div>
                <div class="user-role"><?= htmlspecialchars($role) ?></div>
            </div>
        </div>

        <div class="sidebar-menu">

            <?php if ($role === 'admin'): ?>

                <a href="<?= BASE_URL ?>/admin/dashboard.php"
                   class="<?= menuActive('dashboard.php') ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>

                <a href="<?= BASE_URL ?>/admin/data_kelas.php"
                   class="<?= menuActive(['data_kelas.php', 'data_kelas_list.php']) ?>">
                    <i class="fa-solid fa-school"></i>
                    <span>Data Kelas</span>
                </a>

                <a href="<?= BASE_URL ?>/admin/data_siswa.php"
                   class="<?= menuActive(['data_siswa.php', 'data_siswa_list.php']) ?>">
                    <i class="fa-solid fa-user-graduate"></i>
                    <span>Data Siswa</span>
                </a>

                <a href="<?= BASE_URL ?>/admin/data_guru.php"
                   class="<?= menuActive(['data_guru.php', 'data_guru_list.php']) ?>">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>Data Guru</span>
                </a>

                <a href="<?= BASE_URL ?>/admin/laporan.php"
                   class="<?= menuActive(['laporan.php', 'laporan_list.php', 'laporan_export.php']) ?>">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>Laporan</span>
                </a>

                <a href="<?= BASE_URL ?>/admin/kelola_absen.php"
                   class="<?= menuActive('kelola_absen.php') ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Kelola Absen</span>
                </a>

                <a href="<?= BASE_URL ?>/admin/scan_absensi.php"
                   class="<?= menuActive('scan_absensi.php') ?>">
                    <i class="fa-solid fa-qrcode"></i>
                    <span>Scan Absensi</span>
                </a>

            <?php elseif ($role === 'guru'): ?>

                <a href="<?= BASE_URL ?>/guru/dashboard.php"
                   class="<?= menuActive('dashboard.php') ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>

                <a href="<?= BASE_URL ?>/guru/monitoring.php"
                   class="<?= menuActive(['monitoring.php', 'monitoring_kelas.php']) ?>">
                    <i class="fa-solid fa-eye"></i>
                    <span>Monitoring</span>
                </a>

                <a href="<?= BASE_URL ?>/guru/scan.php"
                   class="<?= menuActive('scan.php') ?>">
                    <i class="fa-solid fa-qrcode"></i>
                    <span>Scan Absensi</span>
                </a>

            <?php elseif ($role === 'siswa'): ?>

                <a href="<?= BASE_URL ?>/siswa/dashboard.php"
                   class="<?= menuActive('dashboard.php') ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>

                <a href="<?= BASE_URL ?>/siswa/kartu_digital.php"
                   class="<?= menuActive('kartu_digital.php') ?>">
                    <i class="fa-solid fa-id-card"></i>
                    <span>Kartu Saya</span>
                </a>

            <?php endif; ?>

        </div>
    </div>

    <div class="sidebar-logout">
        <a href="<?= BASE_URL ?>/logout.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Keluar Aplikasi</span>
        </a>
    </div>
</div>