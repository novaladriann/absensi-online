<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru']);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$guruId = $_SESSION['guru_id'] ?? null;

if (!$guruId) {
    $stmtGuru = $conn->prepare("SELECT id FROM guru WHERE user_id = ? LIMIT 1");
    $stmtGuru->bind_param("i", $userId);
    $stmtGuru->execute();
    $resultGuru = $stmtGuru->get_result();

    if ($resultGuru && $resultGuru->num_rows > 0) {
        $guruRow = $resultGuru->fetch_assoc();
        $guruId = (int) $guruRow['id'];
        $_SESSION['guru_id'] = $guruId;
    }

    $stmtGuru->close();
}

if (!$guruId) {
    die('Data guru tidak ditemukan.');
}

function formatTanggalIndonesia($tanggal)
{
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

    $hasil = date('l, d F Y', strtotime($tanggal));
    $hasil = strtr($hasil, $hariMap);
    $hasil = strtr($hasil, $bulanMap);

    return $hasil;
}

function hariIndonesia($tanggal)
{
    $map = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu'
    ];

    return $map[date('l', strtotime($tanggal))] ?? '';
}

$tanggalDipilih = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalDipilih)) {
    $tanggalDipilih = date('Y-m-d');
}

$statusFilter = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');

$allowedStatus = ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $siswaId = (int) ($_POST['siswa_id'] ?? 0);
    $statusBaru = trim($_POST['status_baru'] ?? '');
    $tanggalPost = trim($_POST['tanggal'] ?? date('Y-m-d'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPost)) {
        $tanggalPost = date('Y-m-d');
    }

    if ($siswaId > 0 && in_array($statusBaru, $allowedStatus, true)) {
        $stmtCheck = $conn->prepare("
            SELECT s.id
            FROM siswa s
            INNER JOIN guru_kelas gk ON gk.kelas_id = s.kelas_id
            WHERE s.id = ?
              AND gk.guru_id = ?
            LIMIT 1
        ");
        $stmtCheck->bind_param("ii", $siswaId, $guruId);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        $isValid = $resultCheck && $resultCheck->num_rows > 0;
        $stmtCheck->close();

        if ($isValid) {
            $stmtAbsensi = $conn->prepare("
                SELECT id
                FROM absensi
                WHERE siswa_id = ? AND tanggal = ?
                LIMIT 1
            ");
            $stmtAbsensi->bind_param("is", $siswaId, $tanggalPost);
            $stmtAbsensi->execute();
            $resultAbsensi = $stmtAbsensi->get_result();

            if ($resultAbsensi && $resultAbsensi->num_rows > 0) {
                $absensi = $resultAbsensi->fetch_assoc();
                $absensiId = (int) $absensi['id'];

                $keterangan = 'Status diperbarui guru dari monitoring';
                $stmtUpdate = $conn->prepare("
                    UPDATE absensi
                    SET status_masuk = ?,
                        metode_absen = 'manual',
                        scanned_by_user_id = ?,
                        keterangan = ?
                    WHERE id = ?
                ");
                $stmtUpdate->bind_param("sisi", $statusBaru, $userId, $keterangan, $absensiId);
                $stmtUpdate->execute();
                $stmtUpdate->close();
            } else {
                $keterangan = 'Status dibuat guru dari monitoring';
                $stmtInsert = $conn->prepare("
                    INSERT INTO absensi (siswa_id, tanggal, status_masuk, metode_absen, scanned_by_user_id, keterangan)
                    VALUES (?, ?, ?, 'manual', ?, ?)
                ");
                $stmtInsert->bind_param("issis", $siswaId, $tanggalPost, $statusBaru, $userId, $keterangan);
                $stmtInsert->execute();
                $stmtInsert->close();
            }

            $stmtAbsensi->close();
        }
    }

    $redirectUrl = BASE_URL . '/guru/monitoring.php?tanggal=' . urlencode($tanggalPost);
    if ($statusFilter !== '') {
        $redirectUrl .= '&status=' . urlencode($statusFilter);
    }
    if ($q !== '') {
        $redirectUrl .= '&q=' . urlencode($q);
    }

    header("Location: $redirectUrl");
    exit;
}

$labelTanggal = formatTanggalIndonesia($tanggalDipilih);
$hariJadwal = hariIndonesia($tanggalDipilih);

$batasTerlambat = null;
$stmtJadwal = $conn->prepare("
    SELECT batas_terlambat
    FROM jadwal_absensi
    WHERE hari = ?
      AND status_aktif = 'aktif'
    LIMIT 1
");
$stmtJadwal->bind_param("s", $hariJadwal);
$stmtJadwal->execute();
$resultJadwal = $stmtJadwal->get_result();
if ($resultJadwal && $resultJadwal->num_rows > 0) {
    $jadwal = $resultJadwal->fetch_assoc();
    $batasTerlambat = $jadwal['batas_terlambat'] ?? null;
}
$stmtJadwal->close();

$sql = "
    SELECT
        s.id AS siswa_id,
        u.nama,
        s.nisn,
        k.nama_kelas,
        a.id AS absensi_id,
        a.jam_masuk,
        a.jam_pulang,
        a.status_masuk,
        a.status_pulang,
        a.keterangan
    FROM siswa s
    INNER JOIN users u ON u.id = s.user_id
    INNER JOIN kelas k ON k.id = s.kelas_id
    INNER JOIN guru_kelas gk ON gk.kelas_id = k.id
    LEFT JOIN absensi a
        ON a.siswa_id = s.id
       AND a.tanggal = ?
    WHERE gk.guru_id = ?
      AND s.status_siswa = 'aktif'
";

$params = [$tanggalDipilih, $guruId];
$types = "si";

if ($statusFilter !== '') {
    if ($statusFilter === 'Belum Absen') {
        $sql .= " AND a.id IS NULL ";
    } else {
        $sql .= " AND a.status_masuk = ? ";
        $params[] = $statusFilter;
        $types .= "s";
    }
}

if ($q !== '') {
    $sql .= " AND (
        u.nama LIKE ?
        OR s.nisn LIKE ?
        OR k.nama_kelas LIKE ?
    ) ";
    $searchLike = '%' . $q . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "sss";
}

$sql .= " ORDER BY k.nama_kelas ASC, u.nama ASC ";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$dataMonitoring = [];
while ($row = $result->fetch_assoc()) {
    $statusSaatIni = $row['status_masuk'] ?? 'Belum Absen';

    $keteranganWaktu = 'Belum Absen';
    $keteranganClass = 'status-yellow';

    if ($statusSaatIni === 'Terlambat') {
        if (!empty($row['jam_masuk']) && !empty($batasTerlambat)) {
            $terlambatMenit = floor((strtotime($row['jam_masuk']) - strtotime($batasTerlambat)) / 60);
            if ($terlambatMenit < 0) {
                $terlambatMenit = 0;
            }
            $keteranganWaktu = 'Terlambat (' . $terlambatMenit . ' m)';
        } else {
            $keteranganWaktu = 'Terlambat';
        }
        $keteranganClass = 'status-red';
    } elseif ($statusSaatIni === 'Hadir') {
        $keteranganWaktu = 'Tepat Waktu';
        $keteranganClass = 'status-green';
    } elseif ($statusSaatIni === 'Izin') {
        $keteranganWaktu = 'Izin';
        $keteranganClass = 'status-yellow';
    } elseif ($statusSaatIni === 'Sakit') {
        $keteranganWaktu = 'Sakit';
        $keteranganClass = 'status-yellow';
    } elseif ($statusSaatIni === 'Alpa') {
        $keteranganWaktu = 'Tidak Hadir';
        $keteranganClass = 'status-red';
    }

    $row['status_saat_ini'] = $statusSaatIni;
    $row['keterangan_waktu'] = $keteranganWaktu;
    $row['keterangan_class'] = $keteranganClass;

    $dataMonitoring[] = $row;
}
$stmt->close();

$totalData = count($dataMonitoring);

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
                <div class="table-header">
                    <div>
                        <div class="table-title">Monitoring Kehadiran</div>
                        <div style="color:#7f879c; margin-top:6px;">
                            Data Realtime:
                            <strong style="color:#5b4cf0;"><?= htmlspecialchars($labelTanggal); ?></strong>
                        </div>
                    </div>

                    <div class="table-tools">
                        <a href="<?= BASE_URL; ?>/guru/monitoring.php?tanggal=<?= urlencode($tanggalDipilih); ?>&status=<?= urlencode($statusFilter); ?>&q=<?= urlencode($q); ?>" class="btn-light-theme">
                            <i class="fa-solid fa-rotate-right"></i>
                        </a>
                    </div>
                </div>

                <form method="GET" class="filter-row monitoring-filter-row">
                    <div class="filter-left monitoring-filter-left">
                        <input type="date" name="tanggal" value="<?= htmlspecialchars($tanggalDipilih); ?>" class="input-theme">

                        <select name="status" class="select-theme">
                            <option value="">Semua Status</option>
                            <option value="Hadir" <?= $statusFilter === 'Hadir' ? 'selected' : ''; ?>>Hadir</option>
                            <option value="Terlambat" <?= $statusFilter === 'Terlambat' ? 'selected' : ''; ?>>Terlambat</option>
                            <option value="Izin" <?= $statusFilter === 'Izin' ? 'selected' : ''; ?>>Izin</option>
                            <option value="Sakit" <?= $statusFilter === 'Sakit' ? 'selected' : ''; ?>>Sakit</option>
                            <option value="Alpa" <?= $statusFilter === 'Alpa' ? 'selected' : ''; ?>>Alpa</option>
                            <option value="Belum Absen" <?= $statusFilter === 'Belum Absen' ? 'selected' : ''; ?>>Belum Absen</option>
                        </select>
                    </div>

                    <div class="filter-right monitoring-filter-right">
                        <input
                            type="text"
                            name="q"
                            value="<?= htmlspecialchars($q); ?>"
                            class="input-theme search-theme"
                            placeholder="Cari Nama / Kelas..."
                        >
                        <button type="submit" class="btn-light-theme">Terapkan</button>
                    </div>
                </form>

                <div class="table-responsive-theme">
                    <table class="theme-table monitoring-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th>Jam Datang</th>
                                <th>Jam Pulang</th>
                                <th>Keterangan Waktu</th>
                                <th>Status Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($totalData > 0): ?>
                                <?php foreach ($dataMonitoring as $index => $row): ?>
                                    <tr>
                                        <td><?= $index + 1; ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['nama']); ?></strong><br>
                                            <span style="color:#7f879c;"><?= htmlspecialchars($row['nisn']); ?></span>
                                        </td>
                                        <td>
                                            <span class="status-pill" style="background:#efedff; color:#5b4cf0;">
                                                <?= htmlspecialchars($row['nama_kelas']); ?>
                                            </span>
                                        </td>
                                        <td><?= !empty($row['jam_masuk']) ? substr($row['jam_masuk'], 0, 5) : '-'; ?></td>
                                        <td><?= !empty($row['jam_pulang']) ? substr($row['jam_pulang'], 0, 5) : '-'; ?></td>
                                        <td>
                                            <span class="status-pill <?= $row['keterangan_class']; ?>">
                                                <?= htmlspecialchars($row['keterangan_waktu']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="status-update-form">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="siswa_id" value="<?= (int) $row['siswa_id']; ?>">
                                                <input type="hidden" name="tanggal" value="<?= htmlspecialchars($tanggalDipilih); ?>">

                                                <select
                                                    name="status_baru"
                                                    class="status-select <?= strtolower(str_replace(' ', '-', $row['status_saat_ini'])); ?>"
                                                    onchange="this.form.submit()"
                                                >
                                                    <option value="Hadir" <?= $row['status_saat_ini'] === 'Hadir' ? 'selected' : ''; ?>>Hadir</option>
                                                    <option value="Terlambat" <?= $row['status_saat_ini'] === 'Terlambat' ? 'selected' : ''; ?>>Terlambat</option>
                                                    <option value="Izin" <?= $row['status_saat_ini'] === 'Izin' ? 'selected' : ''; ?>>Izin</option>
                                                    <option value="Sakit" <?= $row['status_saat_ini'] === 'Sakit' ? 'selected' : ''; ?>>Sakit</option>
                                                    <option value="Alpa" <?= $row['status_saat_ini'] === 'Alpa' ? 'selected' : ''; ?>>Alpa</option>
                                                    <?php if ($row['status_saat_ini'] === 'Belum Absen'): ?>
                                                        <option value="" selected disabled>Belum Absen</option>
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
                                            Tidak ada data monitoring untuk filter yang dipilih.
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="monitoring-footer-note">
                    Menampilkan <?= $totalData > 0 ? '1 - ' . $totalData : '0'; ?> dari <?= $totalData; ?> data
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>