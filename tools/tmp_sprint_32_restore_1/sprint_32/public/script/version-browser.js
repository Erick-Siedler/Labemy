(() => {
    const container = document.querySelector('[data-version-browser]');
    if (!container) return;

    const viewUrlBase = container.getAttribute('data-view-url');
    const rawUrlBase = container.getAttribute('data-raw-url');
    const viewerBody = container.querySelector('[data-viewer-body]');
    const breadcrumb = container.querySelector('[data-viewer-breadcrumb]');
    const downloadBtn = container.querySelector('[data-viewer-download]');
    const fileLinks = Array.from(container.querySelectorAll('[data-zip-file]'));

    if (!viewUrlBase || !rawUrlBase || !viewerBody) {
        return;
    }

    const setLoading = () => {
        viewerBody.innerHTML = '<div class="zip-viewer-loading">Carregando arquivo...</div>';
    };

    const setError = () => {
        viewerBody.innerHTML = '<div class="zip-viewer-error">Não foi possível carregar o arquivo.</div>';
    };

    const setBreadcrumb = (path) => {
        if (breadcrumb) {
            breadcrumb.textContent = path || 'Selecione um arquivo';
        }
    };

    const setDownload = (path) => {
        if (!downloadBtn) return;
        if (!path) {
            downloadBtn.classList.add('is-hidden');
            downloadBtn.removeAttribute('href');
            return;
        }
        const url = new URL(rawUrlBase, window.location.origin);
        url.searchParams.set('path', path);
        url.searchParams.set('download', '1');
        downloadBtn.href = url.toString();
        downloadBtn.classList.remove('is-hidden');
    };

    const fetchView = async (path) => {
        setLoading();
        const url = new URL(viewUrlBase, window.location.origin);
        url.searchParams.set('path', path);

        try {
            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) {
                throw new Error('Request failed');
            }
            const html = await response.text();
            viewerBody.innerHTML = html;
        } catch (err) {
            setError();
        }
    };

    const markActive = (activeLink) => {
        fileLinks.forEach((link) => link.classList.remove('is-active'));
        if (activeLink) {
            activeLink.classList.add('is-active');
        }
    };

    const openParents = (link) => {
        let current = link.closest('details');
        while (current) {
            current.open = true;
            current = current.parentElement ? current.parentElement.closest('details') : null;
        }
    };

    const handleFileClick = (link) => {
        const path = link.getAttribute('data-path');
        if (!path) return;

        markActive(link);
        openParents(link);
        setBreadcrumb(path);
        setDownload(path);
        fetchView(path);

        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('path', path);
        window.history.replaceState({}, '', currentUrl.toString());
    };

    fileLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            handleFileClick(link);
        });
    });

    const params = new URLSearchParams(window.location.search);
    const initialPath = params.get('path');
    if (initialPath) {
        const initialLink = fileLinks.find((link) => link.getAttribute('data-path') === initialPath);
        if (initialLink) {
            handleFileClick(initialLink);
        }
    }
})();
