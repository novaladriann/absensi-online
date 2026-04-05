<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';

/* ringkasan */
if ($q !== '') {
    $stmtSummary = $conn->prepare("
        SELECT
            COUNT(*) AS total_kelas,
            SUM(CASE WHEN k.wali_guru_id IS NOT NULL THEN 1 ELSE 0 END) AS sudah_ada_wali,
            SUM(CASE WHEN k.wali_guru_id IS NULL THEN 1 ELSE 0 END) AS belum_ada_wali,
            (
                SELECT COUNT(*)
                FROM siswa s2
                INNER JOIN kelas k2 ON k2.id = s2.kelas_id
                LEFT JOIN guru gw2 ON gw2.id = k2.wali_guru_id
                LEFT JOIN users uw2 ON uw2.id = gw2.user_id
                WHERE s2.status_siswa = 'aktif'
                  AND (k2.nama_kelas LIKE ? OR COALESCE(uw2.nama, '') LIKE ?)
            ) AS total_siswa
        FROM kelas k
        LEFT JOIN guru gw ON gw.id = k.wali_guru_id
        LEFT JOIN users uw ON uw.id = gw.user_id
        WHERE k.nama_kelas LIKE ? OR COALESCE(uw.nama, '') LIKE ?
    ");
    $stmtSummary->bind_param("ssss", $like, $like, $like, $like);
} else {
    $stmtSummary = $conn->prepare("
        SELECT
            COUNT(*) AS total_kelas,
            SUM(CASE WHEN wali_guru_id IS NOT NULL THEN 1 ELSE 0 END) AS sudah_ada_wali,
            SUM(CASE WHEN wali_guru_id IS NULL THEN 1 ELSE 0 END) AS belum_ada_wali,
            (SELECT COUNT(*) FROM siswa WHERE status_siswa = 'aktif') AS total_siswa
        FROM kelas
    ");
}
$stmtSummary->execute();
$summary = $stmtSummary->get_result()->fetch_assoc() ?: [
    'total_kelas' => 0,
    'sudah_ada_wali' => 0,
    'belum_ada_wali' => 0,
    'total_siswa' => 0,
];
$stmtSummary->close();

/* data kelas */
$sql = "
    SELECT
        k.id,
        k.nama_kelas,
        k.wali_guru_id,
        uw.nama AS wali_nama,
        COUNT(DISTINCT CASE WHEN gk.role_guru_kelas = 'pengajar' THEN gk.guru_id END) AS jumlah_pengajar,
        COUNT(DISTINCT s.id) AS jumlah_siswa,
        GROUP_CONCAT(DISTINCT CASE WHEN gk.role_guru_kelas = 'pengajar' THEN gk.guru_id END ORDER BY gk.guru_id SEPARATOR ',') AS pengajar_ids_csv
    FROM kelas k
    LEFT JOIN guru gw ON gw.id = k.wali_guru_id
    LEFT JOIN users uw ON uw.id = gw.user_id
    LEFT JOIN guru_kelas gk ON gk.kelas_id = k.id
    LEFT JOIN siswa s ON s.kelas_id = k.id AND s.status_siswa = 'aktif'
";

if ($q !== '') {
    $sql .= " WHERE k.nama_kelas LIKE ? OR COALESCE(uw.nama, '') LIKE ? ";
}

$sql .= "
    GROUP BY k.id, k.nama_kelas, k.wali_guru_id, uw.nama
    ORDER BY k.nama_kelas ASC
";

if ($q !== '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $like, $like);
} else {
    $stmt = $conn->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $row['jumlah_pengajar'] = (int)($row['jumlah_pengajar'] ?? 0);
    $row['jumlah_siswa'] = (int)($row['jumlah_siswa'] ?? 0);
    $row['pengajar_ids'] = !empty($row['pengajar_ids_csv'])
        ? array_map('intval', explode(',', $row['pengajar_ids_csv']))
        : [];
    unset($row['pengajar_ids_csv']);
    $rows[] = $row;
}
$stmt->close();

/* opsi guru */
$resultGuru = $conn->query("
    SELECT
        g.id,
        g.nip,
        u.nama,
        (
            SELECT k.nama_kelas
            FROM kelas k
            WHERE k.wali_guru_id = g.id
            LIMIT 1
        ) AS wali_di_kelas
    FROM guru g
    INNER JOIN users u ON u.id = g.user_id
    ORDER BY u.nama ASC
");

$guruOptions = [];
while ($guru = $resultGuru->fetch_assoc()) {
    $guruOptions[] = $guru;
}

echo json_encode([
    'success' => true,
    'summary' => [
        'total_kelas' => (int)($summary['total_kelas'] ?? 0),
        'sudah_ada_wali' => (int)($summary['sudah_ada_wali'] ?? 0),
        'belum_ada_wali' => (int)($summary['belum_ada_wali'] ?? 0),
        'total_siswa' => (int)($summary['total_siswa'] ?? 0),
    ],
    'rows' => $rows,
    'guru_options' => $guruOptions,
]);