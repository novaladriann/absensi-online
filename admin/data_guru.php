<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);

$pageTitle = 'Data Guru';
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
                    <h1>Data Guru</h1>
                    <p>Kelola akun guru, biodata, dan relasi kelas.</p>
                </div>

                <div class="guru-filter-actions">
                    <button type="button" class="btn-primary-theme" id="btnTambahGuru">
                        <i class="fa-solid fa-plus"></i>
                        <span>Tambah Guru</span>
                    </button>
                </div>
            </div>

            <div class="guru-ref-stats data-guru-stats">
                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-purple-soft">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>
                    <div class="guru-ref-label">TOTAL GURU</div>
                    <div class="guru-ref-value" id="statTotalGuru">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="guru-ref-label">AKUN AKTIF</div>
                    <div class="guru-ref-value" id="statAkunAktif">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-yellow-soft">
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <div class="guru-ref-label">WALI KELAS</div>
                    <div class="guru-ref-value" id="statWaliKelas">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-blue-soft">
                        <i class="fa-solid fa-users-gear"></i>
                    </div>
                    <div class="guru-ref-label">RELASI MENGAJAR</div>
                    <div class="guru-ref-value" id="statRelasiMengajar">0</div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <div>
                        <div class="table-title">Daftar Guru</div>
                        <div class="monitoring-sub-label">Tabel otomatis diperbarui setelah aksi berhasil.</div>
                    </div>

                    <div class="table-tools" style="flex-wrap:wrap;gap:8px;">
                        <select id="guruStatusFilter" class="select-theme">
                            <option value="">Semua Status</option>
                            <option value="aktif">Akun Aktif</option>
                            <option value="nonaktif">Akun Nonaktif</option>
                        </select>
                        <input type="text" id="guruSearchInput" class="input-theme search-theme" placeholder="Cari nama / username / NIP...">
                        <button type="button" class="btn-light-theme" id="btnRefreshGuru" title="Refresh">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <div id="guruAlert" class="alert-theme" style="display:none; margin-bottom:14px;"></div>

                <div class="table-responsive-theme">
                    <table class="theme-table monitoring-table">
                        <thead>
                            <tr>
                                <th style="width:70px;">No</th>
                                <th>Guru</th>
                                <th>NIP</th>
                                <th>No HP</th>
                                <th>Kelas</th>
                                <th>Status</th>
                                <th style="width:260px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="guruTableBody">
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state-theme">Memuat data guru...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="monitoring-footer-note">
                    Menampilkan <strong id="guruCountText">0</strong> guru
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal tambah/edit guru -->
<div id="guruModal" class="warn-modal-overlay" style="display:none;">
    <div class="warn-modal-box koreksi-modal-box guru-modal-box">
        <div class="warn-modal-icon">
            <i class="fa-solid fa-chalkboard-user"></i>
        </div>

        <h3 class="warn-modal-title" id="guruModalTitle">Tambah Guru</h3>
        <p class="warn-modal-body">Isi data akun dan biodata guru.</p>

        <form id="guruForm" class="koreksi-form-inner">
            <input type="hidden" name="action" value="save_guru">
            <input type="hidden" name="guru_id" id="guruIdInput">

            <div class="guru-form-grid">
                <div>
                    <label class="form-label">Nama</label>
                    <input type="text" name="nama" id="guruNamaInput" class="input-theme" required maxlength="100">
                </div>

                <div>
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="guruUsernameInput" class="input-theme" required maxlength="50">
                </div>

                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="guruEmailInput" class="input-theme" maxlength="100">
                </div>

                <div>
                    <label class="form-label">Password <span class="text-muted-soft" id="guruPasswordHint">(wajib saat tambah)</span></label>
                    <input type="password" name="password" id="guruPasswordInput" class="input-theme" maxlength="100">
                </div>

                <div>
                    <label class="form-label">NIP</label>
                    <input type="text" name="nip" id="guruNipInput" class="input-theme" required maxlength="30">
                </div>

                <div>
                    <label class="form-label">No HP</label>
                    <input type="text" name="no_hp" id="guruNoHpInput" class="input-theme" maxlength="20">
                </div>

                <div class="guru-form-full">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" id="guruAlamatInput" class="input-theme koreksi-textarea" rows="3"></textarea>
                </div>

                <div>
                    <label class="form-label">Status Akun</label>
                    <select name="status_akun" id="guruStatusAkunInput" class="select-theme">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="warn-modal-actions">
                <button type="button" class="warn-btn-cancel" onclick="closeGuruModal()">Batal</button>
                <button type="submit" class="warn-btn-confirm" id="guruSubmitBtn">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal atur kelas guru -->
<div id="guruKelasModal" class="warn-modal-overlay" style="display:none;">
    <div class="warn-modal-box koreksi-modal-box guru-modal-box">
        <div class="warn-modal-icon">
            <i class="fa-solid fa-users-gear"></i>
        </div>

        <h3 class="warn-modal-title">Atur Relasi Kelas</h3>
        <p class="warn-modal-body">
            Guru: <strong id="guruKelasModalName">-</strong>
        </p>

        <form id="guruKelasForm" class="koreksi-form-inner">
            <input type="hidden" name="action" value="save_guru_kelas">
            <input type="hidden" name="guru_id" id="guruKelasGuruIdInput">

            <div>
                <label class="form-label">Wali Kelas</label>
                <select name="wali_kelas_id" id="waliKelasSelect" class="select-theme">
                    <option value="">- Tidak Menjadi Wali -</option>
                </select>
            </div>

            <div>
                <label class="form-label">Kelas yang Diajar</label>
                <div class="kelas-guru-checklist" id="kelasChecklistWrap">
                    <div class="empty-state-theme" style="padding:18px 12px;">Memuat data kelas...</div>
                </div>
            </div>

            <div class="warn-modal-actions">
                <button type="button" class="warn-btn-cancel" onclick="closeGuruKelasModal()">Batal</button>
                <button type="submit" class="warn-btn-confirm" id="guruKelasSubmitBtn">Simpan Relasi</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const listUrl = '<?= BASE_URL; ?>/admin/data_guru_list.php';
    const actionUrl = '<?= BASE_URL; ?>/admin/data_guru_action.php';

    const guruTableBody = document.getElementById('guruTableBody');
    const guruCountText = document.getElementById('guruCountText');
    const guruAlert = document.getElementById('guruAlert');

    const btnTambahGuru = document.getElementById('btnTambahGuru');
    const btnRefreshGuru = document.getElementById('btnRefreshGuru');
    const guruSearchInput = document.getElementById('guruSearchInput');
    const guruStatusFilter = document.getElementById('guruStatusFilter');

    const statTotalGuru = document.getElementById('statTotalGuru');
    const statAkunAktif = document.getElementById('statAkunAktif');
    const statWaliKelas = document.getElementById('statWaliKelas');
    const statRelasiMengajar = document.getElementById('statRelasiMengajar');

    const guruModal = document.getElementById('guruModal');
    const guruModalTitle = document.getElementById('guruModalTitle');
    const guruForm = document.getElementById('guruForm');
    const guruIdInput = document.getElementById('guruIdInput');
    const guruNamaInput = document.getElementById('guruNamaInput');
    const guruUsernameInput = document.getElementById('guruUsernameInput');
    const guruEmailInput = document.getElementById('guruEmailInput');
    const guruPasswordInput = document.getElementById('guruPasswordInput');
    const guruPasswordHint = document.getElementById('guruPasswordHint');
    const guruNipInput = document.getElementById('guruNipInput');
    const guruNoHpInput = document.getElementById('guruNoHpInput');
    const guruAlamatInput = document.getElementById('guruAlamatInput');
    const guruStatusAkunInput = document.getElementById('guruStatusAkunInput');
    const guruSubmitBtn = document.getElementById('guruSubmitBtn');

    const guruKelasModal = document.getElementById('guruKelasModal');
    const guruKelasForm = document.getElementById('guruKelasForm');
    const guruKelasGuruIdInput = document.getElementById('guruKelasGuruIdInput');
    const guruKelasModalName = document.getElementById('guruKelasModalName');
    const waliKelasSelect = document.getElementById('waliKelasSelect');
    const kelasChecklistWrap = document.getElementById('kelasChecklistWrap');
    const guruKelasSubmitBtn = document.getElementById('guruKelasSubmitBtn');

    let guruRows = [];
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
        guruAlert.style.display = 'block';
        guruAlert.className = 'alert-theme ' + (type === 'error' ? 'alert-error-theme' : '');
        guruAlert.innerHTML = escapeHtml(message);

        clearTimeout(showAlert._timer);
        showAlert._timer = setTimeout(() => {
            guruAlert.style.display = 'none';
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
        statTotalGuru.textContent = summary.total_guru || 0;
        statAkunAktif.textContent = summary.akun_aktif || 0;
        statWaliKelas.textContent = summary.total_wali || 0;
        statRelasiMengajar.textContent = summary.total_relasi_pengajar || 0;
    }

    function formatStatusBadge(status) {
        return status === 'aktif'
            ? '<span class="status-pill status-green">Aktif</span>'
            : '<span class="status-pill status-red">Nonaktif</span>';
    }

    function renderTable(rows) {
        if (!rows.length) {
            guruTableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state-theme">Tidak ada data guru.</div>
                    </td>
                </tr>
            `;
            guruCountText.textContent = '0';
            return;
        }

        let html = '';
        rows.forEach((row, index) => {
            const kelasInfo = [];

            if (row.wali_kelas_nama) {
                kelasInfo.push('<span class="kelas-role-badge kelas-role-wali">Wali: ' + escapeHtml(row.wali_kelas_nama) + '</span>');
            }

            (row.pengajar_kelas_list || []).forEach(item => {
                kelasInfo.push('<span class="kelas-role-badge kelas-role-pengajar">Pengajar: ' + escapeHtml(item) + '</span>');
            });

            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>
                        <strong>${escapeHtml(row.nama)}</strong><br>
                        <span class="monitoring-nisn">@${escapeHtml(row.username)}</span>
                    </td>
                    <td>${escapeHtml(row.nip || '-')}</td>
                    <td>${escapeHtml(row.no_hp || '-')}</td>
                    <td>
                        ${kelasInfo.length ? '<div class="kelas-role-wrap">' + kelasInfo.join('') + '</div>' : '<span class="text-muted-soft">Belum ada relasi kelas</span>'}
                    </td>
                    <td>${formatStatusBadge(row.status_akun)}</td>
                    <td>
                        <div class="aksi-btn-group">
                            <button type="button" class="btn-light-theme btn-kelas-aksi" onclick="openEditGuru(${row.id})">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                            <button type="button" class="btn-light-theme btn-kelas-aksi" onclick="openGuruKelasModal(${row.id})">
                                <i class="fa-solid fa-users-gear"></i> Kelas
                            </button>
                            <button type="button" class="btn-light-theme btn-kelas-aksi btn-danger-soft" onclick="deleteGuru(${row.id})">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        guruTableBody.innerHTML = html;
        guruCountText.textContent = rows.length;
    }

    function findGuruById(id) {
        return guruRows.find(item => String(item.id) === String(id)) || null;
    }

    function renderWaliKelasOptions(selectedId = '') {
        let html = '<option value="">- Tidak Menjadi Wali -</option>';

        kelasOptions.forEach(kelas => {
            const selected = String(selectedId) === String(kelas.id) ? 'selected' : '';
            const note = kelas.current_wali_name ? ' (Wali: ' + kelas.current_wali_name + ')' : '';
            html += `<option value="${kelas.id}" ${selected}>${escapeHtml(kelas.nama_kelas)}${escapeHtml(note)}</option>`;
        });

        waliKelasSelect.innerHTML = html;
    }

    function renderPengajarChecklist(selectedIds = [], waliKelasId = '') {
        const selectedSet = new Set((selectedIds || []).map(id => String(id)));
        const waliId = String(waliKelasId || '');

        if (!kelasOptions.length) {
            kelasChecklistWrap.innerHTML = '<div class="empty-state-theme" style="padding:18px 12px;">Belum ada data kelas.</div>';
            return;
        }

        let html = '';
        kelasOptions.forEach(kelas => {
            const checked = selectedSet.has(String(kelas.id)) ? 'checked' : '';
            const disabled = waliId && waliId === String(kelas.id) ? 'disabled' : '';
            const note = waliId && waliId === String(kelas.id)
                ? '<span class="kelas-guru-note">Dipakai sebagai wali kelas guru ini</span>'
                : (kelas.current_wali_name ? '<span class="kelas-guru-note">Wali saat ini: ' + escapeHtml(kelas.current_wali_name) + '</span>' : '');

            html += `
                <label class="kelas-guru-item">
                    <div class="kelas-guru-main">
                        <input type="checkbox" name="pengajar_kelas_ids[]" value="${kelas.id}" ${checked} ${disabled}>
                        <div>
                            <div class="kelas-guru-name">${escapeHtml(kelas.nama_kelas)}</div>
                        </div>
                    </div>
                    ${note}
                </label>
            `;
        });

        kelasChecklistWrap.innerHTML = html;
    }

    async function loadGuruTable() {
        try {
            guruTableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state-theme">Memuat data guru...</div>
                    </td>
                </tr>
            `;

            const url = new URL(listUrl, window.location.origin);
            const q = guruSearchInput.value.trim();
            const status = guruStatusFilter.value;

            if (q) url.searchParams.set('q', q);
            if (status) url.searchParams.set('status', status);

            const res = await fetch(url.toString(), { cache: 'no-store' });
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Gagal memuat data guru.');
            }

            guruRows = data.rows || [];
            kelasOptions = data.kelas_options || [];

            renderStats(data.summary || {});
            renderTable(guruRows);
            renderWaliKelasOptions('');
        } catch (err) {
            guruTableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state-theme">Gagal memuat data guru.</div>
                    </td>
                </tr>
            `;
            showAlert(err.message || 'Terjadi kesalahan saat memuat data guru.', 'error');
        }
    }

    window.openTambahGuru = function () {
        guruModalTitle.textContent = 'Tambah Guru';
        guruForm.reset();
        guruIdInput.value = '';
        guruPasswordInput.required = true;
        guruPasswordHint.textContent = '(wajib saat tambah)';
        guruModal.style.display = 'flex';
        setTimeout(() => guruNamaInput.focus(), 50);
    };

    window.openEditGuru = function (id) {
        const row = findGuruById(id);
        if (!row) return;

        guruModalTitle.textContent = 'Edit Guru';
        guruIdInput.value = row.id;
        guruNamaInput.value = row.nama || '';
        guruUsernameInput.value = row.username || '';
        guruEmailInput.value = row.email || '';
        guruPasswordInput.value = '';
        guruPasswordInput.required = false;
        guruPasswordHint.textContent = '(kosongkan jika tidak diubah)';
        guruNipInput.value = row.nip || '';
        guruNoHpInput.value = row.no_hp || '';
        guruAlamatInput.value = row.alamat || '';
        guruStatusAkunInput.value = row.status_akun || 'aktif';

        guruModal.style.display = 'flex';
        setTimeout(() => guruNamaInput.focus(), 50);
    };

    window.closeGuruModal = function () {
        guruModal.style.display = 'none';
    };

    window.openGuruKelasModal = function (id) {
        const row = findGuruById(id);
        if (!row) return;

        guruKelasGuruIdInput.value = row.id;
        guruKelasModalName.textContent = row.nama || '-';

        renderWaliKelasOptions(row.wali_kelas_id || '');
        renderPengajarChecklist(row.pengajar_kelas_ids || [], row.wali_kelas_id || '');

        waliKelasSelect.onchange = function () {
            renderPengajarChecklist(row.pengajar_kelas_ids || [], this.value || '');
        };

        guruKelasModal.style.display = 'flex';
    };

    window.closeGuruKelasModal = function () {
        guruKelasModal.style.display = 'none';
    };

    window.deleteGuru = async function (id) {
        const row = findGuruById(id);
        if (!row) return;

        const ok = confirm('Hapus guru "' + row.nama + '"? Relasi kelas akan ikut dilepas.');
        if (!ok) return;

        try {
            const formData = new FormData();
            formData.append('action', 'delete_guru');
            formData.append('guru_id', id);

            const res = await fetch(actionUrl, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Gagal menghapus guru.');
            }

            showAlert(data.message || 'Guru berhasil dihapus.');
            loadGuruTable();
        } catch (err) {
            showAlert(err.message || 'Terjadi kesalahan saat menghapus guru.', 'error');
        }
    };

    guruForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        try {
            setButtonLoading(guruSubmitBtn, true);

            const formData = new FormData(guruForm);
            const res = await fetch(actionUrl, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Gagal menyimpan data guru.');
            }

            closeGuruModal();
            showAlert(data.message || 'Data guru berhasil disimpan.');
            loadGuruTable();
        } catch (err) {
            showAlert(err.message || 'Terjadi kesalahan saat menyimpan data guru.', 'error');
        } finally {
            setButtonLoading(guruSubmitBtn, false);
        }
    });

    guruKelasForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        try {
            setButtonLoading(guruKelasSubmitBtn, true, 'Menyimpan...');

            const formData = new FormData(guruKelasForm);
            const res = await fetch(actionUrl, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Gagal menyimpan relasi kelas guru.');
            }

            closeGuruKelasModal();
            showAlert(data.message || 'Relasi kelas guru berhasil diperbarui.');
            loadGuruTable();
        } catch (err) {
            showAlert(err.message || 'Terjadi kesalahan saat menyimpan relasi kelas.', 'error');
        } finally {
            setButtonLoading(guruKelasSubmitBtn, false);
        }
    });

    btnTambahGuru.addEventListener('click', openTambahGuru);
    btnRefreshGuru.addEventListener('click', loadGuruTable);

    guruSearchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadGuruTable, 300);
    });

    guruStatusFilter.addEventListener('change', loadGuruTable);

    guruModal.addEventListener('click', function (e) {
        if (e.target === guruModal) closeGuruModal();
    });

    guruKelasModal.addEventListener('click', function (e) {
        if (e.target === guruKelasModal) closeGuruKelasModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (guruModal.style.display === 'flex') closeGuruModal();
            if (guruKelasModal.style.display === 'flex') closeGuruKelasModal();
        }
    });

    document.addEventListener('DOMContentLoaded', loadGuruTable);
})();
</script>

<?php include '../includes/footer.php'; ?>