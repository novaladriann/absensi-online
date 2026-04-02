<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_role(['admin']);

$totalSiswa = $conn->query("SELECT COUNT(*) as total FROM users WHERE role='siswa'")->fetch_assoc()['total'] ?? 0;
$hadir      = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE tanggal = CURDATE() AND status_masuk='Hadir'")->fetch_assoc()['total'] ?? 0;
$sakit      = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE tanggal = CURDATE() AND status_masuk='Sakit'")->fetch_assoc()['total'] ?? 0;
$izin       = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE tanggal = CURDATE() AND status_masuk='Izin'")->fetch_assoc()['total'] ?? 0;
$alpa       = $conn->query("SELECT COUNT(*) as total FROM absensi WHERE tanggal = CURDATE() AND status_masuk='Alpa'")->fetch_assoc()['total'] ?? 0;

$pageTitle = 'Dashboard';
include '../includes/header.php';
?>
<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>

        <div class="content-area">
            <div class="page-heading">
                <div>
                    <h1>Dashboard Admin</h1>
                    <p>Pusat kontrol data absensi sekolah.</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="btn-light-theme"><i class="fa-regular fa-clock"></i> <?= date('d/m/Y'); ?></button>
                    <button class="btn-primary-theme"><i class="fa-solid fa-rotate-right"></i> Refresh Data</button>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Siswa</div>
                    <div class="stat-value"><?= $totalSiswa; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Hadir</div>
                    <div class="stat-value"><?= $hadir; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Sakit</div>
                    <div class="stat-value"><?= $sakit; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Izin</div>
                    <div class="stat-value"><?= $izin; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Alpa</div>
                    <div class="stat-value"><?= $alpa; ?></div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="panel-card">
                    <h3 style="margin-top:0;">Grafik Statistik Kehadiran</h3>
                    <div class="chart-placeholder">
                        <div class="chart-bar" style="left:16%; height:40%;"></div>
                        <div class="chart-bar" style="left:32%; height:18%;"></div>
                        <div class="chart-bar" style="left:48%; height:15%;"></div>
                        <div class="chart-bar" style="left:64%; height:10%;"></div>
                        <div class="chart-bar" style="left:80%; height:80%;"></div>
                    </div>
                </div>

                <div class="panel-card">
                    <h3 style="margin-top:0;">Akses Cepat</h3>
                    <div class="quick-menu">
                        <div class="quick-item">
                            <div class="quick-icon icon-purple"><i class="fa-solid fa-qrcode"></i></div>
                            <div>
                                <strong>Scan Absensi</strong><br>
                                <span style="color:#7f879c;">Mode scanner kamera</span>
                            </div>
                        </div>

                        <div class="quick-item">
                            <div class="quick-icon icon-blue"><i class="fa-solid fa-user-graduate"></i></div>
                            <div>
                                <strong>Data Siswa</strong><br>
                                <span style="color:#7f879c;">Kelola database siswa</span>
                            </div>
                        </div>

                        <div class="quick-item">
                            <div class="quick-icon icon-green"><i class="fa-solid fa-file-lines"></i></div>
                            <div>
                                <strong>Laporan</strong><br>
                                <span style="color:#7f879c;">Export & rekap data</span>
                            </div>
                        </div>

                        <div class="quick-item">
                            <div class="quick-icon icon-red"><i class="fa-solid fa-calendar-xmark"></i></div>
                            <div>
                                <strong>Hari Libur</strong><br>
                                <span style="color:#7f879c;">Setting tanggal merah</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>