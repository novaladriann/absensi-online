<?php
$scanConfig = $scanConfig ?? [];

$pageTitle      = $scanConfig['pageTitle'] ?? 'Scan Absensi';
$scanTitle      = $scanConfig['scanTitle'] ?? 'Scan Absensi';
$scanSub        = $scanConfig['scanSub'] ?? 'Arahkan kamera ke kartu QR siswa';
$backUrl        = $scanConfig['backUrl'] ?? (BASE_URL . '/');
$backLabel      = $scanConfig['backLabel'] ?? 'Kembali';
$processUrl     = $scanConfig['processUrl'] ?? (BASE_URL . '/scan/process.php');
$infoUrl        = $scanConfig['infoUrl'] ?? (BASE_URL . '/scan/info.php');
$infoTitle      = $scanConfig['infoTitle'] ?? 'Jadwal Hari Ini';

include __DIR__ . '/header.php';
?>

<div class="app-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <?php include __DIR__ . '/topbar.php'; ?>

        <div class="content-area scan-page">
            <div class="scan-wrapper">

                <div class="scan-card">
                    <div class="scan-card-header">
                        <div class="scan-header-left">
                            <div class="scan-header-icon">
                                <i class="fa-solid fa-qrcode"></i>
                            </div>
                            <div>
                                <div class="scan-header-title"><?= htmlspecialchars($scanTitle); ?></div>
                                <div class="scan-header-sub" id="scanHeaderSub"><?= htmlspecialchars($scanSub); ?></div>
                            </div>
                        </div>
                        <div class="scan-mode-badge" id="scanModeBadge">
                            <i class="fa-solid fa-circle scan-mode-dot" id="scanModeDot"></i>
                            <span id="scanModeLabel">Siap</span>
                        </div>
                    </div>

                    <div class="scan-viewport-wrap">
                        <video id="scanVideo" autoplay playsinline muted></video>

                        <div class="scan-frame">
                            <span class="sf-tl"></span>
                            <span class="sf-tr"></span>
                            <span class="sf-bl"></span>
                            <span class="sf-br"></span>
                        </div>

                        <div class="scan-line" id="scanLine"></div>

                        <div class="scan-overlay" id="scanOverlay">
                            <div class="scan-overlay-inner" id="scanOverlayInner"></div>
                        </div>
                    </div>

                    <div class="scan-result-panel" id="scanResultPanel" style="display:none;">
                        <div class="srp-icon-wrap" id="srpIconWrap">
                            <i class="fa-solid fa-circle-check" id="srpIcon"></i>
                        </div>
                        <div class="srp-body">
                            <div class="srp-nama" id="srpNama">-</div>
                            <div class="srp-kelas" id="srpKelas">-</div>
                            <div class="srp-status-row">
                                <span class="srp-mode" id="srpMode">-</span>
                                <span class="srp-status" id="srpStatus">-</span>
                            </div>
                            <div class="srp-jam" id="srpJam">-</div>
                        </div>
                    </div>

                    <div class="scan-msg" id="scanMsg" style="display:none;">
                        <div class="scan-msg-icon" id="scanMsgIcon">
                            <i class="fa-solid fa-xmark"></i>
                        </div>
                        <div class="scan-msg-text">
                            <div class="scan-msg-title" id="scanMsgTitle">-</div>
                            <div class="scan-msg-body" id="scanMsgBody">-</div>
                        </div>
                    </div>

                    <div class="scan-controls">
                        <button class="scan-ctrl-btn" id="btnBelakang" data-facing="environment">
                            <i class="fa-solid fa-camera-rotate"></i> Belakang
                        </button>
                        <button class="scan-ctrl-btn" id="btnDepan" data-facing="user">
                            <i class="fa-solid fa-user"></i> Depan
                        </button>
                    </div>

                    <a href="<?= htmlspecialchars($backUrl); ?>" class="scan-back-btn">
                        <i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars($backLabel); ?>
                    </a>
                </div>

                <div class="scan-info-card" id="scanInfoCard">
                    <div class="sic-title">
                        <i class="fa-solid fa-clock-rotate-left"></i> <?= htmlspecialchars($infoTitle); ?>
                    </div>
                    <div class="sic-body" id="sicBody">
                        <div class="sic-loading">
                            <i class="fa-solid fa-spinner fa-spin"></i> Memuat jadwal...
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script src="<?= BASE_URL; ?>/assets/js/scan-shared.js"></script>
<script>
window.initScanPage({
    processUrl: <?= json_encode($processUrl); ?>,
    infoUrl: <?= json_encode($infoUrl); ?>
});
</script>

<?php include __DIR__ . '/footer.php'; ?>