<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

$allowedStatus = ['aktif', 'nonaktif'];
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$where = ["u.role = 'guru'"];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(u.nama LIKE ? OR u.username LIKE ? OR g.nip LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($status !== '') {
    $where[] = "u.status_akun = ?";
    $params[] = $status;
    $types .= 's';
}

$whereSql = implode(' AND ', $where);

/* summary */
$sqlSummary = "
    SELECT
        COUNT(*) AS total_guru,
        SUM(CASE WHEN u.status_akun = 'aktif' THEN 1 ELSE 0 END) AS akun_aktif,
        SUM(CASE WHEN EXISTS (
            SELECT 1 FROM kelas k WHERE k.wali_guru_id = g.id
        ) THEN 1 ELSE 0 END) AS total_wali,
        (
            SELECT COUNT(*)
            FROM guru_kelas gk
            INNER JOIN guru g2 ON g2.id = gk.guru_id
            INNER JOIN users u2 ON u2.id = g2.user_id
            WHERE gk.role_guru_kelas = 'pengajar'
              AND u2.role = 'guru'
        ) AS total_relasi_pengajar
    FROM guru g
    INNER JOIN users u ON u.id = g.user_id
    WHERE {$whereSql}
";

$stmtSummary = $conn->prepare($sqlSummary);
if (!empty($params)) {
    $stmtSummary->bind_param($types, ...$params);
}
$stmtSummary->execute();
$summary = $stmtSummary->get_result()->fetch_assoc() ?: [
    'total_guru' => 0,
    'akun_aktif' => 0,
    'total_wali' => 0,
    'total_relasi_pengajar' => 0,
];
$stmtSummary->close();

/* table rows */
$sql = "
    SELECT
        g.id,
        g.user_id,
        g.nip,
        g.no_hp,
        g.alamat,
        u.nama,
        u.username,
        u.email,
        u.status_akun,
        (
            SELECT k.nama_kelas
            FROM kelas k
            WHERE k.wali_guru_id = g.id
            LIMIT 1
        ) AS wali_kelas_nama,
        (
            SELECT k.id
            FROM kelas k
            WHERE k.wali_guru_id = g.id
            LIMIT 1
        ) AS wali_kelas_id,
        (
            SELECT GROUP_CONCAT(k2.nama_kelas ORDER BY k2.nama_kelas SEPARATOR '||')
            FROM guru_kelas gk2
            INNER JOIN kelas k2 ON k2.id = gk2.kelas_id
            WHERE gk2.guru_id = g.id
              AND gk2.role_guru_kelas = 'pengajar'
        ) AS pengajar_kelas_csv,
        (
            SELECT GROUP_CONCAT(gk3.kelas_id ORDER BY gk3.kelas_id SEPARATOR ',')
            FROM guru_kelas gk3
            WHERE gk3.guru_id = g.id
              AND gk3.role_guru_kelas = 'pengajar'
        ) AS pengajar_kelas_ids_csv
    FROM guru g
    INNER JOIN users u ON u.id = g.user_id
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
    $row['pengajar_kelas_list'] = !empty($row['pengajar_kelas_csv'])
        ? explode('||', $row['pengajar_kelas_csv'])
        : [];

    $row['pengajar_kelas_ids'] = !empty($row['pengajar_kelas_ids_csv'])
        ? array_map('intval', explode(',', $row['pengajar_kelas_ids_csv']))
        : [];

    unset($row['pengajar_kelas_csv'], $row['pengajar_kelas_ids_csv']);
    $rows[] = $row;
}
$stmt->close();

/* kelas options */
$resultKelas = $conn->query("
    SELECT
        k.id,
        k.nama_kelas,
        u.nama AS current_wali_name
    FROM kelas k
    LEFT JOIN guru g ON g.id = k.wali_guru_id
    LEFT JOIN users u ON u.id = g.user_id
    ORDER BY k.nama_kelas ASC
");

$kelasOptions = [];
while ($kelas = $resultKelas->fetch_assoc()) {
    $kelasOptions[] = $kelas;
}

echo json_encode([
    'success' => true,
    'summary' => [
        'total_guru' => (int)($summary['total_guru'] ?? 0),
        'akun_aktif' => (int)($summary['akun_aktif'] ?? 0),
        'total_wali' => (int)($summary['total_wali'] ?? 0),
        'total_relasi_pengajar' => (int)($summary['total_relasi_pengajar'] ?? 0),
    ],
    'rows' => $rows,
    'kelas_options' => $kelasOptions,
]);