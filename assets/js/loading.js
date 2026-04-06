/**
 * assets/js/loading.js
 * Global loading handler — otomatis intercept semua form submit
 * dan bisa dipanggil manual untuk AJAX/fetch
 */

const Loading = (function () {

    const overlay  = document.getElementById('loadingOverlay');
    const textEl   = document.getElementById('loadingText');

    // Pesan default per konteks
    const MESSAGES = {
        default  : 'Memuat...',
        login    : 'Sedang masuk...',
        save     : 'Menyimpan data...',
        delete   : 'Menghapus data...',
        export   : 'Menyiapkan laporan...',
        scan     : 'Memproses absensi...',
        import   : 'Mengimpor data...',
    };

    // -------------------------------------------------------
    //  SHOW & HIDE
    // -------------------------------------------------------

    function show(pesan) {
        if (!overlay) return;
        textEl.textContent = pesan || MESSAGES.default;
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
    }

    function hide() {
        if (!overlay) return;
        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
    }

    // -------------------------------------------------------
    //  AUTO INTERCEPT — semua form submit di halaman ini
    //  Kecuali form yang punya data-no-loading="true"
    // -------------------------------------------------------

    function attachForms() {
        document.querySelectorAll('form').forEach(function (form) {

            // Skip form yang tidak mau loading
            if (form.dataset.noLoading === 'true') return;

            // Skip form scan (ditangani scan-shared.js)
            if (form.dataset.loading === 'skip') return;

            form.addEventListener('submit', function (e) {

                // Validasi HTML5 native — jangan tampilkan loading
                // jika form belum valid
                if (!form.checkValidity()) return;

                // Tentukan pesan berdasarkan atribut atau deteksi otomatis
                const pesan = form.dataset.loadingMsg
                    || detectMessage(form);

                show(pesan);

                // Disable tombol submit agar tidak double-click
                form.querySelectorAll('[type="submit"]').forEach(function (btn) {
                    btn.disabled = true;
                });
            });
        });
    }

    /**
     * Deteksi otomatis pesan berdasarkan isi form / nama action
     */
    function detectMessage(form) {
        const action = (form.action || '').toLowerCase();
        const id     = (form.id   || '').toLowerCase();

        if (action.includes('login')  || id.includes('login'))  return MESSAGES.login;
        if (action.includes('delete') || id.includes('delete')) return MESSAGES.delete;
        if (action.includes('export') || id.includes('export')) return MESSAGES.export;
        if (action.includes('import') || id.includes('import')) return MESSAGES.import;
        if (action.includes('scan')   || id.includes('scan'))   return MESSAGES.scan;

        return MESSAGES.save;
    }

    // -------------------------------------------------------
    //  SEMBUNYIKAN LOADING saat halaman baru selesai load
    //  (penting untuk form biasa yang redirect ke halaman baru)
    // -------------------------------------------------------
    window.addEventListener('pageshow', function (e) {
        // pageshow juga terpanggil saat back/forward cache
        hide();
    });

    // Fallback: sembunyikan saat DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        hide();
        attachForms();
    });

    // Jika script diload setelah DOM ready (misal di footer)
    if (document.readyState !== 'loading') {
        hide();
        attachForms();
    }

    // -------------------------------------------------------
    //  PUBLIC API
    // -------------------------------------------------------
    return {
        show : show,
        hide : hide,
        msg  : MESSAGES,

        /**
         * Wrapper fetch yang otomatis tampilkan loading
         *
         * Contoh:
         *   const data = await Loading.fetch('/scan/process.php', {
         *     method: 'POST', body: formData
         *   }, 'Memproses absensi...');
         */
        fetch: async function (url, options, pesan) {
            show(pesan || MESSAGES.default);
            try {
                const res = await fetch(url, options);
                return res;
            } finally {
                hide();
            }
        }
    };

})();