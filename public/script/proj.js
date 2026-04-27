// Retorna os alvos de tab disponiveis para persistencia de contexto.
function collectPanelTargets() {
    const uniqueTargets = new Set();

    document.querySelectorAll('[data-panel-tabs] [data-panel-target]').forEach((tab) => {
        const target = (tab.dataset.panelTarget || '').trim();
        if (target) {
            uniqueTargets.add(target);
        }
    });

    return Array.from(uniqueTargets);
}

// Monta uma chave estavel por pagina/contexto de tabs.
function getPanelStorageKey(targets) {
    if (!targets.length) return '';
    return `panel-tab:${window.location.pathname}:${targets.join('|')}`;
}

// Retorna a tab de painel persistida, quando valida.
function readStoredPanelTarget(targets) {
    const storageKey = getPanelStorageKey(targets);
    if (!storageKey) return null;

    try {
        const storedTarget = localStorage.getItem(storageKey);
        if (!storedTarget) return null;
        return targets.includes(storedTarget) ? storedTarget : null;
    } catch (error) {
        return null;
    }
}

// Persiste a tab de painel atual para restaurar apos redirect/reload.
function persistPanelTarget(panelName) {
    if (!panelName) return;

    const targets = collectPanelTargets();
    if (!targets.includes(panelName)) return;

    const storageKey = getPanelStorageKey(targets);
    if (!storageKey) return;

    try {
        localStorage.setItem(storageKey, panelName);
    } catch (error) {
        // Ignora falhas de storage para manter navegacao funcional.
    }
}

// Configura comportamento e listeners desta interface.
function setupVersionModal() {
    const overlay = document.getElementById('versionOverlay');
    const form = document.getElementById('versionForm');
    const openButtons = [
        document.getElementById('openVersionFormBtn'),
        document.getElementById('openVersionFormBtnSecondary')
    ].filter(Boolean);
    const closeButton = document.getElementById('closeVersionForm');

    if (!overlay || !form) return;

    // Controla exibicao e transicoes de componentes visuais.
    const open = () => {
        activatePanel('versions');
        overlay.classList.add('is-open');
        form.classList.add('is-open');
    };

    // Controla exibicao e transicoes de componentes visuais.
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

// Executa a rotina 'activatePanel' no fluxo da interface.
function activatePanel(panelName) {
    const ownerTab = document.querySelector(`[data-owner-tab="${panelName}"]`);
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

    persistPanelTarget(panelName);
}

// Configura comportamento e listeners desta interface.
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

// Configura comportamento e listeners desta interface.
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

    const availableTargets = collectPanelTargets();
    if (!availableTargets.length) return;

    const storedTarget = readStoredPanelTarget(availableTargets);
    const initialTarget = storedTarget
        || document.querySelector('[data-panel-tabs] [data-panel-target].is-active')?.dataset.panelTarget
        || document.querySelector('[data-panel-tabs] [data-panel-target][aria-selected="true"]')?.dataset.panelTarget
        || availableTargets[0];

    if (initialTarget) {
        activatePanel(initialTarget);
    }
}

// Configura comportamento e listeners desta interface.
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

// Configura comportamento e listeners desta interface.
function setupVersionCommentPanels() {
    const toggles = document.querySelectorAll('.version-more-btn');
    if (!toggles.length) return;

    // Executa a rotina 'resolveContainer' no fluxo da interface.
    const resolveContainer = (btn) => {
        return btn.closest('.version-card') || btn.closest('.board-column');
    };

    // Controla exibicao e transicoes de componentes visuais.
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

// Configura comportamento e listeners desta interface.
function setupCommentListPanels() {
    const toggles = document.querySelectorAll('[data-comment-list-toggle]');
    if (!toggles.length) return;

    const overlay = document.querySelector('[data-comment-overlay]');

    // Executa a rotina 'setExpandedState' no fluxo da interface.
    const setExpandedState = (panelId, expanded) => {
        toggles.forEach((toggle) => {
            if (toggle.getAttribute('aria-controls') === panelId) {
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }
        });
    };

    // Atualiza o estado da interface apos interacoes do usuario.
    const updateOverlay = () => {
        if (!overlay) return;
        const isOpen = Boolean(document.querySelector('.comment-list-panel.is-open'));
        overlay.classList.toggle('is-open', isOpen);
    };

    // Controla exibicao e transicoes de componentes visuais.
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

    // Controla exibicao e transicoes de componentes visuais.
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

// Configura comportamento e listeners desta interface.
function setupVersionDetailPanels() {
    const openButtons = document.querySelectorAll('[data-version-detail-open]');
    if (!openButtons.length) return;

    const overlay = document.querySelector('[data-version-detail-overlay]');
    const panels = document.querySelectorAll('[data-version-detail-panel]');

    // Executa a rotina 'setMode' no fluxo da interface.
    const setMode = (panel, mode) => {
        panel.classList.toggle('is-edit', mode === 'edit');
        const tabs = panel.querySelectorAll('[data-detail-tab]');
        tabs.forEach((tab) => {
            const isActive = tab.dataset.detailTab === mode;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    };

    // Controla exibicao e transicoes de componentes visuais.
    const closeAll = () => {
        panels.forEach((panel) => {
            panel.classList.remove('is-open', 'is-edit');
            panel.setAttribute('aria-hidden', 'true');
        });
        if (overlay) {
            overlay.classList.remove('is-open');
        }
    };

    // Controla exibicao e transicoes de componentes visuais.
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

// Configura comportamento e listeners desta interface.
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

        // Executa a rotina 'shouldIgnore' no fluxo da interface.
        const shouldIgnore = (event) => {
            return event.target.closest('a, button, input, textarea, select, label, summary, .version-comment-panel, .comment-list-panel, .version-detail-panel, .version-detail-overlay');
        };

        // Executa a rotina 'shouldAllowWheel' no fluxo da interface.
        const shouldAllowWheel = (event) => {
            return event.target.closest('.version-comment-panel, .comment-list-panel, .version-detail-panel, textarea, input, select');
        };

        // Executa a rotina 'onPointerDown' no fluxo da interface.
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

        // Executa a rotina 'onPointerMove' no fluxo da interface.
        const onPointerMove = (event) => {
            if (!isPanning) return;
            event.preventDefault();
            const dx = event.clientX - startX;
            const dy = event.clientY - startY;
            viewport.scrollLeft = scrollLeft - dx;
            viewport.scrollTop = scrollTop - dy;
        };

        // Executa a rotina 'endPan' no fluxo da interface.
        const endPan = (event) => {
            if (!isPanning) return;
            isPanning = false;
            viewport.classList.remove('is-panning');
            if (event?.pointerId) {
                viewport.releasePointerCapture(event.pointerId);
            }
        };

        // Executa a rotina 'centerBoard' no fluxo da interface.
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

// Configura comportamento e listeners desta interface.
function setupTaskModal() {
    const overlay = document.getElementById('taskOverlay');
    const form = document.getElementById('taskForm');
    const openButtons = [
        document.getElementById('openTaskFormBtn'),
        document.getElementById('openTaskFormBtnSecondary')
    ].filter(Boolean);
    const closeButton = document.getElementById('closeTaskForm');
    const editButtons = Array.from(document.querySelectorAll('.task-action-edit[data-task-id]'));

    if (!overlay || !form) return;

    const titleInput = document.getElementById('task-title');
    const descriptionInput = document.getElementById('task-description');
    const versionInput = document.getElementById('task-version-id');
    const taskIdInput = document.getElementById('task-id');
    const formModeInput = document.getElementById('taskFormMode');
    const methodInput = document.getElementById('taskFormMethod');
    const formTitle = document.getElementById('taskFormTitle');
    const submitLabel = document.getElementById('taskFormSubmitLabel');
    const createAction = form.dataset.createAction || form.getAttribute('action');
    const editActionTemplate = form.dataset.editActionTemplate || '';
    const taskPanelName = (() => {
        if (document.querySelector('[data-owner-panel="dashboard"]') || document.querySelector('[data-owner-tab="dashboard"]')) {
            return 'dashboard';
        }
        if (document.querySelector('[data-panel="tasks"]')) {
            return 'tasks';
        }
        return 'dashboard';
    })();

    if (!openButtons.length && !editButtons.length) return;

    if (overlay.classList.contains('is-open') || form.classList.contains('is-open')) {
        activatePanel(taskPanelName);
    }

    const setCreateMode = (resetFields = true) => {
        form.setAttribute('action', createAction);

        if (methodInput) {
            methodInput.value = '';
            methodInput.disabled = true;
        }
        if (formModeInput) formModeInput.value = 'create';
        if (taskIdInput) taskIdInput.value = '';
        if (formTitle) formTitle.textContent = 'Nova Task';
        if (submitLabel) submitLabel.textContent = 'Adicionar Task';

        if (!resetFields) return;
        if (titleInput) titleInput.value = '';
        if (descriptionInput) descriptionInput.value = '';
        if (versionInput) versionInput.value = '';
    };

    const setEditMode = (taskData) => {
        if (!taskData.id || !editActionTemplate) return;

        const editAction = editActionTemplate.replace('__TASK__', encodeURIComponent(taskData.id));
        form.setAttribute('action', editAction);

        if (methodInput) {
            methodInput.value = 'PUT';
            methodInput.disabled = false;
        }
        if (formModeInput) formModeInput.value = 'edit';
        if (taskIdInput) taskIdInput.value = String(taskData.id);
        if (formTitle) formTitle.textContent = 'Editar Task';
        if (submitLabel) submitLabel.textContent = 'Salvar Alteracoes';

        if (titleInput) titleInput.value = taskData.title || '';
        if (descriptionInput) descriptionInput.value = taskData.description || '';
        if (versionInput) versionInput.value = taskData.versionId || '';
    };

    const open = () => {
        activatePanel(taskPanelName);
        overlay.classList.add('is-open');
        form.classList.add('is-open');
    };

    const close = () => {
        overlay.classList.remove('is-open');
        form.classList.remove('is-open');
    };

    openButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            setCreateMode(true);
            open();
        });
    });

    editButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            let decodedDescription = btn.dataset.taskDescription || '';
            try {
                decodedDescription = decodeURIComponent(decodedDescription);
            } catch (err) {
                decodedDescription = btn.dataset.taskDescription || '';
            }

            setEditMode({
                id: btn.dataset.taskId || '',
                title: btn.dataset.taskTitle || '',
                description: decodedDescription,
                versionId: btn.dataset.taskVersionId || '',
            });
            open();
        });
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

    setupVersionChunkedUpload(form);
}

const VERSION_CHUNK_SIZE = 5 * 1024 * 1024;
const VERSION_CHUNK_MIN_FILE_SIZE = 8 * 1024 * 1024;
const VERSION_CHUNK_UPLOAD_URL = '/home/project/version/chunk';
const VERSION_CHUNK_STATUS_URL = '/home/project/version/chunk/status';
const VERSION_CHUNK_COMPLETE_URL = '/home/project/version/chunk/complete';
const VERSION_CHUNK_LOCAL_KEY_PREFIX = 'version-upload-session:';

// Configura upload de versoes em partes para arquivos grandes.
function setupVersionChunkedUpload(form) {
    if (!form || form.dataset.chunkUploadBound === '1') return;
    form.dataset.chunkUploadBound = '1';

    const fileInput = form.querySelector('input[name="version_file"]');
    const submitButton = form.querySelector('.btn-submit');
    if (!fileInput || !submitButton) return;

    const statusElement = document.createElement('small');
    statusElement.className = 'version-upload-status';
    statusElement.hidden = true;
    const fileGroup = fileInput.closest('.form-group');
    if (fileGroup) {
        fileGroup.appendChild(statusElement);
    }

    let uploadInProgress = false;

    // Executa a rotina 'safeJson' no fluxo da interface.
    const safeJson = async (response) => {
        try {
            return await response.json();
        } catch (error) {
            return {};
        }
    };

    // Executa a rotina 'setStatus' no fluxo da interface.
    const setStatus = (message, tone = 'neutral') => {
        statusElement.hidden = false;
        statusElement.textContent = message;
        statusElement.dataset.tone = tone;
    };

    // Executa a rotina 'clearStatus' no fluxo da interface.
    const clearStatus = () => {
        statusElement.hidden = true;
        statusElement.textContent = '';
        statusElement.dataset.tone = 'neutral';
    };

    // Executa a rotina 'setSubmitting' no fluxo da interface.
    const setSubmitting = (isSubmitting) => {
        uploadInProgress = isSubmitting;
        submitButton.disabled = isSubmitting;
        submitButton.classList.toggle('is-uploading', isSubmitting);
    };

    // Executa a rotina 'postMultipart' no fluxo da interface.
    const postMultipart = async (url, payload) => {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload,
        });
    };

    // Executa a rotina 'buildUploadFingerprint' no fluxo da interface.
    const buildUploadFingerprint = (file) => {
        const projectId = String(form.querySelector('[name="project_id"]')?.value || '');
        const subfolderId = String(form.querySelector('[name="subfolder_id"]')?.value || '');
        return [
            window.location.pathname,
            projectId,
            subfolderId,
            file.name,
            String(file.size),
            String(file.lastModified || 0),
        ].join('|');
    };

    // Executa a rotina 'hashFingerprint' no fluxo da interface.
    const hashFingerprint = (value) => {
        let hash = 0;
        for (let index = 0; index < value.length; index += 1) {
            hash = ((hash << 5) - hash) + value.charCodeAt(index);
            hash |= 0;
        }
        return Math.abs(hash).toString(36);
    };

    // Executa a rotina 'generateUploadId' no fluxo da interface.
    const generateUploadId = () => {
        if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
            const bytes = new Uint8Array(10);
            window.crypto.getRandomValues(bytes);
            const randomPart = Array.from(bytes).map((byte) => byte.toString(16).padStart(2, '0')).join('');
            return `vup_${Date.now().toString(36)}_${randomPart}`;
        }

        const fallback = Math.random().toString(36).slice(2, 16);
        return `vup_${Date.now().toString(36)}_${fallback}`;
    };

    // Executa a rotina 'collectUploadedChunks' no fluxo da interface.
    const collectUploadedChunks = async (token, uploadId, totalChunks) => {
        const statusPayload = new FormData();
        statusPayload.append('_token', token);
        statusPayload.append('upload_id', uploadId);
        statusPayload.append('total_chunks', String(totalChunks));

        const response = await postMultipart(VERSION_CHUNK_STATUS_URL, statusPayload);
        const body = await safeJson(response);
        if (!response.ok || !body.success) {
            const message = body.message || 'Nao foi possivel consultar status do upload.';
            throw new Error(message);
        }

        const uploadedList = Array.isArray(body.uploaded_chunks) ? body.uploaded_chunks : [];
        return new Set(
            uploadedList
                .map((chunkIndex) => Number(chunkIndex))
                .filter((chunkIndex) => Number.isInteger(chunkIndex) && chunkIndex >= 0),
        );
    };

    // Executa a rotina 'uploadChunk' no fluxo da interface.
    const uploadChunk = async (token, file, uploadId, chunkIndex, totalChunks) => {
        const start = chunkIndex * VERSION_CHUNK_SIZE;
        const end = Math.min(file.size, start + VERSION_CHUNK_SIZE);
        const chunkBlob = file.slice(start, end);
        const chunkPayload = new FormData();
        chunkPayload.append('_token', token);
        chunkPayload.append('upload_id', uploadId);
        chunkPayload.append('chunk_index', String(chunkIndex));
        chunkPayload.append('total_chunks', String(totalChunks));
        chunkPayload.append('file_name', file.name);
        chunkPayload.append('file_size', String(file.size));
        chunkPayload.append('file_mime', file.type || 'application/zip');
        chunkPayload.append('chunk', chunkBlob, `${file.name}.part${chunkIndex}`);

        const response = await postMultipart(VERSION_CHUNK_UPLOAD_URL, chunkPayload);
        const body = await safeJson(response);
        if (!response.ok || !body.success) {
            const message = body.message || 'Falha ao enviar parte do arquivo.';
            throw new Error(message);
        }
    };

    // Executa a rotina 'completeUpload' no fluxo da interface.
    const completeUpload = async (token, file, uploadId, totalChunks) => {
        const completePayload = new FormData();
        completePayload.append('_token', token);
        completePayload.append('upload_id', uploadId);
        completePayload.append('project_id', String(form.querySelector('[name="project_id"]')?.value || ''));
        const subfolderId = form.querySelector('[name="subfolder_id"]')?.value;
        if (subfolderId) {
            completePayload.append('subfolder_id', String(subfolderId));
        }
        completePayload.append('title', String(form.querySelector('[name="title"]')?.value || ''));
        completePayload.append('description', String(form.querySelector('[name="description"]')?.value || ''));
        completePayload.append('total_chunks', String(totalChunks));
        completePayload.append('file_name', file.name);
        completePayload.append('file_size', String(file.size));
        completePayload.append('file_mime', file.type || 'application/zip');

        const response = await postMultipart(VERSION_CHUNK_COMPLETE_URL, completePayload);
        const body = await safeJson(response);
        if (!response.ok || !body.success) {
            const message = body.message || 'Falha ao concluir upload em partes.';
            throw new Error(message);
        }

        return body;
    };

    form.addEventListener('submit', async (event) => {
        if (uploadInProgress) {
            event.preventDefault();
            return;
        }

        const file = fileInput.files?.[0];
        if (!file) return;

        const browserSupportsChunking = typeof window.fetch === 'function' && typeof window.FormData !== 'undefined';
        if (!browserSupportsChunking || file.size < VERSION_CHUNK_MIN_FILE_SIZE) {
            clearStatus();
            return;
        }

        if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
            return;
        }

        const token = String(form.querySelector('input[name="_token"]')?.value || '');
        if (!token) {
            return;
        }

        event.preventDefault();
        setSubmitting(true);

        const totalChunks = Math.max(1, Math.ceil(file.size / VERSION_CHUNK_SIZE));
        const fingerprint = buildUploadFingerprint(file);
        const localKey = VERSION_CHUNK_LOCAL_KEY_PREFIX + hashFingerprint(fingerprint);

        let uploadId = '';
        try {
            uploadId = localStorage.getItem(localKey) || '';
        } catch (error) {
            uploadId = '';
        }
        if (!/^[A-Za-z0-9_-]{16,120}$/.test(uploadId)) {
            uploadId = generateUploadId();
        }

        try {
            localStorage.setItem(localKey, uploadId);
        } catch (error) {
            // Ignora falha de persistencia local e segue com upload normal em partes.
        }

        try {
            setStatus(`Preparando upload em partes... 0% (0/${totalChunks})`);
            let uploadedChunks;
            try {
                uploadedChunks = await collectUploadedChunks(token, uploadId, totalChunks);
            } catch (statusError) {
                uploadId = generateUploadId();
                try {
                    localStorage.setItem(localKey, uploadId);
                } catch (error) {
                    // Sem persistencia local, segue fluxo.
                }
                uploadedChunks = await collectUploadedChunks(token, uploadId, totalChunks);
            }

            let uploadedCount = uploadedChunks.size;
            const initialPercent = Math.floor((uploadedCount / totalChunks) * 100);
            setStatus(`Enviando versao... ${initialPercent}% (${uploadedCount}/${totalChunks})`);

            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex += 1) {
                if (uploadedChunks.has(chunkIndex)) {
                    continue;
                }

                let lastError = null;
                for (let attempt = 1; attempt <= 3; attempt += 1) {
                    try {
                        await uploadChunk(token, file, uploadId, chunkIndex, totalChunks);
                        lastError = null;
                        break;
                    } catch (error) {
                        lastError = error;
                    }
                }

                if (lastError) {
                    throw lastError;
                }

                uploadedChunks.add(chunkIndex);
                uploadedCount = uploadedChunks.size;
                const percent = Math.floor((uploadedCount / totalChunks) * 100);
                setStatus(`Enviando versao... ${percent}% (${uploadedCount}/${totalChunks})`);
            }

            setStatus('Concluindo envio da versao...', 'neutral');
            const completed = await completeUpload(token, file, uploadId, totalChunks);

            try {
                localStorage.removeItem(localKey);
            } catch (error) {
                // Ignora falha de limpeza local.
            }

            setStatus('Upload concluido com sucesso.', 'success');
            const redirectTo = String(completed.redirect_to || window.location.href);
            window.location.assign(redirectTo);
        } catch (error) {
            const message = error?.message || 'Nao foi possivel enviar a versao.';
            setStatus(message, 'error');
            setSubmitting(false);
        }
    });
}

// Configura comportamento visual das colunas de tasks.
function setupTaskListColumns(lists) {
    const statusTitles = {
        draft: 'Rascunho',
        approved: 'Aprovadas',
        in_progress: 'Em Progresso',
        done: 'Concluidas',
    };
    const collapseStoragePrefix = 'task-list-collapsed';
    const responsiveBreakpoint = window.matchMedia('(max-width: 900px)');
    let resizeTimerId = null;

    const buildStorageKey = (list) => {
        const status = String(list?.dataset?.taskStatus || '').trim();
        return `${collapseStoragePrefix}:${window.location.pathname}:${status}`;
    };

    const readCollapsedState = (list) => {
        try {
            return localStorage.getItem(buildStorageKey(list)) === '1';
        } catch (error) {
            return false;
        }
    };

    const persistCollapsedState = (list, collapsed) => {
        try {
            localStorage.setItem(buildStorageKey(list), collapsed ? '1' : '0');
        } catch (error) {
            // Ignora falhas de storage para nao interromper o fluxo.
        }
    };

    const resolveColumnTitle = (list) => {
        const explicitTitle = String(list.dataset.taskTitle || '').trim();
        if (explicitTitle) return explicitTitle;

        const status = String(list.dataset.taskStatus || '').trim();
        if (statusTitles[status]) {
            return statusTitles[status];
        }

        if (!status) return 'Tasks';
        return status.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
    };

    const readTaskCount = (list) => list.querySelectorAll(':scope > .task').length;

    const updateCounter = (list) => {
        const counter = list.querySelector(':scope > .task-list-toggle .task-list-count');
        if (!counter) return;
        counter.textContent = String(readTaskCount(list));
    };

    const computeHeight = (list) => {
        if (!list || list.classList.contains('is-collapsed')) return null;

        const minHeight = 180;
        const maxHeight = 2400;
        const listStyle = window.getComputedStyle(list);
        const rowGap = parseFloat(listStyle.rowGap || listStyle.gap || '0') || 0;
        const paddingTop = parseFloat(listStyle.paddingTop || '0') || 0;
        const paddingBottom = parseFloat(listStyle.paddingBottom || '0') || 0;

        const visibleItems = Array.from(list.children).filter((child) => {
            return child instanceof HTMLElement
                && child.style.display !== 'none'
                && !child.hidden;
        });

        if (!visibleItems.length) {
            return minHeight;
        }

        const itemsHeight = visibleItems.reduce((sum, child) => {
            return sum + child.getBoundingClientRect().height;
        }, 0);

        const gapsHeight = Math.max(0, visibleItems.length - 1) * rowGap;
        const totalHeight = Math.ceil(itemsHeight + gapsHeight + paddingTop + paddingBottom);

        return Math.min(maxHeight, Math.max(minHeight, totalHeight));
    };

    const applyHeight = (list) => {
        const height = computeHeight(list);
        if (!height) {
            list.style.removeProperty('--task-list-height');
            return;
        }
        list.style.setProperty('--task-list-height', `${height}px`);
    };

    const refreshList = (list) => {
        if (!list) return;
        updateCounter(list);
        applyHeight(list);
    };

    const refreshAll = () => {
        lists.forEach((list) => refreshList(list));
    };

    const scheduleRefresh = () => {
        clearTimeout(resizeTimerId);
        resizeTimerId = window.setTimeout(refreshAll, 80);
    };

    const setCollapsed = (list, collapsed, persist = true) => {
        if (!list) return;

        list.classList.toggle('is-collapsed', collapsed);

        const toggle = list.querySelector(':scope > .task-list-toggle');
        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute('title', collapsed ? 'Expandir lista' : 'Recolher lista');
        }

        if (persist) {
            persistCollapsedState(list, collapsed);
        }

        applyHeight(list);
    };

    lists.forEach((list, index) => {
        list.classList.add('task-list-enhanced');

        if (!list.querySelector(':scope > .task-list-toggle')) {
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'task-list-toggle';
            toggle.innerHTML = `
                <span class="task-list-toggle-left">
                    <span class="task-list-chevron" aria-hidden="true">&#9662;</span>
                    <span class="task-list-title"></span>
                </span>
                <span class="task-list-count">0</span>
            `;

            const status = String(list.dataset.taskStatus || '').trim();
            const contentId = `task-list-${status || 'status'}-${index}`;
            list.id = list.id || contentId;
            toggle.setAttribute('aria-controls', list.id);

            const titleNode = toggle.querySelector('.task-list-title');
            if (titleNode) {
                titleNode.textContent = resolveColumnTitle(list);
            }

            toggle.addEventListener('click', () => {
                const shouldCollapse = !list.classList.contains('is-collapsed');
                setCollapsed(list, shouldCollapse, true);
            });

            list.prepend(toggle);
        }

        setCollapsed(list, readCollapsedState(list), false);
        refreshList(list);
    });

    window.addEventListener('resize', scheduleRefresh);
    if (typeof responsiveBreakpoint.addEventListener === 'function') {
        responsiveBreakpoint.addEventListener('change', scheduleRefresh);
    } else if (typeof responsiveBreakpoint.addListener === 'function') {
        responsiveBreakpoint.addListener(scheduleRefresh);
    }

    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver((mutationList) => {
            const dirtyLists = new Set();

            mutationList.forEach((mutation) => {
                const targetList = mutation.target?.closest?.('.status-list[data-task-status]');
                if (targetList) {
                    dirtyLists.add(targetList);
                }
            });

            dirtyLists.forEach((list) => refreshList(list));
        });

        lists.forEach((list) => {
            observer.observe(list, { childList: true });
        });
    }

    return {
        refreshList,
        refreshAll,
        setCollapsed,
    };
}

// Configura comportamento e listeners desta interface.
function setupTaskDragAndDrop() {
    const lists = Array.from(document.querySelectorAll('.status-list[data-task-status]'));
    if (!lists.length) return;

    const taskListUi = setupTaskListColumns(lists);
    taskListUi.refreshAll();

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('#taskForm input[name="_token"]')?.value
        || '';

    let draggingTask = null;
    let sourceList = null;
    let pendingStatusUpdate = false;
    let touchPointerId = null;
    let touchIdentifier = null;
    let isTouchDragging = false;
    let touchCurrentList = null;
    let touchStartX = 0;
    let touchStartY = 0;
    let suppressClickUntil = 0;
    const taskStatusLabels = {
        draft: 'Rascunho',
        approved: 'Aprovada',
        in_progress: 'Em progresso',
        done: 'Concluida',
    };

    const formatTaskStatusLabel = (status) => {
        const key = String(status || '').trim();
        if (!key) return '';
        if (taskStatusLabels[key]) return taskStatusLabels[key];
        return key.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
    };

    const applyTaskStatusPill = (task, status) => {
        if (!task) return;

        const normalizedStatus = String(status || '').trim();
        if (!normalizedStatus) return;

        const pill = task.querySelector('.task-state-pill');
        if (!pill) return;

        Array.from(pill.classList)
            .filter((className) => className.startsWith('status-'))
            .forEach((className) => pill.classList.remove(className));

        pill.classList.add(`status-${normalizedStatus.replace(/_/g, '-')}`);
        pill.textContent = formatTaskStatusLabel(normalizedStatus);
    };

    const removeEmptyState = (list) => {
        if (!list) return;
        list.querySelectorAll(':scope > .empty').forEach((emptyNode) => emptyNode.remove());
    };

    const ensureEmptyState = (list) => {
        if (!list) return;
        if (list.querySelector(':scope > .task')) return;
        if (list.querySelector(':scope > .empty')) return;

        const empty = document.createElement('div');
        empty.className = 'empty';
        empty.textContent = list.dataset.emptyMessage || 'Nenhuma task nesta lista';
        list.appendChild(empty);
    };

    const clearDropTargets = () => {
        lists.forEach((list) => list.classList.remove('is-drop-target'));
    };

    const markDropTarget = (list) => {
        clearDropTargets();
        if (list) {
            list.classList.add('is-drop-target');
        }
    };

    const findListAtPoint = (clientX, clientY) => {
        const hit = document.elementFromPoint(clientX, clientY);
        if (!hit) return null;
        return hit.closest('.status-list[data-task-status]');
    };

    const persistTaskStatus = async (task, nextStatus) => {
        const url = task.dataset.taskStatusUrl;
        if (!url) {
            throw new Error('Missing task status endpoint.');
        }

        const response = await fetch(url, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({ status: nextStatus }),
        });

        if (!response.ok) {
            throw new Error('Status update failed.');
        }

        const payload = await response.json().catch(() => ({}));
        if (payload.success === false) {
            throw new Error('Status update failed.');
        }
    };

    const commitTaskMove = async (movedTask, fromList, toList) => {
        if (!movedTask || !fromList || !toList) return;

        const nextStatus = toList.dataset.taskStatus || '';
        if (!nextStatus) return;

        const currentStatus = movedTask.dataset.taskStatus || fromList.dataset.taskStatus || '';
        if (nextStatus === currentStatus) return;

        taskListUi.setCollapsed(toList, false, true);
        removeEmptyState(toList);
        toList.appendChild(movedTask);
        movedTask.dataset.taskStatus = nextStatus;
        applyTaskStatusPill(movedTask, nextStatus);
        ensureEmptyState(fromList);
        taskListUi.refreshList(fromList);
        taskListUi.refreshList(toList);

        pendingStatusUpdate = true;

        try {
            await persistTaskStatus(movedTask, nextStatus);
        } catch (error) {
            removeEmptyState(fromList);
            fromList.appendChild(movedTask);
            movedTask.dataset.taskStatus = currentStatus;
            applyTaskStatusPill(movedTask, currentStatus);
            ensureEmptyState(toList);
            taskListUi.refreshList(fromList);
            taskListUi.refreshList(toList);
            alert('Nao foi possivel atualizar o status da task.');
        } finally {
            pendingStatusUpdate = false;
        }
    };

    const resetTouchDragState = () => {
        isTouchDragging = false;
        touchCurrentList = null;
        touchPointerId = null;
        touchIdentifier = null;
        touchStartX = 0;
        touchStartY = 0;
    };

    const startTouchDrag = (task, clientX, clientY, pointerId = null, identifier = null) => {
        if (pendingStatusUpdate) return false;

        draggingTask = task;
        sourceList = task.closest('.status-list[data-task-status]');
        touchPointerId = pointerId;
        touchIdentifier = identifier;
        isTouchDragging = false;
        touchCurrentList = null;
        touchStartX = clientX;
        touchStartY = clientY;

        task.dataset.prevTouchAction = task.style.touchAction || '';
        task.style.touchAction = 'none';
        return true;
    };

    const moveTouchDrag = (clientX, clientY) => {
        if (!draggingTask || !sourceList || pendingStatusUpdate) return false;

        const dx = clientX - touchStartX;
        const dy = clientY - touchStartY;
        const distance = Math.hypot(dx, dy);

        if (!isTouchDragging && distance < 8) return false;

        if (!isTouchDragging) {
            isTouchDragging = true;
            draggingTask.classList.add('is-dragging');
        }

        touchCurrentList = findListAtPoint(clientX, clientY);
        markDropTarget(touchCurrentList);
        return true;
    };

    const finishTouchDrag = async (task) => {
        const movedTask = draggingTask;
        const fromList = sourceList;
        const targetList = touchCurrentList;
        const didTouchDrag = isTouchDragging;

        task.style.touchAction = task.dataset.prevTouchAction || '';
        delete task.dataset.prevTouchAction;

        task.classList.remove('is-dragging');
        clearDropTargets();
        draggingTask = null;
        sourceList = null;
        resetTouchDragState();

        if (!didTouchDrag) return;

        suppressClickUntil = Date.now() + 250;
        await commitTaskMove(movedTask, fromList, targetList);
    };

    const tasks = Array.from(document.querySelectorAll('.task[data-task-id][draggable="true"]'));
    tasks.forEach((task) => {
        task.addEventListener('dragstart', (event) => {
            if (pendingStatusUpdate) {
                event.preventDefault();
                return;
            }

            if (event.target.closest('a, button, form')) {
                event.preventDefault();
                return;
            }

            draggingTask = task;
            sourceList = task.closest('.status-list[data-task-status]');
            task.classList.add('is-dragging');

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', task.dataset.taskId || '');
            }
        });

        task.addEventListener('dragend', () => {
            task.classList.remove('is-dragging');
            clearDropTargets();
            draggingTask = null;
            sourceList = null;
            resetTouchDragState();
        });

        task.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'touch') return;
            if (pendingStatusUpdate) return;
            if (event.target.closest('a, button, form, input, textarea, select, label')) return;

            if (!startTouchDrag(task, event.clientX, event.clientY, event.pointerId, null)) return;

            if (typeof task.setPointerCapture === 'function') {
                task.setPointerCapture(event.pointerId);
            }
        });

        task.addEventListener('pointermove', (event) => {
            if (event.pointerType !== 'touch') return;
            if (event.pointerId !== touchPointerId) return;
            if (!moveTouchDrag(event.clientX, event.clientY)) return;
            event.preventDefault();
        });

        const finishTouchPointerDrag = async (event) => {
            if (event.pointerType !== 'touch') return;
            if (event.pointerId !== touchPointerId) return;

            if (typeof task.hasPointerCapture === 'function'
                && typeof task.releasePointerCapture === 'function'
                && task.hasPointerCapture(event.pointerId)) {
                task.releasePointerCapture(event.pointerId);
            }

            await finishTouchDrag(task);
        };

        task.addEventListener('pointerup', finishTouchPointerDrag);
        task.addEventListener('pointercancel', finishTouchPointerDrag);

        // Fallback para browsers mobile sem PointerEvent.
        task.addEventListener('touchstart', (event) => {
            if (typeof window.PointerEvent !== 'undefined') return;
            if (pendingStatusUpdate) return;
            if (event.target.closest('a, button, form, input, textarea, select, label')) return;

            const firstTouch = event.changedTouches?.[0];
            if (!firstTouch) return;

            startTouchDrag(task, firstTouch.clientX, firstTouch.clientY, null, firstTouch.identifier);
        }, { passive: true });

        task.addEventListener('touchmove', (event) => {
            if (typeof window.PointerEvent !== 'undefined') return;
            if (!draggingTask || draggingTask !== task || pendingStatusUpdate) return;

            const activeTouch = Array.from(event.changedTouches || [])
                .find((touch) => touch.identifier === touchIdentifier);
            if (!activeTouch) return;

            const moved = moveTouchDrag(activeTouch.clientX, activeTouch.clientY);
            if (moved) {
                event.preventDefault();
            }
        }, { passive: false });

        const finishLegacyTouchDrag = async (event) => {
            if (typeof window.PointerEvent !== 'undefined') return;
            if (!draggingTask || draggingTask !== task) return;

            if (touchIdentifier !== null) {
                const sameTouch = Array.from(event.changedTouches || [])
                    .some((touch) => touch.identifier === touchIdentifier);
                if (!sameTouch) return;
            }

            await finishTouchDrag(task);
        };

        task.addEventListener('touchend', finishLegacyTouchDrag, { passive: true });
        task.addEventListener('touchcancel', finishLegacyTouchDrag, { passive: true });

        task.addEventListener('click', (event) => {
            if (Date.now() < suppressClickUntil) {
                event.preventDefault();
                event.stopPropagation();
            }
        }, true);
    });

    lists.forEach((list) => {
        list.addEventListener('dragover', (event) => {
            if (!draggingTask || pendingStatusUpdate) return;
            event.preventDefault();
            list.classList.add('is-drop-target');
        });

        list.addEventListener('dragleave', () => {
            list.classList.remove('is-drop-target');
        });

        list.addEventListener('drop', async (event) => {
            if (!draggingTask || !sourceList || pendingStatusUpdate) return;
            event.preventDefault();
            clearDropTargets();
            await commitTaskMove(draggingTask, sourceList, list);
        });
    });
}

// Configura comportamento e listeners desta interface.
function setupVersionFlowToggle() {
    const boards = document.querySelectorAll('[data-flow-board]');
    if (!boards.length) return;

    boards.forEach((board) => {
        const toggleBtn = board.querySelector('[data-flow-toggle]');
        if (!toggleBtn) return;
        if (toggleBtn.dataset.flowBound === '1') return;

        const hiddenColumns = Array.from(board.querySelectorAll('[data-flow-version-hidden]'));
        if (!hiddenColumns.length) {
            toggleBtn.hidden = true;
            return;
        }

        toggleBtn.dataset.flowBound = '1';
        const expandLabel = toggleBtn.dataset.labelExpand || 'Ver fluxo completo';
        const collapseLabel = toggleBtn.dataset.labelCollapse || 'Mostrar apenas recentes';
        const shouldAutoExpand = board.dataset.flowAutoExpand === '1';

        // Renderiza elementos dinamicos com base no estado atual.
        const applyState = (expanded) => {
            hiddenColumns.forEach((column) => {
                column.hidden = !expanded;
            });

            board.dataset.flowExpanded = expanded ? '1' : '0';
            toggleBtn.textContent = expanded ? collapseLabel : expandLabel;
        };

        applyState(shouldAutoExpand);

        toggleBtn.addEventListener('click', () => {
            const shouldExpand = board.dataset.flowExpanded !== '1';
            applyState(shouldExpand);
        });
    });
}

// Configura comportamento e listeners desta interface.
function setupVersionHighlightFromQuery() {
    const params = new URLSearchParams(window.location.search);
    const highlightVersionId = params.get('highlight_version');
    if (!highlightVersionId) return;

    const target = document.getElementById(`version-node-${highlightVersionId}`)
        || document.querySelector(`[data-version-node-id="${highlightVersionId}"]`);

    if (!target) return;

    target.classList.add('is-version-highlight');

    const board = target.closest('[data-flow-board]');
    if (!board) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
        return;
    }

    const viewport = board.querySelector('[data-board-viewport]');
    const toggleBtn = board.querySelector('[data-flow-toggle]');
    const collapseLabel = toggleBtn?.dataset.labelCollapse || 'Mostrar apenas recentes';

    const revealAndScroll = () => {
        const hiddenColumns = board.querySelectorAll('[data-flow-version-hidden][hidden]');
        if (hiddenColumns.length) {
            hiddenColumns.forEach((column) => {
                column.hidden = false;
            });
            board.dataset.flowExpanded = '1';
            if (toggleBtn) {
                toggleBtn.textContent = collapseLabel;
            }
        }

        if (!viewport) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
            return;
        }

        const viewportRect = viewport.getBoundingClientRect();
        const targetRect = target.getBoundingClientRect();
        const leftOffset = (targetRect.left - viewportRect.left) + viewport.scrollLeft;
        const topOffset = (targetRect.top - viewportRect.top) + viewport.scrollTop;

        const nextLeft = Math.max(0, leftOffset - (viewport.clientWidth - targetRect.width) / 2);
        const nextTop = Math.max(0, topOffset - (viewport.clientHeight - targetRect.height) / 2);

        viewport.scrollTo({
            left: nextLeft,
            top: nextTop,
            behavior: 'smooth',
        });
    };

    window.setTimeout(revealAndScroll, 130);
}

const PROJECT_CHART_PERIOD_OPTIONS = ['3', '6', '12'];
const PROJECT_DEFAULT_CHART_PERIOD = '3';

let projectStorageChart = null;
let projectVersionsChart = null;
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
    PROJECT_CHART_PERIOD_OPTIONS.forEach((period) => {
        periodSeries[period] = normalizeSeries(parsed[period] ?? fallbackSeries);
    });

    return periodSeries;
}

function resolveProjectChartPeriod(value) {
    const normalized = String(value ?? PROJECT_DEFAULT_CHART_PERIOD);
    return PROJECT_CHART_PERIOD_OPTIONS.includes(normalized)
        ? normalized
        : PROJECT_DEFAULT_CHART_PERIOD;
}

function getSeriesForPeriod(periodSeries, period) {
    const key = resolveProjectChartPeriod(period);
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

function renderVerticalBar(existingChart, canvas, series, theme, fallbackColor) {
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
                backgroundColor: safeSeries.map((item) => item.color || fallbackColor),
                borderRadius: 6,
                borderSkipped: false,
                maxBarThickness: 26,
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
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.label}: ${Number(ctx.raw || 0)}`,
                    },
                },
            },
            scales: {
                x: {
                    ticks: { color: theme.text2 },
                    grid: { display: false },
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

function syncProjectChartPeriodSelects(period) {
    document.querySelectorAll('[data-chart-period-filter]').forEach((select) => {
        if (select.value !== period) {
            select.value = period;
        }
    });
}

function getSelectedProjectChartPeriod() {
    const select = document.querySelector('[data-chart-period-filter]');
    return resolveProjectChartPeriod(select?.value);
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

    const selectedPeriod = getSelectedProjectChartPeriod();
    syncProjectChartPeriodSelects(selectedPeriod);

    const storageSeries = getSeriesForPeriod(parsePeriodSeries(storageCanvas), selectedPeriod);
    const versionsSeries = getSeriesForPeriod(parsePeriodSeries(versionsCanvas), selectedPeriod);
    const theme = getChartTheme();

    projectStorageChart = renderVerticalBar(projectStorageChart, storageCanvas, storageSeries, theme, '#ff8c00');
    projectVersionsChart = renderVerticalBar(projectVersionsChart, versionsCanvas, versionsSeries, theme, '#3f51b5');
}

function setupProjectChartPeriodFilter() {
    const selects = document.querySelectorAll('[data-chart-period-filter]');
    if (!selects.length) return;

    const initialPeriod = resolveProjectChartPeriod(selects[0].value || PROJECT_DEFAULT_CHART_PERIOD);
    syncProjectChartPeriodSelects(initialPeriod);

    selects.forEach((select) => {
        select.addEventListener('change', () => {
            const period = resolveProjectChartPeriod(select.value);
            syncProjectChartPeriodSelects(period);
            renderProjectCharts();
        });
    });
}

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
setupTaskModal();
setupTaskDragAndDrop();
setupVersionCarousel();
setupVersionCommentPanels();
setupCommentListPanels();
setupVersionDetailPanels();
setupVersionFlowToggle();
setupVersionBoard();
setupVersionHighlightFromQuery();
setupPanelToggles();
setupPanelTabs();
setupProjectChartPeriodFilter();
renderProjectCharts();

if (typeof window.initEntranceAnimations === 'function') {
    window.initEntranceAnimations();
}

document.addEventListener('owner:tabchange', (event) => {
    if (event.detail?.tabId !== 'dashboard') return;
    renderProjectCharts();
});
