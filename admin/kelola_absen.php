<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);

function tableExists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ((int)($row['total'] ?? 0)) > 0;
}

function normalizeTimeValue(string $value): string
{
    $value = trim($value);
    if ($value !== '' && preg_match('/^\d{2}:\d{2}$/', $value)) {
        return $value . ':00';
    }
    return $value;
}

$hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$hariDipilih = $_GET['hari'] ?? hariIndonesia(date('Y-m-d'));

if (!in_array($hariDipilih, $hariList, true)) {
    $hariDipilih = 'Senin';
}

$holidayTableReady = tableExists($conn, 'hari_libur');

/* ---------- POST: simpan jadwal ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_jadwal') {
    $hari = trim($_POST['hari'] ?? 'Senin');

    if (!in_array($hari, $hariList, true)) {
        $hari = 'Senin';
    }

    $jamMasuk         = normalizeTimeValue($_POST['jam_masuk'] ?? '');
    $batasTerlambat   = normalizeTimeValue($_POST['batas_terlambat'] ?? '');
    $jamPulang        = normalizeTimeValue($_POST['jam_pulang'] ?? '');
    $scanMasukMulai   = normalizeTimeValue($_POST['scan_masuk_mulai'] ?? '');
    $scanMasukSelesai = normalizeTimeValue($_POST['scan_masuk_selesai'] ?? '');
    $scanPulangMulai  = normalizeTimeValue($_POST['scan_pulang_mulai'] ?? '');
    $scanPulangSelesai= normalizeTimeValue($_POST['scan_pulang_selesai'] ?? '');
    $statusAktif      = ($_POST['status_aktif'] ?? 'aktif') === 'nonaktif' ? 'nonaktif' : 'aktif';

    $stmtCheck = $conn->prepare("SELECT id FROM jadwal_absensi WHERE hari = ? LIMIT 1");
    $stmtCheck->bind_param("s", $hari);
    $stmtCheck->execute();
    $jadwalId = $stmtCheck->get_result()->fetch_assoc()['id'] ?? null;
    $stmtCheck->close();

    if ($jadwalId) {
        $stmtSave = $conn->prepare("
            UPDATE jadwal_absensi
            SET jam_masuk = ?,
                batas_terlambat = ?,
                jam_pulang = ?,
                scan_masuk_mulai = ?,
                scan_masuk_selesai = ?,
                scan_pulang_mulai = ?,
                scan_pulang_selesai = ?,
                status_aktif = ?
            WHERE id = ?
        ");
        $stmtSave->bind_param(
            "ssssssssi",
            $jamMasuk,
            $batasTerlambat,
            $jamPulang,
            $scanMasukMulai,
            $scanMasukSelesai,
            $scanPulangMulai,
            $scanPulangSelesai,
            $statusAktif,
            $jadwalId
        );
    } else {
        $stmtSave = $conn->prepare("
            INSERT INTO jadwal_absensi
                (hari, jam_masuk, batas_terlambat, jam_pulang, scan_masuk_mulai, scan_masuk_selesai, scan_pulang_mulai, scan_pulang_selesai, status_aktif)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtSave->bind_param(
            "sssssssss",
            $hari,
            $jamMasuk,
            $batasTerlambat,
            $jamPulang,
            $scanMasukMulai,
            $scanMasukSelesai,
            $scanPulangMulai,
            $scanPulangSelesai,
            $statusAktif
        );
    }

    $stmtSave->execute();
    $stmtSave->close();

    header('Location: ' . BASE_URL . '/admin/kelola_absen.php?hari=' . urlencode($hari));
    exit;
}

/* ---------- POST: tambah hari libur ---------- */
if ($holidayTableReady && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_libur') {
    $tanggalLibur = trim($_POST['tanggal_libur'] ?? '');
    $keterangan   = trim($_POST['keterangan_libur'] ?? '');

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalLibur) && $keterangan !== '') {
        $stmt = $conn->prepare("
            INSERT INTO hari_libur (tanggal, keterangan)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE keterangan = VALUES(keterangan)
        ");
        $stmt->bind_param("ss", $tanggalLibur, $keterangan);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: ' . BASE_URL . '/admin/kelola_absen.php?hari=' . urlencode($hariDipilih));
    exit;
}

/* ---------- POST: hapus hari libur ---------- */
if ($holidayTableReady && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_libur') {
    $idLibur = (int)($_POST['id_libur'] ?? 0);

    if ($idLibur > 0) {
        $stmt = $conn->prepare("DELETE FROM hari_libur WHERE id = ?");
        $stmt->bind_param("i", $idLibur);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: ' . BASE_URL . '/admin/kelola_absen.php?hari=' . urlencode($hariDipilih));
    exit;
}

/* ---------- Ambil jadwal hari terpilih ---------- */
$stmtJadwal = $conn->prepare("
    SELECT *
    FROM jadwal_absensi
    WHERE hari = ?
    LIMIT 1
");
$stmtJadwal->bind_param("s", $hariDipilih);
$stmtJadwal->execute();
$jadwal = $stmtJadwal->get_result()->fetch_assoc();
$stmtJadwal->close();

/* ---------- Ambil hari libur ---------- */
$daftarLibur = [];
if ($holidayTableReady) {
    $resultLibur = $conn->query("
        SELECT id, tanggal, keterangan
        FROM hari_libur
        ORDER BY tanggal DESC
    ");

    while ($row = $resultLibur->fetch_assoc()) {
        $daftarLibur[] = $row;
    }
}

$pageTitle = 'Kelola Absen';
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
                    <h1>Kelola Absen</h1>
                    <p>Pengaturan jadwal absensi dan daftar hari libur.</p>
                </div>
            </div>

            <?php if (!$holidayTableReady): ?>
                <div class="alert-theme" style="margin-bottom:18px;">
                    Tabel <strong>hari_libur</strong> belum ada. Jalankan SQL di bawah jawaban ini dulu agar fitur hari libur aktif.
                </div>
            <?php endif; ?>

            <div class="admin-kelola-grid">
                <div class="panel-card">
                    <div class="admin-card-head">
                        <h3 style="margin:0;">Pengaturan Waktu</h3>
                        <p style="margin:6px 0 0; color:#7f879c;">Konfigurasi jam operasional absensi.</p>
                    </div>

                    <form method="POST" class="admin-jadwal-form">
                        <input type="hidden" name="action" value="save_jadwal">

                        <div class="admin-form-group">
                            <label class="form-label" style="margin-bottom:6px;">Hari</label>
                            <select name="hari" id="selectHariJadwal" class="select-theme">
                                <?php foreach ($hariList as $hari): ?>
                                    <option value="<?= $hari; ?>" <?= $hariDipilih === $hari ? 'selected' : ''; ?>>
                                        <?= $hari; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="admin-jadwal-section">
                            <h4>Absen Datang</h4>
                            <div class="admin-jadwal-grid">
                                <div>
                                    <label class="form-label">Mulai Scan</label>
                                    <input type="time" name="scan_masuk_mulai" class="input-theme"
                                           value="<?= !empty($jadwal['scan_masuk_mulai']) ? substr($jadwal['scan_masuk_mulai'], 0, 5) : ''; ?>">
                                </div>
                                <div>
                                    <label class="form-label">Tutup Scan</label>
                                    <input type="time" name="scan_masuk_selesai" class="input-theme"
                                           value="<?= !empty($jadwal['scan_masuk_selesai']) ? substr($jadwal['scan_masuk_selesai'], 0, 5) : ''; ?>">
                                </div>
                                <div>
                                    <label class="form-label">Jam Masuk</label>
                                    <input type="time" name="jam_masuk" class="input-theme"
                                           value="<?= !empty($jadwal['jam_masuk']) ? substr($jadwal['jam_masuk'], 0, 5) : ''; ?>">
                                </div>
                                <div>
                                    <label class="form-label">Batas Terlambat</label>
                                    <input type="time" name="batas_terlambat" class="input-theme"
                                           value="<?= !empty($jadwal['batas_terlambat']) ? substr($jadwal['batas_terlambat'], 0, 5) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <div class="admin-jadwal-section">
                            <h4>Absen Pulang</h4>
                            <div class="admin-jadwal-grid">
                                <div>
                                    <label class="form-label">Mulai Scan</label>
                                    <input type="time" name="scan_pulang_mulai" class="input-theme"
                                           value="<?= !empty($jadwal['scan_pulang_mulai']) ? substr($jadwal['scan_pulang_mulai'], 0, 5) : ''; ?>">
                                </div>
                                <div>
                                    <label class="form-label">Tutup Scan</label>
                                    <input type="time" name="scan_pulang_selesai" class="input-theme"
                                           value="<?= !empty($jadwal['scan_pulang_selesai']) ? substr($jadwal['scan_pulang_selesai'], 0, 5) : ''; ?>">
                                </div>
                                <div>
                                    <label class="form-label">Jam Pulang</label>
                                    <input type="time" name="jam_pulang" class="input-theme"
                                           value="<?= !empty($jadwal['jam_pulang']) ? substr($jadwal['jam_pulang'], 0, 5) : ''; ?>">
                                </div>
                                <div>
                                    <label class="form-label">Status Jadwal</label>
                                    <select name="status_aktif" class="select-theme">
                                        <option value="aktif" <?= (($jadwal['status_aktif'] ?? 'aktif') === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="nonaktif" <?= (($jadwal['status_aktif'] ?? '') === 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn-primary-theme btn-full">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan
                        </button>
                    </form>
                </div>

                <div class="table-card">
                    <div class="admin-card-head">
                        <h3 style="margin:0;">Daftar Hari Libur</h3>
                        <p style="margin:6px 0 0; color:#7f879c;">Siswa tidak bisa absen pada tanggal ini.</p>
                    </div>

                    <?php if ($holidayTableReady): ?>
                        <form method="POST" class="admin-libur-form">
                            <input type="hidden" name="action" value="add_libur">

                            <input type="date" name="tanggal_libur" class="input-theme" required>
                            <input type="text" name="keterangan_libur" class="input-theme" placeholder="Contoh: Maulid Nabi / Cuti Bersama" required>
                            <button type="submit" class="btn-primary-theme admin-libur-btn">
                                <i class="fa-solid fa-plus"></i> Tambah
                            </button>
                        </form>

                        <div class="table-responsive-theme">
                            <table class="theme-table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Keterangan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($daftarLibur)): ?>
                                        <?php foreach ($daftarLibur as $i => $libur): ?>
                                            <tr>
                                                <td><?= $i + 1; ?></td>
                                                <td><?= htmlspecialchars(formatTanggalIndonesia($libur['tanggal'])); ?></td>
                                                <td><?= htmlspecialchars($libur['keterangan']); ?></td>
                                                <td>
                                                    <form method="POST" onsubmit="return confirm('Hapus hari libur ini?');" style="margin:0;">
                                                        <input type="hidden" name="action" value="delete_libur">
                                                        <input type="hidden" name="id_libur" value="<?= (int)$libur['id']; ?>">
                                                        <button type="submit" class="btn-light-theme" style="padding:8px 12px;">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4">
                                                <div class="empty-state-theme">
                                                    Tidak ada jadwal libur.
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-theme" style="padding:24px 12px;">
                            Tabel <strong>hari_libur</strong> belum tersedia.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<script>
document.getElementById('selectHariJadwal').addEventListener('change', function () {
    const hari = this.value;

    const url = new URL(window.location.href);
    url.searchParams.set('hari', hari);

    window.location.href = url.toString();
});
</script>
<?php include '../includes/footer.php'; ?>