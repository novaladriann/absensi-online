<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru']);

header('Content-Type: application/json');

$guruId = getGuruId($conn);

if (!$guruId) {
    echo json_encode([
        'success' => false,
        'message' => 'Data guru tidak ditemukan.',
    ]);
    exit;
}

$tanggalDipilih = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalDipilih)) {
    $tanggalDipilih = date('Y-m-d');
}

// Daftar kelas yang diampu guru
$stmt = $conn->prepare("
    SELECT GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas SEPARATOR ', ') AS kelas_list
    FROM guru_kelas gk
    INNER JOIN kelas k ON k.id = gk.kelas_id
    WHERE gk.guru_id = ?
");
$stmt->bind_param("i", $guruId);
$stmt->execute();
$kelasResult = $stmt->get_result()->fetch_assoc();
$daftarKelas  = $kelasResult['kelas_list'] ?: '-';
$stmt->close();

// Total siswa aktif di kelas yang diampu
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) AS total
    FROM siswa s
    INNER JOIN guru_kelas gk ON gk.kelas_id = s.kelas_id
    WHERE gk.guru_id = ?
      AND s.status_siswa = 'aktif'
");
$stmt->bind_param("i", $guruId);
$stmt->execute();
$totalSiswa = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

// Rekap absensi pada tanggal yang dipilih
$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN a.status_masuk = 'Hadir'     THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status_masuk = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.status_masuk = 'Sakit'     THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN a.status_masuk = 'Izin'      THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN a.status_masuk = 'Alpa'      THEN 1 ELSE 0 END) AS alpa
    FROM absensi a
    INNER JOIN siswa s ON s.id = a.siswa_id
    INNER JOIN guru_kelas gk ON gk.kelas_id = s.kelas_id
    WHERE gk.guru_id = ?
      AND a.tanggal = ?
");
$stmt->bind_param("is", $guruId, $tanggalDipilih);
$stmt->execute();
$rekap = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalHadir     = (int) ($rekap['hadir']     ?? 0);
$totalTerlambat = (int) ($rekap['terlambat'] ?? 0);
$totalSakit     = (int) ($rekap['sakit']     ?? 0);
$totalIzin      = (int) ($rekap['izin']      ?? 0);
$totalAlpa      = (int) ($rekap['alpa']      ?? 0);

$sudahTercatat = $totalHadir + $totalTerlambat + $totalSakit + $totalIzin + $totalAlpa;
$belumAbsen    = max($totalSiswa - $sudahTercatat, 0);

echo json_encode([
    'success'      => true,
    'tanggal'      => $tanggalDipilih,
    'labelTanggal' => formatTanggalIndonesia($tanggalDipilih),
    'daftarKelas'  => $daftarKelas,
    'stats'        => [
        'totalSiswa' => $totalSiswa,
        'hadir'      => $totalHadir,
        'terlambat'  => $totalTerlambat,
        'sakit'      => $totalSakit,
        'izin'       => $totalIzin,
        'alpa'       => $totalAlpa,
        'belumAbsen' => $belumAbsen,
    ],
]);