function parseSeries(canvas) {
    if (!canvas) return [];
    const raw = canvas.dataset.series;
    if (!raw) return [];

    try {
        return JSON.parse(raw);
    } catch (err) {
        console.error('Invalid chart data', err);
        return [];
    }
}

function getChartTheme() {
    const rootStyle = getComputedStyle(document.documentElement);
    const getVar = (name, fallback) => {
        const v = rootStyle.getPropertyValue(name);
        return (v && v.trim()) ? v.trim() : fallback;
    };

    // Prefer CSS variables (present in your *-dark.css files). Fall back to light defaults.
    const appBg = getVar('--app-bg', '#ffffff');
    const surface2 = getVar('--surface-2', '#ffffff');
    const surface3 = getVar('--surface-3', '#f1f3f5');
    const text1 = getVar('--text-1', '#111');
    const text2 = getVar('--text-2', '#555');
    const muted = getVar('--muted', '#777');
    const border = getVar('--border', '#e0e0e0');
    const accent = getVar('--accent', '#ff8c00');

    const scheme = (getComputedStyle(document.documentElement).colorScheme || '').toLowerCase();
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark = scheme.includes('dark') || prefersDark;

    return {
        isDark,
        appBg,
        surface2,
        surface3,
        text1,
        text2,
        muted,
        border,
        accent,
    };
}

function setupCanvas(canvas) {
    const ctx = canvas.getContext('2d');
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;

    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, rect.width, rect.height);

    return { ctx, width: rect.width, height: rect.height };
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

    series.forEach(item => {
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

function drawPieChart(canvas, series) {
    if (!canvas) return;

    const theme = getChartTheme();

    const total = series.reduce((sum, item) => sum + item.value, 0);
    const { ctx, width, height } = setupCanvas(canvas);
    const centerX = width / 2;
    const centerY = height / 2;
    const radius = Math.min(width, height) / 2 - 8;

    if (total === 0) {
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
        ctx.fillStyle = theme.surface3;
        ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = theme.border;
        ctx.stroke();
        return;
    }

    let startAngle = -Math.PI / 2;
    series.forEach(item => {
        if (item.value <= 0) return;
        const slice = (item.value / total) * Math.PI * 2;

        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, startAngle, startAngle + slice);
        ctx.closePath();
        ctx.fillStyle = item.color || '#ccc';
        ctx.fill();

        startAngle += slice;
    });

    ctx.beginPath();
    ctx.arc(centerX, centerY, radius * 0.55, 0, Math.PI * 2);
    // Donut center should blend with the page surface.
    ctx.fillStyle = theme.isDark ? theme.surface2 : '#fff';
    ctx.fill();
}

function drawLineChart(canvas, series) {
    if (!canvas) return;

    const theme = getChartTheme();

    const { ctx, width, height } = setupCanvas(canvas);
    const padding = 24;
    const labelSpace = 28;
    const top = padding;
    const left = padding;
    const right = width - padding;
    const bottom = height - labelSpace;

    const count = Math.max(1, series.length);
    const stepX = (right - left) / Math.max(1, count - 1);
    const maxValue = Math.max(1, ...series.map(item => item.value));
    const lineColor = series[0]?.color || theme.accent;

    ctx.strokeStyle = theme.border;
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(left, bottom);
    ctx.lineTo(right, bottom);
    ctx.stroke();

    ctx.strokeStyle = lineColor;
    ctx.lineWidth = 2;
    ctx.beginPath();

    series.forEach((item, index) => {
        const x = left + index * stepX;
        const y = bottom - (item.value / maxValue) * (bottom - top);

        if (index === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    });
    ctx.stroke();

    ctx.font = '11px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillStyle = theme.text2;

    series.forEach((item, index) => {
        const x = left + index * stepX;
        const y = bottom - (item.value / maxValue) * (bottom - top);

        ctx.beginPath();
        ctx.arc(x, y, 3, 0, Math.PI * 2);
        ctx.fillStyle = lineColor;
        ctx.fill();

        ctx.fillStyle = theme.text2;
        ctx.textBaseline = 'top';
        ctx.fillText(item.label, x, bottom + 6);
        ctx.textBaseline = 'alphabetic';
    });
}

function renderLabCharts() {
    const pieCanvas = document.getElementById('labPieChart');
    const lineCanvas = document.getElementById('labLineChart');

    if (!pieCanvas && !lineCanvas) return;

    const pieSeries = parseSeries(pieCanvas);
    const lineSeries = parseSeries(lineCanvas);

    drawPieChart(pieCanvas, pieSeries);
    drawLineChart(lineCanvas, lineSeries);

    const pieTotal = pieSeries.reduce((sum, item) => sum + item.value, 0);
    const lineTotal = lineSeries.reduce((sum, item) => sum + item.value, 0);

    buildLegend(document.getElementById('labPieLegend'), pieSeries, pieTotal);
    buildLegend(document.getElementById('labLineLegend'), lineSeries, lineTotal);
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

let resizeTimer = null;
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

renderLabCharts();
setupExpandCards();

if (typeof window.initEntranceAnimations === 'function') {
    window.initEntranceAnimations();
}

document.addEventListener('owner:tabchange', (event) => {
    if (event.detail?.tabId !== 'dashboard') return;
    scheduleChartRender();
});
