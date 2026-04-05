<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['admin']);

$pageTitle = 'Data Siswa';
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
                    <h1>Data Siswa</h1>
                    <p>Kelola akun siswa, biodata, kelas, dan kode kartu.</p>
                </div>

                <div class="guru-filter-actions">
                    <button type="button" class="btn-primary-theme" id="btnTambahSiswa">
                        <i class="fa-solid fa-plus"></i>
                        <span>Tambah Siswa</span>
                    </button>
                </div>
            </div>

            <div class="guru-ref-stats data-siswa-stats">
                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-purple-soft">
                        <i class="fa-solid fa-user-graduate"></i>
                    </div>
                    <div class="guru-ref-label">TOTAL SISWA</div>
                    <div class="guru-ref-value" id="statTotalSiswa">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="guru-ref-label">AKUN AKTIF</div>
                    <div class="guru-ref-value" id="statAkunAktifSiswa">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-blue-soft">
                        <i class="fa-solid fa-school"></i>
                    </div>
                    <div class="guru-ref-label">KELAS TERPAKAI</div>
                    <div class="guru-ref-value" id="statKelasTerpakai">0</div>
                </div>

                <div class="guru-ref-card">
                    <div class="guru-ref-icon icon-yellow-soft">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <div class="guru-ref-label">KARTU AKTIF</div>
                    <div class="guru-ref-value" id="statKartuAktif">0</div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <div>
                        <div class="table-title">Daftar Siswa</div>
                        <div class="monitoring-sub-label">Tabel otomatis diperbarui setelah aksi berhasil.</div>
                    </div>

                    <div class="table-tools" style="flex-wrap:wrap;gap:8px;">
                        <select id="siswaKelasFilter" class="select-theme">
                            <option value="">Semua Kelas</option>
                        </select>
                        <select id="siswaStatusFilter" class="select-theme">
                            <option value="">Semua Status</option>
                            <option value="aktif">Siswa Aktif</option>
                            <option value="nonaktif">Siswa Nonaktif</option>
                        </select>
                        <select id="akunStatusFilter" class="select-theme">
                            <option value="">Semua Akun</option>
                            <option value="aktif">Akun Aktif</option>
                            <option value="nonaktif">Akun Nonaktif</option>
                        </select>
                        <input type="text" id="siswaSearchInput" class="input-theme search-theme" placeholder="Cari nama / NIS / NISN / username...">
                        <button type="button" class="btn-light-theme" id="btnRefreshSiswa" title="Refresh">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                </div>

                <div id="siswaAlert" class="alert-theme" style="display:none; margin-bottom:14px;"></div>

                <div class="table-responsive-theme">
                    <table class="theme-table monitoring-table">
                        <thead>
                            <tr>
                                <th style="width:70px;">No</th>
                                <th>Siswa</th>
                                <th>NIS / NISN</th>
                                <th>Kelas</th>
                                <th>Kode Kartu</th>
                                <th>Status</th>
                                <th style="width:260px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="siswaTableBody">
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state-theme">Memuat data siswa...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="monitoring-footer-note">
                    Menampilkan <strong id="siswaCountText">0</strong> siswa
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal tambah / edit siswa -->
<div id="siswaModal" class="warn-modal-overlay" style="display:none;">
    <div class="warn-modal-box koreksi-modal-box siswa-modal-box">
        <div class="warn-modal-icon">
            <i class="fa-solid fa-user-graduate"></i>
        </div>

        <h3 class="warn-modal-title" id="siswaModalTitle">Tambah Siswa</h3>
        <p class="warn-modal-body">Isi akun dan biodata siswa.</p>
        <div id="siswaModalAlert" class="alert-theme" style="display:none; margin-bottom:14px;"></div>

        <form id="siswaForm" class="koreksi-form-inner">
            <input type="hidden" name="action" value="save_siswa">
            <input type="hidden" name="siswa_id" id="siswaIdInput">

            <div class="siswa-form-grid">
                <div>
                    <label class="form-label">Nama</label>
                    <input type="text" name="nama" id="siswaNamaInput" class="input-theme" required maxlength="100">
                </div>

                <div>
                    <label class="form-label">Username</label>
                    <input type="text" name="username" id="siswaUsernameInput" class="input-theme" required maxlength="50">
                </div>

                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="siswaEmailInput" class="input-theme" maxlength="100">
                </div>

                <div>
                    <label class="form-label">Password <span class="text-muted-soft" id="siswaPasswordHint">(wajib saat tambah)</span></label>
                    <input type="password" name="password" id="siswaPasswordInput" class="input-theme" maxlength="100">
                </div>

                <div>
                    <label class="form-label">NIS</label>
                    <input type="text" name="nis" id="siswaNisInput" class="input-theme" required maxlength="30">
                </div>

                <div>
                    <label class="form-label">NISN</label>
                    <input type="text" name="nisn" id="siswaNisnInput" class="input-theme" required maxlength="30">
                </div>

                <div>
                    <label class="form-label">Kelas</label>
                    <select name="kelas_id" id="siswaKelasSelect" class="select-theme" required>
                        <option value="">- Pilih Kelas -</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">Jenis Kelamin</label>
                    <select name="jenis_kelamin" id="siswaJenisKelaminInput" class="select-theme">
                        <option value="">- Pilih -</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>

                <div class="siswa-form-full">
                    <label class="form-label">Kode Kartu</label>
                    <div class="kode-kartu-wrap">
                        <input type="text" name="kode_kartu" id="siswaKodeKartuInput" class="input-theme" required maxlength="100" placeholder="Contoh: CARD-2026004">
                        <button type="button" class="btn-light-theme btn-kode-generate" id="btnGenerateKodeKartu">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
                        </button>
                    </div>
                </div>

                <div>
                    <label class="form-label">No HP Ortu</label>
                    <input type="text" name="no_hp_ortu" id="siswaNoHpOrtuInput" class="input-theme" maxlength="20">
                </div>

                <div>
                    <label class="form-label">Status Siswa</label>
                    <select name="status_siswa" id="siswaStatusInput" class="select-theme">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>

                <div>
                    <label class="form-label">Status Akun</label>
                    <select name="status_akun" id="siswaStatusAkunInput" class="select-theme">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>

                <div class="siswa-form-full">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" id="siswaAlamatInput" class="input-theme koreksi-textarea" rows="3"></textarea>
                </div>

                <div class="siswa-form-full">
                    <label class="form-label">Foto Siswa</label>

                    <div class="siswa-foto-upload-wrap">
                        <div class="siswa-foto-preview" id="siswaFotoPreview">
                            <i class="fa-regular fa-user"></i>
                        </div>

                        <div class="siswa-foto-actions">
                            <input type="file"
                                name="foto"
                                id="siswaFotoInput"
                                class="input-theme"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">

                            <label class="siswa-foto-remove">
                                <input type="checkbox" name="hapus_foto" id="siswaHapusFotoInput" value="1">
                                Hapus foto lama
                            </label>

                            <small class="text-muted-soft">
                                Format: JPG, JPEG, PNG, WEBP. Maksimal 2 MB.
                            </small>
                        </div>
                    </div>
                </div>

            </div>

            <div class="warn-modal-actions">
                <button type="button" class="warn-btn-cancel" onclick="closeSiswaModal()">Batal</button>
                <button type="submit" class="warn-btn-confirm" id="siswaSubmitBtn">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const listUrl = '<?= BASE_URL; ?>/admin/data_siswa_list.php';
        const actionUrl = '<?= BASE_URL; ?>/admin/data_siswa_action.php';

        const siswaTableBody = document.getElementById('siswaTableBody');
        const siswaCountText = document.getElementById('siswaCountText');
        const siswaAlert = document.getElementById('siswaAlert');

        const btnTambahSiswa = document.getElementById('btnTambahSiswa');
        const btnRefreshSiswa = document.getElementById('btnRefreshSiswa');
        const siswaSearchInput = document.getElementById('siswaSearchInput');
        const siswaKelasFilter = document.getElementById('siswaKelasFilter');
        const siswaStatusFilter = document.getElementById('siswaStatusFilter');
        const akunStatusFilter = document.getElementById('akunStatusFilter');

        const statTotalSiswa = document.getElementById('statTotalSiswa');
        const statAkunAktifSiswa = document.getElementById('statAkunAktifSiswa');
        const statKelasTerpakai = document.getElementById('statKelasTerpakai');
        const statKartuAktif = document.getElementById('statKartuAktif');

        const siswaModal = document.getElementById('siswaModal');
        const siswaModalTitle = document.getElementById('siswaModalTitle');
        const siswaForm = document.getElementById('siswaForm');
        const siswaIdInput = document.getElementById('siswaIdInput');
        const siswaNamaInput = document.getElementById('siswaNamaInput');
        const siswaUsernameInput = document.getElementById('siswaUsernameInput');
        const siswaEmailInput = document.getElementById('siswaEmailInput');
        const siswaPasswordInput = document.getElementById('siswaPasswordInput');
        const siswaPasswordHint = document.getElementById('siswaPasswordHint');
        const siswaNisInput = document.getElementById('siswaNisInput');
        const siswaNisnInput = document.getElementById('siswaNisnInput');
        const siswaKelasSelect = document.getElementById('siswaKelasSelect');
        const siswaJenisKelaminInput = document.getElementById('siswaJenisKelaminInput');
        const siswaKodeKartuInput = document.getElementById('siswaKodeKartuInput');
        const btnGenerateKodeKartu = document.getElementById('btnGenerateKodeKartu');
        const siswaNoHpOrtuInput = document.getElementById('siswaNoHpOrtuInput');
        const siswaStatusInput = document.getElementById('siswaStatusInput');
        const siswaStatusAkunInput = document.getElementById('siswaStatusAkunInput');
        const siswaAlamatInput = document.getElementById('siswaAlamatInput');
        const siswaSubmitBtn = document.getElementById('siswaSubmitBtn');

        const siswaFotoInput = document.getElementById('siswaFotoInput');
        const siswaFotoPreview = document.getElementById('siswaFotoPreview');
        const siswaHapusFotoInput = document.getElementById('siswaHapusFotoInput');

        let siswaRows = [];
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

        const siswaModalAlert = document.getElementById('siswaModalAlert');

        function showAlert(message, type = 'success', target = 'page') {
            const el = target === 'modal' ? siswaModalAlert : siswaAlert;

            if (!el) return;

            el.style.display = 'block';
            el.className = 'alert-theme ' + (type === 'error' ? 'alert-error-theme' : 'alert-success-theme');
            el.innerHTML = escapeHtml(message);

            clearTimeout(el._timer);
            el._timer = setTimeout(() => {
                el.style.display = 'none';
            }, 3500);
        }

        function hideModalAlert() {
            if (siswaModalAlert) {
                siswaModalAlert.style.display = 'none';
                siswaModalAlert.innerHTML = '';
                siswaModalAlert.className = 'alert-theme';
            }
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
            statTotalSiswa.textContent = summary.total_siswa || 0;
            statAkunAktifSiswa.textContent = summary.akun_aktif || 0;
            statKelasTerpakai.textContent = summary.kelas_terpakai || 0;
            statKartuAktif.textContent = summary.kartu_aktif || 0;
        }

        function renderKelasOptions(selectedId = '') {
            let html = '<option value="">- Pilih Kelas -</option>';
            kelasOptions.forEach(kelas => {
                const selected = String(selectedId) === String(kelas.id) ? 'selected' : '';
                html += `<option value="${kelas.id}" ${selected}>${escapeHtml(kelas.nama_kelas)}</option>`;
            });
            siswaKelasSelect.innerHTML = html;

            let filterHtml = '<option value="">Semua Kelas</option>';
            kelasOptions.forEach(kelas => {
                const selected = String(siswaKelasFilter.value) === String(kelas.id) ? 'selected' : '';
                filterHtml += `<option value="${kelas.id}" ${selected}>${escapeHtml(kelas.nama_kelas)}</option>`;
            });
            siswaKelasFilter.innerHTML = filterHtml;
        }

        function statusBadge(status, type) {
            if (type === 'akun') {
                return status === 'aktif' ?
                    '<span class="status-pill status-green">Akun Aktif</span>' :
                    '<span class="status-pill status-red">Akun Nonaktif</span>';
            }
            return status === 'aktif' ?
                '<span class="status-pill status-green">Aktif</span>' :
                '<span class="status-pill status-red">Nonaktif</span>';
        }

        function renderTable(rows) {
            if (!rows.length) {
                siswaTableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state-theme">Tidak ada data siswa.</div>
                    </td>
                </tr>
            `;
                siswaCountText.textContent = '0';
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
                    <td>${escapeHtml(row.nama_kelas || '-')}</td>
                    <td><code class="siswa-kartu-code">${escapeHtml(row.kode_kartu || '-')}</code></td>
                    <td>
                        <div class="kelas-role-wrap">
                            ${statusBadge(row.status_siswa, 'siswa')}
                            ${statusBadge(row.status_akun, 'akun')}
                        </div>
                    </td>
                    <td>
                        <div class="aksi-btn-group">
                            <button type="button" class="btn-light-theme btn-kelas-aksi" onclick="openEditSiswa(${row.id})">
                                <i class="fa-solid fa-pen"></i> Edit
                            </button>
                            <button type="button" class="btn-light-theme btn-kelas-aksi btn-danger-soft" onclick="deleteSiswa(${row.id})">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            });

            siswaTableBody.innerHTML = html;
            siswaCountText.textContent = rows.length;
        }

        function findSiswaById(id) {
            return siswaRows.find(item => String(item.id) === String(id)) || null;
        }

        async function loadSiswaTable() {
            try {
                siswaTableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state-theme">Memuat data siswa...</div>
                    </td>
                </tr>
            `;

                const url = new URL(listUrl, window.location.origin);
                const q = siswaSearchInput.value.trim();
                const kelasId = siswaKelasFilter.value;
                const statusSiswa = siswaStatusFilter.value;
                const statusAkun = akunStatusFilter.value;

                if (q) url.searchParams.set('q', q);
                if (kelasId) url.searchParams.set('kelas_id', kelasId);
                if (statusSiswa) url.searchParams.set('status_siswa', statusSiswa);
                if (statusAkun) url.searchParams.set('status_akun', statusAkun);

                const res = await fetch(url.toString(), {
                    cache: 'no-store'
                });
                const data = await res.json();

                if (!data.success) {
                    throw new Error(data.message || 'Gagal memuat data siswa.');
                }

                siswaRows = data.rows || [];
                kelasOptions = data.kelas_options || [];

                renderStats(data.summary || {});
                renderKelasOptions(siswaKelasFilter.value || '');
                renderTable(siswaRows);
            } catch (err) {
                siswaTableBody.innerHTML = `
                <tr>
                    <td colspan="7">
                        <div class="empty-state-theme">Gagal memuat data siswa.</div>
                    </td>
                </tr>
            `;
                showAlert(err.message || 'Terjadi kesalahan saat memuat data siswa.', 'error');
            }
        }

        function generateKodeKartu() {
            const nis = siswaNisInput.value.trim();
            if (nis) return 'CARD-' + nis;
            return 'CARD-' + Date.now();
        }

        window.openTambahSiswa = function() {
            siswaModalTitle.textContent = 'Tambah Siswa';
            siswaForm.reset();
            siswaIdInput.value = '';
            siswaPasswordInput.required = true;
            siswaPasswordHint.textContent = '(wajib saat tambah)';
            siswaFotoInput.value = '';
            siswaHapusFotoInput.checked = false;
            siswaFotoPreview.innerHTML = '<i class="fa-regular fa-user"></i>';
            renderKelasOptions('');
            siswaModal.style.display = 'flex';
            hideModalAlert();
            setTimeout(() => siswaNamaInput.focus(), 50);
        };

        window.openEditSiswa = function(id) {
            const row = findSiswaById(id);
            if (!row) return;

            siswaModalTitle.textContent = 'Edit Siswa';
            siswaIdInput.value = row.id;
            siswaNamaInput.value = row.nama || '';
            siswaUsernameInput.value = row.username || '';
            siswaEmailInput.value = row.email || '';
            siswaPasswordInput.value = '';
            siswaPasswordInput.required = false;
            siswaPasswordHint.textContent = '(kosongkan jika tidak diubah)';
            siswaFotoInput.value = '';
            siswaHapusFotoInput.checked = false;
            hideModalAlert();

            if (row.foto) {
                siswaFotoPreview.innerHTML = `
        <img src="<?= BASE_URL; ?>/upload/siswa/${encodeURIComponent(row.foto)}"
             alt="Foto Siswa"
             class="siswa-foto-preview-img">
    `;
            } else {
                siswaFotoPreview.innerHTML = '<i class="fa-regular fa-user"></i>';
            }
            siswaNisInput.value = row.nis || '';
            siswaNisnInput.value = row.nisn || '';
            renderKelasOptions(row.kelas_id || '');
            siswaJenisKelaminInput.value = row.jenis_kelamin || '';
            siswaKodeKartuInput.value = row.kode_kartu || '';
            siswaNoHpOrtuInput.value = row.no_hp_ortu || '';
            siswaStatusInput.value = row.status_siswa || 'aktif';
            siswaStatusAkunInput.value = row.status_akun || 'aktif';
            siswaAlamatInput.value = row.alamat || '';

            siswaModal.style.display = 'flex';
            setTimeout(() => siswaNamaInput.focus(), 50);
        };

        window.closeSiswaModal = function() {
            hideModalAlert();
            siswaModal.style.display = 'none';
        };

        window.deleteSiswa = async function(id) {
            const row = findSiswaById(id);
            if (!row) return;

            const ok = confirm(
                'Hapus siswa "' + row.nama + '"?\n\n' +
                'Data absensi dan izin yang terkait juga bisa ikut terhapus karena relasi database.'
            );
            if (!ok) return;

            try {
                const formData = new FormData();
                formData.append('action', 'delete_siswa');
                formData.append('siswa_id', id);

                const res = await fetch(actionUrl, {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (!data.success) {
                    throw new Error(data.message || 'Gagal menghapus siswa.');
                }

                showAlert(data.message || 'Siswa berhasil dihapus.');
                loadSiswaTable();
            } catch (err) {
                showAlert(err.message || 'Terjadi kesalahan saat menghapus siswa.', 'error');
            }
        };

        btnGenerateKodeKartu.addEventListener('click', function() {
            siswaKodeKartuInput.value = generateKodeKartu();
        });

        siswaForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            try {
                setButtonLoading(siswaSubmitBtn, true);
                hideModalAlert();

                const formData = new FormData(siswaForm);
                const res = await fetch(actionUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();

                if (!data.success) {
                    showAlert(data.message || 'Gagal menyimpan data siswa.', 'error', 'modal');
                    return;
                }

                closeSiswaModal();
                showAlert(data.message || 'Data siswa berhasil disimpan.', 'success', 'page');
                loadSiswaTable();
            } catch (err) {
                showAlert(err.message || 'Terjadi kesalahan saat menyimpan data siswa.', 'error', 'modal');
            } finally {
                setButtonLoading(siswaSubmitBtn, false);
            }
        });

        siswaFotoInput.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                siswaFotoPreview.innerHTML = `
            <img src="${e.target.result}"
                 alt="Preview Foto"
                 class="siswa-foto-preview-img">
        `;
            };
            reader.readAsDataURL(file);

            siswaHapusFotoInput.checked = false;
        });

        btnTambahSiswa.addEventListener('click', openTambahSiswa);
        btnRefreshSiswa.addEventListener('click', loadSiswaTable);

        siswaSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadSiswaTable, 300);
        });

        siswaKelasFilter.addEventListener('change', loadSiswaTable);
        siswaStatusFilter.addEventListener('change', loadSiswaTable);
        akunStatusFilter.addEventListener('change', loadSiswaTable);

        siswaModal.addEventListener('click', function(e) {
            if (e.target === siswaModal) closeSiswaModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && siswaModal.style.display === 'flex') {
                closeSiswaModal();
            }
        });

        document.addEventListener('DOMContentLoaded', loadSiswaTable);
    })();
</script>

<?php include '../includes/footer.php'; ?>