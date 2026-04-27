const CHART_PERIOD_OPTIONS = ['3', '6', '12'];
const DEFAULT_CHART_PERIOD = '3';

let labPieChart = null;
let labLineChart = null;
let resizeTimer = null;

function parseJson(raw, fallback) {
    if (!raw) return fallback;
    try {
        const parsed = JSON.parse(raw);
        return parsed ?? fallback;
    } catch (err) {
        console.error('Invalid chart data', err);
        return fallback;
    }
}

function normalizeSeries(series) {
    if (!Array.isArray(series)) return [];
    return series.map((item) => ({
        label: String(item?.label ?? ''),
        value: Number(item?.value ?? 0),
        color: String(item?.color ?? ''),
    }));
}

function parseSeries(canvas) {
    if (!canvas) return [];
    return normalizeSeries(parseJson(canvas.dataset.series, []));
}

function parsePeriodSeries(canvas) {
    const fallbackSeries = parseSeries(canvas);
    const parsed = parseJson(canvas?.dataset.periodSeries, null);

    if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
        return {
            '3': fallbackSeries,
            '6': fallbackSeries,
            '12': fallbackSeries,
        };
    }

    const periodSeries = {};
    CHART_PERIOD_OPTIONS.forEach((period) => {
        periodSeries[period] = normalizeSeries(parsed[period] ?? fallbackSeries);
    });

    return periodSeries;
}

function resolvePeriod(value) {
    const normalized = String(value ?? DEFAULT_CHART_PERIOD);
    return CHART_PERIOD_OPTIONS.includes(normalized) ? normalized : DEFAULT_CHART_PERIOD;
}

function getSeriesForPeriod(periodSeries, period) {
    const key = resolvePeriod(period);
    const candidate = periodSeries?.[key];
    if (Array.isArray(candidate)) return candidate;
    return [];
}

function getChartTheme() {
    const rootStyle = getComputedStyle(document.documentElement);
    const getVar = (name, fallback) => {
        const v = rootStyle.getPropertyValue(name);
        return (v && v.trim()) ? v.trim() : fallback;
    };

    const text1 = getVar('--text-1', '#111');
    const text2 = getVar('--text-2', '#555');
    const border = getVar('--border', '#e0e0e0');
    const accent = getVar('--accent', '#ff8c00');

    return { text1, text2, border, accent };
}

function hexToRgba(hex, alpha) {
    const raw = String(hex || '').replace('#', '');
    const full = raw.length === 3
        ? raw.split('').map((part) => part + part).join('')
        : raw.padEnd(6, '0').slice(0, 6);

    const r = parseInt(full.slice(0, 2), 16);
    const g = parseInt(full.slice(2, 4), 16);
    const b = parseInt(full.slice(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function buildLegend(container, series, total) {
    if (!container) return;
    container.innerHTML = '';

    if (!series.length || total === 0) {
        const empty = document.createElement('span');
        empty.className = 'chart-empty';
        empty.textContent = 'Sem dados';
        container.appendChild(empty);
        return;
    }

    series.forEach((item) => {
        const entry = document.createElement('div');
        entry.className = 'legend-item';

        const dot = document.createElement('span');
        dot.className = 'legend-dot';
        dot.style.background = item.color || '#ccc';

        const label = document.createElement('span');
        label.textContent = `${item.label}: ${item.value}`;

        entry.appendChild(dot);
        entry.appendChild(label);
        container.appendChild(entry);
    });
}

function renderPieChart(canvas, series, theme) {
    if (labPieChart) {
        labPieChart.destroy();
        labPieChart = null;
    }

    if (!canvas || typeof window.Chart === 'undefined') return;

    const safeSeries = normalizeSeries(series);
    labPieChart = new window.Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: safeSeries.map((item) => item.label),
            datasets: [{
                data: safeSeries.map((item) => item.value),
                backgroundColor: safeSeries.map((item) => item.color || theme.accent),
                borderWidth: 0,
                hoverOffset: 8,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '58%',
            animation: {
                duration: 280,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const label = ctx.label || '';
                            const value = Number(ctx.raw || 0);
                            return `${label}: ${value}`;
                        },
                    },
                },
            },
        },
    });
}

function renderLineChart(canvas, series, theme) {
    if (labLineChart) {
        labLineChart.destroy();
        labLineChart = null;
    }

    if (!canvas || typeof window.Chart === 'undefined') return;

    const safeSeries = normalizeSeries(series);
    const lineColor = safeSeries[0]?.color || theme.accent;

    labLineChart = new window.Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: safeSeries.map((item) => item.label),
            datasets: [{
                label: 'Projetos',
                data: safeSeries.map((item) => item.value),
                borderColor: lineColor,
                backgroundColor: hexToRgba(lineColor, 0.2),
                fill: true,
                tension: 0.28,
                pointRadius: 3,
                pointHoverRadius: 5,
                pointBackgroundColor: lineColor,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 280,
            },
            plugins: {
                legend: { display: false },
            },
            scales: {
                x: {
                    ticks: { color: theme.text2 },
                    grid: { color: hexToRgba(theme.border, 0.55) },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: theme.text2,
                        precision: 0,
                    },
                    grid: { color: hexToRgba(theme.border, 0.55) },
                },
            },
        },
    });
}

function syncChartPeriodSelects(period) {
    document.querySelectorAll('[data-chart-period-filter]').forEach((select) => {
        if (select.value !== period) {
            select.value = period;
        }
    });
}

function getSelectedPeriod() {
    const select = document.querySelector('[data-chart-period-filter]');
    return resolvePeriod(select?.value);
}

function renderLabCharts() {
    const pieCanvas = document.getElementById('labPieChart');
    const lineCanvas = document.getElementById('labLineChart');

    if (!pieCanvas && !lineCanvas) return;

    const selectedPeriod = getSelectedPeriod();
    syncChartPeriodSelects(selectedPeriod);

    const pieSeries = getSeriesForPeriod(parsePeriodSeries(pieCanvas), selectedPeriod);
    const lineSeries = getSeriesForPeriod(parsePeriodSeries(lineCanvas), selectedPeriod);
    const theme = getChartTheme();

    renderPieChart(pieCanvas, pieSeries, theme);
    renderLineChart(lineCanvas, lineSeries, theme);

    const pieTotal = pieSeries.reduce((sum, item) => sum + Number(item.value || 0), 0);
    const lineTotal = lineSeries.reduce((sum, item) => sum + Number(item.value || 0), 0);

    buildLegend(document.getElementById('labPieLegend'), pieSeries, pieTotal);
    buildLegend(document.getElementById('labLineLegend'), lineSeries, lineTotal);
}

function setupChartPeriodFilter() {
    const selects = document.querySelectorAll('[data-chart-period-filter]');
    if (!selects.length) return;

    const initialPeriod = resolvePeriod(selects[0].value || DEFAULT_CHART_PERIOD);
    syncChartPeriodSelects(initialPeriod);

    selects.forEach((select) => {
        select.addEventListener('change', () => {
            const period = resolvePeriod(select.value);
            syncChartPeriodSelects(period);
            renderLabCharts();
        });
    });
}

function setupExpandCards() {
    const cards = document.querySelectorAll('.expand-card');
    if (!cards.length) return;

    cards.forEach((card) => {
        const openBtn = card.querySelector('.expand-trigger');
        const closeBtn = card.querySelector('.expand-close');

        if (openBtn) {
            openBtn.addEventListener('click', () => {
                const willOpen = !card.classList.contains('is-open');
                cards.forEach((other) => {
                    if (other !== card) {
                        other.classList.remove('is-open');
                    }
                });
                card.classList.toggle('is-open', willOpen);
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                card.classList.remove('is-open');
            });
        }
    });
}

function scheduleChartRender() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(renderLabCharts, 80);
}

window.addEventListener('resize', scheduleChartRender);

if (typeof ResizeObserver !== 'undefined') {
    const chartWraps = document.querySelectorAll('.chart-wrap');
    const chartResizeObserver = new ResizeObserver(() => {
        scheduleChartRender();
    });

    chartWraps.forEach((wrap) => chartResizeObserver.observe(wrap));
}

const bodyObserver = new MutationObserver(() => {
    scheduleChartRender();
});

bodyObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });

setupChartPeriodFilter();
renderLabCharts();
setupExpandCards();

if (typeof window.initEntranceAnimations === 'function') {
    window.initEntranceAnimations();
}

document.addEventListener('owner:tabchange', (event) => {
    if (event.detail?.tabId !== 'dashboard') return;
    scheduleChartRender();
});
