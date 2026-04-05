<?php
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

function normalizeReportDateRange(string $tanggalMulai = '', string $tanggalSelesai = ''): array
{
    $today = date('Y-m-d');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalMulai)) {
        $tanggalMulai = date('Y-m-01');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSelesai)) {
        $tanggalSelesai = $today;
    }

    if ($tanggalMulai > $tanggalSelesai) {
        [$tanggalMulai, $tanggalSelesai] = [$tanggalSelesai, $tanggalMulai];
    }

    return [$tanggalMulai, $tanggalSelesai];
}

function getHariIndonesiaByDate(string $date): string
{
    $map = [
        'Sunday'    => 'Minggu',
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
    ];

    $day = date('l', strtotime($date));
    return $map[$day] ?? '';
}

function getEffectiveDays(mysqli $conn, string $tanggalMulai, string $tanggalSelesai): int
{
    $activeDays = [];

    $resultJadwal = $conn->query("
        SELECT hari
        FROM jadwal_absensi
        WHERE status_aktif = 'aktif'
    ");

    while ($row = $resultJadwal->fetch_assoc()) {
        $activeDays[$row['hari']] = true;
    }

    $holidayDates = [];
    if (tableExists($conn, 'hari_libur')) {
        $stmtHoliday = $conn->prepare("
            SELECT tanggal
            FROM hari_libur
            WHERE tanggal BETWEEN ? AND ?
        ");
        $stmtHoliday->bind_param("ss", $tanggalMulai, $tanggalSelesai);
        $stmtHoliday->execute();
        $resultHoliday = $stmtHoliday->get_result();

        while ($row = $resultHoliday->fetch_assoc()) {
            $holidayDates[$row['tanggal']] = true;
        }
        $stmtHoliday->close();
    }

    $start = new DateTime($tanggalMulai);
    $end = new DateTime($tanggalSelesai);
    $end->modify('+1 day');

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($start, $interval, $end);

    $count = 0;

    foreach ($period as $dateObj) {
        $tanggal = $dateObj->format('Y-m-d');
        $hari = getHariIndonesiaByDate($tanggal);

        if (isset($activeDays[$hari]) && !isset($holidayDates[$tanggal])) {
            $count++;
        }
    }

    return $count;
}

function getKelasOptionsForLaporan(mysqli $conn): array
{
    $rows = [];
    $result = $conn->query("
        SELECT id, nama_kelas
        FROM kelas
        ORDER BY nama_kelas ASC
    ");

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function getLaporanRows(mysqli $conn, string $tanggalMulai, string $tanggalSelesai, int $kelasId = 0, string $q = ''): array
{
    [$tanggalMulai, $tanggalSelesai] = normalizeReportDateRange($tanggalMulai, $tanggalSelesai);
    $hariEfektif = getEffectiveDays($conn, $tanggalMulai, $tanggalSelesai);

    $where = ["s.status_siswa = 'aktif'"];
    $params = [$tanggalMulai, $tanggalSelesai];
    $types = "ss";

    if ($kelasId > 0) {
        $where[] = "s.kelas_id = ?";
        $params[] = $kelasId;
        $types .= "i";
    }

    if ($q !== '') {
        $like = '%' . $q . '%';
        $where[] = "(u.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ? OR u.username LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $types .= "ssss";
    }

    $sql = "
        SELECT
            s.id AS siswa_id,
            u.nama,
            u.username,
            s.nis,
            s.nisn,
            k.nama_kelas,
            COUNT(DISTINCT a.tanggal) AS hari_tercatat,
            SUM(CASE WHEN a.status_masuk = 'Hadir' THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN a.status_masuk = 'Terlambat' THEN 1 ELSE 0 END) AS terlambat,
            SUM(CASE WHEN a.status_masuk = 'Sakit' THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN a.status_masuk = 'Izin' THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN a.status_masuk = 'Alpa' THEN 1 ELSE 0 END) AS alpa
        FROM siswa s
        INNER JOIN users u ON u.id = s.user_id
        INNER JOIN kelas k ON k.id = s.kelas_id
        LEFT JOIN absensi a
            ON a.siswa_id = s.id
           AND a.tanggal BETWEEN ? AND ?
        WHERE " . implode(' AND ', $where) . "
        GROUP BY s.id, u.nama, u.username, s.nis, s.nisn, k.nama_kelas
        ORDER BY k.nama_kelas ASC, u.nama ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    $summary = [
        'total_siswa' => 0,
        'hari_efektif' => $hariEfektif,
        'total_hadir' => 0,
        'total_tidak_hadir' => 0,
    ];

    while ($row = $result->fetch_assoc()) {
        $row['hadir'] = (int)($row['hadir'] ?? 0);
        $row['terlambat'] = (int)($row['terlambat'] ?? 0);
        $row['sakit'] = (int)($row['sakit'] ?? 0);
        $row['izin'] = (int)($row['izin'] ?? 0);
        $row['alpa'] = (int)($row['alpa'] ?? 0);
        $row['hari_tercatat'] = (int)($row['hari_tercatat'] ?? 0);

        $row['belum_absen'] = max($hariEfektif - $row['hari_tercatat'], 0);
        $row['hadir_total'] = $row['hadir'] + $row['terlambat'];

        $row['persentase_kehadiran'] = $hariEfektif > 0
            ? round(($row['hadir_total'] / $hariEfektif) * 100, 1)
            : 0;

        $summary['total_siswa']++;
        $summary['total_hadir'] += $row['hadir_total'];
        $summary['total_tidak_hadir'] += ($row['sakit'] + $row['izin'] + $row['alpa'] + $row['belum_absen']);

        $rows[] = $row;
    }

    $stmt->close();

    return [
        'tanggal_mulai' => $tanggalMulai,
        'tanggal_selesai' => $tanggalSelesai,
        'summary' => $summary,
        'rows' => $rows,
    ];
}