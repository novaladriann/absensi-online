<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$kelasId = (int)($_GET['kelas_id'] ?? 0);
$statusSiswa = trim($_GET['status_siswa'] ?? '');
$statusAkun = trim($_GET['status_akun'] ?? '');

$allowedStatus = ['aktif', 'nonaktif'];
if (!in_array($statusSiswa, $allowedStatus, true)) $statusSiswa = '';
if (!in_array($statusAkun, $allowedStatus, true)) $statusAkun = '';

$where = ["u.role = 'siswa'"];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(u.nama LIKE ? OR u.username LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

if ($kelasId > 0) {
    $where[] = "s.kelas_id = ?";
    $params[] = $kelasId;
    $types .= 'i';
}

if ($statusSiswa !== '') {
    $where[] = "s.status_siswa = ?";
    $params[] = $statusSiswa;
    $types .= 's';
}

if ($statusAkun !== '') {
    $where[] = "u.status_akun = ?";
    $params[] = $statusAkun;
    $types .= 's';
}

$whereSql = implode(' AND ', $where);

/* summary */
$sqlSummary = "
    SELECT
        COUNT(*) AS total_siswa,
        SUM(CASE WHEN u.status_akun = 'aktif' THEN 1 ELSE 0 END) AS akun_aktif,
        COUNT(DISTINCT s.kelas_id) AS kelas_terpakai,
        SUM(CASE WHEN s.kode_kartu IS NOT NULL AND s.kode_kartu <> '' THEN 1 ELSE 0 END) AS kartu_aktif
    FROM siswa s
    INNER JOIN users u ON u.id = s.user_id
    WHERE {$whereSql}
";

$stmtSummary = $conn->prepare($sqlSummary);
if (!empty($params)) {
    $stmtSummary->bind_param($types, ...$params);
}
$stmtSummary->execute();
$summary = $stmtSummary->get_result()->fetch_assoc() ?: [
    'total_siswa' => 0,
    'akun_aktif' => 0,
    'kelas_terpakai' => 0,
    'kartu_aktif' => 0,
];
$stmtSummary->close();

/* rows */
$sql = "
    SELECT
        s.id,
        s.user_id,
        s.nis,
        s.nisn,
        s.kelas_id,
        s.jenis_kelamin,
        s.alamat,
        s.no_hp_ortu,
        s.foto,
        s.kode_kartu,
        s.status_siswa,
        u.nama,
        u.username,
        u.email,
        u.status_akun,
        k.nama_kelas
    FROM siswa s
    INNER JOIN users u ON u.id = s.user_id
    INNER JOIN kelas k ON k.id = s.kelas_id
    WHERE {$whereSql}
    ORDER BY u.nama ASC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

/* kelas options */
$resultKelas = $conn->query("
    SELECT id, nama_kelas
    FROM kelas
    ORDER BY nama_kelas ASC
");

$kelasOptions = [];
while ($kelas = $resultKelas->fetch_assoc()) {
    $kelasOptions[] = $kelas;
}

echo json_encode([
    'success' => true,
    'summary' => [
        'total_siswa' => (int)($summary['total_siswa'] ?? 0),
        'akun_aktif' => (int)($summary['akun_aktif'] ?? 0),
        'kelas_terpakai' => (int)($summary['kelas_terpakai'] ?? 0),
        'kartu_aktif' => (int)($summary['kartu_aktif'] ?? 0),
    ],
    'rows' => $rows,
    'kelas_options' => $kelasOptions,
]);