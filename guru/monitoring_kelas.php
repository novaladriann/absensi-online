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

/* ---------- Helper: cek kolom tabel ---------- */
function hasTableColumn(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $sql = "
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cache[$key] = ((int) ($row['total'] ?? 0)) > 0;
    return $cache[$key];
}

function redirectMonitoringKelas(string $tanggal, int $kelasId, string $statusFilter = '', string $q = ''): void
{
    $url = BASE_URL . '/guru/monitoring_kelas.php'
        . '?kelas_id=' . $kelasId
        . '&tanggal=' . urlencode($tanggal);

    if ($statusFilter !== '') {
        $url .= '&status=' . urlencode($statusFilter);
    }
    if ($q !== '') {
        $url .= '&q=' . urlencode($q);
    }

    header('Location: ' . $url);
    exit;
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

$statusFilter = trim($_GET['status'] ?? '');
$q = trim($_GET['q'] ?? '');

/* ---------- Capability schema ---------- */
$hasMetodeMasuk      = hasTableColumn($conn, 'absensi', 'metode_masuk');
$hasMetodePulang     = hasTableColumn($conn, 'absensi', 'metode_pulang');
$hasCorrectedBy      = hasTableColumn($conn, 'absensi', 'corrected_by_user_id');
$hasCorrectedAt      = hasTableColumn($conn, 'absensi', 'corrected_at');
$hasAlasanKoreksi    = hasTableColumn($conn, 'absensi', 'alasan_koreksi');
$hasMetodeAbsen      = hasTableColumn($conn, 'absensi', 'metode_absen');
$hasScannedBy        = hasTableColumn($conn, 'absensi', 'scanned_by_user_id');

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
    header('Location: ' . BASE_URL . '/guru/monitoring.php?tanggal=' . urlencode($tanggalDipilih));
    exit;
}

$roleGuru = $rowRole['role_guru_kelas']; // wali | pengajar
$isWali   = ($roleGuru === 'wali');

/* ---------- Info kelas ---------- */
$stmtKelas = $conn->prepare("SELECT nama_kelas FROM kelas WHERE id = ? LIMIT 1");
$stmtKelas->bind_param("i", $kelasId);
$stmtKelas->execute();
$namaKelas = $stmtKelas->get_result()->fetch_assoc()['nama_kelas'] ?? '-';
$stmtKelas->close();

/* ---------- Jadwal tanggal yang dipilih ---------- */
$hariJadwal   = hariIndonesia($tanggalDipilih);
$labelTanggal = formatTanggalIndonesia($tanggalDipilih);

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
$jamMasukJadwal  = $jadwalAktif ? substr($jadwalAktif['jam_masuk'], 0, 5) : null;
$jamPulangJadwal = $jadwalAktif ? substr($jadwalAktif['jam_pulang'], 0, 5) : null;

/* ---------- Apakah pengajar dalam window? ---------- */
$isHariIni = ($tanggalDipilih === date('Y-m-d'));
$pengajarDalamWindow = false;

if (!$isWali && $isHariIni && $jadwalAktif) {
    $now       = strtotime(date('H:i:s'));
    $jamMasuk  = strtotime($jadwalAktif['jam_masuk']);
    $jamPulang = strtotime($jadwalAktif['jam_pulang']);
    $pengajarDalamWindow = ($now >= $jamMasuk && $now <= $jamPulang);
}

/* ---------- POST: simpan koreksi ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'simpan_koreksi') {
    $siswaId         = (int) ($_POST['siswa_id'] ?? 0);
    $tanggalPost     = trim($_POST['tanggal'] ?? date('Y-m-d'));
    $modeKoreksi     = trim($_POST['mode_koreksi'] ?? '');
    $jamMasukManual  = trim($_POST['jam_masuk_manual'] ?? '');
    $jamPulangManual = trim($_POST['jam_pulang_manual'] ?? '');
    $statusNonHadir  = trim($_POST['status_nonhadir'] ?? '');
    $alasanKoreksi   = trim($_POST['alasan_koreksi'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalPost)) {
        $tanggalPost = date('Y-m-d');
    }

    if ($siswaId <= 0 || $alasanKoreksi === '') {
        redirectMonitoringKelas($tanggalPost, $kelasId, $statusFilter, $q);
    }

    // pastikan siswa memang milik kelas ini
    $stmtCheck = $conn->prepare("
        SELECT id
        FROM siswa
        WHERE id = ? AND kelas_id = ? AND status_siswa = 'aktif'
        LIMIT 1
    ");
    $stmtCheck->bind_param("ii", $siswaId, $kelasId);
    $stmtCheck->execute();
    $isValid = $stmtCheck->get_result()->num_rows > 0;
    $stmtCheck->close();

    if (!$isValid) {
        redirectMonitoringKelas($tanggalPost, $kelasId, $statusFilter, $q);
    }

    // ambil record absensi
    $stmtCek = $conn->prepare("
        SELECT id, jam_masuk, jam_pulang
        FROM absensi
        WHERE siswa_id = ? AND tanggal = ?
        LIMIT 1
    ");
    $stmtCek->bind_param("is", $siswaId, $tanggalPost);
    $stmtCek->execute();
    $recAbsensi = $stmtCek->get_result()->fetch_assoc();
    $stmtCek->close();

    // batas terlambat untuk tanggal koreksi
    $hariManual = hariIndonesia($tanggalPost);
    $stmtJ2 = $conn->prepare("
        SELECT batas_terlambat
        FROM jadwal_absensi
        WHERE hari = ? AND status_aktif = 'aktif'
        LIMIT 1
    ");
    $stmtJ2->bind_param("s", $hariManual);
    $stmtJ2->execute();
    $jadwal2 = $stmtJ2->get_result()->fetch_assoc();
    $stmtJ2->close();

    $batasTerlambat2 = $jadwal2['batas_terlambat'] ?? '07:15:00';

    if ($jamMasukManual !== '' && strlen($jamMasukManual) === 5) {
        $jamMasukManual .= ':00';
    }
    if ($jamPulangManual !== '' && strlen($jamPulangManual) === 5) {
        $jamPulangManual .= ':00';
    }

    /* ----- Mode 1: koreksi masuk ----- */
    if ($modeKoreksi === 'masuk') {
        if ($jamMasukManual === '') {
            redirectMonitoringKelas($tanggalPost, $kelasId, $statusFilter, $q);
        }

        $statusMasuk = (strtotime($jamMasukManual) <= strtotime($batasTerlambat2)) ? 'Hadir' : 'Terlambat';

        if ($recAbsensi) {
            $sets = [
                "jam_masuk = ?",
                "status_masuk = ?",
                "keterangan = 'Koreksi manual guru'"
            ];
            $params = [$jamMasukManual, $statusMasuk];
            $types  = "ss";

            if ($hasMetodeMasuk) {
                $sets[] = "metode_masuk = 'manual'";
            } elseif ($hasMetodeAbsen) {
                $sets[] = "metode_absen = 'manual'";
            }

            if ($hasCorrectedBy) {
                $sets[] = "corrected_by_user_id = ?";
                $params[] = $userId;
                $types .= "i";
            } elseif ($hasScannedBy) {
                $sets[] = "scanned_by_user_id = ?";
                $params[] = $userId;
                $types .= "i";
            }

            if ($hasCorrectedAt) {
                $sets[] = "corrected_at = NOW()";
            }
            if ($hasAlasanKoreksi) {
                $sets[] = "alasan_koreksi = ?";
                $params[] = $alasanKoreksi;
                $types .= "s";
            }

            $sqlUpdate = "UPDATE absensi SET " . implode(", ", $sets) . " WHERE id = ?";
            $params[] = $recAbsensi['id'];
            $types .= "i";

            $stmtSave = $conn->prepare($sqlUpdate);
            $stmtSave->bind_param($types, ...$params);
            $stmtSave->execute();
            $stmtSave->close();
        } else {
            $columns = ["siswa_id", "tanggal", "jam_masuk", "status_masuk", "keterangan"];
            $values  = ["?", "?", "?", "?", "'Koreksi manual guru'"];
            $params  = [$siswaId, $tanggalPost, $jamMasukManual, $statusMasuk];
            $types   = "isss";

            if ($hasMetodeMasuk) {
                $columns[] = "metode_masuk";
                $values[]  = "'manual'";
            } elseif ($hasMetodeAbsen) {
                $columns[] = "metode_absen";
                $values[]  = "'manual'";
            }

            if ($hasCorrectedBy) {
                $columns[] = "corrected_by_user_id";
                $values[]  = "?";
                $params[]  = $userId;
                $types    .= "i";
            } elseif ($hasScannedBy) {
                $columns[] = "scanned_by_user_id";
                $values[]  = "?";
                $params[]  = $userId;
                $types    .= "i";
            }

            if ($hasCorrectedAt) {
                $columns[] = "corrected_at";
                $values[]  = "NOW()";
            }
            if ($hasAlasanKoreksi) {
                $columns[] = "alasan_koreksi";
                $values[]  = "?";
                $params[]  = $alasanKoreksi;
                $types    .= "s";
            }

            $sqlInsert = "INSERT INTO absensi (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";
            $stmtSave = $conn->prepare($sqlInsert);
            $stmtSave->bind_param($types, ...$params);
            $stmtSave->execute();
            $stmtSave->close();
        }
    }

    /* ----- Mode 2: koreksi non-hadir ----- */ elseif ($modeKoreksi === 'nonhadir') {
        if (!in_array($statusNonHadir, ['Izin', 'Sakit', 'Alpa'], true)) {
            redirectMonitoringKelas($tanggalPost, $kelasId, $statusFilter, $q);
        }

        if ($recAbsensi) {
            $sets = [
                "jam_masuk = NULL",
                "jam_pulang = NULL",
                "status_masuk = ?",
                "status_pulang = NULL",
                "keterangan = 'Koreksi manual guru'"
            ];
            $params = [$statusNonHadir];
            $types  = "s";

            if ($hasMetodeMasuk) {
                $sets[] = "metode_masuk = 'manual'";
            } elseif ($hasMetodeAbsen) {
                $sets[] = "metode_absen = 'manual'";
            }

            if ($hasMetodePulang) {
                $sets[] = "metode_pulang = NULL";
            }

            if ($hasCorrectedBy) {
                $sets[] = "corrected_by_user_id = ?";
                $params[] = $userId;
                $types .= "i";
            } elseif ($hasScannedBy) {
                $sets[] = "scanned_by_user_id = ?";
                $params[] = $userId;
                $types .= "i";
            }

            if ($hasCorrectedAt) {
                $sets[] = "corrected_at = NOW()";
            }
            if ($hasAlasanKoreksi) {
                $sets[] = "alasan_koreksi = ?";
                $params[] = $alasanKoreksi;
                $types .= "s";
            }

            $sqlUpdate = "UPDATE absensi SET " . implode(", ", $sets) . " WHERE id = ?";
            $params[] = $recAbsensi['id'];
            $types .= "i";

            $stmtSave = $conn->prepare($sqlUpdate);
            $stmtSave->bind_param($types, ...$params);
            $stmtSave->execute();
            $stmtSave->close();
        } else {
            $columns = ["siswa_id", "tanggal", "status_masuk", "keterangan"];
            $values  = ["?", "?", "?", "'Koreksi manual guru'"];
            $params  = [$siswaId, $tanggalPost, $statusNonHadir];
            $types   = "iss";

            if ($hasMetodeMasuk) {
                $columns[] = "metode_masuk";
                $values[]  = "'manual'";
            } elseif ($hasMetodeAbsen) {
                $columns[] = "metode_absen";
                $values[]  = "'manual'";
            }

            if ($hasCorrectedBy) {
                $columns[] = "corrected_by_user_id";
                $values[]  = "?";
                $params[]  = $userId;
                $types    .= "i";
            } elseif ($hasScannedBy) {
                $columns[] = "scanned_by_user_id";
                $values[]  = "?";
                $params[]  = $userId;
                $types    .= "i";
            }

            if ($hasCorrectedAt) {
                $columns[] = "corrected_at";
                $values[]  = "NOW()";
            }
            if ($hasAlasanKoreksi) {
                $columns[] = "alasan_koreksi";
                $values[]  = "?";
                $params[]  = $alasanKoreksi;
                $types    .= "s";
            }

            $sqlInsert = "INSERT INTO absensi (" . implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ")";
            $stmtSave = $conn->prepare($sqlInsert);
            $stmtSave->bind_param($types, ...$params);
            $stmtSave->execute();
            $stmtSave->close();
        }
    }

    /* ----- Mode 3: koreksi pulang ----- */ elseif ($modeKoreksi === 'pulang') {
        if (!$recAbsensi || empty($recAbsensi['jam_masuk']) || $jamPulangManual === '') {
            redirectMonitoringKelas($tanggalPost, $kelasId, $statusFilter, $q);
        }

        if (strtotime($jamPulangManual) < strtotime($recAbsensi['jam_masuk'])) {
            redirectMonitoringKelas($tanggalPost, $kelasId, $statusFilter, $q);
        }

        $sets = [
            "jam_pulang = ?",
            "status_pulang = 'Pulang'",
            "keterangan = 'Koreksi manual guru'"
        ];
        $params = [$jamPulangManual];
        $types  = "s";

        if ($hasMetodePulang) {
            $sets[] = "metode_pulang = 'manual'";
        } elseif ($hasMetodeAbsen) {
            $sets[] = "metode_absen = 'manual'";
        }

        if ($hasCorrectedBy) {
            $sets[] = "corrected_by_user_id = ?";
            $params[] = $userId;
            $types .= "i";
        } elseif ($hasScannedBy) {
            $sets[] = "scanned_by_user_id = ?";
            $params[] = $userId;
            $types .= "i";
        }

        if ($hasCorrectedAt) {
            $sets[] = "corrected_at = NOW()";
        }
        if ($hasAlasanKoreksi) {
            $sets[] = "alasan_koreksi = ?";
            $params[] = $alasanKoreksi;
            $types .= "s";
        }

        $sqlUpdate = "UPDATE absensi SET " . implode(", ", $sets) . " WHERE id = ?";
        $params[] = $recAbsensi['id'];
        $types .= "i";

        $stmtSave = $conn->prepare($sqlUpdate);
        $stmtSave->bind_param($types, ...$params);
        $stmtSave->execute();
        $stmtSave->close();
    }

    redirectMonitoringKelas($tanggalPost, $kelasId, $statusFilter, $q);
}

/* ---------- Query data siswa + absensi ---------- */
$selectAudit = [];
$joinAudit = [];

if ($hasMetodeMasuk) {
    $selectAudit[] = "a.metode_masuk";
} else {
    $selectAudit[] = "NULL AS metode_masuk";
}

if ($hasMetodePulang) {
    $selectAudit[] = "a.metode_pulang";
} else {
    $selectAudit[] = "NULL AS metode_pulang";
}

if ($hasAlasanKoreksi) {
    $selectAudit[] = "a.alasan_koreksi";
} else {
    $selectAudit[] = "NULL AS alasan_koreksi";
}

if ($hasCorrectedAt) {
    $selectAudit[] = "a.corrected_at";
} else {
    $selectAudit[] = "NULL AS corrected_at";
}

if ($hasCorrectedBy) {
    $selectAudit[] = "uc.nama AS corrected_by_name";
    $joinAudit[] = "LEFT JOIN users uc ON uc.id = a.corrected_by_user_id";
} else {
    $selectAudit[] = "NULL AS corrected_by_name";
}

if (hasTableColumn($conn, 'absensi', 'scanned_masuk_by_user_id')) {
    $selectAudit[] = "um.nama AS scanned_masuk_by_name";
    $joinAudit[] = "LEFT JOIN users um ON um.id = a.scanned_masuk_by_user_id";
} else {
    $selectAudit[] = "NULL AS scanned_masuk_by_name";
}

if (hasTableColumn($conn, 'absensi', 'scanned_pulang_by_user_id')) {
    $selectAudit[] = "up.nama AS scanned_pulang_by_name";
    $joinAudit[] = "LEFT JOIN users up ON up.id = a.scanned_pulang_by_user_id";
} else {
    $selectAudit[] = "NULL AS scanned_pulang_by_name";
}

$sql = "
    SELECT
        s.id AS siswa_id,
        u.nama,
        s.nisn,
        a.id AS absensi_id,
        a.jam_masuk,
        a.jam_pulang,
        a.status_masuk,
        a.status_pulang,
        a.keterangan,
        " . implode(",\n        ", $selectAudit) . "
    FROM siswa s
    INNER JOIN users u ON u.id = s.user_id
    LEFT JOIN absensi a ON a.siswa_id = s.id
                       AND a.tanggal = ?
    " . implode("\n    ", $joinAudit) . "
    WHERE s.kelas_id = ?
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
        $types .= "s";
    }
}

if ($q !== '') {
    $like = '%' . $q . '%';
    $sql .= " AND (u.nama LIKE ? OR s.nisn LIKE ?) ";
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
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
                $menit = max((int) floor((strtotime($row['jam_masuk']) - strtotime($batasTerlambat)) / 60), 0);
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

                <form method="GET" class="filter-row monitoring-filter-row">
                    <input type="hidden" name="kelas_id" value="<?= $kelasId; ?>">
                    <div class="filter-left monitoring-filter-left">
                        <input type="date" name="tanggal"
                            value="<?= htmlspecialchars($tanggalDipilih); ?>"
                            class="input-theme">
                        <select name="status" class="select-theme">
                            <option value="">Semua Status</option>
                            <?php foreach (['Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', 'Belum Absen'] as $opt): ?>
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

                <div class="table-responsive-theme">
                    <table class="theme-table monitoring-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Siswa</th>
                                <th>Masuk</th>
                                <th>Pulang</th>
                                <th>Status</th>
                                <th>Aksi</th>
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

                                        <td><?= !empty($row['jam_masuk']) ? substr($row['jam_masuk'], 0, 5) : '-'; ?></td>
                                        <td><?= !empty($row['jam_pulang']) ? substr($row['jam_pulang'], 0, 5) : '-'; ?></td>

                                        <td>
                                            <span class="status-pill <?= $row['keterangan_class']; ?>">
                                                <?= htmlspecialchars($row['keterangan_waktu']); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?php if ($row['edit_mode'] === 'locked'): ?>
                                                <div class="status-locked-wrap" title="Hanya bisa diubah pada hari yang bersangkutan">
                                                    <span class="status-pill status-locked-pill status-muted">Terkunci</span>
                                                    <i class="fa-solid fa-lock status-lock-icon"></i>
                                                </div>
                                            <?php else: ?>
                                                <div class="aksi-btn-group">
                                                    <button
                                                        type="button"
                                                        class="btn-light-theme btn-koreksi-modal"
                                                        data-siswa-id="<?= (int) $row['siswa_id']; ?>"
                                                        data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>"
                                                        data-tanggal="<?= htmlspecialchars($tanggalDipilih, ENT_QUOTES); ?>"
                                                        data-edit-mode="<?= htmlspecialchars($row['edit_mode'], ENT_QUOTES); ?>"
                                                        data-jam-masuk="<?= !empty($row['jam_masuk']) ? substr($row['jam_masuk'], 0, 5) : ''; ?>"
                                                        data-jam-pulang="<?= !empty($row['jam_pulang']) ? substr($row['jam_pulang'], 0, 5) : ''; ?>"
                                                        onclick="openKoreksiModal(this)">
                                                        <i class="fa-solid fa-pen-to-square"></i> Koreksi
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="btn-light-theme btn-riwayat-modal"
                                                        data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>"
                                                        data-masuk="<?= !empty($row['jam_masuk']) ? substr($row['jam_masuk'], 0, 5) : '-'; ?>"
                                                        data-pulang="<?= !empty($row['jam_pulang']) ? substr($row['jam_pulang'], 0, 5) : '-'; ?>"
                                                        data-status="<?= htmlspecialchars($row['status_saat_ini'], ENT_QUOTES); ?>"
                                                        data-metode-masuk="<?= htmlspecialchars($row['metode_masuk'] ?? '-', ENT_QUOTES); ?>"
                                                        data-metode-pulang="<?= htmlspecialchars($row['metode_pulang'] ?? '-', ENT_QUOTES); ?>"
                                                        data-scan-masuk="<?= htmlspecialchars($row['scanned_masuk_by_name'] ?? '-', ENT_QUOTES); ?>"
                                                        data-scan-pulang="<?= htmlspecialchars($row['scanned_pulang_by_name'] ?? '-', ENT_QUOTES); ?>"
                                                        data-corrected-by="<?= htmlspecialchars($row['corrected_by_name'] ?? '-', ENT_QUOTES); ?>"
                                                        data-corrected-at="<?= !empty($row['corrected_at']) ? date('d/m/Y H:i', strtotime($row['corrected_at'])) : '-'; ?>"
                                                        data-alasan="<?= htmlspecialchars($row['alasan_koreksi'] ?? '-', ENT_QUOTES); ?>"
                                                        onclick="openRiwayatModal(this)">
                                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                                    </button>
                                                </div>
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

<div id="koreksiModal" class="warn-modal-overlay" style="display:none;">
    <div class="warn-modal-box koreksi-modal-box">
        <div class="warn-modal-icon">
            <i class="fa-solid fa-pen-to-square"></i>
        </div>

        <h3 class="warn-modal-title">Koreksi Absensi</h3>
        <p class="warn-modal-body">
            Siswa: <strong id="kmNamaSiswa">-</strong><br>
            Tanggal: <strong><?= htmlspecialchars($labelTanggal); ?></strong>
        </p>

        <div id="kmWarningBox" class="alert-theme" style="display:none; margin-top:0; margin-bottom:14px;">
            Anda sedang melakukan koreksi di luar jam pelajaran. Perubahan ini akan dicatat sebagai override manual.
        </div>

        <form method="POST" id="koreksiModalForm" class="koreksi-form-inner">
            <input type="hidden" name="action" value="simpan_koreksi">
            <input type="hidden" name="siswa_id" id="kmSiswaId">
            <input type="hidden" name="tanggal" id="kmTanggal">

            <select name="mode_koreksi" id="kmModeKoreksi" class="select-theme" onchange="handleModalModeKoreksi()">
                <option value="">Pilih Jenis Koreksi</option>
                <option value="masuk">Koreksi Masuk</option>
                <option value="nonhadir">Izin / Sakit / Alpa</option>
                <option value="pulang">Koreksi Pulang</option>
            </select>

            <div id="kmGroupMasuk" style="display:none;">
                <input type="time" name="jam_masuk_manual" id="kmJamMasuk" class="input-theme">
            </div>

            <div id="kmGroupNonHadir" style="display:none;">
                <select name="status_nonhadir" id="kmStatusNonHadir" class="select-theme">
                    <option value="">Pilih Status</option>
                    <option value="Izin">Izin</option>
                    <option value="Sakit">Sakit</option>
                    <option value="Alpa">Alpa</option>
                </select>
            </div>

            <div id="kmGroupPulang" style="display:none;">
                <input type="time" name="jam_pulang_manual" id="kmJamPulang" class="input-theme">
            </div>

            <textarea
                name="alasan_koreksi"
                id="kmAlasan"
                class="input-theme koreksi-textarea"
                placeholder="Alasan koreksi..."
                required></textarea>

            <div class="warn-modal-actions">
                <button type="button" class="warn-btn-cancel" onclick="closeKoreksiModal()">Batal</button>
                <button type="submit" class="warn-btn-confirm">Simpan Koreksi</button>
            </div>
        </form>
    </div>
</div>

<div id="riwayatModal" class="warn-modal-overlay" style="display:none;">
    <div class="warn-modal-box koreksi-modal-box">
        <div class="warn-modal-icon">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>

        <h3 class="warn-modal-title">Riwayat Absensi</h3>
        <p class="warn-modal-body">
            Siswa: <strong id="rmNamaSiswa">-</strong>
        </p>

        <div class="riwayat-detail-grid">
            <div class="riwayat-item">
                <span>Jam Masuk</span>
                <strong id="rmMasuk">-</strong>
            </div>
            <div class="riwayat-item">
                <span>Jam Pulang</span>
                <strong id="rmPulang">-</strong>
            </div>
            <div class="riwayat-item">
                <span>Status</span>
                <strong id="rmStatus">-</strong>
            </div>
            <div class="riwayat-item">
                <span>Metode Masuk</span>
                <strong id="rmMetodeMasuk">-</strong>
            </div>
            <div class="riwayat-item">
                <span>Metode Pulang</span>
                <strong id="rmMetodePulang">-</strong>
            </div>
            <div class="riwayat-item">
                <span>Scan Masuk Oleh</span>
                <strong id="rmScanMasuk">-</strong>
            </div>
            <div class="riwayat-item">
                <span>Scan Pulang Oleh</span>
                <strong id="rmScanPulang">-</strong>
            </div>
            <div class="riwayat-item">
                <span>Dikoreksi Oleh</span>
                <strong id="rmCorrectedBy">-</strong>
            </div>
            <div class="riwayat-item riwayat-item-full">
                <span>Waktu Koreksi</span>
                <strong id="rmCorrectedAt">-</strong>
            </div>
            <div class="riwayat-item riwayat-item-full">
                <span>Alasan Koreksi</span>
                <strong id="rmAlasan">-</strong>
            </div>
        </div>

        <div class="warn-modal-actions">
            <button type="button" class="warn-btn-cancel" onclick="closeRiwayatModal()">Tutup</button>
        </div>
    </div>
</div>

<script>
    const koreksiModal = document.getElementById('koreksiModal');
    const kmNamaSiswa = document.getElementById('kmNamaSiswa');
    const kmSiswaId = document.getElementById('kmSiswaId');
    const kmTanggal = document.getElementById('kmTanggal');
    const kmModeKoreksi = document.getElementById('kmModeKoreksi');
    const kmJamMasuk = document.getElementById('kmJamMasuk');
    const kmJamPulang = document.getElementById('kmJamPulang');
    const kmStatusNonHadir = document.getElementById('kmStatusNonHadir');
    const kmAlasan = document.getElementById('kmAlasan');
    const kmWarningBox = document.getElementById('kmWarningBox');

    const kmGroupMasuk = document.getElementById('kmGroupMasuk');
    const kmGroupNonHadir = document.getElementById('kmGroupNonHadir');
    const kmGroupPulang = document.getElementById('kmGroupPulang');

    window.openKoreksiModal = function(btn) {
        kmNamaSiswa.textContent = btn.dataset.nama || '-';
        kmSiswaId.value = btn.dataset.siswaId || '';
        kmTanggal.value = btn.dataset.tanggal || '';

        kmModeKoreksi.value = '';
        kmJamMasuk.value = btn.dataset.jamMasuk || '';
        kmJamPulang.value = btn.dataset.jamPulang || '';
        kmStatusNonHadir.value = '';
        kmAlasan.value = '';

        if ((btn.dataset.editMode || '') === 'warnEdit') {
            kmWarningBox.style.display = 'block';
        } else {
            kmWarningBox.style.display = 'none';
        }

        handleModalModeKoreksi();
        koreksiModal.style.display = 'flex';
    };

    window.closeKoreksiModal = function() {
        koreksiModal.style.display = 'none';
    };

    window.handleModalModeKoreksi = function() {
        kmGroupMasuk.style.display = 'none';
        kmGroupNonHadir.style.display = 'none';
        kmGroupPulang.style.display = 'none';

        if (kmModeKoreksi.value === 'masuk') {
            kmGroupMasuk.style.display = 'block';
        } else if (kmModeKoreksi.value === 'nonhadir') {
            kmGroupNonHadir.style.display = 'block';
        } else if (kmModeKoreksi.value === 'pulang') {
            kmGroupPulang.style.display = 'block';
        }
    };

    koreksiModal.addEventListener('click', function(e) {
        if (e.target === koreksiModal) {
            closeKoreksiModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && koreksiModal.style.display === 'flex') {
            closeKoreksiModal();
        }
    });
</script>

<script>
const riwayatModal = document.getElementById('riwayatModal');
const rmNamaSiswa = document.getElementById('rmNamaSiswa');
const rmMasuk = document.getElementById('rmMasuk');
const rmPulang = document.getElementById('rmPulang');
const rmStatus = document.getElementById('rmStatus');
const rmMetodeMasuk = document.getElementById('rmMetodeMasuk');
const rmMetodePulang = document.getElementById('rmMetodePulang');
const rmScanMasuk = document.getElementById('rmScanMasuk');
const rmScanPulang = document.getElementById('rmScanPulang');
const rmCorrectedBy = document.getElementById('rmCorrectedBy');
const rmCorrectedAt = document.getElementById('rmCorrectedAt');
const rmAlasan = document.getElementById('rmAlasan');

window.openRiwayatModal = function(btn) {
    rmNamaSiswa.textContent = btn.dataset.nama || '-';
    rmMasuk.textContent = btn.dataset.masuk || '-';
    rmPulang.textContent = btn.dataset.pulang || '-';
    rmStatus.textContent = btn.dataset.status || '-';
    rmMetodeMasuk.textContent = btn.dataset.metodeMasuk || '-';
    rmMetodePulang.textContent = btn.dataset.metodePulang || '-';
    rmScanMasuk.textContent = btn.dataset.scanMasuk || '-';
    rmScanPulang.textContent = btn.dataset.scanPulang || '-';
    rmCorrectedBy.textContent = btn.dataset.correctedBy || '-';
    rmCorrectedAt.textContent = btn.dataset.correctedAt || '-';
    rmAlasan.textContent = btn.dataset.alasan || '-';

    riwayatModal.style.display = 'flex';
};

window.closeRiwayatModal = function() {
    riwayatModal.style.display = 'none';
};

riwayatModal.addEventListener('click', function(e) {
    if (e.target === riwayatModal) {
        closeRiwayatModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && riwayatModal.style.display === 'flex') {
        closeRiwayatModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>