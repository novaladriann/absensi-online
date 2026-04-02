<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru']);

header('Content-Type: application/json');

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
    echo json_encode([
        'success' => false,
        'message' => 'Data guru tidak ditemukan.'
    ]);
    exit;
}

$tanggalDipilih = $_GET['tanggal'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalDipilih)) {
    $tanggalDipilih = date('Y-m-d');
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

$totalSiswa = 0;
$totalSakit = 0;
$totalIzin = 0;
$totalAlpa = 0;
$totalHadir = 0;
$totalTerlambat = 0;
$belumAbsen = 0;
$daftarKelas = '-';

$stmt = $conn->prepare("
    SELECT GROUP_CONCAT(DISTINCT k.nama_kelas ORDER BY k.nama_kelas SEPARATOR ', ') AS kelas_list
    FROM guru_kelas gk
    INNER JOIN kelas k ON k.id = gk.kelas_id
    WHERE gk.guru_id = ?
");
$stmt->bind_param("i", $guruId);
$stmt->execute();
$kelasResult = $stmt->get_result()->fetch_assoc();
$daftarKelas = $kelasResult['kelas_list'] ?: '-';
$stmt->close();

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

$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN a.status_masuk = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN a.status_masuk = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN a.status_masuk = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN a.status_masuk = 'Izin' THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN a.status_masuk = 'Alpa' THEN 1 ELSE 0 END) AS alpa
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

$totalHadir = (int) ($rekap['hadir'] ?? 0);
$totalTerlambat = (int) ($rekap['terlambat'] ?? 0);
$totalSakit = (int) ($rekap['sakit'] ?? 0);
$totalIzin = (int) ($rekap['izin'] ?? 0);
$totalAlpa = (int) ($rekap['alpa'] ?? 0);

$sudahTercatat = $totalHadir + $totalTerlambat + $totalSakit + $totalIzin + $totalAlpa;
$belumAbsen = max($totalSiswa - $sudahTercatat, 0);

echo json_encode([
    'success' => true,
    'tanggal' => $tanggalDipilih,
    'labelTanggal' => formatTanggalIndonesia($tanggalDipilih),
    'daftarKelas' => $daftarKelas,
    'stats' => [
        'totalSiswa' => $totalSiswa,
        'sakit' => $totalSakit,
        'izin' => $totalIzin,
        'alpa' => $totalAlpa,
        'hadir' => $totalHadir,
        'terlambat' => $totalTerlambat,
        'belumAbsen' => $belumAbsen
    ]
]);