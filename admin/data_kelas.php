<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);

$pageTitle = 'Data Kelas';
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
                    <h1>Data Kelas</h1>
                    <p>Kelola kelas, wali kelas, dan guru pengajar.</p>
                </div>

                <div class="guru-filter-actions">
                    <button type="button" class="btn-primary-theme" id="btnTambahKelas">
                        <i class="fa-solid fa-plus"></i>
                        <span>Tambah Kelas</span>
                    </button>
                </div>
            </div>

            <div class="guru-ref-stats data-kelas-stats">
                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-purple-soft">
                        <i class="fa-solid fa-school"></i>
                    </div>
                    <div class="guru-ref-label">TOTAL KELAS</div>
                    <div class="guru-ref-value" id="statTotalKelas">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="guru-ref-label">SUDAH ADA WALI</div>
                    <div class="guru-ref-value" id="statSudahWali">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-yellow-soft">
                        <i class="fa-solid fa-user-clock"></i>
                    </div>
                    <div class="guru-ref-label">BELUM ADA WALI</div>
                    <div class="guru-ref-value" id="statBelumWali">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-blue-soft">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <div class="guru-ref-label">TOTAL SISWA</div>
                    <div class="guru-ref-value" id="statTotalSiswa">0</div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <div>
                        <div class="table-title">Daftar Kelas</div>
                        <div class="monitoring-sub-label">Data akan diperbarui otomatis setelah aksi berhasil.</div>
                    </div>

                    <div class="table-tools" style="flex-wrap:wrap;gap:8px;">
                        <input type="text" id="kelasSearchInput" class="input-theme search-theme" placeholder="Cari nama kelas / wali kelas...">
                        <button type="button" class="btn-light-theme" id="btnRefreshKelas" title="Refresh">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <div id="kelasAlert" class="alert-theme" style="display:none; margin-bottom:14px;"></div>

                <div class="table-responsive-theme">
                    <table class="theme-table monitoring-table">
                        <thead>
                            <tr>
                                <th style="width:70px;">No</th>
                                <th>Nama Kelas</th>
                                <th>Wali Kelas</th>
                                <th>Pengajar</th>
                                <th>Siswa</th>
                                <th style="width:240px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="kelasTableBody">
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state-theme">Memuat data kelas...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="monitoring-footer-note">
                    Menampilkan <strong id="kelasCountText">0</strong> kelas
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal tambah / edit kelas -->
<div id="kelasModal" class="warn-modal-overlay" style="display:none;">
    <div class="warn-modal-box koreksi-modal-box">
        <div class="warn-modal-icon">
            <i class="fa-solid fa-school"></i>
        </div>

        <h3 class="warn-modal-title" id="kelasModalTitle">Tambah Kelas</h3>
        <p class="warn-modal-body">Isi data kelas dan pilih wali kelas.</p>

        <form id="kelasForm" class="koreksi-form-inner">
            <input type="hidden" name="action" value="save_kelas">
            <input type="hidden" name="kelas_id" id="kelasIdInput">

            <div>
                <label class="form-label">Nama Kelas</label>
                <input type="text" name="nama_kelas" id="namaKelasInput" class="input-theme" required maxlength="20" placeholder="Contoh: XII IPA 1">
            </div>

            <div>
                <label class="form-label">Wali Kelas</label>
                <select name="wali_guru_id" id="waliGuruSelect" class="select-theme">
                    <option value="">- Pilih Wali Kelas -</option>
                </select>
            </div>

            <div class="warn-modal-actions">
                <button type="button" class="warn-btn-cancel" onclick="closeKelasModal()">Batal</button>
                <button type="submit" class="warn-btn-confirm" id="kelasSubmitBtn">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal atur pengajar -->
<div id="pengajarModal" class="warn-modal-overlay" style="display:none;">
    <div class="warn-modal-box koreksi-modal-box">
        <div class="warn-modal-icon">
            <i class="fa-solid fa-chalkboard-user"></i>
        </div>

        <h3 class="warn-modal-title">Atur Guru Pengajar</h3>
        <p class="warn-modal-body">
            Kelas: <strong id="pengajarModalKelasName">-</strong>
        </p>

        <form id="pengajarForm" class="koreksi-form-inner">
            <input type="hidden" name="action" value="save_pengajar">
            <input type="hidden" name="kelas_id" id="pengajarKelasIdInput">

            <div class="kelas-guru-checklist" id="guruChecklistWrap">
                <div class="empty-state-theme" style="padding:18px 12px;">Memuat daftar guru...</div>
            </div>

            <div class="warn-modal-actions">
                <button type="button" class="warn-btn-cancel" onclick="closePengajarModal()">Batal</button>
                <button type="submit" class="warn-btn-confirm" id="pengajarSubmitBtn">Simpan Pengajar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const listUrl = '<?= BASE_URL; ?>/admin/data_kelas_list.php';
    const actionUrl = '<?= BASE_URL; ?>/admin/data_kelas_action.php';

    const kelasTableBody = document.getElementById('kelasTableBody');
    const kelasCountText = document.getElementById('kelasCountText');
    const kelasAlert = document.getElementById('kelasAlert');
    const btnRefreshKelas = document.getElementById('btnRefreshKelas');
    const btnTambahKelas = document.getElementById('btnTambahKelas');
    const kelasSearchInput = document.getElementById('kelasSearchInput');

    const statTotalKelas = document.getElementById('statTotalKelas');
    const statSudahWali = document.getElementById('statSudahWali');
    const statBelumWali = document.getElementById('statBelumWali');
    const statTotalSiswa = document.getElementById('statTotalSiswa');

    const kelasModal = document.getElementById('kelasModal');
    const kelasModalTitle = document.getElementById('kelasModalTitle');
    const kelasForm = document.getElementById('kelasForm');
    const kelasIdInput = document.getElementById('kelasIdInput');
    const namaKelasInput = document.getElementById('namaKelasInput');
    const waliGuruSelect = document.getElementById('waliGuruSelect');
    const kelasSubmitBtn = document.getElementById('kelasSubmitBtn');

    const pengajarModal = document.getElementById('pengajarModal');
    const pengajarForm = document.getElementById('pengajarForm');
    const pengajarKelasIdInput = document.getElementById('pengajarKelasIdInput');
    const pengajarModalKelasName = document.getElementById('pengajarModalKelasName');
    const guruChecklistWrap = document.getElementById('guruChecklistWrap');
    const pengajarSubmitBtn = document.getElementById('pengajarSubmitBtn');

    let kelasRows = [];
    let guruOptions = [];
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
        kelasAlert.style.display = 'block';
        kelasAlert.className = 'alert-theme ' + (type === 'error' ? 'alert-error-theme' : '');
        kelasAlert.innerHTML = escapeHtml(message);

        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => {
            kelasAlert.style.display = 'none';
        }, 3000);
    }

    function setButtonLoading(btn, isLoading, textLoading = 'Menyimpan...') {
        if (!btn) return;
        if (isLoading) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + textLoading;
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalHtml || 'Simpan';
        }
    }

    function renderStats(summary) {
        statTotalKelas.textContent = summary.total_kelas || 0;
        statSudahWali.textContent = summary.sudah_ada_wali || 0;
        statBelumWali.textContent = summary.belum_ada_wali || 0;
        statTotalSiswa.textContent = summary.total_siswa || 0;
    }

    function renderWaliOptions(selectedId = '') {
        let html = '<option value="">- Pilih Wali Kelas -</option>';
        guruOptions.forEach(guru => {
            const selected = String(selectedId) === String(guru.id) ? 'selected' : '';
            const extra = guru.wali_di_kelas ? ' (Wali ' + guru.wali_di_kelas + ')' : '';
            html += '<option value="' + guru.id + '" ' + selected + '>' +
                escapeHtml(guru.nama) + extra + '</option>';
        });
        waliGuruSelect.innerHTML = html;
    }

    function renderGuruChecklist(selectedIds = [], waliId = '') {
        const selectedSet = new Set((selectedIds || []).map(id => String(id)));
        const currentWaliId = String(waliId || '');

        if (!guruOptions.length) {
            guruChecklistWrap.innerHTML = '<div class="empty-state-theme" style="padding:18px 12px;">Belum ada data guru.</div>';
            return;
        }

        let html = '';
        guruOptions.forEach(guru => {
            const checked = selectedSet.has(String(guru.id)) ? 'checked' : '';
            const disabled = currentWaliId && currentWaliId === String(guru.id) ? 'disabled' : '';
            const note = currentWaliId && currentWaliId === String(guru.id)
                ? '<span class="kelas-guru-note">Sedang dipakai sebagai wali kelas</span>'
                : (guru.wali_di_kelas ? '<span class="kelas-guru-note">Wali ' + escapeHtml(guru.wali_di_kelas) + '</span>' : '');

            html += `
                <label class="kelas-guru-item">
                    <div class="kelas-guru-main">
                        <input type="checkbox" name="guru_ids[]" value="${guru.id}" ${checked} ${disabled}>
                        <div>
                            <div class="kelas-guru-name">${escapeHtml(guru.nama)}</div>
                            <div class="kelas-guru-sub">${escapeHtml(guru.nip || '-')}</div>
                        </div>
                    </div>
                    ${note}
                </label>
            `;
        });

        guruChecklistWrap.innerHTML = html;
    }

    function renderTable(rows) {
        if (!rows.length) {
            kelasTableBody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state-theme">Tidak ada data kelas.</div>
                    </td>
                </tr>
            `;
            kelasCountText.textContent = '0';
            return;
        }

        let html = '';
        rows.forEach((row, index) => {
            const wali = row.wali_nama ? escapeHtml(row.wali_nama) : '<span class="text-muted-soft">Belum ditentukan</span>';
            const pengajarCount = row.jumlah_pengajar || 0;
            const siswaCount = row.jumlah_siswa || 0;

            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td><strong>${escapeHtml(row.nama_kelas)}</strong></td>
                    <td>${wali}</td>
                    <td>
                        <span class="status-pill status-yellow">${pengajarCount} guru</span>
                    </td>
                    <td>
                        <span class="status-pill status-green">${siswaCount} siswa</span>
                    </td>
                    <td>
                        <div class="aksi-btn-group">
                            <button type="button" class="btn-light-theme btn-kelas-aksi" onclick="openEditKelas(${row.id})">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                            <button type="button" class="btn-light-theme btn-kelas-aksi" onclick="openPengajarModal(${row.id})">
                                <i class="fa-solid fa-users"></i> Pengajar
                            </button>
                            <button type="button" class="btn-light-theme btn-kelas-aksi btn-danger-soft" onclick="deleteKelas(${row.id})">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        kelasTableBody.innerHTML = html;
        kelasCountText.textContent = rows.length;
    }

    async function loadKelasTable() {
        try {
            kelasTableBody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state-theme">Memuat data kelas...</div>
                    </td>
                </tr>
            `;

            const q = kelasSearchInput.value.trim();
            const url = new URL(listUrl, window.location.origin);
            if (q) {
                url.searchParams.set('q', q);
            }

            const res = await fetch(url.toString(), { cache: 'no-store' });
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Gagal memuat data.');
            }

            kelasRows = data.rows || [];
            guruOptions = data.guru_options || [];

            renderStats(data.summary || {});
            renderTable(kelasRows);
            renderWaliOptions('');
        } catch (err) {
            kelasTableBody.innerHTML = `
                <tr>
                    <td colspan="6">
                        <div class="empty-state-theme">Gagal memuat data kelas.</div>
                    </td>
                </tr>
            `;
            showAlert(err.message || 'Terjadi kesalahan saat memuat data.', 'error');
        }
    }

    function findKelasById(id) {
        return kelasRows.find(item => String(item.id) === String(id)) || null;
    }

    window.openTambahKelas = function () {
        kelasModalTitle.textContent = 'Tambah Kelas';
        kelasForm.reset();
        kelasIdInput.value = '';
        renderWaliOptions('');
        kelasModal.style.display = 'flex';
        setTimeout(() => namaKelasInput.focus(), 50);
    };

    window.openEditKelas = function (id) {
        const row = findKelasById(id);
        if (!row) return;

        kelasModalTitle.textContent = 'Edit Kelas';
        kelasIdInput.value = row.id;
        namaKelasInput.value = row.nama_kelas || '';
        renderWaliOptions(row.wali_guru_id || '');
        kelasModal.style.display = 'flex';
        setTimeout(() => namaKelasInput.focus(), 50);
    };

    window.closeKelasModal = function () {
        kelasModal.style.display = 'none';
    };

    window.openPengajarModal = function (id) {
        const row = findKelasById(id);
        if (!row) return;

        pengajarKelasIdInput.value = row.id;
        pengajarModalKelasName.textContent = row.nama_kelas || '-';
        renderGuruChecklist(row.pengajar_ids || [], row.wali_guru_id || '');
        pengajarModal.style.display = 'flex';
    };

    window.closePengajarModal = function () {
        pengajarModal.style.display = 'none';
    };

    window.deleteKelas = async function (id) {
        const row = findKelasById(id);
        if (!row) return;

        const ok = confirm('Hapus kelas "' + row.nama_kelas + '"?');
        if (!ok) return;

        try {
            const formData = new FormData();
            formData.append('action', 'delete_kelas');
            formData.append('kelas_id', id);

            const res = await fetch(actionUrl, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Gagal menghapus kelas.');
            }

            showAlert(data.message || 'Kelas berhasil dihapus.');
            loadKelasTable();
        } catch (err) {
            showAlert(err.message || 'Terjadi kesalahan saat menghapus kelas.', 'error');
        }
    };

    kelasForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        try {
            setButtonLoading(kelasSubmitBtn, true);

            const formData = new FormData(kelasForm);
            const res = await fetch(actionUrl, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Gagal menyimpan kelas.');
            }

            closeKelasModal();
            showAlert(data.message || 'Data kelas berhasil disimpan.');
            loadKelasTable();
        } catch (err) {
            showAlert(err.message || 'Terjadi kesalahan saat menyimpan kelas.', 'error');
        } finally {
            setButtonLoading(kelasSubmitBtn, false);
        }
    });

    pengajarForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        try {
            setButtonLoading(pengajarSubmitBtn, true, 'Menyimpan...');

            const formData = new FormData(pengajarForm);
            const res = await fetch(actionUrl, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Gagal menyimpan guru pengajar.');
            }

            closePengajarModal();
            showAlert(data.message || 'Guru pengajar berhasil diperbarui.');
            loadKelasTable();
        } catch (err) {
            showAlert(err.message || 'Terjadi kesalahan saat menyimpan pengajar.', 'error');
        } finally {
            setButtonLoading(pengajarSubmitBtn, false);
        }
    });

    btnTambahKelas.addEventListener('click', openTambahKelas);
    btnRefreshKelas.addEventListener('click', loadKelasTable);

    kelasSearchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadKelasTable, 300);
    });

    kelasModal.addEventListener('click', function (e) {
        if (e.target === kelasModal) closeKelasModal();
    });

    pengajarModal.addEventListener('click', function (e) {
        if (e.target === pengajarModal) closePengajarModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (kelasModal.style.display === 'flex') closeKelasModal();
            if (pengajarModal.style.display === 'flex') closePengajarModal();
        }
    });

    document.addEventListener('DOMContentLoaded', loadKelasTable);
})();
</script>

<?php include '../includes/footer.php'; ?>