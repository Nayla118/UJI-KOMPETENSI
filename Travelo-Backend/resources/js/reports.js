import Chart from 'chart.js/auto';

/**
 * Admin analytics charts.
 *
 * Chart.js is bundled by Vite (no CDN). The server renders the aggregated
 * numbers as JSON in #report-chart-data; this script only paints them, so the
 * browser never receives raw booking rows and cannot disagree with the cards.
 */
const SOURCE_ID = 'report-chart-data';

const COLORS = {
    primary: '#a95372',
    primarySoft: 'rgba(169, 83, 114, 0.16)',
    sage: '#4f7d66',
    sageSoft: 'rgba(79, 125, 102, 0.16)',
    warning: '#c98a4b',
    danger: '#b03a4e',
    blue: '#5b7fa6',
    slate: '#94a3b8',
};

const STATUS_COLORS = {
    Pending: COLORS.warning,
    Confirmed: COLORS.sage,
    Completed: COLORS.blue,
    Cancelled: COLORS.danger,
};

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const compactRupiah = new Intl.NumberFormat('id-ID', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

function money(value) {
    return rupiah.format(Math.round(Number(value) || 0));
}

function compactMoney(value) {
    return 'Rp ' + compactRupiah.format(Math.round(Number(value) || 0));
}

function sourceData() {
    const node = document.getElementById(SOURCE_ID);

    if (!node) return null;

    try {
        return JSON.parse(node.textContent || '{}');
    } catch (error) {
        console.warn('Report chart data is not valid JSON', error);

        return null;
    }
}

function mount(id, build) {
    const canvas = document.getElementById(id);

    if (!canvas || canvas.dataset.chartMounted === 'true') return;

    build(canvas);
    canvas.dataset.chartMounted = 'true';
}

function baseOptions() {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'bottom',
                labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true },
            },
            tooltip: {
                backgroundColor: '#493747',
                padding: 10,
                cornerRadius: 8,
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
            },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(148, 163, 184, 0.18)' },
            },
        },
    };
}

function initRevenue(data) {
    mount('chart-revenue', (canvas) => {
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Gross revenue',
                        data: data.gross,
                        borderColor: COLORS.primary,
                        backgroundColor: COLORS.primarySoft,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 4,
                    },
                    {
                        label: 'Net after refunds',
                        data: data.net,
                        borderColor: COLORS.sage,
                        backgroundColor: COLORS.sageSoft,
                        fill: false,
                        tension: 0.35,
                        pointRadius: 2,
                        pointHoverRadius: 4,
                        borderDash: [6, 4],
                    },
                ],
            },
            options: {
                ...baseOptions(),
                plugins: {
                    ...baseOptions().plugins,
                    tooltip: {
                        ...baseOptions().plugins.tooltip,
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${money(context.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    ...baseOptions().scales,
                    y: {
                        ...baseOptions().scales.y,
                        ticks: { callback: (value) => compactMoney(value) },
                    },
                },
            },
        });
    });
}

function initBookings(data) {
    mount('chart-bookings', (canvas) => {
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Bookings',
                        data: data.total,
                        backgroundColor: COLORS.primarySoft,
                        borderColor: COLORS.primary,
                        borderWidth: 1,
                        borderRadius: 6,
                    },
                    {
                        label: 'Paid',
                        data: data.paid,
                        backgroundColor: COLORS.sage,
                        borderRadius: 6,
                    },
                ],
            },
            options: baseOptions(),
        });
    });
}

function initStatus(data) {
    mount('chart-status', (canvas) => {
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        data: data.values,
                        backgroundColor: data.labels.map(
                            (label) => STATUS_COLORS[label] || COLORS.slate,
                        ),
                        borderWidth: 0,
                        hoverOffset: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } },
                },
            },
        });
    });
}

function initTopPackages(data) {
    mount('chart-packages', (canvas) => {
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: data.revenue,
                        backgroundColor: COLORS.primary,
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                ...baseOptions(),
                indexAxis: 'y',
                plugins: {
                    ...baseOptions().plugins,
                    legend: { display: false },
                    tooltip: {
                        ...baseOptions().plugins.tooltip,
                        callbacks: {
                            label: (context) => money(context.parsed.x),
                            afterLabel: (context) =>
                                `${data.bookings[context.dataIndex] ?? 0} booking(s)`,
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.18)' },
                        ticks: { callback: (value) => compactMoney(value) },
                    },
                    y: { grid: { display: false } },
                },
            },
        });
    });
}

function initUsers(data) {
    mount('chart-users', (canvas) => {
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'New travellers',
                        data: data.values,
                        borderColor: COLORS.blue,
                        backgroundColor: 'rgba(91, 127, 166, 0.16)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                    },
                ],
            },
            options: baseOptions(),
        });
    });
}

function initOccupancy(data) {
    mount('chart-occupancy', (canvas) => {
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Occupancy %',
                        data: data.values,
                        backgroundColor: COLORS.sage,
                        borderRadius: 6,
                    },
                ],
            },
            options: {
                ...baseOptions(),
                indexAxis: 'y',
                plugins: {
                    ...baseOptions().plugins,
                    legend: { display: false },
                    tooltip: {
                        ...baseOptions().plugins.tooltip,
                        callbacks: {
                            label: (context) => `${context.parsed.x}% filled`,
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        suggestedMax: 100,
                        grid: { color: 'rgba(148, 163, 184, 0.18)' },
                        ticks: { callback: (value) => `${value}%` },
                    },
                    y: { grid: { display: false } },
                },
            },
        });
    });
}

const charts = sourceData();

if (charts) {
    Chart.defaults.font.family = "'Nunito Sans', ui-sans-serif, system-ui, sans-serif";
    Chart.defaults.color = '#6b7280';

    initRevenue(charts.revenue);
    initBookings(charts.bookings);
    initStatus(charts.status);
    initTopPackages(charts.topPackages);
    initUsers(charts.users);
    initOccupancy(charts.occupancy);
}
