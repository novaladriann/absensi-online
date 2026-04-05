(function () {
    window.initSharedDashboard = function (config) {
        const dateInput = document.getElementById('tanggalFilter');
        const dateDisplayBtn = document.getElementById('dateDisplayBtn');
        const dateDisplayText = document.getElementById('dateDisplayText');
        const refreshBtn = document.getElementById('refreshDashboardBtn');
        const kelasSelect = document.getElementById('kelasFilterSelect');

        const dashboardDesc = document.getElementById('dashboardDesc');
        const labelTanggal = document.getElementById('labelTanggalDashboard');
        const labelKelas = document.getElementById('labelKelasDashboard');
        const dashboardChip = document.getElementById('dashboardChip');
        const holidayBanner = document.getElementById('dashboardHolidayBanner');
        const holidayText = document.getElementById('dashboardHolidayText');

        let currentTanggal = config.initialDate || new Date().toISOString().split('T')[0];
        let currentKelasId = 0;
        let chartInstance = null;

        if (dateInput) {
            dateInput.value = currentTanggal;
        }
        if (dateDisplayText && config.initialDateLabel) {
            dateDisplayText.textContent = config.initialDateLabel;
        }

        const chartCanvas = document.getElementById('dashboardChart');
        if (chartCanvas) {
            chartInstance = new Chart(chartCanvas, {
                type: 'bar',
                data: {
                    labels: ['Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa', 'Belum Absen'],
                    datasets: [{
                        label: 'Jumlah Siswa',
                        data: [0, 0, 0, 0, 0, 0],
                        borderRadius: 10,
                        backgroundColor: ['#4f46e5', '#8b5cf6', '#f4c542', '#60a5fa', '#ef4444', '#cbd5e1']
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
        }

        function updateUrl(tanggal, kelasId) {
            const url = new URL(window.location);
            url.searchParams.set('tanggal', tanggal);

            if (config.showKelasFilter) {
                if (kelasId > 0) {
                    url.searchParams.set('kelas_id', kelasId);
                } else {
                    url.searchParams.delete('kelas_id');
                }
            }

            window.history.replaceState({}, '', url);
        }

        function populateKelasDropdown(daftarKelas, selectedId) {
            if (!config.showKelasFilter || !kelasSelect) return;

            kelasSelect.innerHTML = '';

            const optAll = document.createElement('option');
            optAll.value = '0';
            optAll.textContent = 'Semua Kelas';
            kelasSelect.appendChild(optAll);

            (daftarKelas || []).forEach(function (k) {
                const opt = document.createElement('option');
                opt.value = String(k.id);
                opt.textContent = k.nama_kelas + (k.role_guru_kelas === 'wali' ? ' (Wali)' : '');
                kelasSelect.appendChild(opt);
            });

            kelasSelect.value = String(selectedId || 0);
        }

        function updateStats(stats) {
            document.querySelectorAll('[data-stat-key]').forEach(function (el) {
                const key = el.getAttribute('data-stat-key');
                el.textContent = (stats && typeof stats[key] !== 'undefined') ? stats[key] : 0;
            });
        }

        function updateChart(stats) {
            if (!chartInstance) return;

            chartInstance.data.datasets[0].data = [
                stats.hadir || 0,
                stats.terlambat || 0,
                stats.sakit || 0,
                stats.izin || 0,
                stats.alpa || 0,
                stats.belumAbsen || 0
            ];
            chartInstance.update();
        }

        function updateHoliday(holiday) {
            if (!config.showHolidayBanner || !holidayBanner || !holidayText || !dashboardChip) return;

            if (holiday && holiday.isHoliday) {
                holidayText.textContent = holiday.label;
                holidayBanner.style.display = 'block';
                dashboardChip.textContent = 'Hari Libur';
            } else {
                holidayBanner.style.display = 'none';
                dashboardChip.textContent = 'Realtime';
            }
        }

        async function loadDashboard(tanggal, kelasId) {
            try {
                if (refreshBtn) {
                    refreshBtn.disabled = true;
                    refreshBtn.classList.add('is-loading');
                }

                let url = config.endpoint + '?tanggal=' + encodeURIComponent(tanggal);
                if (config.showKelasFilter) {
                    url += '&kelas_id=' + encodeURIComponent(kelasId || 0);
                }

                const response = await fetch(url);
                const data = await response.json();

                if (!data.success) {
                    alert(data.message || 'Gagal memuat data dashboard.');
                    return;
                }

                currentTanggal = data.tanggal;
                currentKelasId = parseInt(data.kelasId || 0, 10);

                if (dateInput) dateInput.value = data.tanggal;
                if (dateDisplayText) dateDisplayText.textContent = data.labelTanggal;
                if (labelTanggal) labelTanggal.textContent = data.labelTanggal;
                if (labelKelas && typeof data.labelKelas !== 'undefined') labelKelas.textContent = data.labelKelas;
                if (dashboardDesc && data.description) dashboardDesc.textContent = data.description;

                populateKelasDropdown(data.daftarKelas || [], data.kelasId || 0);
                updateStats(data.stats || {});
                updateChart(data.stats || {});
                updateHoliday(data.holiday || null);
                updateUrl(data.tanggal, currentKelasId);

            } catch (err) {
                alert('Terjadi kesalahan saat memuat data.');
                console.error(err);
            } finally {
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                    refreshBtn.classList.remove('is-loading');
                }
            }
        }

        if (dateDisplayBtn && dateInput) {
            dateDisplayBtn.addEventListener('click', function () {
                if (dateInput.showPicker) {
                    dateInput.showPicker();
                } else {
                    dateInput.click();
                }
            });
        }

        if (dateInput) {
            dateInput.addEventListener('change', function () {
                if (this.value) {
                    loadDashboard(this.value, currentKelasId);
                }
            });
        }

        if (kelasSelect) {
            kelasSelect.addEventListener('change', function () {
                currentKelasId = parseInt(this.value || '0', 10);
                loadDashboard(currentTanggal, currentKelasId);
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                loadDashboard(currentTanggal, currentKelasId);
            });
        }

        loadDashboard(currentTanggal, currentKelasId);
    };
})();