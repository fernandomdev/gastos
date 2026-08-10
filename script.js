(() => {
    const API_URL = 'importar.php';

    const els = {
        openImportModal: document.getElementById('openImportModal'),
        importModal: document.getElementById('importModal'),
        importForm: document.getElementById('importForm'),
        importFeedback: document.getElementById('importFeedback'),
        importSubmit: document.getElementById('importSubmit'),
        movementsBody: document.getElementById('movimientosBody'),
        statMovimientos: document.getElementById('statMovimientos'),
        statGastos: document.getElementById('statGastos'),
        statIngresos: document.getElementById('statIngresos'),
        statSaldo: document.getElementById('statSaldo'),
        tableMeta: document.getElementById('tableMeta'),
        chartMeta: document.getElementById('chartMeta'),
        fileInput: document.getElementById('files'),
    };

    let balanceChart = null;

    const moneyFormatter = new Intl.NumberFormat('es-PY', {
        style: 'currency',
        currency: 'PYG',
        maximumFractionDigits: 0,
    });

    const numberFormatter = new Intl.NumberFormat('es-PY');

    function money(value) {
        const num = Number(value || 0);
        return moneyFormatter.format(Number.isFinite(num) ? num : 0);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function setFeedback(message, type = '') {
        els.importFeedback.textContent = message || '';
        els.importFeedback.className = `feedback ${type}`.trim();
    }

    function openModal() {
        els.importModal.classList.add('is-open');
        els.importModal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        els.importModal.classList.remove('is-open');
        els.importModal.setAttribute('aria-hidden', 'true');
        els.importForm.reset();
        setFeedback('');
    }

    function renderStats(stats) {
        els.statMovimientos.textContent = numberFormatter.format(stats.total_movimientos || 0);
        els.statGastos.textContent = money(stats.total_gastos || 0);
        els.statIngresos.textContent = money(stats.total_ingresos || 0);
        els.statSaldo.textContent = money(stats.saldo_neto || 0);
    }

    function renderTable(movimientos) {
        els.tableMeta.textContent = `${numberFormatter.format(movimientos.length)} registros`;

        if (!movimientos.length) {
            els.movementsBody.innerHTML = `
                <tr>
                    <td colspan="7" class="empty-state">Todavía no hay movimientos cargados.</td>
                </tr>
            `;
            return;
        }

        els.movementsBody.innerHTML = movimientos.map((item) => `
            <tr>
                <td>${escapeHtml(item.fecha_movimiento)}</td>
                <td>${escapeHtml(item.nro_comprobante)}</td>
                <td>${escapeHtml(item.descripcion)}</td>
                <td>${escapeHtml(item.categoria)}</td>
                <td class="col-right">${money(item.debito)}</td>
                <td class="col-right">${money(item.credito)}</td>
                <td class="col-right">${money(item.monto_neto)}</td>
            </tr>
        `).join('');
    }

    function buildChart(data) {
        const ctx = document.getElementById('balanceChart').getContext('2d');

        if (balanceChart) {
            balanceChart.destroy();
        }

        if (!data.labels.length || !data.datasets.length) {
            balanceChart = new Chart(ctx, {
                type: 'line',
                data: { labels: ['Sin datos'], datasets: [] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                    },
                },
            });

            els.chartMeta.textContent = 'Sin movimientos';
            return;
        }

        const palette = [
            '#0f766e', '#5b8a72', '#4f7ea8', '#d19a5b', '#c86e64',
            '#7c99a4', '#8b8f95', '#9bb4a7', '#2f6f68', '#6f8fb8'
        ];

        const datasets = data.datasets.map((dataset, index) => ({
            label: dataset.label,
            data: dataset.data,
            borderColor: palette[index % palette.length],
            backgroundColor: palette[index % palette.length],
            borderWidth: 2.5,
            pointRadius: 2.5,
            pointHoverRadius: 5,
            tension: 0.22,
            fill: false,
            spanGaps: true,
        }));

        balanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#667085',
                            usePointStyle: true,
                            boxWidth: 10,
                            boxHeight: 10,
                            padding: 16,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label(context) {
                                return `${context.dataset.label}: ${money(context.raw ?? 0)}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Día del mes',
                            color: '#667085',
                        },
                        ticks: {
                            color: '#667085',
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.10)',
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Dinero acumulado',
                            color: '#667085',
                        },
                        ticks: {
                            color: '#667085',
                            callback(value) {
                                return money(value);
                            }
                        },
                        grid: {
                            color: 'rgba(148, 163, 184, 0.10)',
                        }
                    }
                }
            }
        });

        els.chartMeta.textContent = data.meta?.label || 'Comparativa mensual';
    }

    async function loadDashboard() {
        const response = await fetch(`${API_URL}?action=dashboard`, {
            headers: { 'Accept': 'application/json' },
        });

        const json = await response.json();

        if (!json.ok) {
            throw new Error(json.mensaje || 'No se pudo cargar el dashboard.');
        }

        renderStats(json.stats || {});
        renderTable(json.movimientos || []);
        buildChart(json.chart || { labels: [], datasets: [] });
    }

    async function handleImport(event) {
        event.preventDefault();
        setFeedback('Subiendo archivo...', '');
        els.importSubmit.disabled = true;

        try {
            const formData = new FormData(els.importForm);
            formData.append('action', 'importar');

            const response = await fetch(API_URL, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' },
            });

            const json = await response.json();

            if (!json.ok) {
                throw new Error(json.mensaje || 'No se pudo importar el archivo.');
            }

            setFeedback(json.mensaje || 'Importación completada.', 'success');
            await loadDashboard();
            setTimeout(() => closeModal(), 550);
        } catch (error) {
            setFeedback(error.message || 'Error inesperado.', 'error');
        } finally {
            els.importSubmit.disabled = false;
        }
    }

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    els.openImportModal.addEventListener('click', openModal);
    els.importForm.addEventListener('submit', handleImport);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && els.importModal.classList.contains('is-open')) {
            closeModal();
        }
    });

    els.importModal.addEventListener('click', (event) => {
        if (event.target === els.importModal) {
            closeModal();
        }
    });

    loadDashboard().catch((error) => {
        console.error(error);
        els.movementsBody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">${escapeHtml(error.message || 'Error al cargar los datos.')}</td>
            </tr>
        `;
        setFeedback(error.message || 'Error al cargar el dashboard.', 'error');
    });
})();
