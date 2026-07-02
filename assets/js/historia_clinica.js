/**
 * Historia Clinica - DentiSoft 1.0
 */

document.addEventListener('DOMContentLoaded', function () {
    initHistoriaSearch();
    initHistoriaUploads();
    initHistoriaEditor();
});

function initHistoriaSearch() {
    const form = document.getElementById('historiaSearchForm');
    const searchInput = document.getElementById('searchHistoria');
    const clearButton = document.getElementById('clearHistoriaSearch');
    const tableBody = document.getElementById('historiaTableBody');
    const pagination = document.getElementById('paginacionHistoria');
    const resultCount = document.getElementById('historiaResultCount');

    if (!form || !searchInput || !tableBody || !pagination) return;

    let timer = null;
    let activeRequest = null;
    let currentPage = Number.parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10) || 1;

    toggleClear();

    searchInput.addEventListener('input', function () {
        currentPage = 1;
        toggleClear();
        clearTimeout(timer);
        timer = setTimeout(() => fetchHistorias(searchInput.value.trim(), currentPage), 260);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        currentPage = 1;
        fetchHistorias(searchInput.value.trim(), currentPage);
    });

    clearButton?.addEventListener('click', function () {
        searchInput.value = '';
        currentPage = 1;
        toggleClear();
        searchInput.focus();
        fetchHistorias('', currentPage);
    });

    pagination.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-page]');
        if (!link || link.closest('.disabled') || link.closest('.active')) return;
        event.preventDefault();
        currentPage = Number.parseInt(link.dataset.page, 10) || 1;
        fetchHistorias(searchInput.value.trim(), currentPage);
    });

    async function fetchHistorias(query, page) {
        if (activeRequest) activeRequest.abort();
        activeRequest = new AbortController();
        tableBody.innerHTML = skeletonRows();

        try {
            const params = new URLSearchParams({ action: 'buscar', query, page: String(page) });
            const data = await fetchJson(`${BASE_URL}/api/historias_api.php?${params.toString()}`, {
                signal: activeRequest.signal,
                timeout: 12000,
            });

            if (!data?.success || !Array.isArray(data.historias) || !data.meta) {
                throw new Error(data?.error || 'La busqueda devolvio una respuesta incompleta.');
            }

            renderHistorias(data.historias);
            renderPagination(data.meta);
            updateCount(data.meta.total || 0);
            updateUrl(query, data.meta.page || 1);
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Error al buscar historias clinicas:', { query, page, error });
            tableBody.innerHTML = emptyRow('No fue posible cargar historias clínicas', error.message || 'Intenta nuevamente.');
            updateCount(0);
        } finally {
            activeRequest = null;
        }
    }

    function renderHistorias(historias) {
        if (historias.length === 0) {
            tableBody.innerHTML = emptyRow('No se encontraron historias clínicas', 'Prueba con otro paciente, documento o número de historia.');
            return;
        }

        tableBody.innerHTML = historias.map(normalizeHistoria).map(historia => {
            const estado = historia.estado === 'activa' ? 'activa' : 'archivada';
            const paciente = joinName(historia.paciente_nombre, historia.paciente_apellido) || 'Paciente no disponible';
            const odontologo = joinName(historia.odontologo_nombre, historia.odontologo_apellido) || 'Sin odontólogo';

            return `
                <tr data-historia-id="${escapeAttribute(historia.id)}">
                    <td class="col-code"><span class="history-code">${escapeHtml(historia.numero_historia || '-')}</span></td>
                    <td class="col-patient">
                        <div class="history-patient">
                            <div class="history-avatar">${escapeHtml(getInitials(historia.paciente_nombre, historia.paciente_apellido))}</div>
                            <div><strong>${escapeHtml(paciente)}</strong><span>${escapeHtml(historia.numero_documento || '-')}</span></div>
                        </div>
                    </td>
                    <td class="col-doctor"><strong>${escapeHtml(odontologo)}</strong><span>Odontólogo tratante</span></td>
                    <td class="col-date"><span><i class="bi bi-calendar3"></i>${escapeHtml(formatDate(historia.fecha_apertura))}</span></td>
                    <td class="col-status"><span class="history-status history-status-${estado}"><i></i>${estado === 'activa' ? 'Activa' : 'Archivada'}</span></td>
                    <td class="col-actions">
                        <div class="history-actions">
                            <a href="ver.php?id=${encodeURIComponent(historia.id)}" class="history-action" title="Ver"><i class="bi bi-eye"></i></a>
                            <a href="editar.php?id=${encodeURIComponent(historia.id)}" class="history-action history-action-edit" title="Editar"><i class="bi bi-pencil-square"></i></a>
                            <a href="odontograma.php?historia_id=${encodeURIComponent(historia.id)}" class="history-action history-action-chart" title="Seguimiento"><i class="bi bi-graph-up"></i></a>
                            <a href="adjuntos.php?historia_id=${encodeURIComponent(historia.id)}" class="history-action history-action-clip" title="Adjuntos"><i class="bi bi-paperclip"></i></a>
                        </div>
                    </td>
                </tr>`;
        }).join('');
    }

    function renderPagination(meta) {
        const totalPages = Math.max(1, Number.parseInt(meta.total_pages, 10) || 1);
        const page = Math.max(1, Number.parseInt(meta.page, 10) || 1);
        const query = searchInput.value.trim();
        let html = '<ul>';
        html += pageItem('Anterior', Math.max(1, page - 1), page <= 1, false, query);
        visiblePages(page, totalPages).forEach(item => {
            html += item === 'gap' ? '<li class="disabled"><span>...</span></li>' : pageItem(String(item), item, false, item === page, query);
        });
        html += pageItem('Siguiente', Math.min(totalPages, page + 1), page >= totalPages, false, query);
        html += '</ul>';
        pagination.innerHTML = html;
    }

    function pageItem(label, page, disabled, active, query) {
        const params = new URLSearchParams();
        if (query) params.set('search', query);
        params.set('page', String(page));
        return `<li class="${disabled ? 'disabled' : ''} ${active ? 'active' : ''}"><a href="?${params.toString()}" data-page="${page}">${escapeHtml(label)}</a></li>`;
    }

    function visiblePages(page, totalPages) {
        if (totalPages <= 7) return Array.from({ length: totalPages }, (_, index) => index + 1);
        const pages = [1];
        const start = Math.max(2, page - 1);
        const end = Math.min(totalPages - 1, page + 1);
        if (start > 2) pages.push('gap');
        for (let i = start; i <= end; i += 1) pages.push(i);
        if (end < totalPages - 1) pages.push('gap');
        pages.push(totalPages);
        return pages;
    }

    function toggleClear() {
        clearButton?.classList.toggle('is-visible', searchInput.value.trim() !== '');
    }

    function updateCount(total) {
        if (resultCount) resultCount.textContent = `${new Intl.NumberFormat('es-CO').format(total)} registros`;
    }

    function updateUrl(query, page) {
        const params = new URLSearchParams();
        if (query) params.set('search', query);
        if (page > 1) params.set('page', String(page));
        window.history.replaceState({}, '', `${window.location.pathname}${params.toString() ? `?${params}` : ''}`);
    }
}

function initHistoriaUploads() {
    const dropzone = document.querySelector('[data-history-dropzone]');
    const input = document.getElementById('historiaArchivos');
    const list = document.getElementById('historiaUploadPreview');
    if (!dropzone || !input || !list) return;

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, event => {
            event.preventDefault();
            dropzone.classList.add('is-dragging');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, event => {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');
        });
    });

    dropzone.addEventListener('drop', event => {
        if (event.dataTransfer?.files?.length) {
            input.files = event.dataTransfer.files;
            renderPreview();
        }
    });

    input.addEventListener('change', renderPreview);

    function renderPreview() {
        const files = Array.from(input.files || []);
        if (files.length === 0) {
            list.innerHTML = '';
            return;
        }

        list.innerHTML = files.map(file => `
            <div class="history-upload-file">
                <i class="bi bi-file-earmark-image"></i>
                <span>${escapeHtml(file.name)}</span>
                <small>${formatBytes(file.size)}</small>
            </div>
        `).join('');
    }
}

function initHistoriaEditor() {
    const form = document.querySelector('[data-history-editor]');
    if (!form) return;

    const tabs = Array.from(form.querySelectorAll('[data-hc-tab]'));
    const panels = Array.from(form.querySelectorAll('[data-hc-panel]'));
    const saveState = document.querySelector('[data-save-state]');
    const submitLabel = form.querySelector('[data-submit-label]');
    const fields = Array.from(form.querySelectorAll('input, select, textarea'));

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.hcTab;
            tabs.forEach(item => item.classList.toggle('active', item === tab));
            panels.forEach(panel => panel.classList.toggle('is-active', panel.dataset.hcPanel === target));
            const firstField = form.querySelector(`[data-hc-panel="${target}"] input:not([type="hidden"]), [data-hc-panel="${target}"] select, [data-hc-panel="${target}"] textarea`);
            firstField?.focus({ preventScroll: true });
        });
    });

    fields.forEach(field => {
        syncFloatingLabel(field);
        field.addEventListener('input', () => {
            syncFloatingLabel(field);
            autosizeTextarea(field);
            markDirty();
        });
        field.addEventListener('change', () => {
            syncFloatingLabel(field);
            markDirty();
        });
        autosizeTextarea(field);
    });

    form.addEventListener('submit', () => {
        if (saveState) {
            saveState.classList.remove('is-dirty');
            saveState.classList.add('is-saving');
            saveState.innerHTML = '<i class="bi bi-cloud-arrow-up"></i>Guardando...';
        }
        if (submitLabel) submitLabel.textContent = 'Guardando...';
    });

    function markDirty() {
        if (!saveState || saveState.classList.contains('is-dirty')) return;
        saveState.classList.add('is-dirty');
        saveState.innerHTML = '<i class="bi bi-pencil-square"></i>Cambios sin guardar';
    }
}

function syncFloatingLabel(field) {
    const wrapper = field.closest('.hc-floating-field');
    if (!wrapper) return;
    const hasValue = field.type === 'date' || field.tagName === 'SELECT' || String(field.value || '').trim() !== '';
    wrapper.classList.toggle('is-filled', hasValue);
}

function autosizeTextarea(field) {
    if (field.tagName !== 'TEXTAREA') return;
    field.style.height = 'auto';
    field.style.height = `${Math.max(field.scrollHeight, 108)}px`;
}

async function fetchJson(url, options = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), options.timeout || 12000);
    if (options.signal) {
        if (options.signal.aborted) controller.abort();
        options.signal.addEventListener('abort', () => controller.abort(), { once: true });
    }

    try {
        const response = await fetch(url, {
            ...options,
            signal: controller.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
        });
        const text = await response.text();
        const data = text ? JSON.parse(text) : null;
        if (!response.ok) throw new Error(data?.error || `Error HTTP ${response.status}`);
        if (!data) throw new Error('El servidor devolvio una respuesta vacia.');
        return data;
    } catch (error) {
        if (error.name === 'AbortError') error.message = 'La solicitud tardo demasiado. Intenta nuevamente.';
        throw error;
    } finally {
        clearTimeout(timer);
    }
}

function normalizeHistoria(historia) {
    return {
        id: historia?.id ?? '',
        numero_historia: historia?.numero_historia ?? '',
        fecha_apertura: historia?.fecha_apertura ?? '',
        estado: historia?.estado ?? 'archivada',
        numero_documento: historia?.numero_documento ?? '',
        paciente_nombre: historia?.paciente_nombre ?? '',
        paciente_apellido: historia?.paciente_apellido ?? '',
        odontologo_nombre: historia?.odontologo_nombre ?? '',
        odontologo_apellido: historia?.odontologo_apellido ?? '',
    };
}

function skeletonRows() {
    return Array.from({ length: 5 }).map(() => '<tr class="history-skeleton-row"><td colspan="6"><div class="history-skeleton"></div></td></tr>').join('');
}

function emptyRow(title, text) {
    return `<tr><td colspan="6"><div class="historia-empty"><i class="bi bi-journal-medical"></i><strong>${escapeHtml(title)}</strong><span>${escapeHtml(text)}</span></div></td></tr>`;
}

function joinName(first, last) {
    return [first, last].map(value => String(value || '').trim()).filter(Boolean).join(' ');
}

function getInitials(first, last) {
    const value = `${String(first || '').trim().charAt(0)}${String(last || '').trim().charAt(0)}`;
    return value ? value.toUpperCase() : '--';
}

function formatDate(value) {
    if (!value) return '-';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatBytes(bytes) {
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(2)} MB`;
    if (bytes >= 1024) return `${(bytes / 1024).toFixed(2)} KB`;
    return `${bytes} bytes`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = String(text ?? '');
    return div.innerHTML;
}

function escapeAttribute(text) {
    return escapeHtml(text).replace(/"/g, '&quot;');
}
