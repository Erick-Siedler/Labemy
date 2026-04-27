function setupVersionModal() {
    const overlay = document.getElementById('versionOverlay');
    const form = document.getElementById('versionForm');
    const openButtons = [
        document.getElementById('openVersionFormBtn'),
        document.getElementById('openVersionFormBtnSecondary')
    ].filter(Boolean);
    const closeButton = document.getElementById('closeVersionForm');

    if (!overlay || !form) return;

    const open = () => {
        activatePanel('versions');
        overlay.classList.add('is-open');
        form.classList.add('is-open');
    };

    const close = () => {
        overlay.classList.remove('is-open');
        form.classList.remove('is-open');
    };

    openButtons.forEach((btn) => {
        btn.addEventListener('click', open);
    });

    if (closeButton) {
        closeButton.addEventListener('click', close);
    }

    overlay.addEventListener('click', close);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
}

function activatePanel(panelName) {
    const ownerTab = document.querySelector(`.owner-tab[data-owner-tab="${panelName}"]`);
    if (ownerTab) {
        ownerTab.click();
        return;
    }

    const panels = document.querySelectorAll('[data-panel]');
    panels.forEach((panel) => {
        panel.classList.toggle('is-active', panel.dataset.panel === panelName);
    });

    const tabs = document.querySelectorAll('[data-panel-tabs] [data-panel-target]');
    tabs.forEach((tab) => {
        const isActive = tab.dataset.panelTarget === panelName;
        tab.classList.toggle('is-active', isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
}

function setupPanelToggles() {
    const triggers = document.querySelectorAll('[data-panel-target]');
    if (!triggers.length) return;

    triggers.forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.panelTarget;
            if (!target) return;
            activatePanel(target);

            if (btn.dataset.openForm === 'true') {
                const openMain = document.getElementById('openVersionFormBtn');
                if (openMain) openMain.click();
            }
        });
    });
}

function setupPanelTabs() {
    const tabLists = document.querySelectorAll('[data-panel-tabs]');
    if (!tabLists.length) return;

    tabLists.forEach((list) => {
        const tabs = Array.from(list.querySelectorAll('[data-panel-target]'));
        if (!tabs.length) return;

        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                tabs.forEach((item) => {
                    const isActive = item === tab;
                    item.classList.toggle('is-active', isActive);
                    item.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
            });
        });
    });
}

function setupVersionCarousel() {
    const carousel = document.querySelector('.versions-carousel');
    if (!carousel || typeof window.Swiper === 'undefined') return;

    new window.Swiper(carousel, {
        slidesPerView: 1,
        spaceBetween: 16,
        loop: false,
        grabCursor: true,
        autoHeight: true,
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        breakpoints: {
            900: {
                slidesPerView: 2,
            },
            1200: {
                slidesPerView: 3,
            }
        }
    });
}

function setupVersionCommentPanels() {
    const toggles = document.querySelectorAll('.version-more-btn');
    if (!toggles.length) return;

    const resolveContainer = (btn) => {
        return btn.closest('.version-card') || btn.closest('.board-column');
    };

    const closeOtherPanels = (activePanel) => {
        const openPanels = document.querySelectorAll('.version-comment-panel.is-open');
        openPanels.forEach((panel) => {
            if (panel === activePanel) return;
            panel.classList.remove('is-open');
            const btn = panel.closest('.version-card, .board-column')?.querySelector('.version-more-btn');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    };

    toggles.forEach((btn) => {
        btn.addEventListener('click', () => {
            const card = resolveContainer(btn);
            const panel = card ? card.querySelector('.version-comment-panel') : null;
            if (!panel) return;

            const willOpen = !panel.classList.contains('is-open');
            closeOtherPanels(panel);
            panel.classList.toggle('is-open', willOpen);
            btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');

            const carousel = document.querySelector('.versions-carousel');
            if (carousel?.swiper && typeof carousel.swiper.updateAutoHeight === 'function') {
                carousel.swiper.updateAutoHeight(200);
            }
        });
    });
}

function setupCommentListPanels() {
    const toggles = document.querySelectorAll('[data-comment-list-toggle]');
    if (!toggles.length) return;

    const overlay = document.querySelector('[data-comment-overlay]');

    const setExpandedState = (panelId, expanded) => {
        toggles.forEach((toggle) => {
            if (toggle.getAttribute('aria-controls') === panelId) {
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }
        });
    };

    const updateOverlay = () => {
        if (!overlay) return;
        const isOpen = Boolean(document.querySelector('.comment-list-panel.is-open'));
        overlay.classList.toggle('is-open', isOpen);
    };

    const closeAllLists = (exceptPanel) => {
        const openPanels = document.querySelectorAll('.comment-list-panel.is-open');
        openPanels.forEach((panel) => {
            if (panel === exceptPanel) return;
            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
            if (panel.id) {
                setExpandedState(panel.id, false);
            }
        });

        if (!exceptPanel) {
            updateOverlay();
        }
    };

    const closeAllCommentForms = () => {
        const openPanels = document.querySelectorAll('.version-comment-panel.is-open');
        openPanels.forEach((panel) => {
            panel.classList.remove('is-open');
            const btn = panel.closest('.version-card, .board-column')?.querySelector('.version-more-btn');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    };

    toggles.forEach((toggle) => {
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const targetId = toggle.getAttribute('aria-controls');
            if (!targetId) return;
            const panel = document.getElementById(targetId);
            if (!panel) return;

            const willOpen = !panel.classList.contains('is-open');
            closeAllLists(panel);
            closeAllCommentForms();

            panel.classList.toggle('is-open', willOpen);
            panel.setAttribute('aria-hidden', willOpen ? 'false' : 'true');
            if (panel.id) {
                setExpandedState(panel.id, willOpen);
            }
            updateOverlay();
        });
    });

    const closeButtons = document.querySelectorAll('[data-comment-list-close]');
    closeButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.stopPropagation();
            closeAllLists();
        });
    });

    if (overlay) {
        overlay.addEventListener('click', () => closeAllLists());
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeAllLists();
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('.comment-list-panel')) return;
        if (event.target.closest('[data-comment-list-toggle]')) return;
        closeAllLists();
    });
}

function setupVersionDetailPanels() {
    const openButtons = document.querySelectorAll('[data-version-detail-open]');
    if (!openButtons.length) return;

    const overlay = document.querySelector('[data-version-detail-overlay]');
    const panels = document.querySelectorAll('[data-version-detail-panel]');

    const setMode = (panel, mode) => {
        panel.classList.toggle('is-edit', mode === 'edit');
        const tabs = panel.querySelectorAll('[data-detail-tab]');
        tabs.forEach((tab) => {
            const isActive = tab.dataset.detailTab === mode;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    };

    const closeAll = () => {
        panels.forEach((panel) => {
            panel.classList.remove('is-open', 'is-edit');
            panel.setAttribute('aria-hidden', 'true');
        });
        if (overlay) {
            overlay.classList.remove('is-open');
        }
    };

    const openPanel = (panel) => {
        closeAll();
        const openCommentLists = document.querySelectorAll('.comment-list-panel.is-open');
        openCommentLists.forEach((list) => {
            list.classList.remove('is-open');
            list.setAttribute('aria-hidden', 'true');
        });
        const commentOverlay = document.querySelector('[data-comment-overlay]');
        if (commentOverlay) {
            commentOverlay.classList.remove('is-open');
        }
        const openCommentForms = document.querySelectorAll('.version-comment-panel.is-open');
        openCommentForms.forEach((formPanel) => {
            formPanel.classList.remove('is-open');
            const btn = formPanel.closest('.version-card, .board-column')?.querySelector('.version-more-btn');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
        });
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
        setMode(panel, 'view');
        if (overlay) {
            overlay.classList.add('is-open');
        }
    };

    openButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('aria-controls');
            if (!targetId) return;
            const panel = document.getElementById(targetId);
            if (!panel) return;
            openPanel(panel);
        });
    });

    panels.forEach((panel) => {
        const tabs = panel.querySelectorAll('[data-detail-tab]');
        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const mode = tab.dataset.detailTab || 'view';
                setMode(panel, mode);
            });
        });

        const closeBtn = panel.querySelector('[data-detail-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeAll);
        }
    });

    if (overlay) {
        overlay.addEventListener('click', closeAll);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeAll();
    });
}

function setupVersionBoard() {
    const boards = document.querySelectorAll('[data-version-board]');
    if (!boards.length) return;

    boards.forEach((board) => {
        const viewport = board.querySelector('[data-board-viewport]');
        if (!viewport) return;

        const resetBtn = board.querySelector('[data-board-reset]');

        let isPanning = false;
        let startX = 0;
        let startY = 0;
        let scrollLeft = 0;
        let scrollTop = 0;

        const shouldIgnore = (event) => {
            return event.target.closest('a, button, input, textarea, select, label, summary, .version-comment-panel, .comment-list-panel, .version-detail-panel, .version-detail-overlay');
        };

        const shouldAllowWheel = (event) => {
            return event.target.closest('.version-comment-panel, .comment-list-panel, .version-detail-panel, textarea, input, select');
        };

        const onPointerDown = (event) => {
            if (event.button !== 0) return;
            if (shouldIgnore(event)) return;
            event.preventDefault();

            isPanning = true;
            startX = event.clientX;
            startY = event.clientY;
            scrollLeft = viewport.scrollLeft;
            scrollTop = viewport.scrollTop;
            viewport.classList.add('is-panning');
            viewport.setPointerCapture(event.pointerId);
        };

        const onPointerMove = (event) => {
            if (!isPanning) return;
            event.preventDefault();
            const dx = event.clientX - startX;
            const dy = event.clientY - startY;
            viewport.scrollLeft = scrollLeft - dx;
            viewport.scrollTop = scrollTop - dy;
        };

        const endPan = (event) => {
            if (!isPanning) return;
            isPanning = false;
            viewport.classList.remove('is-panning');
            if (event?.pointerId) {
                viewport.releasePointerCapture(event.pointerId);
            }
        };

        const centerBoard = (smooth) => {
            const left = 0;
            const top = Math.max(0, (viewport.scrollHeight - viewport.clientHeight) / 2);
            if (smooth) {
                viewport.scrollTo({ left, top, behavior: 'smooth' });
                return;
            }
            viewport.scrollLeft = left;
            viewport.scrollTop = top;
        };

        viewport.addEventListener('pointerdown', onPointerDown);
        viewport.addEventListener('pointermove', onPointerMove);
        viewport.addEventListener('pointerup', endPan);
        viewport.addEventListener('pointercancel', endPan);
        viewport.addEventListener('pointerleave', endPan);
        viewport.addEventListener('wheel', (event) => {
            if (shouldAllowWheel(event)) return;
            event.preventDefault();
        }, { passive: false });

        if (resetBtn) {
            resetBtn.addEventListener('click', () => centerBoard(true));
        }

        setTimeout(() => centerBoard(false), 0);
    });
}

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

function renderProjectCharts() {
    const dashboardPanel = document.querySelector('[data-owner-panel="dashboard"]');
    if (dashboardPanel && dashboardPanel.hasAttribute('hidden')) {
        return;
    }

    const legacyActivePanel = document.querySelector('[data-panel].is-active');
    if (legacyActivePanel && legacyActivePanel.dataset.panel !== 'dashboard') {
        return;
    }

    const storageCanvas = document.getElementById('projectStorageChart');
    const versionsCanvas = document.getElementById('projectVersionsChart');

    if (!storageCanvas && !versionsCanvas) return;

    const storageSeries = parseSeries(storageCanvas);
    const versionsSeries = parseSeries(versionsCanvas);

    drawHorizontalBarChart(storageCanvas, storageSeries);
    drawHorizontalBarChart(versionsCanvas, versionsSeries);
}

let resizeTimer = null;
function scheduleChartRender() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(renderProjectCharts, 80);
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

setupVersionModal();
setupVersionCarousel();
setupVersionCommentPanels();
setupCommentListPanels();
setupVersionDetailPanels();
setupVersionBoard();
setupPanelToggles();
setupPanelTabs();
renderProjectCharts();

if (typeof window.initEntranceAnimations === 'function') {
    window.initEntranceAnimations();
}

document.addEventListener('owner:tabchange', (event) => {
    if (event.detail?.tabId !== 'dashboard') return;
    renderProjectCharts();
});
