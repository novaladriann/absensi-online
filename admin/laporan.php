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

$pageTitle = 'Laporan';
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
                    <h1>Laporan Absensi</h1>
                    <p>Rekap absensi siswa per periode dengan filter kelas dan export data.</p>
                </div>

                <div class="guru-filter-actions">
                    <button type="button" class="btn-light-theme" id="btnExportCsv">
                        <i class="fa-solid fa-file-csv"></i>
                        <span>Export CSV</span>
                    </button>
                    <button type="button" class="btn-primary-theme" id="btnExportExcel">
                        <i class="fa-solid fa-file-excel"></i>
                        <span>Export Excel</span>
                    </button>
                </div>
            </div>

            <div class="table-card" style="margin-bottom:20px;">
                <div class="table-header">
                    <div>
                        <div class="table-title">Filter Laporan</div>
                        <div class="monitoring-sub-label">Data tabel dan export mengikuti filter yang dipilih.</div>
                    </div>
                </div>

                <div class="laporan-filter-grid">
                    <div>
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" id="lapTanggalMulai" class="input-theme" value="<?= htmlspecialchars($tanggalMulai); ?>">
                    </div>

                    <div>
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" id="lapTanggalSelesai" class="input-theme" value="<?= htmlspecialchars($tanggalSelesai); ?>">
                    </div>

                    <div>
                        <label class="form-label">Kelas</label>
                        <select id="lapKelasFilter" class="select-theme">
                            <option value="">Semua Kelas</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Cari</label>
                        <input type="text" id="lapSearchInput" class="input-theme" placeholder="Nama / NIS / NISN / username">
                    </div>
                </div>
            </div>

            <div class="guru-ref-stats laporan-stats">
                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-purple-soft">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <div class="guru-ref-label">TOTAL SISWA</div>
                    <div class="guru-ref-value" id="lapStatTotalSiswa">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-blue-soft">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div class="guru-ref-label">HARI EFEKTIF</div>
                    <div class="guru-ref-value" id="lapStatHariEfektif">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-green">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div class="guru-ref-label">TOTAL HADIR</div>
                    <div class="guru-ref-value" id="lapStatTotalHadir">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-red-soft">
                        <i class="fa-solid fa-user-xmark"></i>
                    </div>
                    <div class="guru-ref-label">TOTAL TIDAK HADIR</div>
                    <div class="guru-ref-value" id="lapStatTotalTidakHadir">0</div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <div>
                        <div class="table-title">Rekap Siswa</div>
                        <div class="monitoring-sub-label" id="laporanSubLabel">Memuat data laporan...</div>
                    </div>

                    <div class="table-tools" style="gap:8px;">
                        <button type="button" class="btn-light-theme" id="btnRefreshLaporan" title="Refresh">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <div id="laporanAlert" class="alert-theme" style="display:none; margin-bottom:14px;"></div>

                <div class="table-responsive-theme">
                    <table class="theme-table monitoring-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Siswa</th>
                                <th>NIS / NISN</th>
                                <th>Kelas</th>
                                <th>Hari Efektif</th>
                                <th>Hadir</th>
                                <th>Terlambat</th>
                                <th>Sakit</th>
                                <th>Izin</th>
                                <th>Alpa</th>
                                <th>Belum Absen</th>
                                <th>% Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody id="laporanTableBody">
                            <tr>
                                <td colspan="12">
                                    <div class="empty-state-theme">Memuat data laporan...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="monitoring-footer-note">
                    Menampilkan <strong id="laporanCountText">0</strong> siswa
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const listUrl = '<?= BASE_URL; ?>/admin/laporan_list.php';
    const exportUrl = '<?= BASE_URL; ?>/admin/laporan_export.php';

    const lapTanggalMulai = document.getElementById('lapTanggalMulai');
    const lapTanggalSelesai = document.getElementById('lapTanggalSelesai');
    const lapKelasFilter = document.getElementById('lapKelasFilter');
    const lapSearchInput = document.getElementById('lapSearchInput');

    const btnRefreshLaporan = document.getElementById('btnRefreshLaporan');
    const btnExportCsv = document.getElementById('btnExportCsv');
    const btnExportExcel = document.getElementById('btnExportExcel');

    const laporanTableBody = document.getElementById('laporanTableBody');
    const laporanCountText = document.getElementById('laporanCountText');
    const laporanSubLabel = document.getElementById('laporanSubLabel');
    const laporanAlert = document.getElementById('laporanAlert');

    const lapStatTotalSiswa = document.getElementById('lapStatTotalSiswa');
    const lapStatHariEfektif = document.getElementById('lapStatHariEfektif');
    const lapStatTotalHadir = document.getElementById('lapStatTotalHadir');
    const lapStatTotalTidakHadir = document.getElementById('lapStatTotalTidakHadir');

    let kelasOptions = [];
    let searchTimer = null;

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showAlert(message, type = 'success') {
        laporanAlert.style.display = 'block';
        laporanAlert.className = 'alert-theme ' + (type === 'error' ? 'alert-error-theme' : '');
        laporanAlert.innerHTML = escapeHtml(message);

        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => {
            laporanAlert.style.display = 'none';
        }, 3000);
    }

    function currentParams() {
        return {
            tanggal_mulai: lapTanggalMulai.value,
            tanggal_selesai: lapTanggalSelesai.value,
            kelas_id: lapKelasFilter.value,
            q: lapSearchInput.value.trim()
        };
    }

    function fillKelasOptions(selected = '') {
        let html = '<option value="">Semua Kelas</option>';
        kelasOptions.forEach(kelas => {
            const isSelected = String(selected) === String(kelas.id) ? 'selected' : '';
            html += `<option value="${kelas.id}" ${isSelected}>${escapeHtml(kelas.nama_kelas)}</option>`;
        });
        lapKelasFilter.innerHTML = html;
    }

    function renderSummary(summary) {
        lapStatTotalSiswa.textContent = summary.total_siswa || 0;
        lapStatHariEfektif.textContent = summary.hari_efektif || 0;
        lapStatTotalHadir.textContent = summary.total_hadir || 0;
        lapStatTotalTidakHadir.textContent = summary.total_tidak_hadir || 0;
    }

    function renderRows(rows, hariEfektif) {
        if (!rows.length) {
            laporanTableBody.innerHTML = `
                <tr>
                    <td colspan="12">
                        <div class="empty-state-theme">Tidak ada data laporan untuk filter yang dipilih.</div>
                    </td>
                </tr>
            `;
            laporanCountText.textContent = '0';
            return;
        }

        let html = '';
        rows.forEach((row, index) => {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <strong>${escapeHtml(row.nama)}</strong><br>
                        <span class="monitoring-nisn">@${escapeHtml(row.username)}</span>
                    </td>
                    <td>
                        <div><strong>NIS:</strong> ${escapeHtml(row.nis)}</div>
                        <div><strong>NISN:</strong> ${escapeHtml(row.nisn)}</div>
                    </td>
                    <td>${escapeHtml(row.nama_kelas)}</td>
                    <td>${hariEfektif}</td>
                    <td><span class="status-pill status-green">${row.hadir}</span></td>
                    <td><span class="status-pill status-yellow">${row.terlambat}</span></td>
                    <td>${row.sakit}</td>
                    <td>${row.izin}</td>
                    <td>${row.alpa}</td>
                    <td>${row.belum_absen}</td>
                    <td><strong>${row.persentase_kehadiran}%</strong></td>
                </tr>
            `;
        });

        laporanTableBody.innerHTML = html;
        laporanCountText.textContent = rows.length;
    }

    async function loadLaporan() {
        try {
            laporanTableBody.innerHTML = `
                <tr>
                    <td colspan="12">
                        <div class="empty-state-theme">Memuat data laporan...</div>
                    </td>
                </tr>
            `;

            const params = currentParams();
            const url = new URL(listUrl, window.location.origin);

            Object.keys(params).forEach(key => {
                if (params[key] !== '') {
                    url.searchParams.set(key, params[key]);
                }
            });

            const res = await fetch(url.toString(), { cache: 'no-store' });
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Gagal memuat laporan.');
            }

            kelasOptions = data.kelas_options || [];
            fillKelasOptions(params.kelas_id);

            renderSummary(data.summary || {});
            renderRows(data.rows || [], data.summary?.hari_efektif || 0);

            laporanSubLabel.textContent =
                'Periode ' + data.label_tanggal_mulai + ' s.d. ' + data.label_tanggal_selesai +
                ' • ' + (data.label_kelas || 'Semua Kelas');

            const newUrl = new URL(window.location);
            Object.keys(params).forEach(key => {
                if (params[key] !== '') {
                    newUrl.searchParams.set(key, params[key]);
                } else {
                    newUrl.searchParams.delete(key);
                }
            });
            window.history.replaceState({}, '', newUrl);

        } catch (err) {
            laporanTableBody.innerHTML = `
                <tr>
                    <td colspan="12">
                        <div class="empty-state-theme">Gagal memuat data laporan.</div>
                    </td>
                </tr>
            `;
            showAlert(err.message || 'Terjadi kesalahan saat memuat laporan.', 'error');
        }
    }

    function doExport(format) {
        const params = currentParams();
        const url = new URL(exportUrl, window.location.origin);

        Object.keys(params).forEach(key => {
            if (params[key] !== '') {
                url.searchParams.set(key, params[key]);
            }
        });

        url.searchParams.set('format', format);
        window.location.href = url.toString();
    }

    btnRefreshLaporan.addEventListener('click', loadLaporan);
    btnExportCsv.addEventListener('click', () => doExport('csv'));
    btnExportExcel.addEventListener('click', () => doExport('xls'));

    lapTanggalMulai.addEventListener('change', loadLaporan);
    lapTanggalSelesai.addEventListener('change', loadLaporan);
    lapKelasFilter.addEventListener('change', loadLaporan);

    lapSearchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadLaporan, 300);
    });

    document.addEventListener('DOMContentLoaded', loadLaporan);
})();
</script>

<?php include '../includes/footer.php'; ?>