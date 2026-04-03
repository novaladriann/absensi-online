<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru']);

header('Content-Type: application/json');

$userId = (int) ($_SESSION['user_id'] ?? 0);
$guruId = getGuruId($conn);

if (!$guruId) {
    echo json_encode([
        'success' => false,
        'code' => 'NO_GURU',
        'message' => 'Data guru tidak ditemukan.'
    ]);
    exit;
}

$kodeKartu = trim($_POST['kode_kartu'] ?? '');
if ($kodeKartu === '') {
    echo json_encode([
        'success' => false,
        'code' => 'INVALID',
        'message' => 'Kode kartu tidak valid.'
    ]);
    exit;
}

$tanggalHariIni = date('Y-m-d');
$jamSekarang    = date('H:i:s');

$hariMap = [
    'Sunday'    => 'Minggu',
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu',
];
$hariIndonesia = $hariMap[date('l')] ?? '';

/* 1. Cek jadwal aktif hari ini */
$stmtJ = $conn->prepare("
    SELECT *
    FROM jadwal_absensi
    WHERE hari = ? AND status_aktif = 'aktif'
    LIMIT 1
");
$stmtJ->bind_param("s", $hariIndonesia);
$stmtJ->execute();
$jadwal = $stmtJ->get_result()->fetch_assoc();
$stmtJ->close();

if (!$jadwal) {
    echo json_encode([
        'success' => false,
        'code' => 'LIBUR',
        'message' => 'Hari ini tidak ada jadwal absensi aktif.'
    ]);
    exit;
}

/* 2. Cari siswa */
$stmtS = $conn->prepare("
    SELECT s.id AS siswa_id, s.kelas_id, u.nama, k.nama_kelas
    FROM siswa s
    INNER JOIN users u ON u.id = s.user_id
    INNER JOIN kelas k ON k.id = s.kelas_id
    WHERE s.kode_kartu = ? AND s.status_siswa = 'aktif'
    LIMIT 1
");
$stmtS->bind_param("s", $kodeKartu);
$stmtS->execute();
$siswa = $stmtS->get_result()->fetch_assoc();
$stmtS->close();

if (!$siswa) {
    echo json_encode([
        'success' => false,
        'code' => 'NOT_FOUND',
        'message' => 'Kartu tidak dikenali atau siswa tidak aktif.'
    ]);
    exit;
}

/* 3. Ambil nama guru untuk audit */
$stmtNamaGuru = $conn->prepare("SELECT nama FROM users WHERE id = ? LIMIT 1");
$stmtNamaGuru->bind_param("i", $userId);
$stmtNamaGuru->execute();
$namaGuru = $stmtNamaGuru->get_result()->fetch_assoc()['nama'] ?? 'Guru';
$stmtNamaGuru->close();

/* 4. Window waktu */
$ts          = strtotime($jamSekarang);
$tsMasukMul  = strtotime($jadwal['scan_masuk_mulai']);
$tsMasukSel  = strtotime($jadwal['scan_masuk_selesai']);
$tsPulangMul = strtotime($jadwal['scan_pulang_mulai']);
$tsPulangSel = strtotime($jadwal['scan_pulang_selesai']);
$tsBatas     = strtotime($jadwal['batas_terlambat']);

if ($ts < $tsMasukMul) {
    echo json_encode([
        'success' => false,
        'code' => 'TERLALU_AWAL',
        'message' => 'Absensi belum dibuka. Scan masuk mulai pukul ' . substr($jadwal['scan_masuk_mulai'], 0, 5) . '.'
    ]);
    exit;
}

$diWindowMasuk  = ($ts >= $tsMasukMul && $ts <= $tsMasukSel);
$diWindowPulang = ($ts >= $tsPulangMul && $ts <= $tsPulangSel);

/* 5. Record absensi hari ini */
$stmtR = $conn->prepare("
    SELECT id, jam_masuk, jam_pulang, status_masuk, status_pulang
    FROM absensi
    WHERE siswa_id = ? AND tanggal = ?
    LIMIT 1
");
$stmtR->bind_param("is", $siswa['siswa_id'], $tanggalHariIni);
$stmtR->execute();
$rec = $stmtR->get_result()->fetch_assoc();
$stmtR->close();

/* 6. Guard untuk data janggal */
if ($rec && empty($rec['jam_masuk']) && !empty($rec['jam_pulang'])) {
    echo json_encode([
        'success' => false,
        'code' => 'DATA_TIDAK_KONSISTEN',
        'message' => 'Data absensi hari ini tidak konsisten. Silakan koreksi dari menu Monitoring.',
        'siswa' => [
            'nama' => $siswa['nama'],
            'kelas' => $siswa['nama_kelas']
        ]
    ]);
    exit;
}

/* 7. Di luar semua window → tolak */
if (!$diWindowMasuk && !$diWindowPulang) {
    echo json_encode([
        'success' => false,
        'code' => 'DILUAR_WINDOW',
        'message' => 'Di luar jam absensi aktif. Koreksi absensi dilakukan melalui menu Monitoring.',
        'siswa' => [
            'nama' => $siswa['nama'],
            'kelas' => $siswa['nama_kelas']
        ]
    ]);
    exit;
}

/* 8. Tentukan mode */
$mode = null;

if ($diWindowMasuk) {
    // Dalam window masuk, hanya boleh masuk
    if ($rec && !empty($rec['jam_masuk'])) {
        echo json_encode([
            'success' => false,
            'code' => 'SUDAH_MASUK',
            'message' => $siswa['nama'] . ' sudah absen masuk pukul ' . substr($rec['jam_masuk'], 0, 5) . '.',
            'siswa' => [
                'nama' => $siswa['nama'],
                'kelas' => $siswa['nama_kelas']
            ]
        ]);
        exit;
    }

    $mode = 'masuk';
}

elseif ($diWindowPulang) {
    // Dalam window pulang, hanya boleh pulang dan harus sudah punya jam masuk
    if (!$rec || empty($rec['jam_masuk'])) {
        echo json_encode([
            'success' => false,
            'code' => 'BELUM_MASUK',
            'message' => $siswa['nama'] . ' belum tercatat absen masuk hari ini.',
            'siswa' => [
                'nama' => $siswa['nama'],
                'kelas' => $siswa['nama_kelas']
            ]
        ]);
        exit;
    }

    if (!empty($rec['jam_pulang'])) {
        echo json_encode([
            'success' => false,
            'code' => 'SUDAH_PULANG',
            'message' => $siswa['nama'] . ' sudah absen pulang pukul ' . substr($rec['jam_pulang'], 0, 5) . '.',
            'siswa' => [
                'nama' => $siswa['nama'],
                'kelas' => $siswa['nama_kelas']
            ]
        ]);
        exit;
    }

    $mode = 'pulang';
}

/* 9. Status masuk */
$statusMasuk = null;
$menitTelat  = 0;

if ($mode === 'masuk') {
    $statusMasuk = ($ts <= $tsBatas) ? 'Hadir' : 'Terlambat';
    if ($statusMasuk === 'Terlambat') {
        $menitTelat = max((int) floor(($ts - $tsBatas) / 60), 0);
    }
}

/* 10. Simpan */
/* 10. Simpan */
$ket = 'Scan oleh guru: ' . $namaGuru;

if ($mode === 'masuk') {
    if ($rec) {
        $q = $conn->prepare("
            UPDATE absensi
            SET jam_masuk = ?,
                status_masuk = ?,
                metode_absen = 'scan_guru',
                metode_masuk = 'scan_guru',
                scanned_masuk_by_user_id = ?,
                keterangan = ?
            WHERE id = ?
        ");
        $q->bind_param("ssisi", $jamSekarang, $statusMasuk, $userId, $ket, $rec['id']);
    } else {
        $q = $conn->prepare("
            INSERT INTO absensi
                (siswa_id, tanggal, jam_masuk, status_masuk, metode_absen, metode_masuk, scanned_masuk_by_user_id, keterangan)
            VALUES (?, ?, ?, ?, 'scan_guru', 'scan_guru', ?, ?)
        ");
        $q->bind_param("isssis", $siswa['siswa_id'], $tanggalHariIni, $jamSekarang, $statusMasuk, $userId, $ket);
    }
} else {
    $q = $conn->prepare("
        UPDATE absensi
        SET jam_pulang = ?,
            status_pulang = 'Pulang',
            metode_absen = 'scan_guru',
            metode_pulang = 'scan_guru',
            scanned_pulang_by_user_id = ?,
            keterangan = ?
        WHERE id = ?
    ");
    $q->bind_param("sisi", $jamSekarang, $userId, $ket, $rec['id']);
}

$q->execute();
$q->close();

/* 11. Response */
$labelStatus = ($mode === 'masuk')
    ? (($statusMasuk === 'Terlambat') ? 'Terlambat (' . $menitTelat . ' menit)' : 'Tepat Waktu')
    : 'Pulang';

echo json_encode([
    'success'     => true,
    'code'        => 'OK',
    'mode'        => $mode,
    'labelMode'   => ($mode === 'masuk') ? 'Absen Masuk' : 'Absen Pulang',
    'labelStatus' => $labelStatus,
    'statusMasuk' => $statusMasuk,
    'jam'         => substr($jamSekarang, 0, 5),
    'menitTelat'  => $menitTelat,
    'siswa'       => [
        'nama'  => $siswa['nama'],
        'kelas' => $siswa['nama_kelas']
    ],
]);