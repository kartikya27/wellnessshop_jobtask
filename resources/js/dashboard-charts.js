import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

bootChart('#marketing-trend-chart', '#marketing-trend-data', createMarketingChart);
bootChart('#operations-trend-chart', '#operations-trend-data', createOperationsChart);

function bootChart(canvasSelector, dataSelector, factory) {
    const canvas = document.querySelector(canvasSelector);
    const source = document.querySelector(dataSelector);

    if (!canvas || !source) {
        return;
    }

    let data = [];
    try {
        data = JSON.parse(source.textContent || '[]');
    } catch {
        data = [];
    }

    if (!Array.isArray(data) || !data.length) {
        return;
    }

    new Chart(canvas, factory(data));
}

function createMarketingChart(rows) {
    return {
        type: 'line',
        data: {
            labels: rows.map((row) => formatLabel(row.date)),
            datasets: [
                {
                    label: 'Spend',
                    data: rows.map((row) => row.spend),
                    borderColor: '#0891b2',
                    backgroundColor: 'rgba(8, 145, 178, 0.12)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 2,
                },
                {
                    label: 'Revenue',
                    data: rows.map((row) => row.revenue),
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.12)',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 2,
                },
            ],
        },
        options: options({
            y: {
                title: 'INR',
            },
        }),
    };
}

function createOperationsChart(rows) {
    return {
        type: 'bar',
        data: {
            labels: rows.map((row) => formatLabel(row.date)),
            datasets: [
                {
                    label: 'Orders',
                    data: rows.map((row) => row.orders),
                    backgroundColor: 'rgba(14, 165, 233, 0.45)',
                    borderColor: '#0284c7',
                    borderWidth: 1,
                    borderRadius: 6,
                },
                {
                    label: 'OTD %',
                    data: rows.map((row) => row.otd_percent),
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.15)',
                    tension: 0.3,
                    type: 'line',
                    yAxisID: 'y1',
                    pointRadius: 2,
                },
                {
                    label: 'RTO %',
                    data: rows.map((row) => row.rto_rate),
                    borderColor: '#e11d48',
                    backgroundColor: 'rgba(225, 29, 72, 0.15)',
                    tension: 0.3,
                    type: 'line',
                    yAxisID: 'y1',
                    pointRadius: 2,
                },
            ],
        },
        options: options({
            y: {
                title: 'Orders',
            },
            y1: {
                title: 'Percent',
                position: 'right',
                min: 0,
                max: 100,
                grid: { drawOnChartArea: false },
            },
        }),
    };
}

function options(scales) {
    const configuredScales = {};

    Object.entries(scales).forEach(([key, value]) => {
        configuredScales[key] = {
            border: { color: '#e7e5e4' },
            grid: { color: '#f1f0ee', ...(value.grid || {}) },
            ticks: { color: '#78716c', maxTicksLimit: 6 },
            position: value.position || 'left',
            min: value.min,
            max: value.max,
            title: { display: true, text: value.title, color: '#78716c' },
        };
    });

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
            legend: { labels: { color: '#44403c', boxWidth: 10, boxHeight: 10 } },
            tooltip: { backgroundColor: '#1c1917', borderColor: '#292524', borderWidth: 1 },
        },
        scales: configuredScales,
    };
}

function formatLabel(value) {
    return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short' }).format(new Date(value));
}
