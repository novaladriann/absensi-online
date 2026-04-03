<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru']);

$guruId = getGuruId($conn);

if (!$guruId) {
    die('Data guru tidak ditemukan.');
}

/* ---------- Tanggal ---------- */
$tanggalDipilih = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalDipilih)) {
    $tanggalDipilih = date('Y-m-d');
}
$labelTanggal = formatTanggalIndonesia($tanggalDipilih);

/* ---------- Ambil daftar kelas + rekap absensi ---------- */
$stmt = $conn->prepare("
    SELECT
        k.id                                                            AS kelas_id,
        k.nama_kelas,
        gk.role_guru_kelas,
        COUNT(DISTINCT s.id)                                            AS total_siswa,
        SUM(CASE WHEN a.status_masuk = 'Hadir'     THEN 1 ELSE 0 END)  AS hadir,
        SUM(CASE WHEN a.status_masuk = 'Terlambat' THEN 1 ELSE 0 END)  AS terlambat,
        SUM(CASE WHEN a.status_masuk = 'Sakit'     THEN 1 ELSE 0 END)  AS sakit,
        SUM(CASE WHEN a.status_masuk = 'Izin'      THEN 1 ELSE 0 END)  AS izin,
        SUM(CASE WHEN a.status_masuk = 'Alpa'      THEN 1 ELSE 0 END)  AS alpa
    FROM guru_kelas gk
    INNER JOIN kelas k   ON k.id         = gk.kelas_id
    LEFT  JOIN siswa s   ON s.kelas_id   = k.id
                        AND s.status_siswa = 'aktif'
    LEFT  JOIN absensi a ON a.siswa_id   = s.id
                        AND a.tanggal    = ?
    WHERE gk.guru_id = ?
    GROUP BY k.id, k.nama_kelas, gk.role_guru_kelas
    ORDER BY gk.role_guru_kelas DESC, k.nama_kelas ASC
");
$stmt->bind_param("si", $tanggalDipilih, $guruId);
$stmt->execute();
$daftarKelas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Hitung belum absen & persentase kehadiran */
foreach ($daftarKelas as &$k) {
    $tercatat    = $k['hadir'] + $k['terlambat'] + $k['sakit'] + $k['izin'] + $k['alpa'];
    $k['belum']  = max((int)$k['total_siswa'] - $tercatat, 0);
    $hadirTotal  = $k['hadir'] + $k['terlambat'];
    $k['persen'] = $k['total_siswa'] > 0
                 ? round($hadirTotal / $k['total_siswa'] * 100)
                 : 0;
}
unset($k);

$pageTitle = 'Monitoring Kehadiran';
include '../includes/header.php';
?>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>

        <div class="content-area">

            <div class="page-heading">
                <div>
                    <h1>Monitoring Kehadiran</h1>
                    <p>Pilih kelas yang ingin dipantau &mdash; <strong><?= htmlspecialchars($labelTanggal); ?></strong></p>
                </div>
                <div class="guru-filter-actions">
                    <button type="button" class="btn-light-theme guru-date-btn" id="dateDisplayBtn">
                        <i class="fa-regular fa-calendar"></i>
                        <span id="dateDisplayText"><?= htmlspecialchars($labelTanggal); ?></span>
                    </button>
                    <input type="date" id="tanggalInput" class="guru-hidden-date"
                           value="<?= $tanggalDipilih; ?>">
                </div>
            </div>

            <?php if (empty($daftarKelas)): ?>
                <div class="empty-state-theme" style="padding:64px 20px;">
                    <i class="fa-solid fa-school" style="font-size:36px;opacity:.3;display:block;margin-bottom:14px;"></i>
                    Anda belum ditugaskan di kelas manapun.
                </div>
            <?php else: ?>
                <div class="mkelas-grid">
                    <?php foreach ($daftarKelas as $kelas): ?>
                        <?php
                        $isWali  = $kelas['role_guru_kelas'] === 'wali';
                        $persen  = $kelas['persen'];
                        $url     = BASE_URL . '/guru/monitoring_kelas.php'
                                 . '?kelas_id=' . $kelas['kelas_id']
                                 . '&tanggal='  . urlencode($tanggalDipilih);

                        // Warna progress bar
                        if ($persen >= 80)      $fillClass = 'fill-green';
                        elseif ($persen >= 50)  $fillClass = 'fill-yellow';
                        else                    $fillClass = 'fill-red';
                        ?>
                        <a href="<?= $url; ?>" class="mkelas-card <?= $isWali ? 'mkelas-card-wali' : ''; ?>">

                            <div class="mkelas-card-top">
                                <div class="mkelas-badges">
                                    <?php if ($isWali): ?>
                                        <span class="mkelas-role mkelas-role-wali">
                                            <i class="fa-solid fa-star"></i> Wali Kelas
                                        </span>
                                    <?php else: ?>
                                        <span class="mkelas-role mkelas-role-pengajar">
                                            <i class="fa-solid fa-chalkboard-user"></i> Pengajar
                                        </span>
                                    <?php endif; ?>
                                    <span class="mkelas-total">
                                        <i class="fa-solid fa-users"></i>
                                        <?= (int)$kelas['total_siswa']; ?> Siswa
                                    </span>
                                </div>
                                <div class="mkelas-arrow-icon">
                                    <i class="fa-solid fa-arrow-right"></i>
                                </div>
                            </div>

                            <div class="mkelas-name"><?= htmlspecialchars($kelas['nama_kelas']); ?></div>

                            <!-- Progress bar kehadiran -->
                            <div class="mkelas-progress-wrap">
                                <div class="mkelas-progress-track">
                                    <div class="mkelas-progress-fill <?= $fillClass; ?>"
                                         style="width:<?= $persen; ?>%"></div>
                                </div>
                                <span class="mkelas-progress-pct"><?= $persen; ?>% hadir</span>
                            </div>

                            <!-- Statistik mini -->
                            <div class="mkelas-stats-row">
                                <div class="mkelas-stat">
                                    <span class="mkelas-stat-dot dot-green"></span>
                                    <span class="mkelas-stat-num"><?= (int)($kelas['hadir'] + $kelas['terlambat']); ?></span>
                                    <span class="mkelas-stat-lbl">Hadir</span>
                                </div>
                                <div class="mkelas-stat">
                                    <span class="mkelas-stat-dot dot-yellow"></span>
                                    <span class="mkelas-stat-num"><?= (int)($kelas['sakit'] + $kelas['izin']); ?></span>
                                    <span class="mkelas-stat-lbl">Izin/Sakit</span>
                                </div>
                                <div class="mkelas-stat">
                                    <span class="mkelas-stat-dot dot-red"></span>
                                    <span class="mkelas-stat-num"><?= (int)$kelas['alpa']; ?></span>
                                    <span class="mkelas-stat-lbl">Alpa</span>
                                </div>
                                <div class="mkelas-stat">
                                    <span class="mkelas-stat-dot dot-muted"></span>
                                    <span class="mkelas-stat-num"><?= (int)$kelas['belum']; ?></span>
                                    <span class="mkelas-stat-lbl">Belum</span>
                                </div>
                            </div>

                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
(function () {
    const btn   = document.getElementById('dateDisplayBtn');
    const input = document.getElementById('tanggalInput');

    btn.addEventListener('click', function () {
        input.showPicker ? input.showPicker() : input.click();
    });

    input.addEventListener('change', function () {
        if (!this.value) return;
        const url = new URL(window.location.href);
        url.searchParams.set('tanggal', this.value);
        window.location.href = url.toString();
    });
})();
</script>

<?php include '../includes/footer.php'; ?>