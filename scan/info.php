<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru', 'admin']);
header('Content-Type: application/json');

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

$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$userId || !in_array($role, ['guru', 'admin'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'User tidak valid.'
    ]);
    exit;
}

$tanggalHariIni = date('Y-m-d');
$labelTanggal = formatTanggalIndonesia($tanggalHariIni);

/* cek hari libur */
$holiday = ['isHoliday' => false, 'keterangan' => ''];
if (tableExists($conn, 'hari_libur')) {
    $stmtLibur = $conn->prepare("SELECT keterangan FROM hari_libur WHERE tanggal = ? LIMIT 1");
    $stmtLibur->bind_param("s", $tanggalHariIni);
    $stmtLibur->execute();
    $rowLibur = $stmtLibur->get_result()->fetch_assoc();
    $stmtLibur->close();

    if ($rowLibur) {
        $holiday['isHoliday'] = true;
        $holiday['keterangan'] = $rowLibur['keterangan'];
    }
}

if ($role === 'guru') {
    $guruId = getGuruId($conn);
    if (!$guruId) {
        echo json_encode(['success' => false, 'message' => 'Data guru tidak ditemukan.']);
        exit;
    }

    $stmtKelas = $conn->prepare("
        SELECT k.id, k.nama_kelas, gk.role_guru_kelas
        FROM guru_kelas gk
        INNER JOIN kelas k ON k.id = gk.kelas_id
        WHERE gk.guru_id = ?
        ORDER BY k.nama_kelas ASC
    ");
    $stmtKelas->bind_param("i", $guruId);
    $stmtKelas->execute();
    $resKelas = $stmtKelas->get_result();

    $daftarKelas = [];
    $kelasIds = [];
    while ($row = $resKelas->fetch_assoc()) {
        $daftarKelas[] = $row;
        $kelasIds[] = (int)$row['id'];
    }
    $stmtKelas->close();

    $kelasLabel = !empty($daftarKelas)
        ? implode(', ', array_map(function ($k) {
            return $k['nama_kelas'] . ($k['role_guru_kelas'] === 'wali' ? ' (WK)' : '');
        }, $daftarKelas))
        : '-';

    $whereKelas = !empty($kelasIds) ? 's.kelas_id IN (' . implode(',', $kelasIds) . ')' : '1=0';

    $totalSiswa = (int)($conn->query("
        SELECT COUNT(*) AS total
        FROM siswa s
        WHERE {$whereKelas}
          AND s.status_siswa = 'aktif'
    ")->fetch_assoc()['total'] ?? 0);

    $stmtStats = $conn->prepare("
        SELECT
            SUM(CASE WHEN a.status_masuk = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN a.status_masuk = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
            COUNT(DISTINCT a.siswa_id) AS sudah_absen
        FROM absensi a
        INNER JOIN siswa s ON s.id = a.siswa_id
        WHERE a.tanggal = ?
          AND {$whereKelas}
    ");
    $stmtStats->bind_param("s", $tanggalHariIni);
    $stmtStats->execute();
    $stats = $stmtStats->get_result()->fetch_assoc() ?: [];
    $stmtStats->close();

    $hadir = (int)($stats['hadir'] ?? 0);
    $terlambat = (int)($stats['terlambat'] ?? 0);
    $sudahHadir = $hadir + $terlambat;
    $belumAbsen = max($totalSiswa - (int)($stats['sudah_absen'] ?? 0), 0);

    echo json_encode([
        'success' => true,
        'labelTanggal' => $labelTanggal,
        'actorLabel' => 'Guru',
        'kelasLabel' => $kelasLabel,
        'holiday' => $holiday,
        'stats' => [
            'totalSiswa' => $totalSiswa,
            'sudahHadir' => $sudahHadir,
            'belumAbsen' => $belumAbsen
        ]
    ]);
    exit;
}

/* admin */
$totalSiswa = (int)($conn->query("
    SELECT COUNT(*) AS total
    FROM siswa
    WHERE status_siswa = 'aktif'
")->fetch_assoc()['total'] ?? 0);

$stmtStats = $conn->prepare("
    SELECT
        SUM(CASE WHEN status_masuk = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN status_masuk = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
        COUNT(DISTINCT siswa_id) AS sudah_absen
    FROM absensi
    WHERE tanggal = ?
");
$stmtStats->bind_param("s", $tanggalHariIni);
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc() ?: [];
$stmtStats->close();

$hadir = (int)($stats['hadir'] ?? 0);
$terlambat = (int)($stats['terlambat'] ?? 0);
$sudahHadir = $hadir + $terlambat;
$belumAbsen = max($totalSiswa - (int)($stats['sudah_absen'] ?? 0), 0);

echo json_encode([
    'success' => true,
    'labelTanggal' => $labelTanggal,
    'actorLabel' => 'Admin',
    'kelasLabel' => 'Semua Kelas',
    'holiday' => $holiday,
    'stats' => [
        'totalSiswa' => $totalSiswa,
        'sudahHadir' => $sudahHadir,
        'belumAbsen' => $belumAbsen
    ]
]);