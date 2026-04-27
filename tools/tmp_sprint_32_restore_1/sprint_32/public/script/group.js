function parseSeries(canvas) {
    if (!canvas) return [];
    const raw = canvas.dataset.series;
    if (!raw) return [];

    try {
        return JSON.parse(raw);
    } catch (err) {
        console.error('Dados do gráfico inválidos', err);
        return [];
    }
}

function getChartTheme() {
    const rootStyle = getComputedStyle(document.documentElement);
    const getVar = (name, fallback) => {
        const v = rootStyle.getPropertyValue(name);
        return (v && v.trim()) ? v.trim() : fallback;
    };

    const appBg = getVar('--app-bg', '#ffffff');
    const surface3 = getVar('--surface-3', '#f1f3f5');
    const text1 = getVar('--text-1', '#111');
    const text2 = getVar('--text-2', '#555');
    const muted = getVar('--muted', '#777');
    const border = getVar('--border', '#e0e0e0');
    const accent = getVar('--accent', '#ff8c00');

    const scheme = (getComputedStyle(document.documentElement).colorScheme || '').toLowerCase();
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark = scheme.includes('dark') || prefersDark;

    return { isDark, appBg, surface3, text1, text2, muted, border, accent };
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

function drawHorizontalBarChart(canvas, series) {
    if (!canvas) return;

    const { ctx, width, height } = setupCanvas(canvas);
    const padding = 16;

    const theme = getChartTheme();

    if (!series.length) {
        ctx.fillStyle = theme.muted;
        ctx.font = '12px Inter, sans-serif';
        ctx.fillText('Sem dados', padding, padding + 12);
        return;
    }

    ctx.font = '12px Inter, sans-serif';
    let maxLabelWidth = 0;
    series.forEach((item) => {
        maxLabelWidth = Math.max(maxLabelWidth, ctx.measureText(item.label).width);
    });

    const labelArea = Math.min(width * 0.45, Math.max(90, maxLabelWidth + 12));
    const chartLeft = padding + labelArea;
    const chartRight = width - padding;
    const chartWidth = Math.max(10, chartRight - chartLeft);

    const count = series.length;
    const gap = 12;
    const usableHeight = height - padding * 2;
    const barHeight = Math.max(12, Math.min(28, (usableHeight - gap * (count - 1)) / count));

    const maxValue = Math.max(1, ...series.map(item => item.value));

    series.forEach((item, index) => {
        const y = padding + index * (barHeight + gap);

        ctx.fillStyle = theme.surface3;
        ctx.fillRect(chartLeft, y, chartWidth, barHeight);

        const barWidth = (item.value / maxValue) * chartWidth;
        ctx.fillStyle = item.color || theme.accent;
        ctx.fillRect(chartLeft, y, barWidth, barHeight);

        ctx.fillStyle = theme.text2;
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'left';
        ctx.fillText(item.label, padding, y + barHeight / 2);

        // Values: outside uses text, inside uses white for contrast
        if (barWidth < 24) {
            ctx.textAlign = 'left';
            ctx.fillStyle = theme.text1;
            ctx.fillText(String(item.value), chartLeft + barWidth + 6, y + barHeight / 2);
        } else {
            ctx.textAlign = 'right';
            ctx.fillStyle = '#fff';
            ctx.fillText(String(item.value), chartLeft + barWidth - 6, y + barHeight / 2);
        }
    });
}

function renderGroupCharts() {
    const projectCanvas = document.getElementById('groupProjectBar');
    const versionCanvas = document.getElementById('groupVersionBar');

    if (!projectCanvas && !versionCanvas) return;

    const projectSeries = parseSeries(projectCanvas);
    const versionSeries = parseSeries(versionCanvas);

    drawHorizontalBarChart(projectCanvas, projectSeries);
    drawHorizontalBarChart(versionCanvas, versionSeries);
}

let resizeTimer = null;
function scheduleChartRender() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(renderGroupCharts, 80);
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

renderGroupCharts();

function attachMemberRoleSelects() {
    const selects = document.querySelectorAll('.member-role-select');
    if (!selects.length) return;

    selects.forEach((select) => {
        const form = select.closest('form');
        if (!form) return;

        select.addEventListener('change', async () => {
            const previousValue = select.dataset.current || '';
            const nextValue = select.value;

            if (previousValue === nextValue) return;

            const optionLabel = select.options[select.selectedIndex]?.text ?? nextValue;
            const confirmed = confirm(`Confirmar alteração de função para "${optionLabel}"?`);

            if (!confirmed) {
                select.value = previousValue || nextValue;
                return;
            }

            const memberId = form.querySelector('input[name="member_id"]')?.value;
            const groupId = form.querySelector('input[name="group_id"]')?.value;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            if (!memberId || !groupId || !csrfToken) {
                alert('Não foi possível atualizar a função.');
                select.value = previousValue || nextValue;
                return;
            }

            try {
                const response = await fetch(form.action, {
                    method: 'PUT',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        member_id: memberId,
                        group_id: groupId,
                        role: nextValue
                    })
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(errorText);
                }

                select.dataset.current = nextValue;
            } catch (err) {
                console.error(err);
                alert('Erro ao atualizar a função do membro.');
                select.value = previousValue || nextValue;
            }
        });
    });
}

attachMemberRoleSelects();

if (typeof window.initEntranceAnimations === 'function') {
    window.initEntranceAnimations();
}

document.addEventListener('owner:tabchange', (event) => {
    if (event.detail?.tabId !== 'dashboard') return;
    scheduleChartRender();
});
