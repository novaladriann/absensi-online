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

/* ---------- Parameter ---------- */
$kelasId = (int) ($_GET['kelas_id'] ?? 0);
if ($kelasId <= 0) {
    header('Location: ' . BASE_URL . '/guru/monitoring.php');
    exit;
}

$tanggalDipilih = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalDipilih)) {
    $tanggalDipilih = date('Y-m-d');
}

$statusFilter  = trim($_GET['status'] ?? '');
$q             = trim($_GET['q']      ?? '');
$allowedStatus = ['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa'];

/* ---------- Verifikasi: guru memang mengajar di kelas ini ---------- */
$stmtRole = $conn->prepare("
    SELECT role_guru_kelas
    FROM guru_kelas
    WHERE guru_id = ? AND kelas_id = ?
    LIMIT 1
");
$stmtRole->bind_param("ii", $guruId, $kelasId);
$stmtRole->execute();
$rowRole = $stmtRole->get_result()->fetch_assoc();
$stmtRole->close();

if (!$rowRole) {
    // Guru tidak punya akses ke kelas ini
    header('Location: ' . BASE_URL . '/guru/monitoring.php?tanggal=' . urlencode($tanggalDipilih));
    exit;
}

$roleGuru = $rowRole['role_guru_kelas']; // 'wali' | 'pengajar'
$isWali   = ($roleGuru === 'wali');

/* ---------- Info kelas ---------- */
$stmtKelas = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ? LIMIT 1");
$stmtKelas->bind_param("i", $kelasId);
$stmtKelas->execute();
$namaKelas = $stmtKelas->get_result()->fetch_assoc()['nama_kelas'] ?? '-';
$stmtKelas->close();

/* ---------- Jadwal hari ini ---------- */
$hariJadwal   = hariIndonesia($tanggalDipilih);
$labelTanggal = formatTanggalIndonesia($tanggalDipilih);
$jadwalAktif  = null;

$stmtJadwal = $conn->prepare("
    SELECT jam_masuk, jam_pulang, batas_terlambat
    FROM jadwal_absensi
    WHERE hari = ? AND status_aktif = 'aktif'
    LIMIT 1
");
$stmtJadwal->bind_param("s", $hariJadwal);
$stmtJadwal->execute();
$jadwalAktif = $stmtJadwal->get_result()->fetch_assoc();
$stmtJadwal->close();

$batasTerlambat  = $jadwalAktif['batas_terlambat'] ?? null;
$jamMasukJadwal  = $jadwalAktif ? substr($jadwalAktif['jam_masuk'],  0, 5) : null;
$jamPulangJadwal = $jadwalAktif ? substr($jadwalAktif['jam_pulang'], 0, 5) : null;

/* ---------- Apakah pengajar dalam window jam pelajaran? ---------- */
$isHariIni           = ($tanggalDipilih === date('Y-m-d'));
$pengajarDalamWindow = false;

if (!$isWali && $isHariIni && $jadwalAktif) {
    $now       = strtotime(date('H:i:s'));
    $jamMasuk  = strtotime($jadwalAktif['jam_masuk']);
    $jamPulang = strtotime($jadwalAktif['jam_pulang']);
    $pengajarDalamWindow = ($now >= $jamMasuk && $now <= $jamPulang);
}

/* ---------- POST: update status ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $siswaId     = (int)  ($_POST['siswa_id']   ?? 0);
    $statusBaru  = trim(  $_POST['status_baru'] ?? '');
    $tanggalPost = trim(  $_POST['tanggal']     ?? date('Y-m-d'));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPost)) {
        $tanggalPost = date('Y-m-d');
    }

    if ($siswaId > 0 && in_array($statusBaru, $allowedStatus, true)) {
        // Pastikan siswa memang di kelas ini
        $stmtCheck = $conn->prepare("
            SELECT id FROM siswa
            WHERE id = ? AND kelas_id = ? AND status_siswa = 'aktif'
            LIMIT 1
        ");
        $stmtCheck->bind_param("ii", $siswaId, $kelasId);
        $stmtCheck->execute();
        $isValid = $stmtCheck->get_result()->num_rows > 0;
        $stmtCheck->close();

        if ($isValid) {
            $keterangan = $isWali
                ? 'Diperbarui oleh wali kelas'
                : 'Diperbarui oleh pengajar (manual)';

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
                $stmtU = $conn->prepare("
                    UPDATE absensi
                    SET status_masuk       = ?,
                        metode_absen       = 'manual',
                        scanned_by_user_id = ?,
                        keterangan         = ?
                    WHERE id = ?
                ");
                $stmtU->bind_param("sisi", $statusBaru, $userId, $keterangan, $rowCek['id']);
                $stmtU->execute();
                $stmtU->close();
            } else {
                $stmtI = $conn->prepare("
                    INSERT INTO absensi
                        (siswa_id, tanggal, status_masuk, metode_absen, scanned_by_user_id, keterangan)
                    VALUES (?, ?, ?, 'manual', ?, ?)
                ");
                $stmtI->bind_param("issis", $siswaId, $tanggalPost, $statusBaru, $userId, $keterangan);
                $stmtI->execute();
                $stmtI->close();
            }
        }
    }

    $redirectUrl = BASE_URL . '/guru/monitoring_kelas.php'
                 . '?kelas_id=' . $kelasId
                 . '&tanggal='  . urlencode($tanggalPost);
    if ($statusFilter !== '') $redirectUrl .= '&status=' . urlencode($statusFilter);
    if ($q !== '')            $redirectUrl .= '&q='      . urlencode($q);

    header("Location: $redirectUrl");
    exit;
}

/* ---------- Query data siswa + absensi ---------- */
$sql = "
    SELECT
        s.id        AS siswa_id,
        u.nama,
        s.nisn,
        a.id        AS absensi_id,
        a.jam_masuk,
        a.jam_pulang,
        a.status_masuk,
        a.status_pulang,
        a.keterangan
    FROM siswa s
    INNER JOIN users u   ON u.id       = s.user_id
    LEFT  JOIN absensi a ON a.siswa_id = s.id
                        AND a.tanggal  = ?
    WHERE s.kelas_id    = ?
      AND s.status_siswa = 'aktif'
";

$params = [$tanggalDipilih, $kelasId];
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
    $sql .= " AND (u.nama LIKE ? OR s.nisn LIKE ?) ";
    $params[] = $like;
    $params[] = $like;
    $types   .= "ss";
}

$sql .= " ORDER BY u.nama ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

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
                $menit = max((int)floor((strtotime($row['jam_masuk']) - strtotime($batasTerlambat)) / 60), 0);
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

    /*
     * Wali  → canEdit (selalu)
     * Pengajar hari ini & dalam window → canEdit
     * Pengajar hari ini & luar window  → warnEdit
     * Pengajar bukan hari ini          → locked
     */
    if ($isWali) {
        $editMode = 'canEdit';
    } elseif ($isHariIni && $pengajarDalamWindow) {
        $editMode = 'canEdit';
    } elseif ($isHariIni) {
        $editMode = 'warnEdit';
    } else {
        $editMode = 'locked';
    }

    $row['status_saat_ini']  = $statusSaatIni;
    $row['keterangan_waktu'] = $keteranganWaktu;
    $row['keterangan_class'] = $keteranganClass;
    $row['edit_mode']        = $editMode;
    $dataMonitoring[]        = $row;
}
$stmt->close();

$totalData = count($dataMonitoring);

$pageTitle = 'Monitoring ' . $namaKelas;
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
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                            <a href="<?= BASE_URL; ?>/guru/monitoring.php?tanggal=<?= urlencode($tanggalDipilih); ?>"
                               class="mkelas-back-btn" title="Kembali ke daftar kelas">
                                <i class="fa-solid fa-arrow-left"></i>
                            </a>
                            <div class="table-title">
                                <?= htmlspecialchars($namaKelas); ?>
                                <?php if ($isWali): ?>
                                    <span class="mkelas-role mkelas-role-wali" style="font-size:12px;vertical-align:middle;margin-left:6px;">
                                        <i class="fa-solid fa-star"></i> Wali Kelas
                                    </span>
                                <?php else: ?>
                                    <span class="mkelas-role mkelas-role-pengajar" style="font-size:12px;vertical-align:middle;margin-left:6px;">
                                        <i class="fa-solid fa-chalkboard-user"></i> Pengajar
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="monitoring-sub-label">
                            Data Realtime: <strong><?= htmlspecialchars($labelTanggal); ?></strong>
                        </div>
                    </div>

                    <div class="table-tools" style="flex-wrap:wrap;gap:8px;">
                        <?php if (!$isWali && $jadwalAktif): ?>
                            <div class="monitoring-window-info">
                                <i class="fa-solid fa-clock"></i>
                                Edit: <strong><?= $jamMasukJadwal; ?> – <?= $jamPulangJadwal; ?></strong>
                                <?php if ($pengajarDalamWindow): ?>
                                    <span class="window-badge window-open">Aktif</span>
                                <?php else: ?>
                                    <span class="window-badge window-closed">Di Luar Jam</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <a href="<?= BASE_URL; ?>/guru/monitoring_kelas.php?kelas_id=<?= $kelasId; ?>&tanggal=<?= urlencode($tanggalDipilih); ?>&status=<?= urlencode($statusFilter); ?>&q=<?= urlencode($q); ?>"
                           class="btn-light-theme" title="Refresh">
                            <i class="fa-solid fa-rotate-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Filter -->
                <form method="GET" class="filter-row monitoring-filter-row">
                    <input type="hidden" name="kelas_id" value="<?= $kelasId; ?>">
                    <div class="filter-left monitoring-filter-left">
                        <input type="date" name="tanggal"
                               value="<?= htmlspecialchars($tanggalDipilih); ?>"
                               class="input-theme">
                        <select name="status" class="select-theme">
                            <option value="">Semua Status</option>
                            <?php foreach (['Hadir','Terlambat','Izin','Sakit','Alpa','Belum Absen'] as $opt): ?>
                                <option value="<?= $opt; ?>" <?= $statusFilter === $opt ? 'selected' : ''; ?>>
                                    <?= $opt; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-right monitoring-filter-right">
                        <input type="text" name="q"
                               value="<?= htmlspecialchars($q); ?>"
                               class="input-theme search-theme"
                               placeholder="Cari Nama / NISN...">
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

                                        <td><?= !empty($row['jam_masuk'])  ? substr($row['jam_masuk'],  0, 5) : '-'; ?></td>
                                        <td><?= !empty($row['jam_pulang']) ? substr($row['jam_pulang'], 0, 5) : '-'; ?></td>

                                        <td>
                                            <span class="status-pill <?= $row['keterangan_class']; ?>">
                                                <?= htmlspecialchars($row['keterangan_waktu']); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($row['edit_mode'] === 'locked'): ?>
                                                <div class="status-locked-wrap"
                                                     title="Hanya bisa diubah pada hari yang bersangkutan">
                                                    <span class="status-pill status-locked-pill status-muted">
                                                        <?= htmlspecialchars($row['status_saat_ini']); ?>
                                                    </span>
                                                    <i class="fa-solid fa-lock status-lock-icon"></i>
                                                </div>
                                            <?php else: ?>
                                                <form method="POST" class="status-update-form">
                                                    <input type="hidden" name="action"   value="update_status">
                                                    <input type="hidden" name="siswa_id" value="<?= (int)$row['siswa_id']; ?>">
                                                    <input type="hidden" name="tanggal"  value="<?= htmlspecialchars($tanggalDipilih); ?>">

                                                    <select
                                                        name="status_baru"
                                                        class="status-select <?= strtolower(str_replace(' ', '-', $row['status_saat_ini'])); ?>"
                                                        data-edit-mode="<?= $row['edit_mode']; ?>"
                                                        data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>"
                                                        data-jam-masuk="<?= $jamMasukJadwal ?? ''; ?>"
                                                        data-jam-pulang="<?= $jamPulangJadwal ?? ''; ?>"
                                                        data-original="<?= htmlspecialchars($row['status_saat_ini'], ENT_QUOTES); ?>"
                                                    >
                                                        <?php foreach ($allowedStatus as $opt): ?>
                                                            <option value="<?= $opt; ?>"
                                                                <?= $row['status_saat_ini'] === $opt ? 'selected' : ''; ?>>
                                                                <?= $opt; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                        <?php if ($row['status_saat_ini'] === 'Belum Absen'): ?>
                                                            <option value="" selected disabled>— Belum Absen —</option>
                                                        <?php endif; ?>
                                                    </select>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">
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

                <div class="monitoring-footer-note">
                    Menampilkan <strong><?= $totalData; ?></strong> siswa
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal konfirmasi peringatan -->
<div id="warnModal" class="warn-modal-overlay" style="display:none;">
    <div class="warn-modal-box">
        <div class="warn-modal-icon">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 class="warn-modal-title">Di Luar Jam Pelajaran</h3>
        <p class="warn-modal-body">
            Anda sedang mengubah status absensi <strong id="warnSiswaName"></strong>
            di luar jam pelajaran
            (<strong><?= $jamMasukJadwal ?? '-'; ?> &ndash; <?= $jamPulangJadwal ?? '-'; ?></strong>).
            <br><br>
            Perubahan ini tetap akan tersimpan dan dicatat sebagai <em>override manual</em>.
            Yakin ingin melanjutkan?
        </p>
        <div class="warn-modal-actions">
            <button class="warn-btn-cancel"  id="warnCancelBtn">Batal</button>
            <button class="warn-btn-confirm" id="warnConfirmBtn">Ya, Ubah Status</button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal      = document.getElementById('warnModal');
    const cancelBtn  = document.getElementById('warnCancelBtn');
    const confirmBtn = document.getElementById('warnConfirmBtn');
    const siswaName  = document.getElementById('warnSiswaName');

    let pendingSelect = null;
    let pendingValue  = null;

    document.querySelectorAll('.status-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            const editMode = this.dataset.editMode;
            const nama     = this.dataset.nama;
            const newVal   = this.value;

            if (editMode === 'warnEdit') {
                pendingSelect         = this;
                pendingValue          = newVal;
                siswaName.textContent = nama;
                this.value            = this.dataset.original; // kembalikan sementara
                modal.style.display   = 'flex';
            } else {
                this.form.submit();
            }
        });
    });

    cancelBtn.addEventListener('click', function () {
        modal.style.display = 'none';
        pendingSelect = null;
        pendingValue  = null;
    });

    confirmBtn.addEventListener('click', function () {
        modal.style.display = 'none';
        if (pendingSelect && pendingValue) {
            pendingSelect.value = pendingValue;
            pendingSelect.form.submit();
        }
        pendingSelect = null;
        pendingValue  = null;
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) cancelBtn.click();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') cancelBtn.click();
    });
})();
</script>

<?php include '../includes/footer.php'; ?>