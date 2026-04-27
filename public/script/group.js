const GROUP_CHART_PERIOD_OPTIONS = ['3', '6', '12'];
const GROUP_DEFAULT_CHART_PERIOD = '3';

let groupProjectChart = null;
let groupVersionChart = null;
let resizeTimer = null;

function parseJson(raw, fallback) {
    if (!raw) return fallback;
    try {
        const parsed = JSON.parse(raw);
        return parsed ?? fallback;
    } catch (err) {
        console.error('Dados do grafico invalidos', err);
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
    GROUP_CHART_PERIOD_OPTIONS.forEach((period) => {
        periodSeries[period] = normalizeSeries(parsed[period] ?? fallbackSeries);
    });

    return periodSeries;
}

function resolvePeriod(value) {
    const normalized = String(value ?? GROUP_DEFAULT_CHART_PERIOD);
    return GROUP_CHART_PERIOD_OPTIONS.includes(normalized)
        ? normalized
        : GROUP_DEFAULT_CHART_PERIOD;
}

function getSeriesForPeriod(periodSeries, period) {
    const key = resolvePeriod(period);
    const candidate = periodSeries?.[key];
    return Array.isArray(candidate) ? candidate : [];
}

function getChartTheme() {
    const rootStyle = getComputedStyle(document.documentElement);
    const getVar = (name, fallback) => {
        const v = rootStyle.getPropertyValue(name);
        return (v && v.trim()) ? v.trim() : fallback;
    };

    const text2 = getVar('--text-2', '#555');
    const border = getVar('--border', '#e0e0e0');
    return { text2, border };
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

function renderHorizontalBar(existingChart, canvas, series, theme) {
    if (existingChart) {
        existingChart.destroy();
    }

    if (!canvas || typeof window.Chart === 'undefined') {
        return null;
    }

    const safeSeries = normalizeSeries(series);
    return new window.Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: safeSeries.map((item) => item.label),
            datasets: [{
                data: safeSeries.map((item) => item.value),
                backgroundColor: safeSeries.map((item) => item.color || '#ff8c00'),
                borderRadius: 6,
                borderSkipped: false,
                barThickness: 16,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 280,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.label}: ${Number(ctx.raw || 0)}`,
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: {
                        color: theme.text2,
                        precision: 0,
                    },
                    grid: { color: hexToRgba(theme.border, 0.55) },
                },
                y: {
                    ticks: { color: theme.text2 },
                    grid: { display: false },
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

function renderGroupCharts() {
    const projectCanvas = document.getElementById('groupProjectBar');
    const versionCanvas = document.getElementById('groupVersionBar');

    if (!projectCanvas && !versionCanvas) return;

    const selectedPeriod = getSelectedPeriod();
    syncChartPeriodSelects(selectedPeriod);

    const projectSeries = getSeriesForPeriod(parsePeriodSeries(projectCanvas), selectedPeriod);
    const versionSeries = getSeriesForPeriod(parsePeriodSeries(versionCanvas), selectedPeriod);
    const theme = getChartTheme();

    groupProjectChart = renderHorizontalBar(groupProjectChart, projectCanvas, projectSeries, theme);
    groupVersionChart = renderHorizontalBar(groupVersionChart, versionCanvas, versionSeries, theme);
}

function setupChartPeriodFilter() {
    const selects = document.querySelectorAll('[data-chart-period-filter]');
    if (!selects.length) return;

    const initialPeriod = resolvePeriod(selects[0].value || GROUP_DEFAULT_CHART_PERIOD);
    syncChartPeriodSelects(initialPeriod);

    selects.forEach((select) => {
        select.addEventListener('change', () => {
            const period = resolvePeriod(select.value);
            syncChartPeriodSelects(period);
            renderGroupCharts();
        });
    });
}

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

setupChartPeriodFilter();
renderGroupCharts();

function attachMemberRoleSelectsFallback() {
    const selects = document.querySelectorAll('.member-role-select');
    if (!selects.length) return;

    selects.forEach((select) => {
        if (select.dataset.memberRoleBound === '1') {
            return;
        }

        const form = select.closest('form');
        if (!form) return;

        select.dataset.memberRoleBound = '1';

        select.addEventListener('change', async () => {
            const previousValue = select.dataset.current || '';
            const nextValue = select.value;

            if (previousValue === nextValue) return;

            const optionLabel = select.options[select.selectedIndex]?.text ?? nextValue;
            const confirmed = confirm(`Confirmar alteracao de funcao para "${optionLabel}"?`);

            if (!confirmed) {
                select.value = previousValue || nextValue;
                return;
            }

            const memberId = parseInt(form.querySelector('input[name="member_id"]')?.value || '0', 10);
            const groupId = parseInt(form.querySelector('input[name="group_id"]')?.value || '0', 10);
            const labId = parseInt(form.querySelector('input[name="lab_id"]')?.value || '0', 10);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            if (!memberId || !csrfToken || (!groupId && !labId)) {
                alert('Nao foi possivel atualizar a funcao.');
                select.value = previousValue || nextValue;
                return;
            }

            const payload = {
                member_id: memberId,
                role: nextValue,
            };
            if (groupId > 0) payload.group_id = groupId;
            if (labId > 0) payload.lab_id = labId;

            try {
                const response = await fetch(form.action, {
                    method: 'PUT',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(errorText);
                }

                select.dataset.current = nextValue;
            } catch (err) {
                console.error(err);
                alert('Erro ao atualizar a funcao do membro.');
                select.value = previousValue || nextValue;
            }
        });
    });
}

if (typeof window.attachMemberRoleSelects === 'function') {
    window.attachMemberRoleSelects();
} else {
    attachMemberRoleSelectsFallback();
}

if (typeof window.initEntranceAnimations === 'function') {
    window.initEntranceAnimations();
}

document.addEventListener('owner:tabchange', (event) => {
    if (event.detail?.tabId !== 'dashboard') return;
    scheduleChartRender();
});
