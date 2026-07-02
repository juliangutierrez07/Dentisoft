/**
 * Pacientes - DentiSoft 1.0
 */

document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('pacientesSearchForm');
    const searchInput = document.getElementById('searchPacientes');
    const clearButton = document.getElementById('clearPacientesSearch');
    const tableBody = document.getElementById('pacientesTableBody');
    const pagination = document.getElementById('pacientesPagination');
    const resultCount = document.getElementById('pacientesResultCount');

    if (!form || !searchInput || !tableBody || !pagination) return;

    let debounceTimer = null;
    let activeRequest = null;
    let currentPage = getPageFromUrl();
    const canDeletePatients = tableBody?.dataset?.canDelete === '1';

    toggleClearButton();

    searchInput.addEventListener('input', function () {
        currentPage = 1;
        toggleClearButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            fetchPacientes(searchInput.value.trim(), currentPage);
        }, 260);
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        currentPage = 1;
        fetchPacientes(searchInput.value.trim(), currentPage);
    });

    clearButton?.addEventListener('click', function () {
        if (searchInput.value === '') return;
        searchInput.value = '';
        currentPage = 1;
        toggleClearButton();
        searchInput.focus();
        fetchPacientes('', currentPage);
    });

    pagination.addEventListener('click', function (event) {
        const link = event.target.closest('a[data-page]');
        if (!link || link.closest('.disabled') || link.closest('.active')) return;

        event.preventDefault();
        const page = Number.parseInt(link.dataset.page, 10);
        if (!Number.isInteger(page) || page < 1) return;

        currentPage = page;
        fetchPacientes(searchInput.value.trim(), currentPage);
    });

    async function fetchPacientes(query, page) {
        if (activeRequest) {
            activeRequest.abort();
        }

        activeRequest = new AbortController();
        setLoadingState();

        try {
            const params = new URLSearchParams({
                action: 'buscar',
                query,
                page: String(page),
            });

            const data = await fetchJson(`${BASE_URL}/api/pacientes_api.php?${params.toString()}`, {
                signal: activeRequest.signal,
                timeout: 12000,
            });

            if (!data?.success || !Array.isArray(data.pacientes) || !data.meta) {
                throw new Error(data?.error || 'La busqueda devolvio una respuesta incompleta.');
            }

            currentPage = data.meta.page || 1;
            renderPacientes(data.pacientes);
            renderPagination(data.meta);
            updateResultCount(data.meta);
            updateBrowserUrl(query, currentPage);
        } catch (err) {
            if (err.name === 'AbortError') return;
            console.error('Error al buscar pacientes:', {
                query,
                page,
                status: err.status || null,
                code: err.code || null,
                traceId: err.traceId || null,
                error: err,
            });
            tableBody.innerHTML = errorRow(err.message || 'No fue posible buscar pacientes.');
            updateResultCount({ total: 0 });
        } finally {
            activeRequest = null;
        }
    }

    async function fetchJson(url, options = {}) {
        const timeout = options.timeout || 12000;
        const externalSignal = options.signal;
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeout);

        if (externalSignal) {
            if (externalSignal.aborted) controller.abort();
            externalSignal.addEventListener('abort', () => controller.abort(), { once: true });
        }

        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options.headers || {}),
                },
            });

            const contentType = response.headers.get('content-type') || '';
            const text = await response.text();
            let data = null;

            if (text !== '') {
                if (!contentType.includes('application/json')) {
                    throw createHttpError(response, 'El servidor devolvio una respuesta no valida.');
                }

                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON invalido en pacientes:', { url, text, parseError });
                    throw createHttpError(response, 'El servidor devolvio JSON invalido.');
                }
            }

            if (!response.ok) {
                throw createHttpError(response, data?.error || `Error HTTP ${response.status}.`, data);
            }

            if (data === null) {
                throw createHttpError(response, 'El servidor devolvio una respuesta vacia.');
            }

            return data;
        } catch (err) {
            if (err.name === 'AbortError') {
                err.message = 'La solicitud tardo demasiado. Intenta nuevamente.';
            }
            throw err;
        } finally {
            clearTimeout(timer);
        }
    }

    function renderPacientes(pacientes) {
        if (!Array.isArray(pacientes) || pacientes.length === 0) {
            tableBody.innerHTML = emptyRow();
            return;
        }

        tableBody.innerHTML = pacientes.map(normalizePaciente).map(paciente => {
            const nombreCompleto = joinName(paciente.nombre, paciente.apellido) || 'Paciente sin nombre';
            const estado = ['activo', 'inactivo', 'suspendido'].includes(paciente.estado) ? paciente.estado : 'inactivo';
            const estadoLabel = {
                activo: 'Activo',
                inactivo: 'Inactivo',
                suspendido: 'Suspendido',
            }[estado];
            const hasPortalAccess = Boolean(paciente.portal_acceso_id);
            const portalEstado = paciente.portal_estado || 'none';
            const portalLabel = getPortalAccountLabel(portalEstado, paciente.portal_debe_cambiar_password);

            const permanentDeleteAction = canDeletePatients
                ? `<a href="eliminar.php?id=${encodeURIComponent(paciente.id)}&accion=eliminar" class="patient-action patient-action-delete" data-tooltip="Eliminar definitivamente" aria-label="Eliminar definitivamente"><i class="bi bi-trash3" aria-hidden="true"></i></a>`
                : '';

            return `
                <tr data-paciente-id="${escapeAttribute(paciente.id)}">
                    <td class="col-doc">
                        <span class="patient-doc">${escapeHtml(paciente.numero_documento || '-')}</span>
                        <small>${escapeHtml(paciente.tipo_documento || 'CC')}</small>
                    </td>
                    <td class="col-name">
                        <div class="patient-cell">
                            <div class="patient-avatar">${escapeHtml(getInitials(paciente.nombre, paciente.apellido))}</div>
                            <div class="patient-copy">
                                <strong>${escapeHtml(nombreCompleto)}</strong>
                                <span>ID #${escapeHtml(paciente.id || '-')}</span>
                            </div>
                        </div>
                    </td>
                    <td class="col-phone">
                        <span class="patient-phone"><i class="bi bi-telephone" aria-hidden="true"></i>${escapeHtml(paciente.telefono || '-')}</span>
                    </td>
                    <td class="col-email"><span class="patient-email" title="${escapeAttribute(paciente.email || '-')}">${escapeHtml(paciente.email || '-')}</span></td>
                    <td class="col-city">${escapeHtml(paciente.ciudad || '-')}</td>
                    <td class="col-eps"><span class="patient-chip">${escapeHtml(paciente.eps || 'Sin EPS')}</span></td>
                    <td class="col-status">
                        <span class="patient-status patient-status-${estado}">
                            <span></span>${estadoLabel}
                        </span>
                    </td>
                    <td class="col-portal">
                        <span class="patient-portal-badge ${hasPortalAccess ? 'has-access' : 'no-access'}">
                            <i class="bi ${hasPortalAccess ? 'bi-check-circle' : 'bi-dash-circle'}" aria-hidden="true"></i>
                            ${hasPortalAccess ? 'Si' : 'No'}
                        </span>
                    </td>
                    <td class="col-portal-last">${escapeHtml(formatPortalDate(paciente.portal_ultimo_acceso))}</td>
                    <td class="col-account">
                        <span class="patient-chip portal-account-${escapeAttribute(portalEstado)}">${escapeHtml(portalLabel)}</span>
                    </td>
                    <td class="col-actions">
                        <div class="patient-actions">
                            <a href="ver.php?id=${encodeURIComponent(paciente.id)}" class="patient-action patient-action-view" data-tooltip="Ver paciente" aria-label="Ver paciente"><i class="bi bi-eye" aria-hidden="true"></i></a>
                            <a href="editar.php?id=${encodeURIComponent(paciente.id)}" class="patient-action patient-action-edit" data-tooltip="Editar paciente" aria-label="Editar paciente"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
                            <a href="eliminar.php?id=${encodeURIComponent(paciente.id)}&accion=inactivar" class="patient-action patient-action-inactivate" data-tooltip="Inactivar paciente" aria-label="Inactivar paciente"><i class="bi bi-person-x" aria-hidden="true"></i></a>
                            ${permanentDeleteAction}
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
        html += paginationItem('Anterior', Math.max(1, page - 1), page <= 1, false, query);

        const pages = getVisiblePages(page, totalPages);
        pages.forEach(item => {
            if (item === 'gap') {
                html += '<li class="disabled"><span>...</span></li>';
                return;
            }

            html += paginationItem(String(item), item, false, item === page, query);
        });

        html += paginationItem('Siguiente', Math.min(totalPages, page + 1), page >= totalPages, false, query);
        html += '</ul>';
        pagination.innerHTML = html;
    }

    function paginationItem(label, page, disabled, active, query) {
        const params = new URLSearchParams();
        if (query) params.set('search', query);
        params.set('page', String(page));

        return `
            <li class="${disabled ? 'disabled' : ''} ${active ? 'active' : ''}">
                <a href="?${params.toString()}" data-page="${page}">${escapeHtml(label)}</a>
            </li>`;
    }

    function getVisiblePages(page, totalPages) {
        if (totalPages <= 7) {
            return Array.from({ length: totalPages }, (_, index) => index + 1);
        }

        const pages = [1];
        const start = Math.max(2, page - 1);
        const end = Math.min(totalPages - 1, page + 1);

        if (start > 2) pages.push('gap');
        for (let i = start; i <= end; i += 1) pages.push(i);
        if (end < totalPages - 1) pages.push('gap');
        pages.push(totalPages);

        return pages;
    }

    function setLoadingState() {
        tableBody.innerHTML = Array.from({ length: 5 }).map(() => `
            <tr class="patient-skeleton-row">
                <td colspan="11"><div class="patient-skeleton"></div></td>
            </tr>
        `).join('');
    }

    function emptyRow() {
        return `
            <tr>
                <td colspan="11">
                    <div class="patients-empty">
                        <i class="bi bi-person-vcard" aria-hidden="true"></i>
                        <strong>No se encontraron pacientes</strong>
                        <span>Prueba con otro documento, nombre, teléfono o ciudad.</span>
                    </div>
                </td>
            </tr>`;
    }

    function errorRow(message) {
        return `
            <tr>
                <td colspan="11">
                    <div class="patients-empty patients-empty-error">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        <strong>No fue posible cargar pacientes</strong>
                        <span>${escapeHtml(message)}</span>
                    </div>
                </td>
            </tr>`;
    }

    function updateResultCount(meta) {
        if (!resultCount) return;
        const total = Number.parseInt(meta.total, 10) || 0;
        resultCount.textContent = `${new Intl.NumberFormat('es-CO').format(total)} ${total === 1 ? 'paciente' : 'pacientes'}`;
    }

    function updateBrowserUrl(query, page) {
        const params = new URLSearchParams();
        if (query) params.set('search', query);
        if (page > 1) params.set('page', String(page));

        const nextUrl = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ''}`;
        window.history.replaceState({}, '', nextUrl);
    }

    function toggleClearButton() {
        if (!clearButton) return;
        clearButton.classList.toggle('is-visible', searchInput.value.trim() !== '');
    }

    function getPageFromUrl() {
        const page = Number.parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10);
        return Number.isInteger(page) && page > 0 ? page : 1;
    }

    function normalizePaciente(paciente) {
        return {
            id: paciente?.id ?? '',
            numero_documento: paciente?.numero_documento ?? '',
            tipo_documento: paciente?.tipo_documento ?? 'CC',
            nombre: paciente?.nombre ?? '',
            apellido: paciente?.apellido ?? '',
            telefono: paciente?.telefono ?? '',
            email: paciente?.email ?? '',
            ciudad: paciente?.ciudad ?? '',
            eps: paciente?.eps ?? '',
            estado: paciente?.estado ?? 'inactivo',
            portal_acceso_id: paciente?.portal_acceso_id ?? null,
            portal_estado: paciente?.portal_estado ?? null,
            portal_ultimo_acceso: paciente?.portal_ultimo_acceso ?? null,
            portal_debe_cambiar_password: paciente?.portal_debe_cambiar_password ?? 0,
        };
    }

    function getPortalAccountLabel(estado, debeCambiarPassword) {
        if (!estado || estado === 'none') return 'Sin acceso';

        const labels = {
            activo: 'Activa',
            inactivo: 'Inactiva',
            suspendido: 'Suspendida',
        };
        let label = labels[estado] || 'Sin acceso';
        if (Number.parseInt(debeCambiarPassword, 10) === 1 && estado !== 'none') {
            label += ' - cambio pendiente';
        }
        return label;
    }

    function formatPortalDate(value) {
        if (!value) return 'Nunca';

        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return 'Nunca';

        return date.toLocaleString('es-CO', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function joinName(first, last) {
        return [first, last].map(value => String(value || '').trim()).filter(Boolean).join(' ');
    }

    function getInitials(first, last) {
        const initials = `${String(first || '').trim().charAt(0)}${String(last || '').trim().charAt(0)}`;
        return initials ? initials.toUpperCase() : '--';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    function escapeAttribute(text) {
        return escapeHtml(text).replace(/"/g, '&quot;');
    }
});
