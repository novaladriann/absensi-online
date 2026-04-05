<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once 'laporan_helper.php';

require_role(['admin']);

[$tanggalMulai, $tanggalSelesai] = normalizeReportDateRange(
    $_GET['tanggal_mulai'] ?? '',
    $_GET['tanggal_selesai'] ?? ''
);

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$q = trim($_GET['q'] ?? '');
$format = strtolower(trim($_GET['format'] ?? 'csv'));

$data = getLaporanRows($conn, $tanggalMulai, $tanggalSelesai, $kelasId, $q);
$rows = $data['rows'];
$summary = $data['summary'];

$kelasOptions = getKelasOptionsForLaporan($conn);
$labelKelas = 'Semua Kelas';
if ($kelasId > 0) {
    foreach ($kelasOptions as $kelas) {
        if ((int)$kelas['id'] === $kelasId) {
            $labelKelas = $kelas['nama_kelas'];
            break;
        }
    }
}

$filenameBase = 'laporan_absensi_' . $tanggalMulai . '_sampai_' . $tanggalSelesai;

if ($format === 'xls') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html><head><meta charset="utf-8"></head><body>';
    echo '<h3>Laporan Absensi Siswa</h3>';
    echo '<p>Periode: ' . htmlspecialchars(formatTanggalIndonesia($tanggalMulai)) . ' s.d. ' . htmlspecialchars(formatTanggalIndonesia($tanggalSelesai)) . '</p>';
    echo '<p>Kelas: ' . htmlspecialchars($labelKelas) . '</p>';
    echo '<table border="1" cellpadding="6" cellspacing="0">';
    echo '<thead><tr>';
    echo '<th>No</th>';
    echo '<th>Nama</th>';
    echo '<th>Username</th>';
    echo '<th>NIS</th>';
    echo '<th>NISN</th>';
    echo '<th>Kelas</th>';
    echo '<th>Hari Efektif</th>';
    echo '<th>Hadir</th>';
    echo '<th>Terlambat</th>';
    echo '<th>Sakit</th>';
    echo '<th>Izin</th>';
    echo '<th>Alpa</th>';
    echo '<th>Belum Absen</th>';
    echo '<th>% Kehadiran</th>';
    echo '</tr></thead><tbody>';

    if (!empty($rows)) {
        foreach ($rows as $i => $row) {
            echo '<tr>';
            echo '<td>' . ($i + 1) . '</td>';
            echo '<td>' . htmlspecialchars($row['nama']) . '</td>';
            echo '<td>' . htmlspecialchars($row['username']) . '</td>';
            echo '<td>' . htmlspecialchars($row['nis']) . '</td>';
            echo '<td>' . htmlspecialchars($row['nisn']) . '</td>';
            echo '<td>' . htmlspecialchars($row['nama_kelas']) . '</td>';
            echo '<td>' . (int)$summary['hari_efektif'] . '</td>';
            echo '<td>' . (int)$row['hadir'] . '</td>';
            echo '<td>' . (int)$row['terlambat'] . '</td>';
            echo '<td>' . (int)$row['sakit'] . '</td>';
            echo '<td>' . (int)$row['izin'] . '</td>';
            echo '<td>' . (int)$row['alpa'] . '</td>';
            echo '<td>' . (int)$row['belum_absen'] . '</td>';
            echo '<td>' . htmlspecialchars((string)$row['persentase_kehadiran']) . '%</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="14">Tidak ada data.</td></tr>';
    }

    echo '</tbody></table></body></html>';
    exit;
}

/* default csv */
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

/* BOM agar Excel membaca UTF-8 */
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['Laporan Absensi Siswa']);
fputcsv($output, ['Periode', formatTanggalIndonesia($tanggalMulai) . ' s.d. ' . formatTanggalIndonesia($tanggalSelesai)]);
fputcsv($output, ['Kelas', $labelKelas]);
fputcsv($output, []);

fputcsv($output, [
    'No',
    'Nama',
    'Username',
    'NIS',
    'NISN',
    'Kelas',
    'Hari Efektif',
    'Hadir',
    'Terlambat',
    'Sakit',
    'Izin',
    'Alpa',
    'Belum Absen',
    '% Kehadiran'
]);

if (!empty($rows)) {
    foreach ($rows as $i => $row) {
        fputcsv($output, [
            $i + 1,
            $row['nama'],
            $row['username'],
            $row['nis'],
            $row['nisn'],
            $row['nama_kelas'],
            (int)$summary['hari_efektif'],
            (int)$row['hadir'],
            (int)$row['terlambat'],
            (int)$row['sakit'],
            (int)$row['izin'],
            (int)$row['alpa'],
            (int)$row['belum_absen'],
            $row['persentase_kehadiran'] . '%'
        ]);
    }
} else {
    fputcsv($output, ['Tidak ada data']);
}

fclose($output);
exit;