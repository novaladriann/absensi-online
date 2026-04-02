<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['siswa']);

if (!isset($_SESSION['siswa_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$siswaId = (int) $_SESSION['siswa_id'];

$namaSiswa   = $_SESSION['nama'] ?? 'Siswa';
$nis         = '-';
$nisn        = $_SESSION['nisn'] ?? '-';
$kelas       = '-';
$statusSiswa = 'Aktif';
$foto        = null;
$kodeKartu   = '-';

$stmt = $conn->prepare("
    SELECT 
        u.nama,
        s.nis,
        s.nisn,
        s.foto,
        s.kode_kartu,
        s.status_siswa,
        k.nama_kelas
    FROM siswa s
    INNER JOIN users u ON u.id = s.user_id
    INNER JOIN kelas k ON k.id = s.kelas_id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $siswaId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();

    $namaSiswa   = $data['nama'] ?? $namaSiswa;
    $nis         = $data['nis'] ?? '-';
    $nisn        = $data['nisn'] ?? $nisn;
    $kelas       = $data['nama_kelas'] ?? '-';
    $statusSiswa = $data['status_siswa'] ?? 'Aktif';
    $foto        = $data['foto'] ?? null;
    $kodeKartu   = $data['kode_kartu'] ?? '-';
}
$stmt->close();

$pageTitle = 'Kartu Saya';
include '../includes/header.php';
?>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>

        <div class="content-area">
            <div class="page-heading">
                <div>
                    <h1>Kartu Digital Siswa</h1>
                    <p>Gunakan kartu ini untuk proses scan absensi oleh guru atau admin.</p>
                </div>
            </div>

            <div class="kartu-page-grid">
                <div class="kartu-digital-wrap">
                    <div class="kartu-digital-card">
                        <div class="kartu-header">
                            <div>
                                <div class="kartu-school">SMA 12 CIREBON</div>
                                <div class="kartu-subtitle">KARTU IDENTITAS SISWA</div>
                            </div>
                            <div class="kartu-chip">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                        </div>

                        <div class="kartu-body">
                            <div class="kartu-foto-area">
                                <div class="kartu-foto">
                                    <?php if (!empty($foto)) : ?>
                                        <img src="<?= BASE_URL; ?>/assets/img/<?= htmlspecialchars($foto); ?>" alt="Foto Siswa">
                                    <?php else : ?>
                                        <i class="fa-regular fa-user"></i>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="kartu-info">
                                <div class="kartu-nama"><?= htmlspecialchars($namaSiswa); ?></div>
                                <div class="kartu-role">SISWA AKTIF</div>

                                <div class="kartu-detail-grid">
                                    <div class="kartu-detail-item">
                                        <span>NIS</span>
                                        <strong><?= htmlspecialchars($nis); ?></strong>
                                    </div>
                                    <div class="kartu-detail-item">
                                        <span>NISN</span>
                                        <strong><?= htmlspecialchars($nisn); ?></strong>
                                    </div>
                                    <div class="kartu-detail-item">
                                        <span>Kelas</span>
                                        <strong><?= htmlspecialchars($kelas); ?></strong>
                                    </div>
                                    <div class="kartu-detail-item">
                                        <span>Status</span>
                                        <strong class="<?= strtolower($statusSiswa) === 'aktif' ? 'text-success-theme' : 'text-danger-theme'; ?>">
                                            <?= htmlspecialchars($statusSiswa); ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="kartu-footer">
                            <div>
                                <div class="kartu-code-label">Kode Kartu</div>
                                <div class="kartu-code-value"><?= htmlspecialchars($kodeKartu); ?></div>
                            </div>
                            <div class="kartu-brand">
                                E-ABSENSI
                            </div>
                        </div>
                    </div>
                </div>

                <div class="qr-panel">
                    <div class="qr-card">
                        <h3>QR Code Absensi</h3>
                        <p>Tunjukkan QR Code ini kepada guru/admin untuk proses scan absensi.</p>

                        <div class="qr-box">
                            <div id="qrcode"></div>
                        </div>

                        <div class="qr-code-text"><?= htmlspecialchars($kodeKartu); ?></div>

                        <button type="button" class="btn-primary-theme btn-full" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> Cetak / Simpan
                        </button>
                    </div>

                    <div class="qr-note-card">
                        <h4>Catatan</h4>
                        <ul class="qr-note-list">
                            <li>Jangan membagikan kartu digital kepada siswa lain.</li>
                            <li>QR Code ini dipakai saat guru/admin melakukan scan absensi.</li>
                            <li>Jika data kartu salah, hubungi admin sekolah.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const kodeKartu = <?= json_encode($kodeKartu); ?>;
    const qrContainer = document.getElementById('qrcode');

    if (qrContainer && kodeKartu && kodeKartu !== '-') {
        qrContainer.innerHTML = '';
        new QRCode(qrContainer, {
            text: kodeKartu,
            width: 220,
            height: 220
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>