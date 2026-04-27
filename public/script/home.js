//TOGGLE SIDE MENU FEATURE
document.querySelectorAll('.toggle').forEach(toggle => {
  toggle.addEventListener('click', (e) => {
    if (e.target.closest('.add-group-bt, .add-project-bt, .add-subfolder-bt')) return;

    const content = toggle.nextElementSibling;
    if (!content) return;

    const isClosed = content.classList.contains('collapsed');

    if (!isClosed) {
      content.classList.add('collapsed');
      toggle.classList.remove('active');
    } else {
      content.classList.remove('collapsed');
      toggle.classList.add('active');
    }
  });
});

//USER DROPDOWN
const userCont = document.getElementById('userCont');
const userDropdown = document.getElementById('user-dropdown');

if (userCont && userDropdown) {
  userCont.addEventListener('click', (e) => {
    e.stopPropagation();
    userDropdown.hidden = false;
    userDropdown.classList.toggle('show');
  });

  document.addEventListener('click', () => {
    userDropdown.classList.remove('show');
    userDropdown.hidden = true;
  });
}

// NOTIFICATION MENU
const notifBell = document.getElementById('notifBell');
const notMenu = document.getElementById('notMenu');
const closeNotMenu = document.getElementById('closeNotMenu');
const notificationsEnabled = String(document.body?.dataset.notificationsEnabled || '1') === '1';
const activeTenantId = parseInt(document.body?.dataset.activeTenantId || '0', 10) || 0;
const MOBILE_BREAKPOINT = 1024;
let hasAppliedViewportDefaults = false;
let lastViewportWasMobile = false;

// Retorna true quando a tela atual deve usar o layout mobile.
function isMobileViewport() {
    return window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT}px)`).matches;
}

// Controla exibicao e transicoes de componentes visuais.
function openNotMenu() {
    notMenu?.classList.add('show');
    document.body.classList.add('notifications-open');
    if (isMobileViewport()) {
        setSidebarHidden(true, false);
    }
}

// Controla exibicao e transicoes de componentes visuais.
function closeNotMenuFn() {
    notMenu?.classList.remove('show');
    document.body.classList.remove('notifications-open');
}

notifBell?.addEventListener('click', (e) => {
    e.stopPropagation();
    if (notMenu?.classList.contains('show')) {
        closeNotMenuFn();
    } else {
        openNotMenu();
    }
});

closeNotMenu?.addEventListener('click', (e) => {
    e.preventDefault();
    closeNotMenuFn();
});

document.addEventListener('click', (e) => {
    if (!notMenu || !notMenu.classList.contains('show')) return;
    if (notMenu.contains(e.target) || notifBell?.contains(e.target)) return;
    closeNotMenuFn();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeNotMenuFn();
    }
});

// Escapa conteudo textual para evitar injeção no HTML.
function escapeHtml(value) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };

    return String(value ?? '').replace(/[&<>"']/g, (char) => map[char] ?? char);
}

// Retorna o container que lista os cards de notificação.
function getNotificationListContainer() {
    return notMenu?.querySelector('.not-nav') ?? null;
}

// Calcula ids atualmente renderizados no painel de notificacoes.
function getRenderedNotificationIdsCsv() {
    const container = getNotificationListContainer();
    if (!container) return '';

    const ids = Array.from(container.querySelectorAll('.not-card[data-notification-id]'))
        .map((card) => parseInt(card.dataset.notificationId || '0', 10))
        .filter((id) => Number.isFinite(id) && id > 0);

    return ids.join(',');
}

// Recalcula e renderiza contadores da notificação em todos os pontos da UI.
function syncNotificationCounters() {
    const container = getNotificationListContainer();
    if (!container) return;

    const count = container.querySelectorAll('.not-card').length;

    let bellBadge = notifBell?.querySelector('.badge');
    if (count > 0) {
        if (!bellBadge && notifBell) {
            bellBadge = document.createElement('span');
            bellBadge.className = 'badge';
            notifBell.appendChild(bellBadge);
        }
        if (bellBadge) {
            bellBadge.textContent = String(count);
        }
    } else if (bellBadge) {
        bellBadge.remove();
    }

    const headerLeft = notMenu?.querySelector('.header-not-left');
    let panelCount = headerLeft?.querySelector('.not-count') ?? null;
    if (count > 0) {
        if (!panelCount && headerLeft) {
            panelCount = document.createElement('span');
            panelCount.className = 'not-count';
            headerLeft.appendChild(panelCount);
        }
        if (panelCount) {
            panelCount.textContent = String(count);
        }
    } else if (panelCount) {
        panelCount.remove();
    }

    const actionsContainer = notMenu?.querySelector('.header-not-actions');
    let clearForm = actionsContainer?.querySelector('.not-clear-form') ?? null;
    if (count > 0) {
        if (!clearForm && actionsContainer) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const destroyAllUrl = document.body?.dataset.notificationDestroyAllUrl || '/home/notification/destroy-all';

            clearForm = document.createElement('form');
            clearForm.className = 'not-clear-form';
            clearForm.method = 'POST';
            clearForm.action = destroyAllUrl;
            clearForm.onsubmit = () => confirm('Remover todas as notificacoes?');
            clearForm.innerHTML = `
                <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                <input type="hidden" name="ids" value="">
                <button type="submit" class="not-clear-btn">Limpar todas</button>
            `;

            const closeButton = actionsContainer.querySelector('#closeNotMenu');
            if (closeButton) {
                actionsContainer.insertBefore(clearForm, closeButton);
            } else {
                actionsContainer.appendChild(clearForm);
            }
        }

        const idsField = clearForm?.querySelector('input[name="ids"]');
        if (idsField) {
            idsField.value = getRenderedNotificationIdsCsv();
        }
    } else if (clearForm) {
        clearForm.remove();
    }
}

// Adiciona notificação recebida em tempo real sem precisar dar refresh.
function prependRealtimeNotification(payload) {
    if (!payload || !payload.id) return;
    if (!notificationsEnabled) return;

    const payloadTenantId = parseInt(payload.tenant_id || '0', 10) || 0;
    if (activeTenantId > 0 && payloadTenantId > 0 && payloadTenantId !== activeTenantId) {
        return;
    }
    if (activeTenantId > 0 && payloadTenantId <= 0) {
        return;
    }

    const container = getNotificationListContainer();
    if (!container) return;

    const notificationId = String(payload.id);
    if (container.querySelector(`.not-card[data-notification-id="${notificationId}"]`)) {
        return;
    }

    const emptyNode = container.querySelector('.not-empty');
    if (emptyNode) {
        emptyNode.remove();
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const destroyUrl = document.body?.dataset.notificationDestroyUrl || '/home/notification/destroy';

    const card = document.createElement('div');
    card.className = 'not-card';
    card.dataset.notificationId = notificationId;
    card.innerHTML = `
        <div class="not-text">${escapeHtml(payload.description || '')}</div>
        <form action="${escapeHtml(destroyUrl)}" method="POST">
            <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
            <input type="hidden" name="id" value="${escapeHtml(notificationId)}">
            <button type="submit" class="not-delete" title="Excluir">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                </svg>
            </button>
        </form>
    `;

    container.prepend(card);
    syncNotificationCounters();
}

// Inicializa conexão websocket privada do usuário para receber notificações.
function initRealtimeNotifications() {
    if (!notificationsEnabled) {
        return;
    }

    const body = document.body;
    const pusherConstructor = window.Pusher;

    if (!body || !pusherConstructor) {
        return;
    }

    const userId = parseInt(body.dataset.authUserId || '0', 10);
    const appKey = String(body.dataset.reverbAppKey || '');
    if (!userId || !appKey) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const host = String(body.dataset.reverbHost || window.location.hostname);
    const port = parseInt(body.dataset.reverbPort || '8080', 10);
    const scheme = String(body.dataset.reverbScheme || 'http').toLowerCase();
    const useTls = scheme === 'https';

    const client = new pusherConstructor(appKey, {
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: useTls,
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        cluster: 'mt1',
        channelAuthorization: {
            endpoint: '/broadcasting/auth',
            transport: 'ajax',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        }
    });

    const channel = client.subscribe(`private-App.Models.User.${userId}`);
    channel.bind('notification.created', (payload) => {
        prependRealtimeNotification(payload);
    });
}

if (notificationsEnabled) {
    syncNotificationCounters();
    initRealtimeNotifications();
}

//FILTER PERIOD HEATMAP
const periodFilter = document.getElementById('period-filter');

if (periodFilter) {
    periodFilter.addEventListener('change', function() {
        const months = parseInt(this.value, 10);
        filterByPeriod(months);
    });
    
    filterByPeriod(parseInt(periodFilter.value, 10) || 3);
}


// Executa a rotina 'filterByPeriod' no fluxo da interface.
function filterByPeriod(monthsToShow) {
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;
    
    const limitDate = new Date(currentYear, currentMonth - monthsToShow, 1);
    
    document.querySelectorAll('.month-cont').forEach(function(monthCont) {
        const year = parseInt(monthCont.closest('.year-cont').dataset.year);
        const month = parseInt(monthCont.dataset.month);
        
        const monthDate = new Date(year, month - 1, 1);
        
        if (monthDate >= limitDate && monthDate <= now) {
            monthCont.style.display = 'flex';
        } else {
            monthCont.style.display = 'none';
        }
    });
    
    document.querySelectorAll('.year-cont').forEach(function(yearCont) {
        const visibleMonths = yearCont.querySelectorAll('.month-cont[style*="display: flex"], .month-cont:not([style*="display: none"])').length;
        
        if (visibleMonths === 0) {
            yearCont.style.display = 'none';
        } else {
            yearCont.style.display = 'block';
        }
    });

        
    }

//SIDEBAR RESIZING FEATURE
const sidebar = document.getElementById('sidebar');
const resizer = document.getElementById('sidebar-resizer');
const toggleSidebarBtn = document.getElementById('toggle-sidebar');
const logo = document.querySelector('.header-side-menu img');
let isResizing = false;
let startX = 0;
let startWidth = 0;

// Carrega dados para manter a interface sincronizada.
function getSidebarBounds() {
    const rootStyles = getComputedStyle(document.documentElement);
    const fallbackMin = parseInt(rootStyles.getPropertyValue('--sidebar-min-width')) || 200;
    const fallbackMax = parseInt(rootStyles.getPropertyValue('--sidebar-max-width')) || 600;

    if (!sidebar) {
        return { min: fallbackMin, max: Math.max(fallbackMin, fallbackMax) };
    }

    const sidebarStyles = getComputedStyle(sidebar);
    const min = parseInt(sidebarStyles.minWidth) || fallbackMin;
    const max = parseInt(sidebarStyles.maxWidth) || fallbackMax;

    return { min, max: Math.max(min, max) };
}

// Executa a rotina 'clampSidebarWidth' no fluxo da interface.
function clampSidebarWidth(width, bounds = getSidebarBounds()) {
    const numericWidth = Number.isFinite(width) ? width : parseInt(width);
    if (Number.isNaN(numericWidth)) return bounds.min;
    return Math.max(bounds.min, Math.min(bounds.max, numericWidth));
}

// Atualiza o estado da interface apos interacoes do usuario.
function updateLogoSize(width) {
    if (!logo) return;

    const { min: minWidth, max: maxWidth } = getSidebarBounds();
    const boundedWidth = clampSidebarWidth(width, { min: minWidth, max: maxWidth });
    const minLogoSize = 100;
    const maxLogoSize = 280;
    const widthRange = Math.max(1, maxWidth - minWidth);

    const percentage = (boundedWidth - minWidth) / widthRange;
    const logoSize = minLogoSize + (percentage * (maxLogoSize - minLogoSize));

    logo.style.width = Math.max(minLogoSize, Math.min(maxLogoSize, logoSize)) + 'px';
}

// Executa a rotina 'applySidebarWidth' no fluxo da interface.
function applySidebarWidth(width, persist = false) {
    const clampedWidth = clampSidebarWidth(width);
    document.documentElement.style.setProperty('--sidebar-width', clampedWidth + 'px');
    updateLogoSize(clampedWidth);

    if (persist) {
        localStorage.setItem('sidebar-width', String(clampedWidth));
    }
}

const savedWidth = localStorage.getItem('sidebar-width');
if (savedWidth && !isNaN(savedWidth)) {
    applySidebarWidth(parseInt(savedWidth));
}

const sidebarHidden = localStorage.getItem('sidebar-hidden') === 'true';
if (sidebarHidden) {
    document.body.classList.add('sidebar-hidden');
}

// Atualiza visibilidade da sidebar e opcionalmente persiste em desktop.
function setSidebarHidden(hidden, persistDesktopState = true) {
    document.body.classList.toggle('sidebar-hidden', hidden);

    if (persistDesktopState && !isMobileViewport()) {
        localStorage.setItem('sidebar-hidden', hidden ? 'true' : 'false');
    }
}

// Em mobile, a sidebar inicia fechada para nao cobrir o conteudo.
function applyMobileSidebarDefaults() {
    if (!sidebar) return;

    const mobileNow = isMobileViewport();

    if (!hasAppliedViewportDefaults) {
        if (mobileNow && !document.body.classList.contains('sidebar-hidden')) {
            setSidebarHidden(true, false);
        } else if (!mobileNow) {
            const shouldBeHiddenDesktop = localStorage.getItem('sidebar-hidden') === 'true';
            setSidebarHidden(shouldBeHiddenDesktop, false);
        }

        hasAppliedViewportDefaults = true;
        lastViewportWasMobile = mobileNow;
        return;
    }

    if (mobileNow && !lastViewportWasMobile) {
        setSidebarHidden(true, false);
    } else if (!mobileNow && lastViewportWasMobile) {
        const shouldBeHiddenDesktop = localStorage.getItem('sidebar-hidden') === 'true';
        setSidebarHidden(shouldBeHiddenDesktop, false);
    }

    lastViewportWasMobile = mobileNow;
}

if (resizer && sidebar) {
    resizer.addEventListener('mousedown', (e) => {
        isResizing = true;
        startX = e.clientX;
        startWidth = sidebar.offsetWidth;

        document.body.classList.add('resizing');
        resizer.classList.add('resizing');
        e.preventDefault();
    });

    document.addEventListener('mousemove', (e) => {
        if (!isResizing) return;

        const width = startWidth + (e.clientX - startX);
        applySidebarWidth(width);
    });

    document.addEventListener('mouseup', () => {
        if (isResizing) {
            isResizing = false;
            document.body.classList.remove('resizing');
            resizer.classList.remove('resizing');

            const currentWidth = parseInt(getComputedStyle(document.documentElement)
                .getPropertyValue('--sidebar-width'));
            applySidebarWidth(currentWidth, true);
        }
    });
}

// Executa a rotina 'syncSidebarWidthToViewport' no fluxo da interface.
function syncSidebarWidthToViewport(persist = false) {
    const currentWidth = parseInt(getComputedStyle(document.documentElement)
        .getPropertyValue('--sidebar-width')) || (sidebar ? sidebar.offsetWidth : 280);
    applySidebarWidth(currentWidth, persist);
}

window.addEventListener('resize', () => {
    syncSidebarWidthToViewport(false);
    applyMobileSidebarDefaults();
});
applyMobileSidebarDefaults();

// Controla exibicao e transicoes de componentes visuais.
function toggleSidebar() {
    const isCurrentlyHidden = document.body.classList.contains('sidebar-hidden');
    const nextHidden = !isCurrentlyHidden;

    setSidebarHidden(nextHidden, true);

    if (!nextHidden && isMobileViewport()) {
        closeNotMenuFn();
    }
}

if (toggleSidebarBtn) {
    toggleSidebarBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
    });
}

document.addEventListener('keydown', (e) => {

    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
        e.preventDefault();
        e.stopPropagation();
        toggleSidebar();
    }
});

const floatBtn = document.createElement('button');
floatBtn.className = 'sidebar-toggle-float';
floatBtn.title = 'Mostrar Sidebar (Ctrl+B)';
floatBtn.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5z"/>
    </svg>
`;
floatBtn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    toggleSidebar();
});
document.body.appendChild(floatBtn);

document.addEventListener('click', (e) => {
    if (!isMobileViewport() || !sidebar) return;
    if (document.body.classList.contains('sidebar-hidden')) return;
    if (sidebar.contains(e.target)) return;
    if (toggleSidebarBtn?.contains(e.target)) return;
    if (floatBtn.contains(e.target)) return;

    setSidebarHidden(true, false);
});

// Inicializa estados e bindings necessarios da tela.
function initEntranceAnimations() {
    const items = Array.from(document.querySelectorAll('.container-data [data-animate]'));
    if (!items.length) return;

    const visibleItems = items.filter((item) => {
        const hiddenPanel = item.closest('[data-owner-panel][hidden]');
        return !hiddenPanel;
    });

    if (!visibleItems.length) return;

    let index = 0;
    visibleItems.forEach((item) => {
        item.classList.add('enter-item');
        item.classList.remove('is-visible');
        item.style.setProperty('--enter-delay', `${index * 60}ms`);
        index += 1;
    });

    requestAnimationFrame(() => {
        visibleItems.forEach((item) => item.classList.add('is-visible'));
    });
}

const SIDEBAR_MODE_STORAGE_KEY = 'sidebar-mode';
const SIDEBAR_MODE_CREATE = 'create';
const SIDEBAR_MODE_NAVIGATE = 'navigate';

// Monta uma chave estavel para persistir a aba ativa por pagina/contexto.
function getOwnerTabStorageKey(tabIds) {
    const tabScope = tabIds
        .map((tabId) => String(tabId || '').trim())
        .filter((tabId) => tabId.length > 0)
        .join('|');

    if (!tabScope) return '';
    return `owner-tab:${window.location.pathname}:${tabScope}`;
}

// Resolve rotulo padrao para itens de navegacao de painel.
function getDefaultSidebarNavLabel(tabId) {
    const labels = {
        home: 'inicio',
        dashboard: 'dashboard',
        labs: 'laboratorios',
        groups: 'grupos',
        projects: 'projetos',
        members: 'membros',
        versions: 'fluxos',
        tasks: 'tarefas',
        calendar: 'calendario'
    };

    if (labels[tabId]) return labels[tabId];
    return String(tabId || '').replace(/[_-]+/g, ' ');
}

// Resolve o icone exibido no modo de navegacao do sidebar.
function getSidebarNavIcon(iconId) {
    const icons = {
        home: '<path d="M8 1.2 1.5 6.4V15h5.2v-4.2h2.6V15h5.2V6.4z"/>',
        dashboard: '<path d="M1.5 1.5h5v5h-5zm8 0h5v5h-5zm-8 8h5v5h-5zm8 0h5v5h-5z"/>',
        labs: '<path d="M6 1h4v1l-1 2v3.4l4.22 6.33A1 1 0 0 1 12.39 15H3.61a1 1 0 0 1-.83-1.27L7 7.4V4L6 2zm1.8 7.2L4.67 13h6.66L8.2 8.2z"/>',
        groups: '<path d="M5.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5m5 0a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5M1 13.5C1 11.57 2.57 10 4.5 10h2c1.93 0 3.5 1.57 3.5 3.5V15H1zm8 1.5v-1.5c0-.83-.23-1.61-.62-2.28.45-.15.93-.22 1.42-.22h2c1.93 0 3.5 1.57 3.5 3.5V15z"/>',
        members: '<path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5.5 7a5.5 5.5 0 0 1 11 0z"/>',
        projects: '<path d="M3 1.5h8a2 2 0 0 1 2 2V14a.5.5 0 0 1-.5.5H4a2 2 0 0 1-2-2V2.5a1 1 0 0 1 1-1m0 2v9A1 1 0 0 0 4 13.5h8V3.5a1 1 0 0 0-1-1H3.5a.5.5 0 0 0-.5.5"/>',
        versions: '<path d="M8 1l6.5 3.2L8 7.4 1.5 4.2zm-6.5 6L8 10.2 14.5 7v2.8L8 13 1.5 9.8zm0 4L8 14.2l6.5-3.2V13.8L8 17l-6.5-3.2z"/>',
        tasks: '<path d="M2 2.5h7v1H2zm0 5h7v1H2zm0 5h7v1H2zM12.4 2l.7.7-2.9 2.9-1.4-1.4.7-.7.7.7zM12.4 7l.7.7-2.9 2.9-1.4-1.4.7-.7.7.7zM12.4 12l.7.7-2.9 2.9-1.4-1.4.7-.7.7.7z"/>',
        calendar: '<path d="M11 1v2H5V1H4v2H2v12h12V3h-2V1zm2 13H3V6h10zM4.5 8h2v2h-2zm0 3h2v2h-2zm3 0h2v2h-2zm3 0h2v2h-2z"/>'
    };

    return icons[iconId] || icons.dashboard;
}

// Coleta os paineis da pagina para montar a navegacao por icones.
function collectOwnerPanelDefinitions() {
    const panels = Array.from(document.querySelectorAll('[data-owner-panel]'));
    if (!panels.length) return [];

    const seen = new Set();
    const items = [];

    panels.forEach((panel, index) => {
        const panelId = String(panel.dataset.ownerPanel || '').trim();
        if (!panelId || seen.has(panelId)) return;
        seen.add(panelId);

        const orderValue = Number.parseInt(String(panel.dataset.navOrder || ''), 10);
        const order = Number.isFinite(orderValue) ? orderValue : index;
        const label = String(panel.dataset.navLabel || '').trim() || getDefaultSidebarNavLabel(panelId);
        const icon = String(panel.dataset.navIcon || '').trim() || panelId;

        items.push({
            id: panelId,
            panelElementId: String(panel.id || '').trim(),
            label,
            icon,
            order
        });
    });

    return items.sort((a, b) => a.order - b.order);
}

// Monta os botoes de navegacao do sidebar com base nas secoes da pagina.
function renderSidebarNavigationItems() {
    const navList = document.querySelector('[data-sidebar-nav-list]');
    const navEmpty = document.querySelector('[data-sidebar-nav-empty]');
    if (!navList) return [];

    const panelDefs = collectOwnerPanelDefinitions();
    const homeUrl = String(document.body?.dataset.sidebarHomeUrl || '').trim();
    const isHomePage = String(document.body?.dataset.sidebarIsHome || '') === '1';
    const hasHomeShortcut = homeUrl.length > 0 && !isHomePage;
    navList.innerHTML = '';

    if (!panelDefs.length && !hasHomeShortcut) {
        navList.hidden = true;
        if (navEmpty) navEmpty.hidden = false;
        return [];
    }

    navList.hidden = false;
    if (navEmpty) navEmpty.hidden = true;

    if (hasHomeShortcut) {
        const homeLink = document.createElement('a');
        homeLink.className = 'side-nav-btn side-nav-btn-home';
        homeLink.href = homeUrl;
        homeLink.setAttribute('title', getDefaultSidebarNavLabel('home'));
        homeLink.innerHTML = `
            <span class="side-nav-btn-icon" aria-hidden="true">
                <svg viewBox="0 0 16 16" fill="currentColor">
                    ${getSidebarNavIcon('home')}
                </svg>
            </span>
            <span class="side-nav-btn-label">${getDefaultSidebarNavLabel('home')}</span>
        `;
        navList.appendChild(homeLink);
    }

    panelDefs.forEach((panelDef) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'side-nav-btn';
        button.dataset.ownerTab = panelDef.id;
        button.setAttribute('role', 'tab');
        button.setAttribute('aria-selected', 'false');
        button.setAttribute('tabindex', '-1');
        button.setAttribute('title', panelDef.label);
        if (panelDef.panelElementId) {
            button.setAttribute('aria-controls', panelDef.panelElementId);
        }

        button.innerHTML = `
            <span class="side-nav-btn-icon" aria-hidden="true">
                <svg viewBox="0 0 16 16" fill="currentColor">
                    ${getSidebarNavIcon(panelDef.icon)}
                </svg>
            </span>
            <span class="side-nav-btn-label">${panelDef.label}</span>
        `;

        navList.appendChild(button);
    });

    return panelDefs;
}

// Aplica modo de sidebar e sincroniza estado visual.
function setupSidebarModes() {
    const modeToggle = document.querySelector('[data-sidebar-mode-toggle]');
    if (!modeToggle) return;

    const modeButtons = Array.from(modeToggle.querySelectorAll('[data-sidebar-mode]'));
    const createPane = document.querySelector('[data-sidebar-pane="create"]');
    const navigatePane = document.querySelector('[data-sidebar-pane="navigate"]');
    const addLabContainer = document.querySelector('.body-side-menu > .bt');

    if (!modeButtons.length || !createPane || !navigatePane) return;

    const panelDefs = renderSidebarNavigationItems();
    const navList = document.querySelector('[data-sidebar-nav-list]');
    const hasNavigation = (navList?.children?.length ?? 0) > 0;

    modeToggle.classList.toggle('is-single', !hasNavigation);

    const navigateButton = modeButtons.find((button) => button.dataset.sidebarMode === SIDEBAR_MODE_NAVIGATE);
    if (navigateButton) {
        navigateButton.disabled = !hasNavigation;
        navigateButton.hidden = !hasNavigation;
    }

    const normalizeMode = (value) => {
        if (value === SIDEBAR_MODE_NAVIGATE && hasNavigation) {
            return SIDEBAR_MODE_NAVIGATE;
        }
        return SIDEBAR_MODE_CREATE;
    };

    const applyMode = (mode, persist = true) => {
        const normalizedMode = normalizeMode(mode);

        document.body.classList.toggle('sidebar-mode-create', normalizedMode === SIDEBAR_MODE_CREATE);
        document.body.classList.toggle('sidebar-mode-navigate', normalizedMode === SIDEBAR_MODE_NAVIGATE);

        createPane.hidden = normalizedMode !== SIDEBAR_MODE_CREATE;
        navigatePane.hidden = normalizedMode !== SIDEBAR_MODE_NAVIGATE;

        if (addLabContainer) {
            addLabContainer.hidden = normalizedMode !== SIDEBAR_MODE_CREATE;
        }

        modeButtons.forEach((button) => {
            const isActive = button.dataset.sidebarMode === normalizedMode;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            button.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        if (!persist) return;
        try {
            localStorage.setItem(SIDEBAR_MODE_STORAGE_KEY, normalizedMode);
        } catch (error) {
            // Ignora falhas de storage para manter navegacao funcional.
        }
    };

    modeButtons.forEach((button) => {
        if (button.dataset.sidebarModeBound === '1') return;
        button.dataset.sidebarModeBound = '1';

        button.addEventListener('click', () => {
            applyMode(button.dataset.sidebarMode, true);
        });
    });

    let storedMode = SIDEBAR_MODE_CREATE;
    try {
        storedMode = localStorage.getItem(SIDEBAR_MODE_STORAGE_KEY) || SIDEBAR_MODE_CREATE;
    } catch (error) {
        storedMode = SIDEBAR_MODE_CREATE;
    }

    applyMode(storedMode, false);
}

// Configura comportamento e listeners desta interface.
function setupOwnerTabs() {
    const tabs = Array.from(document.querySelectorAll('[data-owner-tab]'))
        .filter((tab) => String(tab.dataset.ownerTab || '').trim().length > 0);
    const panels = Array.from(document.querySelectorAll('[data-owner-panel]'));
    if (!tabs.length || !panels.length) return;

    const tabIds = Array.from(new Set(
        tabs.map((tab) => String(tab.dataset.ownerTab || '').trim()).filter((tabId) => tabId.length > 0)
    ));
    if (!tabIds.length) return;

    const storageKey = getOwnerTabStorageKey(tabIds);

    const readStoredTab = () => {
        if (!storageKey) return null;

        try {
            const storedTab = localStorage.getItem(storageKey);
            if (!storedTab) return null;
            return tabIds.includes(storedTab) ? storedTab : null;
        } catch (error) {
            return null;
        }
    };

    const persistTab = (tabId) => {
        if (!storageKey || !tabId) return;

        try {
            localStorage.setItem(storageKey, tabId);
        } catch (error) {
            // Ignora falhas de storage para manter navegacao funcional.
        }
    };

    // Executa a rotina 'activateTab' no fluxo da interface.
    const activateTab = (tabId, shouldFocus = false) => {
        if (!tabIds.includes(tabId)) return;

        tabs.forEach((tab) => {
            const isActive = tab.dataset.ownerTab === tabId;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
            if (isActive && shouldFocus) {
                tab.focus();
            }
        });

        panels.forEach((panel) => {
            const isActive = panel.dataset.ownerPanel === tabId;
            panel.classList.toggle('is-active', isActive);

            if (isActive) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });

        persistTab(tabId);
        initEntranceAnimations();
        document.dispatchEvent(new CustomEvent('owner:tabchange', { detail: { tabId } }));
    };

    tabs.forEach((tab) => {
        if (tab.dataset.ownerTabBound !== '1') {
            tab.dataset.ownerTabBound = '1';
            tab.addEventListener('click', () => {
                activateTab(tab.dataset.ownerTab);
            });
        }
    });

    const tabLists = Array.from(new Set(
        tabs
            .map((tab) => tab.closest('[data-owner-tablist], .owner-tabs'))
            .filter((tabList) => tabList)
    ));

    tabLists.forEach((tabList) => {
        if (tabList.dataset.ownerTabListBound === '1') return;
        tabList.dataset.ownerTabListBound = '1';

        tabList.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) return;

            const listTabs = tabs.filter((tab) => tabList.contains(tab));
            if (!listTabs.length) return;

            const currentIndexRaw = listTabs.findIndex((tab) => tab.classList.contains('is-active'));
            const currentIndex = currentIndexRaw >= 0 ? currentIndexRaw : 0;
            let nextIndex = currentIndex;

            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex = currentIndex > 0 ? currentIndex - 1 : listTabs.length - 1;
            }
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex = currentIndex < listTabs.length - 1 ? currentIndex + 1 : 0;
            }
            if (event.key === 'Home') {
                nextIndex = 0;
            }
            if (event.key === 'End') {
                nextIndex = listTabs.length - 1;
            }

            event.preventDefault();
            activateTab(listTabs[nextIndex].dataset.ownerTab, true);
        });
    });

    const storedTab = readStoredTab();
    const visiblePanelTab = panels.find((panel) => !panel.hasAttribute('hidden'))?.dataset.ownerPanel;
    const initialTab = storedTab
        || tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.ownerTab
        || (tabIds.includes(visiblePanelTab) ? visiblePanelTab : null)
        || tabIds[0];
    activateTab(initialTab);
}

window.initEntranceAnimations = initEntranceAnimations;
window.setupOwnerTabs = setupOwnerTabs;

// Configura comportamento e listeners desta interface.
function setupOverviewTablePagination() {
    const tables = Array.from(document.querySelectorAll('table[data-overview-page-size]'));
    if (!tables.length) return;

    tables.forEach((table) => {
        const pageSize = parseInt(table.dataset.overviewPageSize || '4', 10);
        if (!Number.isFinite(pageSize) || pageSize <= 0) return;

        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const dataRows = Array.from(tbody.querySelectorAll('tr'))
            .filter((row) => !row.querySelector('.table-empty'));
        const emptyRow = tbody.querySelector('.table-empty')?.closest('tr') || null;
        const wrapper = table.closest('.overview-table');
        if (!wrapper) return;

        let pager = wrapper.nextElementSibling;
        if (!pager || !pager.classList.contains('overview-pagination')) {
            pager = document.createElement('div');
            pager.className = 'overview-pagination';
            pager.setAttribute('data-overview-pagination', '1');
            wrapper.insertAdjacentElement('afterend', pager);
        }

        if (dataRows.length <= pageSize) {
            dataRows.forEach((row) => {
                row.hidden = false;
            });
            if (emptyRow) {
                emptyRow.hidden = false;
            }
            pager.hidden = true;
            return;
        }

        const totalPages = Math.max(1, Math.ceil(dataRows.length / pageSize));
        let currentPage = parseInt(table.dataset.overviewPageCurrent || '1', 10);
        if (!Number.isFinite(currentPage) || currentPage < 1) {
            currentPage = 1;
        }
        currentPage = Math.min(currentPage, totalPages);

        pager.hidden = false;
        pager.innerHTML = `
            <button type="button" class="overview-page-btn" data-page-prev>&lsaquo; Anterior</button>
            <span class="overview-page-info" data-page-info></span>
            <button type="button" class="overview-page-btn" data-page-next>Próxima &rsaquo;</button>
        `;

        const prevBtn = pager.querySelector('[data-page-prev]');
        const nextBtn = pager.querySelector('[data-page-next]');
        const infoEl = pager.querySelector('[data-page-info]');

        // Renderiza elementos dinamicos com base no estado atual.
        const renderPage = () => {
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;

            dataRows.forEach((row, index) => {
                row.hidden = index < start || index >= end;
            });

            if (emptyRow) {
                emptyRow.hidden = true;
            }

            table.dataset.overviewPageCurrent = String(currentPage);
            if (infoEl) {
                infoEl.textContent = `Página ${currentPage} de ${totalPages}`;
            }
            if (prevBtn) {
                prevBtn.disabled = currentPage <= 1;
            }
            if (nextBtn) {
                nextBtn.disabled = currentPage >= totalPages;
            }
        };

        prevBtn?.addEventListener('click', () => {
            if (currentPage <= 1) return;
            currentPage -= 1;
            renderPage();
        });

        nextBtn?.addEventListener('click', () => {
            if (currentPage >= totalPages) return;
            currentPage += 1;
            renderPage();
        });

        renderPage();
    });
}

// Atualiza estado visual do card expansivel.
function setOverviewCardExpanded(card, expanded) {
    if (!card) return;

    const toggle = card.querySelector('[data-overview-toggle]');
    if (!toggle) return;

    const panelId = toggle.getAttribute('aria-controls');
    const panel = panelId ? document.getElementById(panelId) : card.querySelector('.overview-entity-expand');
    if (!panel) return;

    card.classList.toggle('is-expanded', expanded);
    panel.hidden = !expanded;
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

    const openLabel = toggle.dataset.openLabel || 'ver detalhes';
    const closeLabel = toggle.dataset.closeLabel || 'ocultar detalhes';
    toggle.textContent = expanded ? closeLabel : openLabel;
}

// Configura comportamento e listeners desta interface.
function setupOverviewCardExpansion() {
    const cards = Array.from(document.querySelectorAll('.overview-entity-card'));
    if (!cards.length) return;

    cards.forEach((card) => {
        setOverviewCardExpanded(card, false);
    });

    if (document.body.dataset.overviewExpandDelegated === '1') return;
    document.body.dataset.overviewExpandDelegated = '1';

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-overview-toggle]');
        if (!toggle) return;

        const card = toggle.closest('.overview-entity-card');
        if (!card) return;

        event.preventDefault();
        event.stopPropagation();

        const shouldExpand = !card.classList.contains('is-expanded');
        const siblings = Array.from(card.parentElement?.querySelectorAll('.overview-entity-card.is-expanded') || []);
        siblings.forEach((sibling) => {
            if (sibling !== card) {
                setOverviewCardExpanded(sibling, false);
            }
        });

        setOverviewCardExpanded(card, shouldExpand);
    });
}

// Configura comportamento e listeners desta interface.
function setupOverviewEntityActions() {
    const editButtons = Array.from(document.querySelectorAll('[data-overview-edit]'));
    editButtons.forEach((button) => {
        if (button.dataset.actionBound === '1') return;
        button.dataset.actionBound = '1';

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            renameSidebarItem(button);
        });
    });

    const deleteButtons = Array.from(document.querySelectorAll('[data-overview-delete]'));
    deleteButtons.forEach((button) => {
        if (button.dataset.actionBound === '1') return;
        button.dataset.actionBound = '1';

        button.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();
            await deleteSidebarItem(button);
        });
    });
}

// Troca mes do calendario sem recarregar a pagina inteira.
function setupCalendarAsyncNavigation() {
    if (document.body?.dataset.calendarNavBound === '1') return;
    if (!document.querySelector('.calendar-nav')) return;
    document.body.dataset.calendarNavBound = '1';

    document.addEventListener('click', async (event) => {
        const navLink = event.target.closest('a.calendar-nav');
        if (!navLink) return;
        if (event.defaultPrevented) return;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const href = String(navLink.getAttribute('href') || '').trim();
        if (!href) return;

        const calendarSection = navLink.closest('.calendar-logs');
        if (!calendarSection) return;

        event.preventDefault();
        if (calendarSection.dataset.loading === '1') return;

        const targetUrl = new URL(href, window.location.href);
        calendarSection.dataset.loading = '1';
        calendarSection.classList.add('is-loading');

        try {
            const response = await fetch(targetUrl.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`Falha ao carregar calendario (${response.status}).`);
            }

            const html = await response.text();
            const nextDoc = new DOMParser().parseFromString(html, 'text/html');

            const currentSections = Array.from(document.querySelectorAll('.calendar-logs'));
            const sectionIndex = Math.max(currentSections.indexOf(calendarSection), 0);
            const nextSections = Array.from(nextDoc.querySelectorAll('.calendar-logs'));
            const nextSection = nextSections[sectionIndex] || nextDoc.querySelector('.calendar-logs');

            if (!nextSection) {
                throw new Error('Calendario nao encontrado na resposta.');
            }

            const currentBody = calendarSection.querySelector('.body-calendar');
            const nextBody = nextSection.querySelector('.body-calendar');
            if (currentBody && nextBody) {
                currentBody.replaceWith(nextBody);
            }

            const currentEventLists = Array.from(calendarSection.querySelectorAll('.calendar-events-list'));
            const nextEventLists = Array.from(nextSection.querySelectorAll('.calendar-events-list'));
            const replaceCount = Math.min(currentEventLists.length, nextEventLists.length);

            for (let index = 0; index < replaceCount; index += 1) {
                currentEventLists[index].replaceWith(nextEventLists[index]);
            }

            const historyUrl = `${targetUrl.pathname}${targetUrl.search}${targetUrl.hash}`;
            window.history.replaceState(window.history.state, '', historyUrl);
        } catch (err) {
            console.error(err);
            window.location.assign(targetUrl.toString());
        } finally {
            calendarSection.classList.remove('is-loading');
            delete calendarSection.dataset.loading;
        }
    });
}

// Executa a rotina 'runHomeAnimations' no fluxo da interface.
const runHomeAnimations = () => {
    setupSidebarModes();

    if (document.querySelector('[data-owner-panel]')) {
        setupOwnerTabs();
    } else {
        initEntranceAnimations();
    }

    setupOverviewTablePagination();
    setupOverviewCardExpansion();
    setupOverviewEntityActions();
    setupCalendarAsyncNavigation();
};

if (document.readyState === 'loading') {
    window.addEventListener('DOMContentLoaded', runHomeAnimations);
} else {
    runHomeAnimations();
}
window.addEventListener('pageshow', runHomeAnimations);

const initialWidth = parseInt(getComputedStyle(document.documentElement)
    .getPropertyValue('--sidebar-width')) || 280;
applySidebarWidth(initialWidth);
syncSidebarWidthToViewport(false);

// STATUS SELECT (HEADER)
function attachStatusSelect({ selectId, formId, idField, entityLabel }) {
    const select = document.getElementById(selectId);
    const form = document.getElementById(formId);

    if (!select || !form) return;

    select.addEventListener('change', async () => {
        const previousValue = select.dataset.current || '';
        const nextValue = select.value;

        if (previousValue == nextValue) return;

        const optionLabel = select.options[select.selectedIndex]?.text ?? nextValue;
        const confirmed = confirm(`Confirmar alteração de status de ${entityLabel} para "${optionLabel}"?`);

        if (!confirmed) {
            select.value = previousValue || nextValue;
            return;
        }

        const idInput = form.querySelector(`input[name="${idField}"]`);
        const entityId = idInput?.value;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        if (!entityId || !csrfToken) {
            alert('Nao foi possivel atualizar o status.');
            select.value = previousValue || nextValue;
            return;
        }

        try {
            const payload = { status: nextValue };
            payload[idField] = entityId;

            const response = await fetch(form.action, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(errorText);
            }

            select.dataset.current = nextValue;
        } catch (err) {
            console.error(err);
            alert(`Erro ao atualizar o status de ${entityLabel}.`);
            select.value = previousValue || nextValue;
        }
    });
}

attachStatusSelect({
    selectId: 'selStatus',
    formId: 'labStatusForm',
    idField: 'lab_id',
    entityLabel: 'laboratório'
});

attachStatusSelect({
    selectId: 'groupStatusSelect',
    formId: 'groupStatusForm',
    idField: 'group_id',
    entityLabel: 'grupo'
});

attachStatusSelect({
    selectId: 'projectStatusSelect',
    formId: 'projectStatusForm',
    idField: 'project_id',
    entityLabel: 'projeto'
});

//OPEN EVENT FORM
const eventForm = document.getElementById('eventForm');
const overlay = document.getElementById('eventOverlay');

const openEventBtn = document.getElementById('openEventFormBtn');

if (openEventBtn && overlay && eventForm) {
    openEventBtn.addEventListener('click', (e) => {
        e.preventDefault();
        overlay.classList.add('show');
        eventForm.classList.add('show');
    });

    overlay.addEventListener('click', closeEventForm);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeEventForm();
        }
    });

    document.getElementById('closeForm')?.addEventListener('click', closeEventForm);
    document.getElementById('eventOverlay')?.addEventListener('click', closeEventForm);
}

// Controla exibicao e transicoes de componentes visuais.
function closeEventForm() {
    overlay?.classList.remove('show');
    eventForm?.classList.remove('show');
}

if (eventForm?.classList.contains('show')) {
    overlay?.classList.add('show');
}

// Shared ajax form helpers.
function getFormBody(form) {
    return form?.querySelector('.body-form') || form;
}

function clearFormAlert(form) {
    const alertBox = form?.querySelector('.form-alert.js-form-alert');
    if (alertBox) {
        alertBox.remove();
    }
}

function renderFormAlert(form, messages = []) {
    if (!form || messages.length === 0) {
        return;
    }

    clearFormAlert(form);
    const body = getFormBody(form);
    if (!body) {
        return;
    }

    const alertBox = document.createElement('div');
    alertBox.className = 'form-alert js-form-alert';

    const list = document.createElement('ul');
    messages.forEach((message) => {
        if (!message) {
            return;
        }

        const item = document.createElement('li');
        item.textContent = String(message);
        list.appendChild(item);
    });

    alertBox.appendChild(list);
    body.prepend(alertBox);
}

function collectFormErrors(payload, fallbackMessage) {
    if (!payload || typeof payload !== 'object') {
        return [fallbackMessage];
    }

    if (payload.errors && typeof payload.errors === 'object') {
        const messages = [];
        Object.values(payload.errors).forEach((entry) => {
            if (Array.isArray(entry)) {
                entry.forEach((message) => {
                    if (message) {
                        messages.push(String(message));
                    }
                });
                return;
            }

            if (entry) {
                messages.push(String(entry));
            }
        });

        if (messages.length > 0) {
            return messages;
        }
    }

    if (payload.message) {
        return [String(payload.message)];
    }

    return [fallbackMessage];
}

async function parseJsonPayload(response) {
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
        return null;
    }

    try {
        return await response.json();
    } catch (err) {
        console.error(err);
        return null;
    }
}

function setFormSubmitting(form, loadingText, submitButton = null) {
    const targetButton = submitButton || form?.querySelector('button[type="submit"]');
    if (!targetButton) {
        return () => {};
    }

    if (!targetButton.dataset.defaultHtml) {
        targetButton.dataset.defaultHtml = targetButton.innerHTML;
    }

    targetButton.disabled = true;
    targetButton.setAttribute('aria-busy', 'true');
    targetButton.textContent = loadingText;

    return () => {
        targetButton.disabled = false;
        targetButton.removeAttribute('aria-busy');
        targetButton.innerHTML = targetButton.dataset.defaultHtml || targetButton.innerHTML;
    };
}

eventForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    clearFormAlert(eventForm);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        renderFormAlert(eventForm, ['Token CSRF nao encontrado.']);
        return;
    }

    const stopSubmitting = setFormSubmitting(eventForm, 'Criando...');

    try {
        const response = await fetch(eventForm.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: new FormData(eventForm)
        });

        const payload = await parseJsonPayload(response);

        if (!response.ok || !payload || payload.success !== true) {
            const messages = collectFormErrors(payload, 'Nao foi possivel criar o evento.');
            renderFormAlert(eventForm, messages);
            return;
        }

        eventForm.reset();
        closeEventForm();
        window.location.reload();
    } catch (err) {
        console.error(err);
        renderFormAlert(eventForm, [err.message || 'Nao foi possivel criar o evento.']);
    } finally {
        stopSubmitting();
    }
});

// STUDENT INVITE FORM
const studentInviteForm = document.getElementById('studentInviteForm');
const studentOverlay = document.getElementById('studentOverlay');
const inviteLabInput = document.getElementById('student-invite-lab');
const inviteGroupInput = document.getElementById('student-invite-group');
const inviteLabName = document.getElementById('student-invite-lab-name');
const inviteGroupName = document.getElementById('student-invite-group-name');
const inviteResultCard = document.getElementById('inviteResultCard');
const inviteResultUrl = document.getElementById('invite-result-url');
const inviteResultOpen = document.getElementById('invite-result-open');
const inviteResultRaw = document.getElementById('invite-result-raw');
const inviteResultMeta = document.getElementById('invite-result-meta');
const inviteCopyBtn = document.getElementById('inviteCopyBtn');
const inviteCardButtons = Array.from(document.querySelectorAll('[data-open-student-invite]'));

// Atualiza o card de resultado do convite com dados recentes.
function renderInviteResultCard({
    inviteUrl = '',
    inviteLabel = 'Abrir convite',
    inviteExpiresAt = '',
    inviteGroupName = '',
    inviteLabName = ''
} = {}) {
    if (!inviteResultCard) return;

    const normalizedUrl = inviteUrl ? String(inviteUrl) : '';
    const normalizedLabel = inviteLabel ? String(inviteLabel) : 'Abrir convite';
    const normalizedExpiresAt = inviteExpiresAt ? String(inviteExpiresAt) : '';
    const normalizedGroupName = inviteGroupName ? String(inviteGroupName) : '';
    const normalizedLabName = inviteLabName ? String(inviteLabName) : '';

    if (!normalizedUrl) {
        inviteResultCard.hidden = true;
        return;
    }

    inviteResultCard.hidden = false;
    inviteResultCard.dataset.groupName = normalizedGroupName;

    if (inviteResultUrl) {
        inviteResultUrl.value = normalizedUrl;
    }
    if (inviteResultOpen) {
        inviteResultOpen.href = normalizedUrl;
        inviteResultOpen.textContent = normalizedLabel;
    }
    if (inviteResultRaw) {
        inviteResultRaw.textContent = normalizedUrl;
    }
    if (inviteResultMeta) {
        const parts = [];
        if (normalizedExpiresAt) {
            parts.push(`Expira em ${normalizedExpiresAt}`);
        }
        if (normalizedGroupName) {
            parts.push(`Grupo: ${normalizedGroupName}`);
        }
        if (normalizedLabName) {
            parts.push(`Lab: ${normalizedLabName}`);
        }
        inviteResultMeta.textContent = parts.join(' | ');
    }
}

// Copia o link do convite atualmente renderizado.
async function copyInviteResultLink() {
    const currentUrl = inviteResultUrl?.value ? String(inviteResultUrl.value) : '';
    if (!currentUrl) {
        renderFormAlert(studentInviteForm, ['Nao existe link de convite para copiar.']);
        return;
    }

    try {
        if (!navigator?.clipboard?.writeText) {
            throw new Error('Copie manualmente o link exibido.');
        }

        await navigator.clipboard.writeText(currentUrl);
        renderFormAlert(studentInviteForm, ['Link copiado para a area de transferencia.']);
    } catch (err) {
        console.error(err);
        renderFormAlert(studentInviteForm, [err.message || 'Nao foi possivel copiar o link.']);
    }
}

inviteCopyBtn?.addEventListener('click', () => {
    copyInviteResultLink();
});

// Controla exibicao e transicoes de componentes visuais.
function openStudentInviteFormFromGroup(payload = {}) {
    if (!studentOverlay || !studentInviteForm) return;

    clearFormAlert(studentInviteForm);

    const { labId, groupId, labName, groupName } = payload;

    if (inviteLabInput) {
        inviteLabInput.value = labId ? String(labId) : '';
    }
    if (inviteGroupInput) {
        inviteGroupInput.value = groupId ? String(groupId) : '';
    }
    if (inviteLabName) {
        inviteLabName.textContent = labName || 'Laboratorio nao definido';
    }
    if (inviteGroupName) {
        inviteGroupName.textContent = groupName || 'Selecione um grupo no card';
    }

    studentOverlay.classList.add('show');
    studentInviteForm.classList.add('show');
}

inviteCardButtons.forEach((button) => {
    button.addEventListener('click', (event) => {
        event.preventDefault();
        openStudentInviteFormFromGroup({
            labId: button.dataset.labId,
            groupId: button.dataset.groupId,
            labName: button.dataset.labName,
            groupName: button.dataset.groupName
        });
    });
});

// Controla exibicao e transicoes de componentes visuais.
function closeStudentInviteForm() {
    studentOverlay?.classList.remove('show');
    studentInviteForm?.classList.remove('show');
}

studentOverlay?.addEventListener('click', closeStudentInviteForm);
document.getElementById('closeStudentInviteForm')?.addEventListener('click', closeStudentInviteForm);

if (studentInviteForm?.classList.contains('show')) {
    studentOverlay?.classList.add('show');
}

studentInviteForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    clearFormAlert(studentInviteForm);

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        renderFormAlert(studentInviteForm, ['Token CSRF nao encontrado.']);
        return;
    }

    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    const actionType = submitter?.dataset?.inviteAction || 'generate';
    const requestUrl = submitter?.getAttribute('formaction') || studentInviteForm.action;
    const requestMethod = (submitter?.getAttribute('formmethod') || studentInviteForm.method || 'POST').toUpperCase();
    const fallbackError = actionType === 'revoke'
        ? 'Nao foi possivel revogar os links ativos.'
        : 'Nao foi possivel enviar o convite.';
    const loadingLabel = submitter?.dataset?.submittingLabel
        || (actionType === 'revoke' ? 'Revogando links...' : 'Enviando...');

    const stopSubmitting = setFormSubmitting(studentInviteForm, loadingLabel, submitter);

    try {
        const response = await fetch(requestUrl, {
            method: requestMethod,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: new FormData(studentInviteForm)
        });

        const payload = await parseJsonPayload(response);

        if (!response.ok || !payload || payload.success !== true) {
            const messages = collectFormErrors(payload, fallbackError);
            renderFormAlert(studentInviteForm, messages);
            return;
        }

        if (actionType === 'revoke') {
            const revokedCount = Number(payload?.revoked_count || 0);
            renderFormAlert(studentInviteForm, [
                payload?.message || 'Links ativos revogados com sucesso.',
                revokedCount > 0 ? `${revokedCount} link(s) revogado(s).` : '',
            ]);
            return;
        }

        const inviteUrl = payload?.invite_url ? String(payload.invite_url) : '';
        const inviteLabel = payload?.invite_label ? String(payload.invite_label) : 'Abrir convite';
        const inviteExpiresAt = payload?.invite_expires_at ? String(payload.invite_expires_at) : '';
        const inviteGroupName = payload?.invite_group_name ? String(payload.invite_group_name) : '';
        const inviteLabName = payload?.invite_lab_name ? String(payload.invite_lab_name) : '';
        let copied = false;
        if (inviteUrl && navigator?.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(inviteUrl);
                copied = true;
            } catch (err) {
                console.warn(err);
            }
        }

        renderInviteResultCard({
            inviteUrl,
            inviteLabel,
            inviteExpiresAt,
            inviteGroupName,
            inviteLabName
        });

        renderFormAlert(studentInviteForm, [
            payload?.message || 'Link de convite gerado com sucesso.',
            inviteUrl ? inviteLabel : 'Nao foi possivel obter o link de convite.',
            copied ? 'Link copiado para a area de transferencia.' : 'Copie o link manualmente.',
            !copied && inviteUrl ? `URL completa: ${inviteUrl}` : '',
        ]);

    } catch (err) {
        console.error(err);
        renderFormAlert(studentInviteForm, [err.message || fallbackError]);
    } finally {
        stopSubmitting();
    }
});
//ADD LAB FEATURE
const addLabBtn = document.querySelector('.add-bt-lab');
let isCreatingLab = false;
let currentLabInput = null;

if (addLabBtn) {
    addLabBtn.addEventListener('click', (e) => {
        e.preventDefault();
        
        if (isCreatingLab) return;
        
        createLabInput();
    });
}

// Executa a rotina 'createLabInput' no fluxo da interface.
function createLabInput() {
    isCreatingLab = true;
    
    const sideMenuNav = document.querySelector('[data-sidebar-pane="create"]');
    if (!sideMenuNav) return;
    
    const newLabContainer = document.createElement('div');
    newLabContainer.className = 'lab-tag new-lab-input active';
    
    newLabContainer.innerHTML = `
        <svg  class="glass" fill="#ffffff" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 31.166 31.166" xml:space="preserve" stroke="#ffffff" stroke-width="0.00031166" transform="matrix(1, 0, 0, 1, 0, 0)"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#CCCCCC" stroke-width="0.062332"></g><g id="SVGRepo_iconCarrier"> <g> <g> <path d="M28.055,24.561l-7.717-11.044V3.442c0.575-0.197,0.99-0.744,0.99-1.386V1.464C21.329,0.657,20.673,0,19.866,0h-8.523 c-0.807,0-1.464,0.657-1.464,1.464v0.593c0,0.642,0.416,1.189,0.992,1.386v10l-7.76,11.118c-0.898,1.289-1.006,2.955-0.28,4.348 c0.727,1.393,2.154,2.258,3.725,2.258h18.056c1.571,0,2.999-0.866,3.725-2.259C29.062,27.514,28.954,25.848,28.055,24.561z M17.505,3.048v11.21c0,0.097,0.029,0.191,0.085,0.27l2.028,2.904h-8.077l0.906-1.298h3.135c0.261,0,0.472-0.211,0.472-0.473 c0-0.261-0.211-0.472-0.472-0.472h-2.476l0.512-0.733c0.055-0.08,0.084-0.173,0.084-0.271v-0.294h1.879 c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.472-0.472-0.472h-1.879v-1.299h1.879c0.261,0,0.472-0.211,0.472-0.472 c0-0.261-0.211-0.472-0.472-0.472h-1.879V9.405h1.879c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.473-0.472-0.473h-1.879 V7.162h1.879c0.261,0,0.472-0.211,0.472-0.472c0-0.261-0.211-0.472-0.472-0.472h-1.879v-3.17H17.505z M25.825,27.598 c-0.236,0.453-0.702,0.734-1.213,0.734H6.556c-0.511,0-0.976-0.282-1.212-0.734c-0.237-0.453-0.202-0.994,0.09-1.414l5.448-7.807 h9.396l5.454,7.805C26.025,26.602,26.06,27.145,25.825,27.598z"></path> <path d="M15.583,19.676h-3.272c-0.261,0-0.472,0.211-0.472,0.473c0,0.261,0.211,0.472,0.472,0.472h3.272 c0.261,0,0.472-0.211,0.472-0.472C16.056,19.887,15.845,19.676,15.583,19.676z"></path> <circle cx="10.113" cy="25.402" r="1.726"></circle> <circle cx="17.574" cy="22.321" r="0.512"></circle> <circle cx="20.977" cy="25.302" r="0.904"></circle> <circle cx="14.723" cy="25.174" r="0.776"></circle> </g> </g> </g></svg>
        <input 
            name="name"
            type="text" 
            class="lab-name-input" 
            placeholder="Nome do laboratório..." 
            maxlength="50"
            autofocus
        >
        <div class="lab-input-actions">
            <button type="submit" class="lab-input-confirm" title="Confirmar (Enter)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </button>
            <button class="lab-input-cancel" title="Cancelar (Esc)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    `;
    
    sideMenuNav.insertBefore(newLabContainer, sideMenuNav.firstChild);
    
    const input = newLabContainer.querySelector('.lab-name-input');
    const confirmBtn = newLabContainer.querySelector('.lab-input-confirm');
    const cancelBtn = newLabContainer.querySelector('.lab-input-cancel');
    
    currentLabInput = input;
    
    setTimeout(() => input.focus(), 50);
    
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            confirmLab(input.value.trim(), newLabContainer);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelLabCreation(newLabContainer);
        }
    });
    
    confirmBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        confirmLab(input.value.trim(), newLabContainer);
    });

    cancelBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        cancelLabCreation(newLabContainer);
    });
    
    document.addEventListener('click', function outsideClickHandler(e) {
        if (!newLabContainer.contains(e.target) && e.target !== addLabBtn) {
            cancelLabCreation(newLabContainer);
            document.removeEventListener('click', outsideClickHandler);
        }
    });
}

// Executa a rotina 'confirmLab' no fluxo da interface.
function confirmLab(labName, container) {

    if (!labName) {
        alert('Informe um nome para o laboratório');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!csrfToken) {
        alert('CSRF token não encontrado');
        return;
    }

    container.classList.add('loading');
    container.querySelector('.lab-name-input').disabled = true;

    fetch('/home/lab', {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ name: labName })
    })
    .then(async response => {
        if (!response.ok) {
            const error = await extractErrorMessage(response);
            throw new Error(error);
        }
        return response.json();
    })
    .then(data => {
        container.classList.remove('loading');
        container.classList.remove('new-lab-input');

        isCreatingLab = false;
        currentLabInput = null;

        location.reload();
    })
    .catch(err => {
        console.error(err);

        alert(err.message || 'Erro ao criar laboratório');

        container.classList.remove('loading');
        container.querySelector('.lab-name-input').disabled = false;
    });
}

// Executa a rotina 'extractErrorMessage' no fluxo da interface.
async function extractErrorMessage(response) {
  const contentType = response.headers.get('content-type') || '';
  if (contentType.includes('application/json')) {
    try {
      const data = await response.json();
      if (data.message) return data.message;
      if (data.errors) {
        const firstError = Object.values(data.errors)[0];
        if (Array.isArray(firstError)) return firstError[0];
      }
    } catch (err) {
      console.error(err);
    }
  }

  const text = await response.text();
  return text || 'Erro inesperado.';
}

// Executa a rotina 'sendSidebarDeleteRequest' no fluxo da interface.
async function sendSidebarDeleteRequest(url, csrfToken) {
  const baseHeaders = {
    'Accept': 'application/json',
    'X-CSRF-TOKEN': csrfToken
  };

  const deleteResponse = await fetch(url, {
    method: 'DELETE',
    headers: baseHeaders
  });

  if (deleteResponse.status !== 405) {
    return deleteResponse;
  }

  return fetch(url, {
    method: 'POST',
    headers: {
      ...baseHeaders,
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
    },
    body: new URLSearchParams({
      _method: 'DELETE'
    }).toString()
  });
}

const sidebarRenameConfig = {
  lab: {
    updateUrl: '/home/lab/status',
    idField: 'lab_id',
    nameField: 'name',
    label: 'laboratorio'
  },
  group: {
    updateUrl: '/home/group/status',
    idField: 'group_id',
    nameField: 'name',
    label: 'grupo'
  },
  project: {
    updateUrl: '/home/project/status',
    idField: 'project_id',
    nameField: 'title',
    label: 'projeto'
  },
  subfolder: {
    updateUrl: '/home/subfolder/status',
    idField: 'subfolder_id',
    nameField: 'name',
    label: 'subfolder'
  }
};

const sidebarRole = String(document.body?.dataset?.sidebarRole || '').toLowerCase();
const canSidebarDelete = document.body?.dataset?.canSidebarDelete === '1';
const canSidebarRename = document.body?.dataset?.canSidebarRename === '1';
const ownedLabIds = new Set(
  String(document.body?.dataset?.ownedLabIds || '')
    .split(',')
    .map((value) => value.trim())
    .filter((value) => value !== '')
);

const sidebarDeleteConfig = {
  lab: {
    deleteUrl: (id) => `/home/lab/${id}`,
    label: 'laboratorio'
  },
  group: {
    deleteUrl: (id) => `/home/group/${id}`,
    label: 'grupo'
  },
  project: {
    deleteUrl: (id) => `/home/project/${id}`,
    label: 'projeto'
  },
  subfolder: {
    deleteUrl: (id) => `/home/subfolder/${id}`,
    label: 'subfolder'
  }
};

const renameNavigationTimers = new WeakMap();
let isSidebarRenameSubmitting = false;
let isSidebarDeleteSubmitting = false;
let sidebarContextMenu = null;

// Resolve o lab relacionado ao item da sidebar para validar permissoes de exclusao.
function resolveSidebarLabId(target) {
  if (!target) return null;

  const directLabId = String(target.dataset?.labId || '').trim();
  if (directLabId) {
    return directLabId;
  }

  if (target.dataset?.renameType === 'lab') {
    return String(target.dataset.renameId || '').trim() || null;
  }

  const labContent = target.closest('.lab-content');
  if (labContent) {
    const labToggle = labContent.previousElementSibling;
    const labTarget = labToggle?.querySelector?.('.rename-target[data-rename-type="lab"]');
    const labId = String(labTarget?.dataset?.renameId || '').trim();
    return labId || null;
  }

  return null;
}

// Verifica se professor pode excluir o item atual.
function canTeacherDeleteTarget(target) {
  if (sidebarRole !== 'teacher') return true;
  const labId = resolveSidebarLabId(target);
  if (!labId) return false;
  return ownedLabIds.has(String(labId));
}

// Carrega dados para manter a interface sincronizada.
function getSidebarRenameTargetFromEvent(event) {
  if (!event) return null;
  const directTarget = event.target?.closest?.('.rename-target');
  const sidebarRow = event.target?.closest?.('.lab-tag, .group-tag, .proj, .subfolder');
  const renameTarget = directTarget || sidebarRow?.querySelector('.rename-target');

  if (!renameTarget) return null;
  if (renameTarget.closest('.new-lab-input, .new-input, .new-group-input, .new-project-input, .new-subfolder-input')) {
    return null;
  }

  return renameTarget;
}

// Executa a rotina 'hideSidebarContextMenu' no fluxo da interface.
function hideSidebarContextMenu() {
  if (!sidebarContextMenu) return;
  sidebarContextMenu.hidden = true;
  sidebarContextMenu.renameTarget = null;
}

// Executa a rotina 'ensureSidebarContextMenu' no fluxo da interface.
function ensureSidebarContextMenu() {
  if (sidebarContextMenu) return sidebarContextMenu;

  const menu = document.createElement('div');
  menu.className = 'sidebar-context-menu';
  menu.hidden = true;
  menu.innerHTML = `
    <button type="button" class="sidebar-context-menu__item sidebar-context-menu__item--danger" data-sidebar-delete>
      Excluir
    </button>
  `;

  menu.addEventListener('contextmenu', (event) => {
    event.preventDefault();
  });

  const deleteButton = menu.querySelector('[data-sidebar-delete]');
  deleteButton?.addEventListener('click', async (event) => {
    event.preventDefault();
    event.stopPropagation();

    const target = menu.renameTarget;
    hideSidebarContextMenu();

    if (target) {
      await deleteSidebarItem(target);
    }
  });

  document.body.appendChild(menu);
  sidebarContextMenu = menu;

  return menu;
}

// Executa a rotina 'showSidebarContextMenu' no fluxo da interface.
function showSidebarContextMenu(event, renameTarget) {
  const menu = ensureSidebarContextMenu();
  menu.renameTarget = renameTarget;
  menu.hidden = false;
  menu.style.visibility = 'hidden';
  menu.style.left = '0px';
  menu.style.top = '0px';

  const margin = 8;
  const maxLeft = Math.max(margin, window.innerWidth - menu.offsetWidth - margin);
  const maxTop = Math.max(margin, window.innerHeight - menu.offsetHeight - margin);
  const left = Math.min(Math.max(margin, event.clientX), maxLeft);
  const top = Math.min(Math.max(margin, event.clientY), maxTop);

  menu.style.left = `${left}px`;
  menu.style.top = `${top}px`;
  menu.style.visibility = 'visible';
}

// Executa a rotina 'deleteSidebarItem' no fluxo da interface.
async function deleteSidebarItem(target) {
  if (isSidebarDeleteSubmitting) return;

  const entityType = target?.dataset?.renameType;
  const entityId = target?.dataset?.renameId;
  const currentValue = (target?.dataset?.renameValue || target?.textContent || '').trim();
  const config = sidebarDeleteConfig[entityType];

  if (!config || !entityId) return;
  if (sidebarRole === 'student' && entityType !== 'project') {
    alert('Aluno pode excluir apenas projetos.');
    return;
  }
  if (sidebarRole === 'teacher' && !canTeacherDeleteTarget(target)) {
    alert('Professor só pode excluir itens de laboratórios que criou.');
    return;
  }

  const confirmed = confirm(
    `Deseja excluir o ${config.label} "${currentValue}"?\n` +
    'Esta acao nao pode ser desfeita.'
  );
  if (!confirmed) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!csrfToken) {
    alert('Token CSRF nao encontrado.');
    return;
  }

  isSidebarDeleteSubmitting = true;

  try {
    const response = await sendSidebarDeleteRequest(config.deleteUrl(entityId), csrfToken);

    if (!response.ok) {
      throw new Error(await extractErrorMessage(response));
    }

    let redirectUrl = '';
    const contentType = response.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      try {
        const payload = await response.json();
        redirectUrl = payload?.redirect_url || '';
      } catch (err) {
        console.error(err);
      }
    }

    if (redirectUrl) {
      window.location.assign(redirectUrl);
      return;
    }

    window.location.reload();
  } catch (err) {
    console.error(err);
    alert(err.message || 'Nao foi possivel excluir o item.');
  } finally {
    isSidebarDeleteSubmitting = false;
  }
}

// Executa a rotina 'clearRenameNavigationTimer' no fluxo da interface.
function clearRenameNavigationTimer(target) {
  const timer = renameNavigationTimers.get(target);
  if (timer) {
    clearTimeout(timer);
    renameNavigationTimers.delete(target);
  }
}

// Executa a rotina 'scheduleRenameTargetNavigation' no fluxo da interface.
function scheduleRenameTargetNavigation(target, event) {
  if (!(target instanceof HTMLAnchorElement)) return;

  const href = target.getAttribute('href');
  if (!href || href === '#' || href.startsWith('javascript:')) return;

  if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

  event.preventDefault();
  event.stopPropagation();

  clearRenameNavigationTimer(target);

  const timer = window.setTimeout(() => {
    renameNavigationTimers.delete(target);
    window.location.assign(href);
  }, 220);

  renameNavigationTimers.set(target, timer);
}

// Executa a rotina 'syncSidebarRenameValue' no fluxo da interface.
function syncSidebarRenameValue(type, id, nextValue) {
  document.querySelectorAll(`.rename-target[data-rename-type="${type}"]`).forEach((element) => {
    if (String(element.dataset.renameId) !== String(id)) return;
    element.dataset.renameValue = nextValue;
    element.textContent = nextValue;
  });
}

// Executa a rotina 'renameSidebarItem' no fluxo da interface.
async function renameSidebarItem(target) {
  if (isSidebarRenameSubmitting) return;

  const entityType = target?.dataset?.renameType;
  const entityId = target?.dataset?.renameId;
  const currentValue = (target?.dataset?.renameValue || target?.textContent || '').trim();
  const config = sidebarRenameConfig[entityType];

  if (!config || !entityId) return;

  const nextValueInput = prompt(`Novo nome do ${config.label}:`, currentValue);
  if (nextValueInput === null) return;

  const nextValue = nextValueInput.trim();
  if (!nextValue) {
    alert('Informe um nome valido.');
    return;
  }
  if (nextValue === currentValue) return;

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!csrfToken) {
    alert('Token CSRF nao encontrado.');
    return;
  }

  isSidebarRenameSubmitting = true;

  try {
    const payload = {};
    payload[config.idField] = entityId;
    payload[config.nameField] = nextValue;

    const response = await fetch(config.updateUrl, {
      method: 'PUT',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      throw new Error(await extractErrorMessage(response));
    }

    syncSidebarRenameValue(entityType, entityId, nextValue);
  } catch (err) {
    console.error(err);
    alert(err.message || 'Nao foi possivel renomear.');
  } finally {
    isSidebarRenameSubmitting = false;
  }
}

document.addEventListener('click', (event) => {
  const target = event.target.closest('.rename-target');
  if (!target) return;

  if (target.closest('.new-lab-input, .new-input, .new-group-input, .new-project-input, .new-subfolder-input')) {
    return;
  }

  scheduleRenameTargetNavigation(target, event);
}, true);

document.addEventListener('dblclick', (event) => {
  if (!canSidebarRename) return;
  if (event.target.closest('input, textarea, select, button')) return;
  if (event.target.closest('.add-group-bt, .add-project-bt, .add-subfolder-bt, .add-bt-lab')) return;

  const renameTarget = getSidebarRenameTargetFromEvent(event);

  if (!renameTarget) return;
  if (sidebarRole === 'student' && !['project', 'subfolder'].includes(renameTarget.dataset.renameType)) return;

  event.preventDefault();
  event.stopPropagation();
  clearRenameNavigationTimer(renameTarget);
  hideSidebarContextMenu();

  renameSidebarItem(renameTarget);
}, true);

if (canSidebarDelete) {
  document.addEventListener('contextmenu', (event) => {
    if (event.target.closest('input, textarea, select, button')) return;
    if (event.target.closest('.add-group-bt, .add-project-bt, .add-subfolder-bt, .add-bt-lab')) return;

    const renameTarget = getSidebarRenameTargetFromEvent(event);
    if (!renameTarget) return;
    if (sidebarRole === 'student' && renameTarget.dataset.renameType !== 'project') return;
    if (sidebarRole === 'teacher' && !canTeacherDeleteTarget(renameTarget)) return;

    event.preventDefault();
    event.stopPropagation();
    clearRenameNavigationTimer(renameTarget);
    showSidebarContextMenu(event, renameTarget);
  }, true);

  document.addEventListener('click', (event) => {
    if (!sidebarContextMenu || sidebarContextMenu.hidden) return;
    if (sidebarContextMenu.contains(event.target)) return;
    hideSidebarContextMenu();
  }, true);

  document.addEventListener('scroll', () => {
    hideSidebarContextMenu();
  }, true);

  window.addEventListener('resize', () => {
    hideSidebarContextMenu();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      hideSidebarContextMenu();
    }
  });
}


// Executa a rotina 'cancelLabCreation' no fluxo da interface.
function cancelLabCreation(container) {
    container.classList.add('canceling');
    setTimeout(() => {
        container.remove();
        isCreatingLab = false;
        currentLabInput = null;
    }, 200);
}

// ADD GROUP FEATURE
let isCreatingGroup = false;

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.add-group-bt');
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  if (isCreatingGroup) return;

  const labId = btn.dataset.labId;
  const labTag = btn.closest('.lab-tag');
  const labContent = labTag?.nextElementSibling;

  if (!labId || !labContent || !labContent.classList.contains('lab-content')) return;

  labContent.classList.remove('collapsed');
  labTag.classList.add('active');

  createGroupInput(labContent, labId, btn);
});

// Executa a rotina 'createGroupInput' no fluxo da interface.
function createGroupInput(labContent, labId, addGroupBtn) {
  isCreatingGroup = true;

  const newGroupContainer = document.createElement('div');
  newGroupContainer.className = 'group-tag new-input active';

  newGroupContainer.innerHTML = `
    <svg class="group" fill="#ffffff" height="200px" width="200px" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24" xml:space="preserve"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g id="group"> <path d="M24,15.9c0-2.8-1.5-5-3.7-6.1C21.3,8.8,22,7.5,22,6c0-2.8-2.2-5-5-5c-2.1,0-3.8,1.2-4.6,3c0,0,0,0,0,0c-0.1,0-0.3,0-0.4,0 c-0.1,0-0.3,0-0.4,0c0,0,0,0,0,0C10.8,2.2,9.1,1,7,1C4.2,1,2,3.2,2,6c0,1.5,0.7,2.8,1.7,3.8C1.5,10.9,0,13.2,0,15.9V20h5v3h14v-3h5 V15.9z M17,3c1.7,0,3,1.3,3,3c0,1.6-1.3,3-3,3c0-1.9-1.1-3.5-2.7-4.4c0,0,0,0,0,0C14.8,3.6,15.8,3,17,3z M13.4,4.2 C13.4,4.2,13.4,4.2,13.4,4.2C13.4,4.2,13.4,4.2,13.4,4.2z M15,9c0,1.7-1.3,3-3,3s-3-1.3-3-3s1.3-3,3-3S15,7.3,15,9z M10.6,4.2 C10.6,4.2,10.6,4.2,10.6,4.2C10.6,4.2,10.6,4.2,10.6,4.2z M7,3c1.2,0,2.2,0.6,2.7,1.6C8.1,5.5,7,7.1,7,9C5.3,9,4,7.7,4,6S5.3,3,7,3 z M5.1,18H2v-2.1C2,13.1,4.1,11,7,11v0c0,0,0,0,0,0c0.1,0,0.2,0,0.3,0c0,0,0,0,0,0c0.3,0.7,0.8,1.3,1.3,1.8 C6.7,13.8,5.4,15.7,5.1,18z M17,21H7v-2.1c0-2.8,2.2-4.9,5-4.9c2.9,0,5,2.1,5,4.9V21z M22,18h-3.1c-0.3-2.3-1.7-4.2-3.7-5.2 c0.6-0.5,1-1.1,1.3-1.8c0.1,0,0.2,0,0.4,0v0c2.9,0,5,2.1,5,4.9V18z"></path> </g> </g></svg>

    <input
      name="name"
      type="text"
      class="group-name-input"
      placeholder="Nome do Grupo..."
      maxlength="50"
      autocomplete="off"
      style="margin-left: 14px;"
      autofocus
    >

    <div class="input-actions">
      <button type="submit" class="input-confirm" title="Confirmar (Enter)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </button>
      <button type="button" class="input-cancel" title="Cancelar (Esc)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
  `;

  labContent.insertBefore(newGroupContainer, labContent.firstChild);

  const input = newGroupContainer.querySelector('.group-name-input');
  const confirmBtn = newGroupContainer.querySelector('.input-confirm');
  const cancelBtn = newGroupContainer.querySelector('.input-cancel');

  setTimeout(() => input?.focus(), 0);

  input?.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      confirmGroup(input.value.trim(), labId, newGroupContainer);
    } else if (ev.key === 'Escape') {
      ev.preventDefault();
      cancelGroupCreation(newGroupContainer);
    }
  });

  confirmBtn?.addEventListener('click', (ev) => {
    ev.preventDefault();
    ev.stopPropagation();
    confirmGroup(input.value.trim(), labId, newGroupContainer);
  });

  cancelBtn?.addEventListener('click', (ev) => {
    ev.preventDefault();
    ev.stopPropagation();
    cancelGroupCreation(newGroupContainer);
  });

  document.addEventListener('click', function outside(ev) {
    if (!newGroupContainer.contains(ev.target) && ev.target !== addGroupBtn) {
      cancelGroupCreation(newGroupContainer);
      document.removeEventListener('click', outside);
    }
  });
}

// Executa a rotina 'confirmGroup' no fluxo da interface.
function confirmGroup(groupName, labId, container) {
  if (!groupName) return alert('Informe um nome para o grupo');

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!csrfToken) return alert('CSRF token não encontrado');

  container.classList.add('loading');
  const input = container.querySelector('.group-name-input');
  if (input) input.disabled = true;

  fetch('/home/lab/group', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken
    },
    body: JSON.stringify({ name: groupName, lab_id: labId })
  })
    .then(async res => {
      if (!res.ok) throw new Error(await extractErrorMessage(res));
      return res.json();
    })
    .then(() => location.reload())
    .catch(err => {
      console.error(err);
      alert(err.message || 'Erro ao criar grupo');
      container.classList.remove('loading');
      if (input) input.disabled = false;
      isCreatingGroup = false;
    });
}

// Executa a rotina 'cancelGroupCreation' no fluxo da interface.
function cancelGroupCreation(container) {
  container.classList.add('canceling');
  setTimeout(() => {
    container.remove();
    isCreatingGroup = false;
  }, 200);
}

// ADD PROJECT FEATURE
let isCreatingProject = false;

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.add-project-bt');
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  if (isCreatingProject) return;

  const groupId = btn.dataset.groupId;
  const labId = btn.dataset.labId;
  const groupTag = btn.closest('.group-tag');
  const groupContent = groupTag?.nextElementSibling;

  if (!groupId || !groupContent || !groupContent.classList.contains('group-content')) return;

  groupContent.classList.remove('collapsed');
  groupTag.classList.add('active');

  createProjectInput(groupContent, groupId, labId, btn);
});

// Executa a rotina 'createProjectInput' no fluxo da interface.
function createProjectInput(groupContent, groupId, labId, addProjectBtn) {
  isCreatingProject = true;

  const newProjectContainer = document.createElement('div');
  newProjectContainer.className = 'proj new-project-input active';

  newProjectContainer.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-journal" viewBox="0 0 16 16">
      <path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1h1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v1H1V2a2 2 0 0 1 2-2"/>
      <path d="M1 5v-.5a.5.5 0 0 1 1 0V5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0V8h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1z"/>
    </svg>

    <input
      name="title"
      type="text"
      class="project-title-input"
      placeholder="Nome do Projeto..."
      maxlength="80"
      autocomplete="off"
      autofocus
    >

    <div class="project-input-actions">
      <button type="submit" class="project-input-confirm" title="Confirmar (Enter)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </button>
      <button type="button" class="project-input-cancel" title="Cancelar (Esc)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
  `;

  groupContent.insertBefore(newProjectContainer, groupContent.firstChild);

  const input = newProjectContainer.querySelector('.project-title-input');
  const confirmBtn = newProjectContainer.querySelector('.project-input-confirm');
  const cancelBtn = newProjectContainer.querySelector('.project-input-cancel');

  setTimeout(() => input?.focus(), 0);

  input?.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      confirmProject(input.value.trim(), groupId, labId, newProjectContainer);
    } else if (ev.key === 'Escape') {
      ev.preventDefault();
      cancelProjectCreation(newProjectContainer);
    }
  });

  confirmBtn?.addEventListener('click', (ev) => {
    ev.preventDefault();
    ev.stopPropagation();
    confirmProject(input.value.trim(), groupId, labId, newProjectContainer);
  });

  cancelBtn?.addEventListener('click', (ev) => {
    ev.preventDefault();
    ev.stopPropagation();
    cancelProjectCreation(newProjectContainer);
  });

  document.addEventListener('click', function outside(ev) {
    if (!newProjectContainer.contains(ev.target) && ev.target !== addProjectBtn) {
      cancelProjectCreation(newProjectContainer);
      document.removeEventListener('click', outside);
    }
  });
}

let projectDescModal = null;

// Executa a rotina 'ensureProjectDescModal' no fluxo da interface.
function ensureProjectDescModal() {
  if (projectDescModal) return projectDescModal;

  if (!document.getElementById('project-desc-style')) {
    const style = document.createElement('style');
    style.id = 'project-desc-style';
    style.textContent = `
.project-desc-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease;
  z-index: 999;
}
.project-desc-modal {
  position: fixed;
  top: 12%;
  left: 50%;
  transform: translateX(-50%) translateY(-6px);
  width: min(520px, 92vw);
  background: var(--surface-2, #ffffff);
  color: var(--text-1, #111111);
  border: 1px solid var(--border, #d9d9d9);
  border-radius: 12px;
  box-shadow: var(--shadow-1, 0 12px 24px rgba(0, 0, 0, 0.35));
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease, transform 0.15s ease;
  z-index: 1000;
}
.project-desc-overlay.is-open {
  opacity: 1;
  pointer-events: auto;
}
.project-desc-modal.is-open {
  opacity: 1;
  pointer-events: auto;
  transform: translateX(-50%) translateY(0);
}
.project-desc-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border, #e0e0e0);
}
.project-desc-header h3 {
  margin: 0;
  font-size: 16px;
}
.project-desc-actions {
  display: flex;
  gap: 8px;
}
.project-desc-confirm,
.project-desc-cancel {
  border: 1px solid var(--border, #d0d0d0);
  background: var(--surface-3, #f1f3f5);
  color: inherit;
  border-radius: 8px;
  padding: 6px 10px;
  cursor: pointer;
  font-size: 13px;
}
.project-desc-confirm {
  background: #ffb74d;
  border-color: #ffb74d;
  color: #1b1b1b;
}
.project-desc-body {
  padding: 12px 16px 16px;
}
.project-desc-body textarea {
  width: 100%;
  min-height: 120px;
  resize: vertical;
  border-radius: 8px;
  padding: 10px;
  border: 1px solid var(--border, #cccccc);
  background: var(--surface-3, #f7f7f7);
  color: inherit;
}
.project-desc-error {
  min-height: 14px;
  margin-top: 8px;
  color: #ff6b6b;
  font-size: 12px;
}
.project-desc-hint {
  margin-top: 6px;
  color: var(--muted, #777777);
  font-size: 12px;
}
    `;
    document.head.appendChild(style);
  }

  const overlay = document.createElement('div');
  overlay.className = 'project-desc-overlay';

  const modal = document.createElement('div');
  modal.className = 'project-desc-modal';
  modal.innerHTML = `
    <div class="project-desc-header">
      <h3 class="project-desc-title">Descricao do projeto</h3>
      <div class="project-desc-actions">
        <button type="button" class="project-desc-confirm">Confirmar</button>
        <button type="button" class="project-desc-cancel">Cancelar</button>
      </div>
    </div>
    <div class="project-desc-body">
      <textarea rows="4" placeholder="Escreva uma breve descricao..."></textarea>
      <div class="project-desc-error"></div>
      <div class="project-desc-hint">Minimo 3 caracteres.</div>
    </div>
  `;

  document.body.appendChild(overlay);
  document.body.appendChild(modal);

  projectDescModal = {
    overlay,
    modal,
    titleEl: modal.querySelector('.project-desc-title'),
    textarea: modal.querySelector('textarea'),
    confirmBtn: modal.querySelector('.project-desc-confirm'),
    cancelBtn: modal.querySelector('.project-desc-cancel'),
    errorEl: modal.querySelector('.project-desc-error')
  };

  return projectDescModal;
}

// Controla exibicao e transicoes de componentes visuais.
function openProjectDescriptionModal(projectTitle) {
  const modalRef = ensureProjectDescModal();

  modalRef.titleEl.textContent = `Descricao do projeto: ${projectTitle}`;
  modalRef.textarea.value = '';
  modalRef.errorEl.textContent = '';

  modalRef.overlay.classList.add('is-open');
  modalRef.modal.classList.add('is-open');
  setTimeout(() => modalRef.textarea.focus(), 0);

  return new Promise((resolve) => {
    // Executa a rotina 'onConfirm' no fluxo da interface.
    const onConfirm = () => {
      const value = modalRef.textarea.value.trim();
      if (value.length < 3) {
        modalRef.errorEl.textContent = 'Descricao precisa ter ao menos 3 caracteres.';
        modalRef.textarea.focus();
        return;
      }
      cleanup();
      resolve(value);
    };

    // Executa a rotina 'onCancel' no fluxo da interface.
    const onCancel = () => {
      cleanup();
      resolve(null);
    };

    // Executa a rotina 'onKey' no fluxo da interface.
    const onKey = (ev) => {
      if (ev.key === 'Escape') {
        ev.preventDefault();
        onCancel();
      }
      if ((ev.ctrlKey || ev.metaKey) && ev.key === 'Enter') {
        ev.preventDefault();
        onConfirm();
      }
    };

    // Executa a rotina 'cleanup' no fluxo da interface.
    function cleanup() {
      modalRef.overlay.classList.remove('is-open');
      modalRef.modal.classList.remove('is-open');
      modalRef.confirmBtn.removeEventListener('click', onConfirm);
      modalRef.cancelBtn.removeEventListener('click', onCancel);
      modalRef.overlay.removeEventListener('click', onCancel);
      document.removeEventListener('keydown', onKey);
    }

    modalRef.confirmBtn.addEventListener('click', onConfirm);
    modalRef.cancelBtn.addEventListener('click', onCancel);
    modalRef.overlay.addEventListener('click', onCancel);
    document.addEventListener('keydown', onKey);
  });
}

// Executa a rotina 'confirmProject' no fluxo da interface.
async function confirmProject(projectTitle, groupId, labId, container) {
  if (!projectTitle) return alert('Informe um nome para o Projeto');

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!csrfToken) return alert('Token CSRF não encontrado');

  const description = await openProjectDescriptionModal(projectTitle);
  if (!description) return;

  container.classList.add('loading');
  const input = container.querySelector('.project-title-input');
  if (input) input.disabled = true;

  fetch('/home/lab/group/project', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken
    },
    body: JSON.stringify({ title: projectTitle, description, group_id: groupId, lab_id: labId })
  })
    .then(async res => {
      if (!res.ok) throw new Error(await extractErrorMessage(res));
      return res.json();
    })
    .then(() => location.reload())
    .catch(err => {
      console.error(err);
      alert(err.message || 'Erro ao criar Projeto');
      container.classList.remove('loading');
      if (input) input.disabled = false;
      isCreatingProject = false;
    });
}

// Executa a rotina 'cancelProjectCreation' no fluxo da interface.
function cancelProjectCreation(container) {
  container.classList.add('canceling');
  setTimeout(() => {
    container.remove();
    isCreatingProject = false;
  }, 200);
}

// ADD SUBFOLDER FEATURE
let isCreatingSubfolder = false;

document.addEventListener('click', (e) => {
  const btn = e.target.closest('.add-subfolder-bt');
  if (!btn) return;

  e.preventDefault();
  e.stopPropagation();

  if (isCreatingSubfolder) return;

  const projectId = btn.dataset.projectId;
  const projectTag = btn.closest('.proj');
  const projectContent = projectTag?.nextElementSibling;

  if (!projectId || !projectContent || !projectContent.classList.contains('project-content')) return;

  projectContent.classList.remove('collapsed');
  projectTag.classList.add('active');

  createSubfolderInput(projectContent, projectId, btn);
});

// Executa a rotina 'createSubfolderInput' no fluxo da interface.
function createSubfolderInput(projectContent, projectId, addSubfolderBtn) {
  isCreatingSubfolder = true;

  const newSubfolderContainer = document.createElement('div');
  newSubfolderContainer.className = 'subfolder new-subfolder-input active';

  newSubfolderContainer.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-folder-fill" viewBox="0 0 16 16">
      <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3m-8.322.12q.322-.119.684-.12h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981z"/>
    </svg>

    <input
      name="name"
      type="text"
      class="subfolder-name-input"
      placeholder="Nome da Subfolder..."
      maxlength="100"
      autocomplete="off"
      autofocus
    >

    <div class="subfolder-input-actions">
      <button type="submit" class="subfolder-input-confirm" title="Confirmar (Enter)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </button>
      <button type="button" class="subfolder-input-cancel" title="Cancelar (Esc)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
  `;

  projectContent.insertBefore(newSubfolderContainer, projectContent.firstChild);

  const input = newSubfolderContainer.querySelector('.subfolder-name-input');
  const confirmBtn = newSubfolderContainer.querySelector('.subfolder-input-confirm');
  const cancelBtn = newSubfolderContainer.querySelector('.subfolder-input-cancel');

  setTimeout(() => input?.focus(), 0);

  input?.addEventListener('keydown', (ev) => {
    if (ev.key === 'Enter') {
      ev.preventDefault();
      confirmSubfolder(input.value.trim(), projectId, newSubfolderContainer);
    } else if (ev.key === 'Escape') {
      ev.preventDefault();
      cancelSubfolderCreation(newSubfolderContainer);
    }
  });

  confirmBtn?.addEventListener('click', (ev) => {
    ev.preventDefault();
    ev.stopPropagation();
    confirmSubfolder(input.value.trim(), projectId, newSubfolderContainer);
  });

  cancelBtn?.addEventListener('click', (ev) => {
    ev.preventDefault();
    ev.stopPropagation();
    cancelSubfolderCreation(newSubfolderContainer);
  });

  document.addEventListener('click', function outside(ev) {
    if (!newSubfolderContainer.contains(ev.target) && ev.target !== addSubfolderBtn) {
      cancelSubfolderCreation(newSubfolderContainer);
      document.removeEventListener('click', outside);
    }
  });
}

// Executa a rotina 'confirmSubfolder' no fluxo da interface.
function confirmSubfolder(subfolderName, projectId, container) {
  if (!subfolderName) return alert('Informe um nome para a Subfolder');

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!csrfToken) return alert('Token CSRF não encontrado');

  container.classList.add('loading');
  const input = container.querySelector('.subfolder-name-input');
  if (input) input.disabled = true;

  fetch('/home/subfolder', {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken
    },
    body: JSON.stringify({ project_id: projectId, name: subfolderName })
  })
    .then(async res => {
      if (!res.ok) throw new Error(await extractErrorMessage(res));
      return res.json();
    })
    .then(() => location.reload())
    .catch(err => {
      console.error(err);
      alert(err.message || 'Erro ao criar Subfolder');
      container.classList.remove('loading');
      if (input) input.disabled = false;
      isCreatingSubfolder = false;
    });
}

// Executa a rotina 'cancelSubfolderCreation' no fluxo da interface.
function cancelSubfolderCreation(container) {
  container.classList.add('canceling');
  setTimeout(() => {
    container.remove();
    isCreatingSubfolder = false;
  }, 200);
}

// Configura comportamento e listeners desta interface.
function setupProjectHeroEditor() {
  const hero = document.querySelector('[data-project-hero]');
  if (!hero) return;

  const saveBtn = hero.querySelector('[data-project-save]');
  const statusEl = hero.querySelector('[data-project-save-status]');
  const updateUrl = hero.dataset.updateUrl;
  const projectId = hero.dataset.projectId;
  const fields = Array.from(hero.querySelectorAll('[data-project-field]'));

  // Executa a rotina 'setStatus' no fluxo da interface.
  const setStatus = (text, tone) => {
    if (!statusEl) return;
    statusEl.textContent = text || '';
    statusEl.classList.remove('is-error', 'is-success');
    if (tone === 'error') statusEl.classList.add('is-error');
    if (tone === 'success') statusEl.classList.add('is-success');
  };

  // Executa a rotina 'buildPayload' no fluxo da interface.
  const buildPayload = () => {
    const payload = { project_id: projectId };
    fields.forEach((field) => {
      const key = field.dataset.projectField;
      if (!key) return;
      if (field.tagName === 'SELECT') {
        payload[key] = field.value;
        return;
      }
      const value = field.value.trim();
      if (value !== '') {
        payload[key] = value;
      }
    });
    return payload;
  };

  // Executa a rotina 'validate' no fluxo da interface.
  const validate = (payload) => {
    if (payload.title && payload.title.length < 3) {
      return 'Titulo precisa ter ao menos 3 caracteres.';
    }
    if (payload.description && payload.description.length < 3) {
      return 'Descricao precisa ter ao menos 3 caracteres.';
    }
    if (payload.lab_name && payload.lab_name.length < 3) {
      return 'Laboratorio precisa ter ao menos 3 caracteres.';
    }
    if (payload.group_name && payload.group_name.length < 3) {
      return 'Grupo precisa ter ao menos 3 caracteres.';
    }
    return '';
  };

  // Executa a rotina 'save' no fluxo da interface.
  const save = async () => {
    if (!projectId || !updateUrl) {
      setStatus('Dados do projeto ausentes.', 'error');
      return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
      setStatus('Token CSRF nao encontrado.', 'error');
      return;
    }

    const payload = buildPayload();
    if (Object.keys(payload).length === 1) {
      setStatus('Nada para salvar.', '');
      return;
    }

    const validationError = validate(payload);
    if (validationError) {
      setStatus(validationError, 'error');
      return;
    }

    if (saveBtn) saveBtn.disabled = true;
    setStatus('Salvando...', '');

    try {
      const response = await fetch(updateUrl, {
        method: 'PUT',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(payload)
      });

      if (!response.ok) {
        throw new Error(await extractErrorMessage(response));
      }

      setStatus('Salvo!', 'success');
    } catch (err) {
      console.error(err);
      setStatus(err?.message || 'Erro ao salvar.', 'error');
    } finally {
      if (saveBtn) saveBtn.disabled = false;
    }
  };

  saveBtn?.addEventListener('click', save);
  hero.addEventListener('keydown', (ev) => {
    if ((ev.ctrlKey || ev.metaKey) && ev.key === 'Enter') {
      ev.preventDefault();
      save();
    }
  });
}

setupProjectHeroEditor();

// Le opcoes de grupo disponiveis para uma mudanca de papel.
function getRoleGroupOptions(form, labId) {
  const rawOptions = form?.dataset?.groupOptions || '[]';
  const options = [];

  try {
    const parsed = JSON.parse(rawOptions);
    if (Array.isArray(parsed)) {
      parsed.forEach((item) => {
        const id = parseInt(item?.id ?? 0, 10);
        const name = String(item?.name ?? '').trim();
        if (id > 0 && name) {
          options.push({ id, name });
        }
      });
    }
  } catch (err) {
    console.error(err);
  }

  if (options.length) {
    return options;
  }

  const fallbackButtons = Array.from(
    document.querySelectorAll(`[data-open-student-invite][data-lab-id="${labId}"][data-group-id]`)
  );
  const fallback = [];
  fallbackButtons.forEach((btn) => {
    const id = parseInt(btn.dataset.groupId || '0', 10);
    const name = String(btn.dataset.groupName || '').trim();
    if (id > 0 && name) {
      fallback.push({ id, name });
    }
  });

  return fallback;
}

// Abre um modal simples para escolher o grupo ao definir papel de aluno.
function promptStudentGroupSelection(options, currentGroupId, memberName) {
  return new Promise((resolve) => {
    if (!Array.isArray(options) || !options.length) {
      resolve(0);
      return;
    }

    const overlay = document.createElement('div');
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(15, 23, 42, 0.45)';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.padding = '16px';
    overlay.style.zIndex = '2500';

    const modal = document.createElement('div');
    modal.style.width = '100%';
    modal.style.maxWidth = '460px';
    modal.style.background = '#ffffff';
    modal.style.color = '#0f172a';
    modal.style.borderRadius = '14px';
    modal.style.padding = '20px';
    modal.style.boxShadow = '0 20px 40px rgba(15, 23, 42, 0.2)';

    const title = document.createElement('h3');
    title.textContent = 'Vincular aluno a um grupo';
    title.style.margin = '0 0 8px';
    title.style.fontSize = '18px';
    title.style.fontWeight = '700';

    const hint = document.createElement('p');
    hint.textContent = memberName
      ? `Selecione o grupo para ${memberName}.`
      : 'Selecione o grupo para este usuario.';
    hint.style.margin = '0 0 14px';
    hint.style.fontSize = '14px';
    hint.style.color = '#334155';

    const select = document.createElement('select');
    select.style.width = '100%';
    select.style.height = '42px';
    select.style.border = '1px solid #cbd5e1';
    select.style.borderRadius = '10px';
    select.style.padding = '0 12px';
    select.style.background = '#f8fafc';
    select.style.color = '#0f172a';

    options.forEach((option) => {
      const optionEl = document.createElement('option');
      optionEl.value = String(option.id);
      optionEl.textContent = option.name;
      select.appendChild(optionEl);
    });

    const currentExists = options.some((option) => option.id === currentGroupId);
    select.value = String(currentExists ? currentGroupId : options[0].id);

    const actions = document.createElement('div');
    actions.style.display = 'flex';
    actions.style.justifyContent = 'flex-end';
    actions.style.gap = '10px';
    actions.style.marginTop = '16px';

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.textContent = 'Cancelar';
    cancelBtn.style.border = '1px solid #cbd5e1';
    cancelBtn.style.background = '#ffffff';
    cancelBtn.style.color = '#334155';
    cancelBtn.style.borderRadius = '10px';
    cancelBtn.style.padding = '9px 14px';
    cancelBtn.style.cursor = 'pointer';

    const confirmBtn = document.createElement('button');
    confirmBtn.type = 'button';
    confirmBtn.textContent = 'Confirmar';
    confirmBtn.style.border = '0';
    confirmBtn.style.background = '#0f172a';
    confirmBtn.style.color = '#ffffff';
    confirmBtn.style.borderRadius = '10px';
    confirmBtn.style.padding = '9px 14px';
    confirmBtn.style.cursor = 'pointer';

    actions.append(cancelBtn, confirmBtn);
    modal.append(title, hint, select, actions);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    const finalize = (value) => {
      document.removeEventListener('keydown', onKeyDown);
      overlay.remove();
      resolve(value);
    };

    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        finalize(0);
      }
      if (event.key === 'Enter') {
        event.preventDefault();
        finalize(parseInt(select.value || '0', 10) || 0);
      }
    };

    cancelBtn.addEventListener('click', () => finalize(0));
    confirmBtn.addEventListener('click', () => finalize(parseInt(select.value || '0', 10) || 0));
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) {
        finalize(0);
      }
    });

    document.addEventListener('keydown', onKeyDown);
    select.focus();
  });
}

// Configura comportamento e listeners desta interface.
function attachMemberRoleSelects() {
  const selects = document.querySelectorAll('.member-role-select');
  if (!selects.length) return;

  selects.forEach((select) => {
    if (select.dataset.memberRoleBound === '1') return;

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
      const groupField = form.querySelector('input[name="group_id"]');
      let groupId = parseInt(groupField?.value || '0', 10);
      const labId = parseInt(form.querySelector('input[name="lab_id"]')?.value || '0', 10);
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

      if (!memberId || !csrfToken || (!groupId && !labId)) {
        alert('Nao foi possivel atualizar a funcao.');
        select.value = previousValue || nextValue;
        return;
      }

      if (nextValue === 'student' && groupId <= 0) {
        const groupOptions = getRoleGroupOptions(form, labId);
        const memberLabel = form
          .closest('.student-card, .overview-member-card')
          ?.querySelector('h4')
          ?.textContent
          ?.trim() || '';

        if (!groupOptions.length) {
          alert('Nenhum grupo disponivel para vincular este aluno.');
          select.value = previousValue || nextValue;
          return;
        }

        const selectedGroupId = await promptStudentGroupSelection(groupOptions, groupId, memberLabel);
        if (selectedGroupId <= 0) {
          select.value = previousValue || nextValue;
          return;
        }

        groupId = selectedGroupId;
        if (groupField) {
          groupField.value = String(selectedGroupId);
        }
      }

      const payload = {
        member_id: memberId,
        role: nextValue
      };
      if (groupId > 0) payload.group_id = groupId;
      if (labId > 0) payload.lab_id = labId;

      try {
        const response = await fetch(form.action, {
          method: 'PUT',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify(payload)
        });

        if (!response.ok) {
          throw new Error(await extractErrorMessage(response));
        }

        select.dataset.current = nextValue;
      } catch (err) {
        console.error(err);
        alert(err?.message || 'Erro ao atualizar a funcao do membro.');
        select.value = previousValue || nextValue;
      }
    });
  });
}

window.attachMemberRoleSelects = attachMemberRoleSelects;
attachMemberRoleSelects();





