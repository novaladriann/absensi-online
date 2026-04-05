<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['siswa']);

if (!isset($_SESSION['siswa_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$siswaId = (int) $_SESSION['siswa_id'];

$namaSiswa   = $_SESSION['nama'] ?? 'Siswa';
$nis         = '-';
$nisn        = $_SESSION['nisn'] ?? '-';
$kelas       = '-';
$statusSiswa = 'Aktif';
$foto        = null;
$kodeKartu   = '-';

$jamMasuk    = '-';
$jamPulang   = '-';
$statusHariIni = 'Belum Absen';

/*
|--------------------------------------------------------------------------
| Ambil data siswa
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT 
        u.nama,
        s.nis,
        s.nisn,
        s.foto,
        s.kode_kartu,
        s.status_siswa,
        k.nama_kelas
    FROM siswa s
    INNER JOIN users u ON u.id = s.user_id
    INNER JOIN kelas k ON k.id = s.kelas_id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $siswaId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();

    $namaSiswa   = $data['nama'] ?? $namaSiswa;
    $nis         = $data['nis'] ?? '-';
    $nisn        = $data['nisn'] ?? $nisn;
    $kelas       = $data['nama_kelas'] ?? '-';
    $statusSiswa = $data['status_siswa'] ?? 'Aktif';
    $foto        = $data['foto'] ?? null;
    $kodeKartu   = $data['kode_kartu'] ?? '-';
}
$stmt->close();

/*
|--------------------------------------------------------------------------
| Ambil absensi hari ini
|--------------------------------------------------------------------------
*/
$stmtAbsen = $conn->prepare("
    SELECT 
        jam_masuk,
        jam_pulang,
        status_masuk,
        status_pulang
    FROM absensi
    WHERE siswa_id = ? AND tanggal = CURDATE()
    LIMIT 1
");
$stmtAbsen->bind_param("i", $siswaId);
$stmtAbsen->execute();
$resultAbsen = $stmtAbsen->get_result();

if ($resultAbsen && $resultAbsen->num_rows > 0) {
    $absen = $resultAbsen->fetch_assoc();

    $jamMasuk = !empty($absen['jam_masuk']) ? substr($absen['jam_masuk'], 0, 5) : '-';
    $jamPulang = !empty($absen['jam_pulang']) ? substr($absen['jam_pulang'], 0, 5) : '-';

    if (!empty($absen['status_pulang']) && $absen['status_pulang'] === 'Pulang') {
        $statusHariIni = 'Sudah Pulang';
    } elseif (!empty($absen['status_masuk'])) {
        $statusHariIni = $absen['status_masuk'];
    }
}
$stmtAbsen->close();

$pageTitle = 'Dashboard';
include '../includes/header.php';
?>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>

        <div class="content-area">
            <div class="student-layout">
                <div>
                    <div class="student-hero">
                        <div class="student-hero-top">
                            <div>
                                <div class="student-date"><?= strtoupper(date('l, d F Y')); ?></div>
                                <div class="student-greet">Hai, <?= htmlspecialchars($namaSiswa); ?></div>
                                <div class="student-sub">Selamat datang di dashboard absensi siswa.</div>
                            </div>
                            <div class="badge-live"><?= htmlspecialchars($statusHariIni); ?></div>
                        </div>

                        <div class="time-grid">
                            <div class="time-box">
                                <div class="time-label">
                                    <i class="fa-solid fa-right-to-bracket"></i> JAM DATANG
                                </div>
                                <div class="time-value"><?= htmlspecialchars($jamMasuk); ?></div>
                            </div>

                            <div class="time-box">
                                <div class="time-label">
                                    <i class="fa-solid fa-right-from-bracket"></i> JAM PULANG
                                </div>
                                <div class="time-value"><?= htmlspecialchars($jamPulang); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="profile-card">
                        <div class="profile-header"></div>

                        <div class="profile-avatar">
                            <?php if (!empty($foto)) : ?>
                                <img
                                    src="<?= BASE_URL; ?>/upload/siswa/<?= htmlspecialchars($foto); ?>"
                                    alt="Foto Siswa"
                                    class="profile-avatar-img">
                            <?php else : ?>
                                <i class="fa-regular fa-user"></i>
                            <?php endif; ?>
                        </div>

                        <div class="profile-body">
                            <div class="profile-name"><?= htmlspecialchars($namaSiswa); ?></div>
                            <div class="profile-id">NISN: <?= htmlspecialchars($nisn); ?></div>

                            <div class="profile-meta">
                                <div class="meta-box">
                                    <div class="meta-label">NIS</div>
                                    <div class="meta-value meta-small"><?= htmlspecialchars($nis); ?></div>
                                </div>

                                <div class="meta-box">
                                    <div class="meta-label">Kelas</div>
                                    <div class="meta-value meta-small"><?= htmlspecialchars($kelas); ?></div>
                                </div>

                                <div class="meta-box">
                                    <div class="meta-label">Status</div>
                                    <div class="meta-value meta-small <?= strtolower($statusSiswa) === 'aktif' ? 'status-active' : '' ?>">
                                        <?= htmlspecialchars($statusSiswa); ?>
                                    </div>
                                </div>

                                <div class="meta-box">
                                    <div class="meta-label">Kode Kartu</div>
                                    <div class="meta-value meta-small"><?= htmlspecialchars($kodeKartu); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <a href="<?= BASE_URL; ?>/siswa/kartu_digital.php" class="digital-card-link">
                        <div>
                            <h4>Kartu Digital</h4>
                            <p>Lihat kartu absensi siswa</p>
                        </div>
                        <div>
                            <i class="fa-solid fa-qrcode" style="font-size:28px;"></i>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>