document.addEventListener('DOMContentLoaded', function () {
    initReportCounters();
    initReportSearch();
    initReportActions();

    if (!window.REPORTES_DATA || typeof Chart === 'undefined') return;

    const css = getComputedStyle(document.documentElement);
    const palette = [
        css.getPropertyValue('--report-cyan').trim() || '#00d1ff',
        css.getPropertyValue('--report-blue').trim() || '#5b7cfa',
        css.getPropertyValue('--report-green').trim() || '#22e6a8',
        css.getPropertyValue('--report-yellow').trim() || '#f8c46c',
        '#a78bfa',
        css.getPropertyValue('--report-red').trim() || '#ff7373',
        '#38bdf8',
        '#34d399',
    ];

    Chart.defaults.color = 'rgba(226, 232, 240, 0.74)';
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(8, 16, 40, 0.94)';
    Chart.defaults.plugins.tooltip.borderColor = 'rgba(0, 209, 255, 0.22)';
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 12;

    const gridColor = 'rgba(148, 163, 184, 0.12)';
    const charts = window.REPORTES_DATA.charts || {};

    if (window.REPORTES_DATA.page === 'premium') {
        lineChart('reportsIncomeChart', charts.ingresos, 'Ingresos', true);
        barChart('reportsAppointmentsChart', charts.citas, 'Citas');
        lineChart('reportsPatientsChart', charts.pacientes, 'Pacientes', false);
        barChart('reportsTreatmentsChart', charts.tratamientos, 'Facturado', true, true);
        doughnutChart('reportsAppointmentStatesChart', charts.estadosCitas);
        doughnutChart('reportsInvoiceStatesChart', charts.estadosFacturas);
        barChart('reportsDoctorChart', charts.odontologos, 'Ingresos', true);
        return;
    }

    const ingresosLabels = window.REPORTES_DATA.ingresos?.labels || window.REPORTES_DATA.labels || [];
    const ingresosValores = window.REPORTES_DATA.ingresos?.valores || window.REPORTES_DATA.valores || [];
    if (window.REPORTES_DATA.page === 'index') lineChart('reporteIngresosChart', { labels: ingresosLabels, values: ingresosValores }, 'Ingresos', true);
    if (window.REPORTES_DATA.page === 'ingresos') lineChart('ingresosChart', { labels: ingresosLabels, values: ingresosValores }, 'Ingresos', true);
    if (window.REPORTES_DATA.page === 'cuentas_cobrar') doughnutChart('cuentasCobrarChart', { labels: window.REPORTES_DATA.labels || [], values: window.REPORTES_DATA.valores || [] });
    if (window.REPORTES_DATA.page === 'procedimientos') barChart('procedimientosChart', { labels: window.REPORTES_DATA.labels || [], values: window.REPORTES_DATA.valores || [] }, 'Total', true, true);

    function baseOptions(currency = false) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 900, easing: 'easeOutQuart' },
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, padding: 18 } },
                tooltip: {
                    callbacks: {
                        label: context => {
                            const label = context.dataset.label || context.label || 'Total';
                            const value = context.parsed.y ?? context.parsed ?? 0;
                            return `${label}: ${currency ? formatCOP(value) : new Intl.NumberFormat('es-CO').format(value)}`;
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { color: 'rgba(226,232,240,.72)' }, grid: { color: 'transparent' } },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: 'rgba(226,232,240,.72)',
                        callback: value => currency ? compactCOP(value) : value,
                    },
                    grid: { color: gridColor },
                },
            },
        };
    }

    function lineChart(id, data, label, currency) {
        const canvas = document.getElementById(id);
        if (!canvas || !hasData(data)) return emptyChart(canvas);
        const ctx = canvas.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(0, 209, 255, 0.28)');
        gradient.addColorStop(1, 'rgba(0, 209, 255, 0.01)');
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label,
                    data: data.values,
                    borderColor: palette[0],
                    backgroundColor: gradient,
                    borderWidth: 3,
                    tension: 0.42,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: palette[0],
                    pointBorderWidth: 2,
                }],
            },
            options: baseOptions(currency),
        });
    }

    function barChart(id, data, label, currency = false, horizontal = false) {
        const canvas = document.getElementById(id);
        if (!canvas || !hasData(data)) return emptyChart(canvas);
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label,
                    data: data.values,
                    backgroundColor: data.labels.map((_, index) => `${palette[index % palette.length]}cc`),
                    borderColor: data.labels.map((_, index) => palette[index % palette.length]),
                    borderWidth: 1,
                    borderRadius: 12,
                    maxBarThickness: 42,
                }],
            },
            options: Object.assign(baseOptions(currency), {
                indexAxis: horizontal ? 'y' : 'x',
                scales: horizontal
                    ? {
                        x: baseOptions(currency).scales.y,
                        y: { ticks: { color: 'rgba(226,232,240,.72)' }, grid: { color: 'transparent' } },
                    }
                    : baseOptions(currency).scales,
            }),
        });
    }

    function doughnutChart(id, data) {
        const canvas = document.getElementById(id);
        if (!canvas || !hasData(data)) return emptyChart(canvas);
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.labels.map(label => String(label || 'Sin dato').replace('_', ' ')),
                datasets: [{
                    data: data.values,
                    backgroundColor: data.labels.map((_, index) => `${palette[index % palette.length]}d9`),
                    borderColor: 'rgba(8, 16, 40, 0.9)',
                    borderWidth: 3,
                    hoverOffset: 8,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                animation: { duration: 900, easing: 'easeOutQuart' },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, padding: 18 } },
                },
            },
        });
    }

    function hasData(data) {
        return data && Array.isArray(data.labels) && Array.isArray(data.values) && data.labels.length && data.values.some(Number);
    }

    function emptyChart(canvas) {
        const shell = canvas?.closest('.chart-shell');
        if (!shell) return;
        shell.innerHTML = '<div class="reports-empty"><i class="bi bi-bar-chart"></i><strong>Sin datos</strong><p>No hay informacion suficiente para esta grafica.</p></div>';
    }
});

function initReportCounters() {
    const counters = document.querySelectorAll('[data-counter]');
    const formatter = new Intl.NumberFormat('es-CO');

    counters.forEach(counter => {
        const target = Number(counter.dataset.counter || 0);
        const isMoney = counter.dataset.counterType === 'money';
        const duration = 850;
        const start = performance.now();

        function tick(now) {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            const value = target * eased;
            counter.textContent = isMoney ? formatCOP(value) : formatter.format(Math.round(value));
            if (progress < 1) requestAnimationFrame(tick);
        }

        requestAnimationFrame(tick);
    });
}

function initReportSearch() {
    const input = document.querySelector('[data-report-search]');
    if (!input) return;

    input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        document.querySelectorAll('[data-report-table] tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
}

function initReportActions() {
    document.querySelectorAll('[data-export-loader]').forEach(button => {
        button.addEventListener('click', () => {
            button.classList.add('is-loading');
            const icon = button.querySelector('i');
            if (icon) icon.className = 'bi bi-arrow-repeat';
        });
    });

    const share = document.querySelector('[data-report-share]');
    share?.addEventListener('click', async () => {
        try {
            if (navigator.share) {
                await navigator.share({ title: document.title, url: window.location.href });
            } else if (navigator.clipboard) {
                await navigator.clipboard.writeText(window.location.href);
                flashAction(share, 'Copiado');
            }
        } catch (error) {
            if (navigator.clipboard) {
                await navigator.clipboard.writeText(window.location.href);
                flashAction(share, 'Copiado');
            }
        }
    });
}

function flashAction(button, text) {
    const label = button.querySelector('span');
    if (!label) return;
    const original = label.textContent;
    label.textContent = text;
    window.setTimeout(() => {
        label.textContent = original;
    }, 1500);
}

function formatCOP(value) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value || 0);
}

function compactCOP(value) {
    const number = Number(value || 0);
    if (Math.abs(number) >= 1000000) return `$${Math.round(number / 1000000)}M`;
    if (Math.abs(number) >= 1000) return `$${Math.round(number / 1000)}K`;
    return `$${number}`;
}
