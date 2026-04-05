(function () {
    window.initScanPage = function (config) {
        const video = document.getElementById('scanVideo');
        const scanLine = document.getElementById('scanLine');
        const scanOverlay = document.getElementById('scanOverlay');
        const scanOverlayIn = document.getElementById('scanOverlayInner');
        const resultPanel = document.getElementById('scanResultPanel');
        const msgPanel = document.getElementById('scanMsg');
        const scanModeBadge = document.getElementById('scanModeBadge');
        const scanModeLabel = document.getElementById('scanModeLabel');
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

        let currentStream = null;
        let animFrameId = null;
        let isProcessing = false;
        let lastCode = '';
        let lastCodeTime = 0;
        const DEBOUNCE_MS = 3000;

        const canvas = document.createElement('canvas');
        const ctx2d = canvas.getContext('2d');

        async function startCamera(facing) {
            stopCamera();

            btnBelakang.classList.toggle('active', facing === 'environment');
            btnDepan.classList.toggle('active', facing === 'user');

            setOverlayState('loading');

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: facing,
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
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
                currentStream.getTracks().forEach(track => track.stop());
                currentStream = null;
            }
            video.srcObject = null;
        }

        function scanFrame() {
            animFrameId = requestAnimationFrame(scanFrame);

            if (video.readyState < video.HAVE_ENOUGH_DATA) return;
            if (isProcessing) return;

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx2d.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = ctx2d.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height, {
                inversionAttempts: 'dontInvert'
            });

            if (code && code.data) {
                const now = Date.now();
                if (code.data === lastCode && (now - lastCodeTime) < DEBOUNCE_MS) return;
                lastCode = code.data;
                lastCodeTime = now;
                processCode(code.data);
            }
        }

        async function processCode(kode) {
            isProcessing = true;
            setOverlayState('processing');
            hideResult();
            hideMsg();

            try {
                const formData = new FormData();
                formData.append('kode_kartu', kode);

                const res = await fetch(config.processUrl, {
                    method: 'POST',
                    body: formData
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
                setTimeout(() => {
                    setOverlayState('idle');
                    lastCode = '';
                }, 3000);
            }
        }

        function showSuccess(data) {
            hideMsg();
            resultPanel.style.display = 'flex';

            const isMasuk = data.mode === 'masuk';
            const isTerlambat = data.statusMasuk === 'Terlambat';

            srpIconWrap.className = 'srp-icon-wrap ' + (isMasuk ? (isTerlambat ? 'srp-warn' : 'srp-ok') : 'srp-pulang');
            srpIcon.className = 'fa-solid ' + (isTerlambat ? 'fa-clock' : 'fa-circle-check');

            srpNama.textContent = data.siswa.nama;
            srpKelas.textContent = data.siswa.kelas;
            srpMode.textContent = data.labelMode;
            srpMode.className = 'srp-mode ' + (isMasuk ? 'mode-masuk' : 'mode-pulang');

            srpStatus.textContent = data.labelStatus;
            srpStatus.className = 'srp-status ' + (
                isTerlambat ? 'status-terlambat' : (isMasuk ? 'status-hadir' : 'status-pulang')
            );

            srpJam.textContent = 'Pukul ' + data.jam;

            setBadge(isTerlambat ? 'warn' : 'ok', isMasuk ? (isTerlambat ? 'Terlambat' : 'Hadir') : 'Pulang');
            scanHeaderSub.textContent = 'Siap untuk scan berikutnya...';
            setOverlayState('success', data.siswa.nama, data.labelStatus);
        }

        function showError(data) {
            hideResult();

            const code = data.code || '';
            const cfg = {
                LIBUR: { type: 'warn', title: 'Absensi Ditutup', icon: 'fa-calendar-xmark' },
                NOT_FOUND: { type: 'error', title: 'Kartu Tidak Dikenali', icon: 'fa-id-card' },
                FORBIDDEN: { type: 'error', title: 'Akses Ditolak', icon: 'fa-ban' },
                TERLALU_AWAL: { type: 'info', title: 'Belum Waktunya', icon: 'fa-hourglass-start' },
                DILUAR_WINDOW: { type: 'warn', title: 'Di Luar Jam Absensi', icon: 'fa-clock' },
                BELUM_MASUK: { type: 'warn', title: 'Belum Absen Masuk', icon: 'fa-triangle-exclamation' },
                SUDAH_MASUK: { type: 'info', title: 'Sudah Absen Masuk', icon: 'fa-circle-check' },
                SUDAH_PULANG: { type: 'info', title: 'Sudah Absen Pulang', icon: 'fa-circle-check' },
                SUDAH_LENGKAP: { type: 'info', title: 'Absensi Sudah Lengkap', icon: 'fa-circle-check' },
                INVALID: { type: 'error', title: 'Kode Tidak Valid', icon: 'fa-triangle-exclamation' },
                NO_AKTOR: { type: 'error', title: 'User Tidak Valid', icon: 'fa-user-xmark' },
                DATA_TIDAK_KONSISTEN: { type: 'error', title: 'Data Absensi Tidak Konsisten', icon: 'fa-triangle-exclamation' }
            };

            const c = cfg[code] || { type: 'error', title: 'Gagal', icon: 'fa-xmark' };
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

        function setBadge(type, label) {
            const map = {
                idle: 'badge-idle',
                scanning: 'badge-scanning',
                processing: 'badge-processing',
                ok: 'badge-ok',
                warn: 'badge-warn'
            };
            scanModeBadge.className = 'scan-mode-badge ' + (map[type] || 'badge-idle');
            scanModeLabel.textContent = label;
        }

        async function loadInfo() {
            try {
                const res = await fetch(config.infoUrl);
                const data = await res.json();

                if (!data.success) {
                    sicBody.innerHTML = `<div class="sic-empty">${escHtml(data.message || 'Tidak ada data.')}</div>`;
                    return;
                }

                if (data.holiday && data.holiday.isHoliday) {
                    sicBody.innerHTML = `
                        <div class="sic-row">
                            <span class="sic-lbl">Tanggal</span>
                            <span class="sic-val">${escHtml(data.labelTanggal)}</span>
                        </div>
                        <div class="sic-row">
                            <span class="sic-lbl">Status</span>
                            <span class="sic-val sic-muted">Hari Libur</span>
                        </div>
                        <div class="sic-row">
                            <span class="sic-lbl">Keterangan</span>
                            <span class="sic-val">${escHtml(data.holiday.keterangan)}</span>
                        </div>`;
                    return;
                }

                sicBody.innerHTML = `
                    <div class="sic-row">
                        <span class="sic-lbl">Tanggal</span>
                        <span class="sic-val">${escHtml(data.labelTanggal)}</span>
                    </div>
                    <div class="sic-row">
                        <span class="sic-lbl">Pelaku Scan</span>
                        <span class="sic-val">${escHtml(data.actorLabel)}</span>
                    </div>
                    <div class="sic-row">
                        <span class="sic-lbl">Kelas</span>
                        <span class="sic-val">${escHtml(data.kelasLabel)}</span>
                    </div>
                    <div class="sic-divider"></div>
                    <div class="sic-row">
                        <span class="sic-lbl">Total Siswa</span>
                        <span class="sic-val sic-val-bold">${escHtml(String(data.stats.totalSiswa))} siswa</span>
                    </div>
                    <div class="sic-row">
                        <span class="sic-lbl">Sudah Hadir</span>
                        <span class="sic-val sic-green">${escHtml(String(data.stats.sudahHadir))}</span>
                    </div>
                    <div class="sic-row">
                        <span class="sic-lbl">Belum Absen</span>
                        <span class="sic-val sic-muted">${escHtml(String(data.stats.belumAbsen))}</span>
                    </div>`;
            } catch (e) {
                sicBody.innerHTML = `<div class="sic-empty">Gagal memuat info jadwal.</div>`;
            }
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        btnBelakang.addEventListener('click', () => startCamera('environment'));
        btnDepan.addEventListener('click', () => startCamera('user'));
        window.addEventListener('beforeunload', stopCamera);

        document.addEventListener('DOMContentLoaded', function () {
            startCamera('environment');
            loadInfo();
        });
    };
})();