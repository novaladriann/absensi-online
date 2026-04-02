<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru']);

$guruId = getGuruId($conn);
$userId = (int) ($_SESSION['user_id'] ?? 0);

if (!$guruId) {
    die('Data guru tidak ditemukan.');
}

/* ---------- Sanitasi input ---------- */
$tanggalDipilih = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalDipilih)) {
    $tanggalDipilih = date('Y-m-d');
}

$statusFilter   = trim($_GET['status'] ?? '');
$q              = trim($_GET['q']      ?? '');
$allowedStatus  = ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa'];

/* ---------- POST: update status absensi ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $siswaId    = (int)   ($_POST['siswa_id']   ?? 0);
    $statusBaru = trim(   $_POST['status_baru'] ?? '');
    $tanggalPost = trim(  $_POST['tanggal']     ?? date('Y-m-d'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPost)) {
        $tanggalPost = date('Y-m-d');
    }

    if ($siswaId > 0 && in_array($statusBaru, $allowedStatus, true)) {
        // Pastikan siswa memang di kelas yang diampu guru ini
        $stmtCheck = $conn->prepare("
            SELECT s.id
            FROM siswa s
            INNER JOIN guru_kelas gk ON gk.kelas_id = s.kelas_id
            WHERE s.id = ? AND gk.guru_id = ?
            LIMIT 1
        ");
        $stmtCheck->bind_param("ii", $siswaId, $guruId);
        $stmtCheck->execute();
        $isValid = $stmtCheck->get_result()->num_rows > 0;
        $stmtCheck->close();

        if ($isValid) {
            // Cek apakah record absensi sudah ada hari itu
            $stmtCek = $conn->prepare("
                SELECT id FROM absensi
                WHERE siswa_id = ? AND tanggal = ?
                LIMIT 1
            ");
            $stmtCek->bind_param("is", $siswaId, $tanggalPost);
            $stmtCek->execute();
            $rowCek = $stmtCek->get_result()->fetch_assoc();
            $stmtCek->close();

            if ($rowCek) {
                // Update record yang sudah ada
                $keterangan = 'Status diperbarui guru dari monitoring';
                $stmtUpdate = $conn->prepare("
                    UPDATE absensi
                    SET status_masuk        = ?,
                        metode_absen        = 'manual',
                        scanned_by_user_id  = ?,
                        keterangan          = ?
                    WHERE id = ?
                ");
                $stmtUpdate->bind_param("sisi", $statusBaru, $userId, $keterangan, $rowCek['id']);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            } else {
                // Insert record baru
                $keterangan = 'Status dibuat guru dari monitoring';
                $stmtInsert = $conn->prepare("
                    INSERT INTO absensi (siswa_id, tanggal, status_masuk, metode_absen, scanned_by_user_id, keterangan)
                    VALUES (?, ?, ?, 'manual', ?, ?)
                ");
                $stmtInsert->bind_param("issis", $siswaId, $tanggalPost, $statusBaru, $userId, $keterangan);
                $stmtInsert->execute();
                $stmtInsert->close();
            }
        }
    }

    // Redirect kembali dengan mempertahankan filter
    $redirectUrl = BASE_URL . '/guru/monitoring.php?tanggal=' . urlencode($tanggalPost);
    if ($statusFilter !== '') $redirectUrl .= '&status=' . urlencode($statusFilter);
    if ($q !== '')            $redirectUrl .= '&q='      . urlencode($q);

    header("Location: $redirectUrl");
    exit;
}

/* ---------- Jadwal hari ini (untuk hitung terlambat) ---------- */
$hariJadwal     = hariIndonesia($tanggalDipilih);
$labelTanggal   = formatTanggalIndonesia($tanggalDipilih);
$batasTerlambat = null;

$stmtJadwal = $conn->prepare("
    SELECT batas_terlambat
    FROM jadwal_absensi
    WHERE hari = ? AND status_aktif = 'aktif'
    LIMIT 1
");
$stmtJadwal->bind_param("s", $hariJadwal);
$stmtJadwal->execute();
$jadwalRow = $stmtJadwal->get_result()->fetch_assoc();
$stmtJadwal->close();

if ($jadwalRow) {
    $batasTerlambat = $jadwalRow['batas_terlambat'];
}

/* ---------- Query data monitoring ---------- */
$sql = "
    SELECT
        s.id   AS siswa_id,
        u.nama,
        s.nisn,
        k.nama_kelas,
        a.id   AS absensi_id,
        a.jam_masuk,
        a.jam_pulang,
        a.status_masuk,
        a.status_pulang,
        a.keterangan
    FROM siswa s
    INNER JOIN users u         ON u.id        = s.user_id
    INNER JOIN kelas k         ON k.id        = s.kelas_id
    INNER JOIN guru_kelas gk   ON gk.kelas_id = k.id
    LEFT  JOIN absensi a       ON a.siswa_id  = s.id
                               AND a.tanggal  = ?
    WHERE gk.guru_id    = ?
      AND s.status_siswa = 'aktif'
";

$params = [$tanggalDipilih, $guruId];
$types  = "si";

if ($statusFilter !== '') {
    if ($statusFilter === 'Belum Absen') {
        $sql .= " AND a.id IS NULL ";
    } else {
        $sql .= " AND a.status_masuk = ? ";
        $params[] = $statusFilter;
        $types   .= "s";
    }
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $sql .= " AND (u.nama LIKE ? OR s.nisn LIKE ? OR k.nama_kelas LIKE ?) ";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= "sss";
}

$sql .= " ORDER BY k.nama_kelas ASC, u.nama ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* ---------- Proses hasil query ---------- */
$dataMonitoring = [];

while ($row = $result->fetch_assoc()) {
    $statusSaatIni   = $row['status_masuk'] ?? 'Belum Absen';
    $keteranganWaktu = 'Belum Absen';
    $keteranganClass = 'status-yellow';

    switch ($statusSaatIni) {
        case 'Hadir':
            $keteranganWaktu = 'Tepat Waktu';
            $keteranganClass = 'status-green';
            break;

        case 'Terlambat':
            if (!empty($row['jam_masuk']) && !empty($batasTerlambat)) {
                $selisih = floor((strtotime($row['jam_masuk']) - strtotime($batasTerlambat)) / 60);
                $menit   = max($selisih, 0);
                $keteranganWaktu = 'Terlambat (' . $menit . ' m)';
            } else {
                $keteranganWaktu = 'Terlambat';
            }
            $keteranganClass = 'status-red';
            break;

        case 'Izin':
            $keteranganWaktu = 'Izin';
            $keteranganClass = 'status-yellow';
            break;

        case 'Sakit':
            $keteranganWaktu = 'Sakit';
            $keteranganClass = 'status-yellow';
            break;

        case 'Alpa':
            $keteranganWaktu = 'Tidak Hadir';
            $keteranganClass = 'status-red';
            break;
    }

    $row['status_saat_ini']   = $statusSaatIni;
    $row['keterangan_waktu']  = $keteranganWaktu;
    $row['keterangan_class']  = $keteranganClass;

    $dataMonitoring[] = $row;
}

$stmt->close();
$totalData = count($dataMonitoring);

/* ---------- Render ---------- */
$pageTitle = 'Monitoring Realtime';
include '../includes/header.php';
?>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>

        <div class="content-area">
            <div class="table-card">

                <!-- Header -->
                <div class="table-header">
                    <div>
                        <div class="table-title">Monitoring Kehadiran</div>
                        <div class="monitoring-sub-label">
                            Data Realtime:
                            <strong><?= htmlspecialchars($labelTanggal); ?></strong>
                        </div>
                    </div>
                    <div class="table-tools">
                        <a href="<?= BASE_URL; ?>/guru/monitoring.php?tanggal=<?= urlencode($tanggalDipilih); ?>&status=<?= urlencode($statusFilter); ?>&q=<?= urlencode($q); ?>"
                           class="btn-light-theme" title="Refresh">
                            <i class="fa-solid fa-rotate-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Filter -->
                <form method="GET" class="filter-row monitoring-filter-row">
                    <div class="filter-left monitoring-filter-left">
                        <input type="date" name="tanggal"
                               value="<?= htmlspecialchars($tanggalDipilih); ?>"
                               class="input-theme">

                        <select name="status" class="select-theme">
                            <option value="">Semua Status</option>
                            <?php
                            $statusOptions = ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', 'Belum Absen'];
                            foreach ($statusOptions as $opt):
                                $sel = $statusFilter === $opt ? 'selected' : '';
                            ?>
                                <option value="<?= $opt; ?>" <?= $sel; ?>><?= $opt; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-right monitoring-filter-right">
                        <input type="text" name="q"
                               value="<?= htmlspecialchars($q); ?>"
                               class="input-theme search-theme"
                               placeholder="Cari Nama / NISN / Kelas...">
                        <button type="submit" class="btn-light-theme">Terapkan</button>
                    </div>
                </form>

                <!-- Tabel -->
                <div class="table-responsive-theme">
                    <table class="theme-table monitoring-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th>Jam Masuk</th>
                                <th>Jam Pulang</th>
                                <th>Keterangan</th>
                                <th>Status Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($totalData > 0): ?>
                                <?php foreach ($dataMonitoring as $i => $row): ?>
                                    <tr>
                                        <td><?= $i + 1; ?></td>

                                        <td>
                                            <strong><?= htmlspecialchars($row['nama']); ?></strong><br>
                                            <span class="monitoring-nisn"><?= htmlspecialchars($row['nisn']); ?></span>
                                        </td>

                                        <td>
                                            <span class="status-pill status-pill-kelas">
                                                <?= htmlspecialchars($row['nama_kelas']); ?>
                                            </span>
                                        </td>

                                        <td><?= !empty($row['jam_masuk'])  ? substr($row['jam_masuk'],  0, 5) : '-'; ?></td>
                                        <td><?= !empty($row['jam_pulang']) ? substr($row['jam_pulang'], 0, 5) : '-'; ?></td>

                                        <td>
                                            <span class="status-pill <?= $row['keterangan_class']; ?>">
                                                <?= htmlspecialchars($row['keterangan_waktu']); ?>
                                            </span>
                                        </td>

                                        <!-- Dropdown ubah status — submit otomatis saat berubah -->
                                        <td>
                                            <form method="POST" class="status-update-form">
                                                <input type="hidden" name="action"    value="update_status">
                                                <input type="hidden" name="siswa_id"  value="<?= (int) $row['siswa_id']; ?>">
                                                <input type="hidden" name="tanggal"   value="<?= htmlspecialchars($tanggalDipilih); ?>">

                                                <select name="status_baru"
                                                        class="status-select <?= strtolower(str_replace(' ', '-', $row['status_saat_ini'])); ?>"
                                                        onchange="this.form.submit()">
                                                    <?php foreach ($allowedStatus as $opt):
                                                        $sel = $row['status_saat_ini'] === $opt ? 'selected' : '';
                                                    ?>
                                                        <option value="<?= $opt; ?>" <?= $sel; ?>><?= $opt; ?></option>
                                                    <?php endforeach; ?>
                                                    <?php if ($row['status_saat_ini'] === 'Belum Absen'): ?>
                                                        <option value="" selected disabled>— Belum Absen —</option>
                                                    <?php endif; ?>
                                                </select>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state-theme">
                                            <i class="fa-solid fa-inbox" style="font-size:28px;opacity:.4;display:block;margin-bottom:10px;"></i>
                                            Tidak ada data untuk filter yang dipilih.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer info -->
                <div class="monitoring-footer-note">
                    Menampilkan <strong><?= $totalData; ?></strong> dari <strong><?= $totalData; ?></strong> data
                </div>

            </div><!-- /.table-card -->
        </div><!-- /.content-area -->
    </div><!-- /.main-content -->
</div><!-- /.app-layout -->

<?php include '../includes/footer.php'; ?>