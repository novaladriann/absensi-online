<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once 'laporan_helper.php';

require_role(['admin']);
header('Content-Type: application/json');

[$tanggalMulai, $tanggalSelesai] = normalizeReportDateRange(
    $_GET['tanggal_mulai'] ?? '',
    $_GET['tanggal_selesai'] ?? ''
);

$kelasId = (int)($_GET['kelas_id'] ?? 0);
$q = trim($_GET['q'] ?? '');

$data = getLaporanRows($conn, $tanggalMulai, $tanggalSelesai, $kelasId, $q);
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

echo json_encode([
    'success' => true,
    'label_tanggal_mulai' => formatTanggalIndonesia($data['tanggal_mulai']),
    'label_tanggal_selesai' => formatTanggalIndonesia($data['tanggal_selesai']),
    'label_kelas' => $labelKelas,
    'summary' => $data['summary'],
    'rows' => $data['rows'],
    'kelas_options' => $kelasOptions,
]);