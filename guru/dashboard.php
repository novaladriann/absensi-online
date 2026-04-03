<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru']);

$pageTitle = 'Dashboard';
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
                    <h1>Dashboard Guru</h1>
                    <p id="guruDashboardDesc">Ringkasan aktivitas siswa.</p>
                </div>

                <div class="guru-filter-actions">
                    <button type="button" class="btn-light-theme guru-date-btn" id="dateDisplayBtn">
                        <i class="fa-regular fa-calendar"></i>
                        <span id="dateDisplayText">Memuat tanggal...</span>
                    </button>
                    <input type="date" id="tanggalFilter" class="guru-hidden-date">

                    <button type="button" class="btn-light-theme guru-refresh-btn" id="refreshDashboardBtn">
                        <i class="fa-solid fa-rotate-right"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            <!-- Stat cards -->
            <div class="guru-ref-stats">
                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-purple-soft">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <div class="guru-ref-label">TOTAL SISWA</div>
                    <div class="guru-ref-value" id="statTotalSiswa">0</div>
                </div>
                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-yellow-soft">
                        <i class="fa-solid fa-bed"></i>
                    </div>
                    <div class="guru-ref-label">SAKIT</div>
                    <div class="guru-ref-value" id="statSakit">0</div>
                </div>
                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-blue-soft">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div class="guru-ref-label">IZIN</div>
                    <div class="guru-ref-value" id="statIzin">0</div>
                </div>
                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-red-soft">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>
                    <div class="guru-ref-label">ALPA</div>
                    <div class="guru-ref-value" id="statAlpa">0</div>
                </div>
            </div>

            <div class="guru-dashboard-grid">
                <!-- Chart card -->
                <div class="panel-card guru-chart-card">
                    <div class="guru-chart-header">
                        <div>
                            <div class="table-title">Statistik Kehadiran Hari Ini</div>
                            <div class="guru-chart-sub">
                                Tanggal: <strong id="labelTanggalDashboard">-</strong>
                            </div>
                        </div>

                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <!-- Dropdown filter kelas — opsi diisi JS setelah data loaded -->
                            <div class="guru-kelas-filter-wrap">
                                <select id="kelasFilterSelect" class="select-theme guru-kelas-select">
                                    <option value="0">Memuat kelas...</option>
                                </select>
                            </div>
                            <span class="guru-chip">Realtime</span>
                        </div>
                    </div>

                    <div class="guru-chart-wrap">
                        <canvas id="guruChart"></canvas>
                    </div>
                </div>

                <!-- Scan card -->
                <div class="guru-scan-card">
                    <div class="guru-scan-icon">
                        <i class="fa-solid fa-qrcode"></i>
                    </div>
                    <h3>Mulai Absensi</h3>
                    <p>Buka pemindai kamera untuk melakukan absensi siswa secara cepat.</p>
                    <a href="<?= BASE_URL; ?>/guru/scan.php" class="guru-scan-btn">Buka Scanner</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    /* ---- Elemen DOM ---- */
    const dateInput        = document.getElementById('tanggalFilter');
    const dateDisplayBtn   = document.getElementById('dateDisplayBtn');
    const dateDisplayText  = document.getElementById('dateDisplayText');
    const refreshBtn       = document.getElementById('refreshDashboardBtn');
    const kelasSelect      = document.getElementById('kelasFilterSelect');

    const statTotalSiswa   = document.getElementById('statTotalSiswa');
    const statSakit        = document.getElementById('statSakit');
    const statIzin         = document.getElementById('statIzin');
    const statAlpa         = document.getElementById('statAlpa');
    const labelTanggal     = document.getElementById('labelTanggalDashboard');
    const dashboardDesc    = document.getElementById('guruDashboardDesc');

    /* ---- State ---- */
    const params        = new URLSearchParams(window.location.search);
    let currentTanggal  = params.get('tanggal') || new Date().toISOString().split('T')[0];
    let currentKelasId  = parseInt(params.get('kelas_id') || '0', 10);
    let kelasPopulated  = false; // flag: dropdown sudah diisi atau belum

    dateInput.value = currentTanggal;

    /* ---- Chart ---- */
    const ctx       = document.getElementById('guruChart');
    const guruChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa', 'Belum Absen'],
            datasets: [{
                label: 'Jumlah Siswa',
                data: [0, 0, 0, 0, 0, 0],
                borderRadius: 10,
                backgroundColor: ['#4f46e5','#8b5cf6','#f4c542','#60a5fa','#ef4444','#cbd5e1']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1f2430',
                    padding: 12,
                    cornerRadius: 10
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#6b7280', font: { size: 12, weight: '600' } }
                },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: '#6b7280' },
                    grid: { color: '#eef1f7' }
                }
            }
        }
    });

    /* ---- Isi dropdown kelas (hanya sekali, saat data pertama datang) ---- */
    function populateKelasDropdown(daftarKelas, selectedId) {
        if (kelasPopulated) return;
        kelasPopulated = true;

        kelasSelect.innerHTML = '';

        // Opsi "Semua Kelas"
        const optAll  = document.createElement('option');
        optAll.value  = '0';
        optAll.text   = 'Semua Kelas';
        kelasSelect.appendChild(optAll);

        daftarKelas.forEach(function (k) {
            const opt   = document.createElement('option');
            opt.value   = k.id;
            opt.text    = k.nama_kelas
                        + (k.role_guru_kelas === 'wali' ? ' (Wali)' : '');
            kelasSelect.appendChild(opt);
        });

        kelasSelect.value = String(selectedId);
    }

    /* ---- Update URL tanpa reload ---- */
    function updateUrl(tanggal, kelasId) {
        const url = new URL(window.location);
        url.searchParams.set('tanggal', tanggal);
        if (kelasId > 0) url.searchParams.set('kelas_id', kelasId);
        else             url.searchParams.delete('kelas_id');
        window.history.replaceState({}, '', url);
    }

    /* ---- Load data dashboard ---- */
    async function loadDashboard(tanggal, kelasId) {
        try {
            refreshBtn.disabled = true;
            refreshBtn.classList.add('is-loading');

            const url = `<?= BASE_URL; ?>/guru/dashboard_data.php`
                      + `?tanggal=${encodeURIComponent(tanggal)}`
                      + `&kelas_id=${encodeURIComponent(kelasId)}`;

            const res  = await fetch(url);
            const data = await res.json();

            if (!data.success) {
                alert(data.message || 'Gagal memuat data dashboard.');
                return;
            }

            /* Update state */
            currentTanggal = data.tanggal;
            currentKelasId = data.kelasId;
            dateInput.value = data.tanggal;

            /* Isi dropdown kelas (hanya pertama kali) */
            populateKelasDropdown(data.daftarKelas, data.kelasId);

            /* Update teks heading */
            dateDisplayText.textContent = data.labelTanggal;
            labelTanggal.textContent    = data.labelTanggal;
            dashboardDesc.textContent   = `Ringkasan aktivitas siswa — ${data.labelKelas} — ${data.labelTanggal}.`;

            /* Update stat cards */
            statTotalSiswa.textContent = data.stats.totalSiswa;
            statSakit.textContent      = data.stats.sakit;
            statIzin.textContent       = data.stats.izin;
            statAlpa.textContent       = data.stats.alpa;

            /* Update chart */
            guruChart.data.datasets[0].data = [
                data.stats.hadir,
                data.stats.terlambat,
                data.stats.sakit,
                data.stats.izin,
                data.stats.alpa,
                data.stats.belumAbsen
            ];
            guruChart.update();

            updateUrl(data.tanggal, data.kelasId);

        } catch (err) {
            alert('Terjadi kesalahan saat memuat data.');
            console.error(err);
        } finally {
            refreshBtn.disabled = false;
            refreshBtn.classList.remove('is-loading');
        }
    }

    /* ---- Event listeners ---- */
    dateDisplayBtn.addEventListener('click', function () {
        dateInput.showPicker ? dateInput.showPicker() : dateInput.click();
    });

    dateInput.addEventListener('change', function () {
        if (this.value) loadDashboard(this.value, currentKelasId);
    });

    kelasSelect.addEventListener('change', function () {
        currentKelasId = parseInt(this.value, 10);
        loadDashboard(currentTanggal, currentKelasId);
    });

    refreshBtn.addEventListener('click', function () {
        loadDashboard(currentTanggal, currentKelasId);
    });

    /* ---- Initial load ---- */
    document.addEventListener('DOMContentLoaded', function () {
        loadDashboard(currentTanggal, currentKelasId);
    });
})();
</script>

<?php include '../includes/footer.php'; ?>