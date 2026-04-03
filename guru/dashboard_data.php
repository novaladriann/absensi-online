<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru']);

header('Content-Type: application/json');

$guruId = getGuruId($conn);

if (!$guruId) {
    echo json_encode(['success' => false, 'message' => 'Data guru tidak ditemukan.']);
    exit;
}

$tanggalDipilih = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalDipilih)) {
    $tanggalDipilih = date('Y-m-d');
}

// kelas_id: 0 atau kosong = semua kelas
$kelasIdFilter = (int) ($_GET['kelas_id'] ?? 0);

/* ---------- Validasi kelas_id: harus milik guru ini ---------- */
if ($kelasIdFilter > 0) {
    $stmtVal = $conn->prepare("
        SELECT kelas_id FROM guru_kelas
        WHERE guru_id = ? AND kelas_id = ?
        LIMIT 1
    ");
    $stmtVal->bind_param("ii", $guruId, $kelasIdFilter);
    $stmtVal->execute();
    $valid = $stmtVal->get_result()->num_rows > 0;
    $stmtVal->close();

    if (!$valid) {
        $kelasIdFilter = 0; // fallback ke semua kelas
    }
}

/* ---------- Daftar kelas yang diampu (untuk dropdown di frontend) ---------- */
$stmtKelas = $conn->prepare("
    SELECT k.id, k.nama_kelas, gk.role_guru_kelas
    FROM guru_kelas gk
    INNER JOIN kelas k ON k.id = gk.kelas_id
    WHERE gk.guru_id = ?
    ORDER BY gk.role_guru_kelas DESC, k.nama_kelas ASC
");
$stmtKelas->bind_param("i", $guruId);
$stmtKelas->execute();
$daftarKelas = $stmtKelas->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtKelas->close();

/* ---------- Label kelas aktif ---------- */
$labelKelas = 'Semua Kelas';
if ($kelasIdFilter > 0) {
    foreach ($daftarKelas as $k) {
        if ((int)$k['id'] === $kelasIdFilter) {
            $labelKelas = $k['nama_kelas'];
            break;
        }
    }
}

/* ---------- Total siswa ---------- */
if ($kelasIdFilter > 0) {
    $stmtSiswa = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM siswa
        WHERE kelas_id = ? AND status_siswa = 'aktif'
    ");
    $stmtSiswa->bind_param("i", $kelasIdFilter);
} else {
    $stmtSiswa = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) AS total
        FROM siswa s
        INNER JOIN guru_kelas gk ON gk.kelas_id = s.kelas_id
        WHERE gk.guru_id = ? AND s.status_siswa = 'aktif'
    ");
    $stmtSiswa->bind_param("i", $guruId);
}
$stmtSiswa->execute();
$totalSiswa = (int) ($stmtSiswa->get_result()->fetch_assoc()['total'] ?? 0);
$stmtSiswa->close();

/* ---------- Rekap absensi ---------- */
if ($kelasIdFilter > 0) {
    $stmtRekap = $conn->prepare("
        SELECT
            SUM(CASE WHEN a.status_masuk = 'Hadir'     THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN a.status_masuk = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
            SUM(CASE WHEN a.status_masuk = 'Sakit'     THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN a.status_masuk = 'Izin'      THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN a.status_masuk = 'Alpa'      THEN 1 ELSE 0 END) AS alpa
        FROM absensi a
        INNER JOIN siswa s ON s.id = a.siswa_id
        WHERE s.kelas_id = ?
          AND a.tanggal  = ?
    ");
    $stmtRekap->bind_param("is", $kelasIdFilter, $tanggalDipilih);
} else {
    $stmtRekap = $conn->prepare("
        SELECT
            SUM(CASE WHEN a.status_masuk = 'Hadir'     THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN a.status_masuk = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
            SUM(CASE WHEN a.status_masuk = 'Sakit'     THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN a.status_masuk = 'Izin'      THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN a.status_masuk = 'Alpa'      THEN 1 ELSE 0 END) AS alpa
        FROM absensi a
        INNER JOIN siswa s     ON s.id        = a.siswa_id
        INNER JOIN guru_kelas gk ON gk.kelas_id = s.kelas_id
        WHERE gk.guru_id = ?
          AND a.tanggal  = ?
    ");
    $stmtRekap->bind_param("is", $guruId, $tanggalDipilih);
}
$stmtRekap->execute();
$rekap = $stmtRekap->get_result()->fetch_assoc();
$stmtRekap->close();

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
    'kelasId'      => $kelasIdFilter,
    'labelKelas'   => $labelKelas,
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