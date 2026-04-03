<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

require_role(['guru']);

$pageTitle = 'Scan Absensi';
include '../includes/header.php';
?>

<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-content">
        <?php include '../includes/topbar.php'; ?>

        <div class="content-area scan-page">

            <div class="scan-wrapper">

                <!-- ── CARD SCANNER ── -->
                <div class="scan-card">

                    <!-- Header card -->
                    <div class="scan-card-header">
                        <div class="scan-header-left">
                            <div class="scan-header-icon">
                                <i class="fa-solid fa-qrcode"></i>
                            </div>
                            <div>
                                <div class="scan-header-title">Scan Absensi</div>
                                <div class="scan-header-sub" id="scanHeaderSub">Arahkan kamera ke kartu QR siswa</div>
                            </div>
                        </div>
                        <div class="scan-mode-badge" id="scanModeBadge">
                            <i class="fa-solid fa-circle scan-mode-dot" id="scanModeDot"></i>
                            <span id="scanModeLabel">Siap</span>
                        </div>
                    </div>

                    <!-- Viewport kamera -->
                    <div class="scan-viewport-wrap">
                        <video id="scanVideo" autoplay playsinline muted></video>

                        <!-- Overlay sudut -->
                        <div class="scan-frame">
                            <span class="sf-tl"></span>
                            <span class="sf-tr"></span>
                            <span class="sf-bl"></span>
                            <span class="sf-br"></span>
                        </div>

                        <!-- Garis scan animasi -->
                        <div class="scan-line" id="scanLine"></div>

                        <!-- Overlay state -->
                        <div class="scan-overlay" id="scanOverlay">
                            <div class="scan-overlay-inner" id="scanOverlayInner">
                                <!-- diisi JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Panel hasil -->
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

                    <!-- Pesan error / info -->
                    <div class="scan-msg" id="scanMsg" style="display:none;">
                        <div class="scan-msg-icon" id="scanMsgIcon">
                            <i class="fa-solid fa-xmark"></i>
                        </div>
                        <div class="scan-msg-text">
                            <div class="scan-msg-title" id="scanMsgTitle">-</div>
                            <div class="scan-msg-body" id="scanMsgBody">-</div>
                        </div>
                    </div>

                    <!-- Kontrol kamera -->
                    <div class="scan-controls">
                        <button class="scan-ctrl-btn" id="btnBelakang" data-facing="environment">
                            <i class="fa-solid fa-camera-rotate"></i> Belakang
                        </button>
                        <button class="scan-ctrl-btn" id="btnDepan" data-facing="user">
                            <i class="fa-solid fa-user"></i> Depan
                        </button>
                    </div>

                    <a href="<?= BASE_URL; ?>/guru/dashboard.php" class="scan-back-btn">
                        <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
                    </a>

                </div><!-- /.scan-card -->

                <!-- ── INFO JADWAL ── -->
                <div class="scan-info-card" id="scanInfoCard">
                    <div class="sic-title">
                        <i class="fa-solid fa-clock-rotate-left"></i> Jadwal Hari Ini
                    </div>
                    <div class="sic-body" id="sicBody">
                        <div class="sic-loading">
                            <i class="fa-solid fa-spinner fa-spin"></i> Memuat jadwal...
                        </div>
                    </div>
                </div>

            </div><!-- /.scan-wrapper -->
        </div><!-- /.content-area -->
    </div><!-- /.main-content -->
</div><!-- /.app-layout -->

<!-- jsQR library -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
    (function() {
        'use strict';

        /* ── Elemen DOM ── */
        const video = document.getElementById('scanVideo');
        const scanLine = document.getElementById('scanLine');
        const scanOverlay = document.getElementById('scanOverlay');
        const scanOverlayIn = document.getElementById('scanOverlayInner');
        const resultPanel = document.getElementById('scanResultPanel');
        const msgPanel = document.getElementById('scanMsg');
        const scanModeBadge = document.getElementById('scanModeBadge');
        const scanModeLabel = document.getElementById('scanModeLabel');
        const scanModeDot = document.getElementById('scanModeDot');
        const scanHeaderSub = document.getElementById('scanHeaderSub');

        const srpIconWrap = document.getElementById('srpIconWrap');
        const srpIcon = document.getElementById('srpIcon');
        const srpNama = document.getElementById('srpNama');
        const srpKelas = document.getElementById('srpKelas');
        const srpMode = document.getElementById('srpMode');
        const srpStatus = document.getElementById('srpStatus');
        const srpJam = document.getElementById('srpJam');

        const msgIcon = document.getElementById('scanMsgIcon');
        const msgTitle = document.getElementById('scanMsgTitle');
        const msgBody = document.getElementById('scanMsgBody');

        const btnBelakang = document.getElementById('btnBelakang');
        const btnDepan = document.getElementById('btnDepan');
        const sicBody = document.getElementById('sicBody');

        /* ── State ── */
        let currentStream = null;
        let currentFacing = 'environment'; // default kamera belakang
        let animFrameId = null;
        let isProcessing = false;
        let lastCode = '';
        let lastCodeTime = 0;
        const DEBOUNCE_MS = 3000; // cooldown per kode

        /* ── Canvas tersembunyi untuk decode ── */
        const canvas = document.createElement('canvas');
        const ctx2d = canvas.getContext('2d');

        /* ══════════════════════════════════════
           KAMERA
        ══════════════════════════════════════ */
        async function startCamera(facing) {
            stopCamera();
            currentFacing = facing;

            // Update tombol aktif
            btnBelakang.classList.toggle('active', facing === 'environment');
            btnDepan.classList.toggle('active', facing === 'user');

            setOverlayState('loading');

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: facing,
                        width: {
                            ideal: 1280
                        },
                        height: {
                            ideal: 720
                        }
                    },
                    audio: false,
                });
                currentStream = stream;
                video.srcObject = stream;
                await video.play();
                setOverlayState('idle');
                requestAnimationFrame(scanFrame);
            } catch (err) {
                setOverlayState('error', 'Kamera tidak dapat diakses', err.message);
            }
        }

        function stopCamera() {
            if (animFrameId) {
                cancelAnimationFrame(animFrameId);
                animFrameId = null;
            }
            if (currentStream) {
                currentStream.getTracks().forEach(t => t.stop());
                currentStream = null;
            }
            video.srcObject = null;
        }

        /* ══════════════════════════════════════
           SCAN LOOP
        ══════════════════════════════════════ */
        function scanFrame() {
            animFrameId = requestAnimationFrame(scanFrame);

            if (video.readyState < video.HAVE_ENOUGH_DATA) return;
            if (isProcessing) return;

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx2d.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = ctx2d.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: 'dontInvert',
            });

            if (code && code.data) {
                const now = Date.now();
                if (code.data === lastCode && (now - lastCodeTime) < DEBOUNCE_MS) return;
                lastCode = code.data;
                lastCodeTime = now;
                processCode(code.data);
            }
        }

        /* ══════════════════════════════════════
           PROSES KODE
        ══════════════════════════════════════ */
        async function processCode(kode) {
            isProcessing = true;
            setOverlayState('processing');
            hideResult();
            hideMsg();

            try {
                const formData = new FormData();
                formData.append('kode_kartu', kode);

                const res = await fetch('<?= BASE_URL; ?>/guru/scan_process.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await res.json();

                if (data.success) {
                    showSuccess(data);
                } else {
                    showError(data);
                }
            } catch (err) {
                showMsg('error', 'Koneksi Gagal', 'Tidak dapat menghubungi server. Periksa koneksi internet.');
            } finally {
                isProcessing = false;
                // Setelah 3 detik kembali ke idle dan siap scan lagi
                setTimeout(() => {
                    setOverlayState('idle');
                    lastCode = ''; // reset agar kode yang sama bisa scan ulang
                }, 3000);
            }
        }

        /* ══════════════════════════════════════
           SHOW RESULT & MSG
        ══════════════════════════════════════ */
        function showSuccess(data) {
            hideMsg();
            resultPanel.style.display = 'flex';

            const isMasuk = data.mode === 'masuk';
            const isTerlambat = data.statusMasuk === 'Terlambat';

            // Icon & warna
            srpIconWrap.className = 'srp-icon-wrap ' + (isMasuk ?
                (isTerlambat ? 'srp-warn' : 'srp-ok') :
                'srp-pulang');
            srpIcon.className = 'fa-solid ' + (isTerlambat ? 'fa-clock' : 'fa-circle-check');

            srpNama.textContent = data.siswa.nama;
            srpKelas.textContent = data.siswa.kelas;

            srpMode.textContent = data.labelMode;
            srpMode.className = 'srp-mode ' + (isMasuk ? 'mode-masuk' : 'mode-pulang');

            srpStatus.textContent = data.labelStatus;
            srpStatus.className = 'srp-status ' +
                (isTerlambat ? 'status-terlambat' : (isMasuk ? 'status-hadir' : 'status-pulang'));

            srpJam.textContent = 'Pukul ' + data.jam;

            // Mode badge header
            setBadge(isTerlambat ? 'warn' : 'ok',
                isMasuk ? (isTerlambat ? 'Terlambat' : 'Hadir') : 'Pulang');
            scanHeaderSub.textContent = 'Siap untuk siswa berikutnya...';

            setOverlayState('success', data.siswa.nama, data.labelStatus);
        }

        function showError(data) {
            hideResult();
            const code = data.code || '';

            const cfg = {
                LIBUR: {
                    type: 'warn',
                    title: 'Absensi Ditutup',
                    icon: 'fa-calendar-xmark'
                },
                NOT_FOUND: {
                    type: 'error',
                    title: 'Kartu Tidak Dikenali',
                    icon: 'fa-id-card'
                },
                FORBIDDEN: {
                    type: 'error',
                    title: 'Akses Ditolak',
                    icon: 'fa-ban'
                },
                TERLALU_AWAL: {
                    type: 'info',
                    title: 'Belum Waktunya',
                    icon: 'fa-hourglass-start'
                },
                DILUAR_WINDOW: {
                    type: 'warn',
                    title: 'Di Luar Jam Absensi',
                    icon: 'fa-clock'
                },
                BELUM_MASUK: {
                    type: 'warn',
                    title: 'Belum Absen Masuk',
                    icon: 'fa-triangle-exclamation'
                },
                SUDAH_MASUK: {
                    type: 'info',
                    title: 'Sudah Absen Masuk',
                    icon: 'fa-circle-check'
                },
                SUDAH_PULANG: {
                    type: 'info',
                    title: 'Sudah Absen Pulang',
                    icon: 'fa-circle-check'
                },
                SUDAH_LENGKAP: {
                    type: 'info',
                    title: 'Absensi Sudah Lengkap',
                    icon: 'fa-circle-check'
                },
                INVALID: {
                    type: 'error',
                    title: 'Kode Tidak Valid',
                    icon: 'fa-triangle-exclamation'
                },
                NO_GURU: {
                    type: 'error',
                    title: 'Data Guru Tidak Ditemukan',
                    icon: 'fa-user-xmark'
                },
                DATA_TIDAK_KONSISTEN: {
                    type: 'error',
                    title: 'Data Absensi Tidak Konsisten',
                    icon: 'fa-triangle-exclamation'
                },
            };

            const c = cfg[code] || {
                type: 'error',
                title: 'Gagal',
                icon: 'fa-xmark'
            };
            showMsg(c.type, c.title, data.message, c.icon);
            setBadge('idle', 'Siap');
            scanHeaderSub.textContent = 'Arahkan kamera ke kartu QR siswa';
            setOverlayState('fail');
        }

        function showMsg(type, title, body, iconClass) {
            msgPanel.style.display = 'flex';
            msgPanel.className = 'scan-msg scan-msg-' + type;
            msgIcon.innerHTML = '<i class="fa-solid ' + (iconClass || 'fa-xmark') + '"></i>';
            msgTitle.textContent = title;
            msgBody.textContent = body;
        }

        function hideResult() {
            resultPanel.style.display = 'none';
        }

        function hideMsg() {
            msgPanel.style.display = 'none';
        }

        /* ══════════════════════════════════════
           OVERLAY STATE
        ══════════════════════════════════════ */
        function setOverlayState(state, arg1, arg2) {
            scanLine.style.display = 'none';
            scanOverlay.style.display = 'none';

            switch (state) {
                case 'idle':
                    scanLine.style.display = 'block';
                    setBadge('scanning', 'Scanning');
                    scanHeaderSub.textContent = 'Arahkan kamera ke kartu QR siswa';
                    break;

                case 'loading':
                    scanOverlay.style.display = 'flex';
                    scanOverlayIn.innerHTML = `
                <div class="sov-loading">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <span>Membuka kamera...</span>
                </div>`;
                    setBadge('idle', 'Siap');
                    break;

                case 'processing':
                    scanOverlay.style.display = 'flex';
                    scanOverlayIn.innerHTML = `
                <div class="sov-processing">
                    <div class="sov-spinner"></div>
                    <span>Memproses...</span>
                </div>`;
                    setBadge('processing', 'Memproses');
                    scanHeaderSub.textContent = 'Sedang memproses data siswa...';
                    break;

                case 'success':
                    scanOverlay.style.display = 'flex';
                    scanOverlayIn.innerHTML = `
                <div class="sov-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>${escHtml(arg1 || '')}</span>
                    <small>${escHtml(arg2 || '')}</small>
                </div>`;
                    break;

                case 'fail':
                    scanOverlay.style.display = 'flex';
                    scanOverlayIn.innerHTML = `
                <div class="sov-fail">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Scan Gagal</span>
                </div>`;
                    setTimeout(() => setOverlayState('idle'), 2000);
                    break;

                case 'error':
                    scanOverlay.style.display = 'flex';
                    scanOverlayIn.innerHTML = `
                <div class="sov-error">
                    <i class="fa-solid fa-camera-slash"></i>
                    <span>${escHtml(arg1 || 'Error')}</span>
                    <small>${escHtml(arg2 || '')}</small>
                </div>`;
                    setBadge('idle', 'Error');
                    break;
            }
        }

        /* ══════════════════════════════════════
           BADGE MODE
        ══════════════════════════════════════ */
        function setBadge(type, label) {
            const map = {
                idle: 'badge-idle',
                scanning: 'badge-scanning',
                processing: 'badge-processing',
                ok: 'badge-ok',
                warn: 'badge-warn',
            };
            scanModeBadge.className = 'scan-mode-badge ' + (map[type] || 'badge-idle');
            scanModeLabel.textContent = label;
        }

        /* ══════════════════════════════════════
           INFO JADWAL (async)
        ══════════════════════════════════════ */
        async function loadJadwal() {
            try {
                const hariMap = {
                    0: 'Minggu',
                    1: 'Senin',
                    2: 'Selasa',
                    3: 'Rabu',
                    4: 'Kamis',
                    5: 'Jumat',
                    6: 'Sabtu'
                };
                const hari = hariMap[new Date().getDay()];

                // Kita ambil dari dashboard_data karena tidak ada endpoint jadwal sendiri
                // Cukup tampilkan info jam dari jadwal yang sudah kita tahu ada di DB
                // Sederhananya: fetch dashboard_data untuk mendapat info hari ini
                const res = await fetch('<?= BASE_URL; ?>/guru/dashboard_data.php?tanggal=' +
                    new Date().toISOString().split('T')[0]);
                const data = await res.json();

                if (!data.success) {
                    sicBody.innerHTML = `<div class="sic-empty">Tidak ada jadwal aktif hari ini.</div>`;
                    return;
                }

                // Jadwal diambil di server scan_process; di sini tampilkan konteks saja
                sicBody.innerHTML = `
            <div class="sic-row">
                <span class="sic-lbl">Tanggal</span>
                <span class="sic-val">${data.labelTanggal}</span>
            </div>
            <div class="sic-row">
                <span class="sic-lbl">Kelas Diampu</span>
                <span class="sic-val">${data.daftarKelas.map(k =>
                    k.nama_kelas + (k.role_guru_kelas === 'wali' ? ' <em>(WK)</em>' : '')
                ).join(', ')}</span>
            </div>
            <div class="sic-divider"></div>
            <div class="sic-row">
                <span class="sic-lbl">Total Siswa</span>
                <span class="sic-val sic-val-bold">${data.stats.totalSiswa} siswa</span>
            </div>
            <div class="sic-row">
                <span class="sic-lbl">Sudah Hadir</span>
                <span class="sic-val sic-green">${data.stats.hadir + data.stats.terlambat}</span>
            </div>
            <div class="sic-row">
                <span class="sic-lbl">Belum Absen</span>
                <span class="sic-val sic-muted">${data.stats.belumAbsen}</span>
            </div>`;
            } catch (e) {
                sicBody.innerHTML = `<div class="sic-empty">Gagal memuat info jadwal.</div>`;
            }
        }

        /* ══════════════════════════════════════
           HELPERS
        ══════════════════════════════════════ */
        function escHtml(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        /* ══════════════════════════════════════
           EVENT LISTENERS
        ══════════════════════════════════════ */
        btnBelakang.addEventListener('click', () => startCamera('environment'));
        btnDepan.addEventListener('click', () => startCamera('user'));

        // Bersihkan kamera saat tinggalkan halaman
        window.addEventListener('beforeunload', stopCamera);

        /* ══════════════════════════════════════
           INIT
        ══════════════════════════════════════ */
        document.addEventListener('DOMContentLoaded', function() {
            startCamera('environment');
            loadJadwal();
        });

    })();
</script>

<?php include '../includes/footer.php'; ?>