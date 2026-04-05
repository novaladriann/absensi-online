<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);
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

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    $tanggal = date('Y-m-d');
}

$kelasId = (int)($_GET['kelas_id'] ?? 0);

/* ---------- Ambil semua kelas ---------- */
$resultKelas = $conn->query("
    SELECT id, nama_kelas
    FROM kelas
    ORDER BY nama_kelas ASC
");

$daftarKelas = [];
while ($row = $resultKelas->fetch_assoc()) {
    $row['role_guru_kelas'] = 'admin';
    $daftarKelas[] = $row;
}

$kelasIds = array_map('intval', array_column($daftarKelas, 'id'));

if ($kelasId > 0 && !in_array($kelasId, $kelasIds, true)) {
    $kelasId = 0;
}

$labelKelas = 'Semua Kelas';
if ($kelasId > 0) {
    foreach ($daftarKelas as $k) {
        if ((int)$k['id'] === $kelasId) {
            $labelKelas = $k['nama_kelas'];
            break;
        }
    }
}

$whereKelas = $kelasId > 0 ? "s.kelas_id = {$kelasId}" : "1=1";

/* ---------- Statistik ---------- */
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
        SUM(CASE WHEN a.status_masuk = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN a.status_masuk = 'Izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN a.status_masuk = 'Alpa' THEN 1 ELSE 0 END) AS alpa,
        COUNT(DISTINCT a.siswa_id) AS sudah_absen
    FROM absensi a
    INNER JOIN siswa s ON s.id = a.siswa_id
    WHERE a.tanggal = ?
      AND {$whereKelas}
");
$stmtStats->bind_param("s", $tanggal);
$stmtStats->execute();
$stats = $stmtStats->get_result()->fetch_assoc() ?: [];
$stmtStats->close();

$hadir      = (int)($stats['hadir'] ?? 0);
$terlambat  = (int)($stats['terlambat'] ?? 0);
$sakit      = (int)($stats['sakit'] ?? 0);
$izin       = (int)($stats['izin'] ?? 0);
$alpa       = (int)($stats['alpa'] ?? 0);
$sudahAbsen = (int)($stats['sudah_absen'] ?? 0);
$belumAbsen = max($totalSiswa - $sudahAbsen, 0);

/* ---------- Hari libur ---------- */
$holiday = [
    'isHoliday' => false,
    'label' => '',
];

if (tableExists($conn, 'hari_libur')) {
    $stmtLibur = $conn->prepare("
        SELECT keterangan
        FROM hari_libur
        WHERE tanggal = ?
        LIMIT 1
    ");
    $stmtLibur->bind_param("s", $tanggal);
    $stmtLibur->execute();
    $rowLibur = $stmtLibur->get_result()->fetch_assoc();
    $stmtLibur->close();

    if ($rowLibur) {
        $holiday['isHoliday'] = true;
        $holiday['label'] = formatTanggalIndonesia($tanggal) . ' — ' . $rowLibur['keterangan'];
    }
}

echo json_encode([
    'success' => true,
    'tanggal' => $tanggal,
    'labelTanggal' => formatTanggalIndonesia($tanggal),
    'kelasId' => $kelasId,
    'labelKelas' => $labelKelas,
    'description' => 'Pusat kontrol data absensi sekolah — ' . $labelKelas . ' — ' . formatTanggalIndonesia($tanggal) . '.',
    'daftarKelas' => $daftarKelas,
    'holiday' => $holiday,
    'stats' => [
        'totalSiswa' => $totalSiswa,
        'hadir' => $hadir,
        'terlambat' => $terlambat,
        'sakit' => $sakit,
        'izin' => $izin,
        'alpa' => $alpa,
        'belumAbsen' => $belumAbsen
    ]
]);