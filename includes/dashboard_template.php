<?php
$dashboardConfig = $dashboardConfig ?? [];

$heading          = $dashboardConfig['heading'] ?? 'Dashboard';
$description      = $dashboardConfig['description'] ?? '';
$endpoint         = $dashboardConfig['endpoint'] ?? '';
$panelType        = $dashboardConfig['panelType'] ?? 'scan';
$showKelasFilter  = !empty($dashboardConfig['showKelasFilter']);
$showHolidayBanner= !empty($dashboardConfig['showHolidayBanner']);
$initialDate      = $dashboardConfig['initialDate'] ?? date('Y-m-d');
$initialDateLabel = $dashboardConfig['initialDateLabel'] ?? 'Memuat tanggal...';
$chartTitle       = $dashboardConfig['chartTitle'] ?? 'Statistik Kehadiran';
$statsCards       = $dashboardConfig['statsCards'] ?? [];
$quickLinks       = $dashboardConfig['quickLinks'] ?? [];

if (!$statsCards) {
    $statsCards = [
        [
            'key' => 'totalSiswa',
            'label' => 'TOTAL SISWA',
            'iconClass' => 'icon-purple-soft',
            'icon' => 'fa-solid fa-user-graduate',
        ],
        [
            'key' => 'sakit',
            'label' => 'SAKIT',
            'iconClass' => 'icon-yellow-soft',
            'icon' => 'fa-solid fa-bed',
        ],
        [
            'key' => 'izin',
            'label' => 'IZIN',
            'iconClass' => 'icon-blue-soft',
            'icon' => 'fa-solid fa-clipboard-check',
        ],
        [
            'key' => 'alpa',
            'label' => 'ALPA',
            'iconClass' => 'icon-red-soft',
            'icon' => 'fa-solid fa-circle-xmark',
        ],
    ];
}
?>

<div class="app-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <?php include __DIR__ . '/topbar.php'; ?>

        <div class="content-area">
            <div class="page-heading">
                <div>
                    <h1><?= htmlspecialchars($heading); ?></h1>
                    <p id="dashboardDesc"><?= htmlspecialchars($description); ?></p>
                </div>

                <div class="guru-filter-actions">
                    <button type="button" class="btn-light-theme guru-date-btn" id="dateDisplayBtn">
                        <i class="fa-regular fa-calendar"></i>
                        <span id="dateDisplayText"><?= htmlspecialchars($initialDateLabel); ?></span>
                    </button>
                    <input type="date" id="tanggalFilter" class="guru-hidden-date" value="<?= htmlspecialchars($initialDate); ?>">

                    <button type="button" class="btn-light-theme guru-refresh-btn" id="refreshDashboardBtn">
                        <i class="fa-solid fa-rotate-right"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            <?php if ($showHolidayBanner): ?>
                <div class="alert-theme dashboard-holiday-banner" id="dashboardHolidayBanner" style="display:none;">
                    <strong>Hari Libur:</strong>
                    <span id="dashboardHolidayText"></span>
                </div>
            <?php endif; ?>

            <div class="guru-ref-stats">
                <?php foreach ($statsCards as $card): ?>
                    <div class="guru-ref-card">
                        <div class="guru-ref-icon <?= htmlspecialchars($card['iconClass']); ?>">
                            <i class="<?= htmlspecialchars($card['icon']); ?>"></i>
                        </div>
                        <div class="guru-ref-label"><?= htmlspecialchars($card['label']); ?></div>
                        <div class="guru-ref-value" data-stat-key="<?= htmlspecialchars($card['key']); ?>">0</div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="guru-dashboard-grid">
                <div class="panel-card guru-chart-card">
                    <div class="guru-chart-header">
                        <div>
                            <div class="table-title" id="dashboardChartTitle"><?= htmlspecialchars($chartTitle); ?></div>
                            <div class="guru-chart-sub" id="dashboardChartSub">
                                Tanggal: <strong id="labelTanggalDashboard">-</strong>
                                <?php if ($showKelasFilter): ?>
                                    <br>Kelas: <strong id="labelKelasDashboard">-</strong>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <?php if ($showKelasFilter): ?>
                                <div class="guru-kelas-filter-wrap">
                                    <select id="kelasFilterSelect" class="select-theme guru-kelas-select">
                                        <option value="0">Memuat kelas...</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <span class="guru-chip" id="dashboardChip">Realtime</span>
                        </div>
                    </div>

                    <div class="guru-chart-wrap">
                        <canvas id="dashboardChart"></canvas>
                    </div>
                </div>

                <?php if ($panelType === 'scan'): ?>
                    <div class="guru-scan-card">
                        <div class="guru-scan-icon">
                            <i class="fa-solid fa-qrcode"></i>
                        </div>
                        <h3>Mulai Absensi</h3>
                        <p>Buka pemindai kamera untuk melakukan absensi siswa secara cepat.</p>
                        <a href="<?= BASE_URL; ?>/guru/scan.php" class="guru-scan-btn">Buka Scanner</a>
                    </div>
                <?php else: ?>
                    <div class="panel-card">
                        <h3 style="margin-top:0; margin-bottom:18px;">Akses Cepat</h3>
                        <div class="quick-menu">
                            <?php foreach ($quickLinks as $item): ?>
                                <a href="<?= htmlspecialchars($item['href']); ?>" class="quick-item">
                                    <div class="quick-icon <?= htmlspecialchars($item['iconClass']); ?>">
                                        <i class="<?= htmlspecialchars($item['icon']); ?>"></i>
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($item['title']); ?></strong><br>
                                        <span style="color:#7f879c;"><?= htmlspecialchars($item['subtitle']); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= BASE_URL; ?>/assets/js/dashboard-shared.js"></script>
<script>
window.initSharedDashboard({
    endpoint: <?= json_encode($endpoint); ?>,
    initialDate: <?= json_encode($initialDate); ?>,
    initialDateLabel: <?= json_encode($initialDateLabel); ?>,
    showKelasFilter: <?= $showKelasFilter ? 'true' : 'false'; ?>,
    showHolidayBanner: <?= $showHolidayBanner ? 'true' : 'false'; ?>,
    title: <?= json_encode($heading); ?>,
    defaultDescription: <?= json_encode($description); ?>,
    chartTitle: <?= json_encode($chartTitle); ?>
});
</script>