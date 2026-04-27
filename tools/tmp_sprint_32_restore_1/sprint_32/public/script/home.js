//TOGGLE SIDE MENU FEATURE
document.querySelectorAll('.toggle').forEach(toggle => {
  toggle.addEventListener('click', (e) => {
    if (e.target.closest('.add-group-bt') || e.target.closest('.add-project-bt')) return;

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

function openNotMenu() {
    notMenu?.classList.add('show');
    document.body.classList.add('notifications-open');
}

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

//FILTER PERIOD HEATMAP
const periodFilter = document.getElementById('period-filter');

if (periodFilter) {
    periodFilter.addEventListener('change', function() {
        const months = parseInt(this.value);
        filterByPeriod(months);
    });
    
    filterByPeriod(3);
}


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

const savedWidth = localStorage.getItem('sidebar-width');
if (savedWidth && !isNaN(savedWidth)) {
    const width = parseInt(savedWidth);
    document.documentElement.style.setProperty('--sidebar-width', width + 'px');
    updateLogoSize(width);
}

const sidebarHidden = localStorage.getItem('sidebar-hidden') === 'true';
if (sidebarHidden) {
    document.body.classList.add('sidebar-hidden');
}

function updateLogoSize(width) {
    if (!logo) return;
    
    const minWidth = 200;
    const maxWidth = 600;
    const minLogoSize = 100;
    const maxLogoSize = 280;
    
    const percentage = (width - minWidth) / (maxWidth - minWidth);
    const logoSize = minLogoSize + (percentage * (maxLogoSize - minLogoSize));
    
    logo.style.width = Math.max(minLogoSize, Math.min(maxLogoSize, logoSize)) + 'px';
}

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
    
    if (width >= 200 && width <= 600) {
        document.documentElement.style.setProperty('--sidebar-width', width + 'px');
        updateLogoSize(width);
    }
});

document.addEventListener('mouseup', () => {
    if (isResizing) {
        isResizing = false;
        document.body.classList.remove('resizing');
        resizer.classList.remove('resizing');
        
        const currentWidth = parseInt(getComputedStyle(document.documentElement)
            .getPropertyValue('--sidebar-width'));
        localStorage.setItem('sidebar-width', currentWidth);
    }
});

function toggleSidebar() {
    const isCurrentlyHidden = document.body.classList.contains('sidebar-hidden');
    const logCont = document.getElementById('logCont');

    if (isCurrentlyHidden) {
        document.body.classList.remove('sidebar-hidden');
        localStorage.setItem('sidebar-hidden', 'false');
        logCont.classList.remove('max-height')
    } else {
        document.body.classList.add('sidebar-hidden');
        localStorage.setItem('sidebar-hidden', 'true');
        logCont.classList.add('max-height')
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

function setupOwnerTabs() {
    const tabList = document.querySelector('.owner-tabs');
    if (!tabList) return;

    const tabs = Array.from(tabList.querySelectorAll('[data-owner-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-owner-panel]'));

    if (!tabs.length || !panels.length) return;

    const activateTab = (tabId, shouldFocus = false) => {
        tabs.forEach((tab) => {
            const isActive = tab.dataset.ownerTab === tabId;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            if (isActive && shouldFocus) {
                tab.focus();
            }
        });

        panels.forEach((panel) => {
            if (panel.dataset.ownerPanel === tabId) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });

        initEntranceAnimations();
        document.dispatchEvent(new CustomEvent('owner:tabchange', { detail: { tabId } }));
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            activateTab(tab.dataset.ownerTab);
        });
    });

    tabList.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

        const currentIndex = tabs.findIndex((tab) => tab.classList.contains('is-active'));
        let nextIndex = currentIndex;

        if (event.key === 'ArrowLeft') {
            nextIndex = currentIndex > 0 ? currentIndex - 1 : tabs.length - 1;
        }
        if (event.key === 'ArrowRight') {
            nextIndex = currentIndex < tabs.length - 1 ? currentIndex + 1 : 0;
        }
        if (event.key === 'Home') {
            nextIndex = 0;
        }
        if (event.key === 'End') {
            nextIndex = tabs.length - 1;
        }

        event.preventDefault();
        activateTab(tabs[nextIndex].dataset.ownerTab, true);
    });

    const initialTab = tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.ownerTab || tabs[0].dataset.ownerTab;
    activateTab(initialTab);
}

window.initEntranceAnimations = initEntranceAnimations;
window.setupOwnerTabs = setupOwnerTabs;

const runHomeAnimations = () => {
    if (document.querySelector('.owner-tabs')) {
        setupOwnerTabs();
    } else {
        initEntranceAnimations();
    }
};

if (document.readyState === 'loading') {
    window.addEventListener('DOMContentLoaded', runHomeAnimations);
} else {
    runHomeAnimations();
}
window.addEventListener('pageshow', runHomeAnimations);

const initialWidth = parseInt(getComputedStyle(document.documentElement)
    .getPropertyValue('--sidebar-width')) || 280;
updateLogoSize(initialWidth);

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
        const confirmed = confirm(`Confirmar alteraÃ§Ã£o de status de ${entityLabel} para "${optionLabel}"?`);

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

function closeEventForm() {
    overlay?.classList.remove('show');
    eventForm?.classList.remove('show');
}

// STUDENT INVITE FORM
const studentInviteForm = document.getElementById('studentInviteForm');
const studentOverlay = document.getElementById('studentOverlay');
const openStudentInviteBtn = document.getElementById('openStudentInviteFormBtn');

if (openStudentInviteBtn && studentOverlay && studentInviteForm) {
    openStudentInviteBtn.addEventListener('click', (e) => {
        e.preventDefault();
        studentOverlay.classList.add('show');
        studentInviteForm.classList.add('show');
    });
}

function closeStudentInviteForm() {
    studentOverlay?.classList.remove('show');
    studentInviteForm?.classList.remove('show');
}

studentOverlay?.addEventListener('click', closeStudentInviteForm);
document.getElementById('closeStudentInviteForm')?.addEventListener('click', closeStudentInviteForm);

// INVITE GROUP FILTER
const inviteLabSelect = document.getElementById('student-invite-lab');
const inviteGroupSelect = document.getElementById('student-invite-group');
const inviteGroupPlaceholder = document.getElementById('student-invite-group-placeholder');

function filterInviteGroups() {
    if (!inviteLabSelect || !inviteGroupSelect) return;
    const selectedLab = inviteLabSelect.value;
    let hasVisible = false;

    inviteGroupSelect.querySelectorAll('option[data-lab-id]').forEach(option => {
        const isVisible = !!selectedLab && option.dataset.labId === selectedLab;
        option.hidden = !isVisible;
        if (isVisible) hasVisible = true;
    });

    if (inviteGroupPlaceholder) {
        inviteGroupPlaceholder.textContent = hasVisible
            ? 'Selecione o grupo'
            : (selectedLab ? 'Sem grupos disponÃ­veis' : 'Selecione o grupo');
    }

    inviteGroupSelect.disabled = !selectedLab || !hasVisible;

    const initialValue = inviteGroupSelect.dataset.initial;
    if (initialValue) {
        const initialOption = inviteGroupSelect.querySelector(`option[value="${initialValue}"][data-lab-id="${selectedLab}"]`);
        inviteGroupSelect.value = initialOption ? initialValue : '';
        inviteGroupSelect.dataset.initial = '';
    } else {
        inviteGroupSelect.value = '';
    }
}

inviteLabSelect?.addEventListener('change', filterInviteGroups);
filterInviteGroups();

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

function createLabInput() {
    isCreatingLab = true;
    
    const sideMenuNav = document.querySelector('.side-menu-nav');
    
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

function confirmLab(labName, container) {

    if (!labName) {
        alert('Informe um nome para o laboratório');
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!csrfToken) {
        alert('CSRF token nÃ£o encontrado');
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

function confirmGroup(groupName, labId, container) {
  if (!groupName) return alert('Informe um nome para o grupo');

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!csrfToken) return alert('CSRF token nÃ£o encontrado');

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

function createProjectInput(groupContent, groupId, labId, addProjectBtn) {
  isCreatingProject = true;

  const newProjectContainer = document.createElement('div');
  newProjectContainer.className = 'proj new-project-input active';

  newProjectContainer.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-folder-fill" viewBox="0 0 16 16">
      <path d="M9.828 3h3.982a2 2 0 0 1 1.992 2.181l-.637 7A2 2 0 0 1 13.174 14H2.825a2 2 0 0 1-1.991-1.819l-.637-7a2 2 0 0 1 .342-1.31L.5 3a2 2 0 0 1 2-2h3.672a2 2 0 0 1 1.414.586l.828.828A2 2 0 0 0 9.828 3m-8.322.12q.322-.119.684-.12h5.396l-.707-.707A1 1 0 0 0 6.172 2H2.5a1 1 0 0 0-1 .981z"/>
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

function openProjectDescriptionModal(projectTitle) {
  const modalRef = ensureProjectDescModal();

  modalRef.titleEl.textContent = `Descricao do projeto: ${projectTitle}`;
  modalRef.textarea.value = '';
  modalRef.errorEl.textContent = '';

  modalRef.overlay.classList.add('is-open');
  modalRef.modal.classList.add('is-open');
  setTimeout(() => modalRef.textarea.focus(), 0);

  return new Promise((resolve) => {
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

    const onCancel = () => {
      cleanup();
      resolve(null);
    };

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

async function confirmProject(projectTitle, groupId, labId, container) {
  if (!projectTitle) return alert('Informe um nome para o Projeto');

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  if (!csrfToken) return alert('Token CSRF nÃ£o encontrado');

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

function cancelProjectCreation(container) {
  container.classList.add('canceling');
  setTimeout(() => {
    container.remove();
    isCreatingProject = false;
  }, 200);
}

function setupProjectHeroEditor() {
  const hero = document.querySelector('[data-project-hero]');
  if (!hero) return;

  const saveBtn = hero.querySelector('[data-project-save]');
  const statusEl = hero.querySelector('[data-project-save-status]');
  const updateUrl = hero.dataset.updateUrl;
  const projectId = hero.dataset.projectId;
  const fields = Array.from(hero.querySelectorAll('[data-project-field]'));

  const setStatus = (text, tone) => {
    if (!statusEl) return;
    statusEl.textContent = text || '';
    statusEl.classList.remove('is-error', 'is-success');
    if (tone === 'error') statusEl.classList.add('is-error');
    if (tone === 'success') statusEl.classList.add('is-success');
  };

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
      setStatus('Erro ao salvar.', 'error');
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


