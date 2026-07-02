/**
 * Citas - DentiSoft 1.0
 */

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchCitas');
    const tableBody = document.getElementById('citasTableBody');
    const statusFilter = document.getElementById('filtroEstado');
    const dateFilter = document.getElementById('fechaFiltro');
    const editUrlBase = `${BASE_URL}/modules/citas/editar.php`;
    let searchTimeout = null;
    let searchAbort = null;
    let activeEditId = null;
    let activeDetailId = null;
    const detailModal = createAppointmentDetailModal();

    if (searchInput && tableBody) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                buscarCitas(searchInput.value.trim());
            }, 250);
        });
    }

    if (tableBody) {
        tableBody.addEventListener('click', function (event) {
            const detailButton = event.target.closest('[data-action="ver-cita"], .js-view-cita');
            if (detailButton && tableBody.contains(detailButton)) {
                handleDetalleClick(event, detailButton);
                return;
            }

            const editLink = event.target.closest('[data-action="editar-cita"], .js-edit-cita, a[href*="editar.php?id="]');
            if (editLink && tableBody.contains(editLink)) {
                handleEditarClick(event, editLink);
                return;
            }

            const statusButton = event.target.closest('[data-action="cambiar-estado"], .cambiar-estado');
            if (statusButton && tableBody.contains(statusButton)) {
                handleEstadoClick(event, statusButton);
            }
        });
    }

    async function buscarCitas(query) {
        if (!tableBody) return;

        if (searchAbort) {
            searchAbort.abort();
        }
        searchAbort = new AbortController();

        const params = new URLSearchParams({
            action: 'buscar',
            query,
            estado: statusFilter?.value || '',
            fecha_filtro: dateFilter?.value || '',
        });
        const url = `${BASE_URL}/api/citas_api.php?${params.toString()}`;
        tableBody.innerHTML = loadingRow();

        try {
            const data = await fetchJson(url, {
                signal: searchAbort.signal,
                timeout: 12000,
            });

            if (!data?.success || !Array.isArray(data.citas)) {
                throw new Error(data?.error || 'La busqueda devolvio una respuesta incompleta.');
            }

            renderCitas(data.citas);
        } catch (err) {
            if (err.name === 'AbortError') return;
            console.error('Error al buscar citas:', { query, error: err });
            tableBody.innerHTML = errorRow(err.message || 'No fue posible buscar citas.');
        } finally {
            searchAbort = null;
        }
    }

    function renderCitas(citas) {
        if (!tableBody) return;

        if (!Array.isArray(citas) || citas.length === 0) {
            tableBody.innerHTML = emptyRow();
            return;
        }

        tableBody.innerHTML = citas.map(normalizeCita).map(cita => {
            const badgeClass = getBadgeClass(cita.estado);
            const initials = getInitials(cita.paciente_nombre, cita.paciente_apellido);

            return `
                <tr data-cita-id="${escapeAttribute(cita.id)}" data-estado="${escapeAttribute(cita.estado)}">
                    <td data-label="Fecha"><span class="date-pill">${escapeHtml(formatFecha(cita.fecha) || 'Sin fecha')}</span></td>
                    <td data-label="Hora"><span class="time-range">${escapeHtml(formatHora(cita.hora_inicio) || '--:--')} - ${escapeHtml(formatHora(cita.hora_fin) || '--:--')}</span></td>
                    <td data-label="Paciente">
                        <div class="d-flex align-items-center gap-2">
                            <div class="patient-avatar">${escapeHtml(initials)}</div>
                            <div class="patient-info">
                                <div class="patient-name">${escapeHtml(joinName(cita.paciente_nombre, cita.paciente_apellido) || 'Paciente no disponible')}</div>
                                <div class="patient-doc">${escapeHtml(cita.numero_documento || 'Sin documento')}</div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Odontologo"><span class="doctor-name">${escapeHtml(joinName(cita.odontologo_nombre, cita.odontologo_apellido) || 'Odontologo no disponible')}</span></td>
                    <td data-label="Estado"><span class="badge bg-${badgeClass}">${escapeHtml(formatEstado(cita.estado))}</span></td>
                    <td data-label="Motivo"><span class="reason-text">${escapeHtml(cita.motivo || 'Sin motivo')}</span></td>
                    <td class="text-end" data-label="Acciones">
                        <div class="actions-group">
                            <button type="button"
                                    class="action-btn action-view js-view-cita"
                                    data-action="ver-cita"
                                    data-id="${escapeAttribute(cita.id)}"
                                    title="Ver cita"
                                    aria-label="Ver cita ${escapeAttribute(cita.id)}">
                                <i class="bi bi-eye"></i>
                            </button>
                            <a href="${editUrlBase}?id=${encodeURIComponent(cita.id)}"
                               class="action-btn action-edit js-edit-cita"
                               data-action="editar-cita"
                               data-id="${escapeAttribute(cita.id)}"
                               title="Editar cita"
                               aria-label="Editar cita ${escapeAttribute(cita.id)}">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <button type="button" class="action-btn action-confirm cambiar-estado" data-action="cambiar-estado" data-id="${escapeAttribute(cita.id)}" data-estado="confirmada" title="Confirmar cita"><i class="bi bi-check-circle"></i></button>
                            <button type="button" class="action-btn action-done cambiar-estado" data-action="cambiar-estado" data-id="${escapeAttribute(cita.id)}" data-estado="atendida" title="Marcar como completada"><i class="bi bi-check-all"></i></button>
                            <button type="button" class="action-btn action-cancel cambiar-estado" data-action="cambiar-estado" data-id="${escapeAttribute(cita.id)}" data-estado="cancelada" title="Cancelar cita"><i class="bi bi-x-circle"></i></button>
                        </div>
                    </td>
                </tr>`;
        }).join('');
    }

    async function handleEditarClick(event, link) {
        event.preventDefault();

        const citaId = getCitaId(link);
        if (!isValidId(citaId)) {
            console.error('Editar cita bloqueado: ID invalido.', {
                datasetId: link.dataset?.id,
                href: link.getAttribute('href'),
                rowId: link.closest('tr')?.dataset?.citaId,
            });
            mostrarAlertaSeguro('No se puede editar esta cita porque el identificador es invalido.', 'danger');
            return;
        }

        if (activeEditId === citaId) return;
        activeEditId = citaId;
        setEditLoading(link, true);

        try {
            const data = await fetchJson(`${BASE_URL}/api/citas_api.php?action=detalle&id=${encodeURIComponent(citaId)}`, {
                timeout: 10000,
            });

            if (!data?.success || !data?.cita?.id) {
                throw new Error(data?.error || 'No se recibieron datos validos de la cita.');
            }

            window.location.href = `${editUrlBase}?id=${encodeURIComponent(citaId)}`;
        } catch (err) {
            console.error('Error al preparar edicion de cita:', {
                citaId,
                status: err.status || null,
                code: err.code || null,
                error: err,
            });
            mostrarAlertaSeguro(err.message || 'No fue posible cargar la cita para editar.', 'danger', 7000);
            setEditLoading(link, false);
            activeEditId = null;
        }
    }

    async function handleDetalleClick(event, button) {
        event.preventDefault();

        const citaId = getCitaId(button);
        if (!isValidId(citaId)) {
            mostrarAlertaSeguro('No se puede abrir el detalle porque el identificador es invalido.', 'danger');
            return;
        }

        if (activeDetailId === citaId && detailModal.isOpen()) return;
        activeDetailId = citaId;
        setActionLoading(button, true, 'bi bi-eye');
        detailModal.openLoading();

        try {
            const data = await fetchJson(`${BASE_URL}/api/citas_api.php?action=detalle&id=${encodeURIComponent(citaId)}`, {
                timeout: 10000,
            });

            if (!data?.success || !data?.cita?.id) {
                throw new Error(data?.error || 'No se recibieron datos validos de la cita.');
            }

            detailModal.render(data.cita);
        } catch (err) {
            console.error('Error al cargar detalle de cita:', { citaId, error: err });
            detailModal.renderError(err.message || 'No fue posible cargar la informacion de la cita.');
        } finally {
            setActionLoading(button, false, 'bi bi-eye');
        }
    }

    async function handleEstadoClick(event, btn) {
        event.preventDefault();

        const citaId = getCitaId(btn);
        const estado = String(btn.dataset?.estado || '').trim();

        if (!isValidId(citaId) || !estado) {
            console.error('Cambio de estado bloqueado: datos invalidos.', {
                citaId,
                estado,
                dataset: btn.dataset,
            });
            mostrarAlertaSeguro('No se puede cambiar el estado porque faltan datos de la cita.', 'danger');
            return;
        }

        const confirmacion = confirm(`Cambiar estado de la cita a "${formatEstado(estado)}"?`);
        if (!confirmacion) return;

        const payload = new URLSearchParams();
        payload.append('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
        payload.append('id', citaId);
        payload.append('estado', estado);

        btn.disabled = true;
        btn.classList.add('is-loading');

        try {
            const data = await fetchJson(`${BASE_URL}/modules/citas/cambiar_estado.php`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: payload.toString(),
                timeout: 12000,
            });

            if (!data?.success) {
                throw new Error(data?.error || 'No fue posible actualizar el estado.');
            }

            mostrarAlertaSeguro(data.message || 'Estado actualizado.', 'success');
            updateEstadoRow(btn, estado);
            notifyCalendarChanged();
        } catch (err) {
            console.error('Error al cambiar estado de cita:', { citaId, estado, error: err });
            mostrarAlertaSeguro(err.message || 'No fue posible actualizar el estado.', 'danger');
        } finally {
            btn.disabled = false;
            btn.classList.remove('is-loading');
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
                    console.error('JSON invalido recibido:', { url, text, parseError });
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

    function createHttpError(response, message, data = {}) {
        const error = new Error(message);
        error.status = response.status;
        error.code = data?.code || null;
        error.traceId = data?.trace_id || null;
        return error;
    }

    function getCitaId(element) {
        const datasetId = element.dataset?.id;
        const rowId = element.closest('tr')?.dataset?.citaId;
        const hrefId = getIdFromHref(element.getAttribute('href'));
        return String(datasetId || rowId || hrefId || '').trim();
    }

    function getIdFromHref(href) {
        if (!href) return '';
        try {
            return new URL(href, window.location.href).searchParams.get('id') || '';
        } catch (err) {
            console.error('Href invalido para editar cita:', { href, error: err });
            return '';
        }
    }

    function setEditLoading(link, isLoading) {
        if (!link) return;
        link.classList.toggle('is-loading', isLoading);
        link.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        link.style.pointerEvents = isLoading ? 'none' : '';

        const icon = link.querySelector('i');
        if (icon) {
            icon.className = isLoading ? 'spinner-border spinner-border-sm' : 'bi bi-pencil-square';
        }
    }

    function setActionLoading(button, isLoading, iconClass) {
        if (!button) return;

        button.disabled = Boolean(isLoading);
        button.classList.toggle('is-loading', Boolean(isLoading));
        button.setAttribute('aria-busy', isLoading ? 'true' : 'false');

        const icon = button.querySelector('i');
        if (icon) {
            icon.className = isLoading ? 'spinner-border spinner-border-sm' : iconClass;
        }
    }

    function updateEstadoRow(btn, estado) {
        const row = btn.closest('tr');
        if (!row) return;

        row.dataset.estado = estado;
        const badge = row.querySelector('td:nth-child(5) span.badge');
        if (badge) {
            badge.textContent = formatEstado(estado);
            badge.className = `badge bg-${getBadgeClass(estado)}`;
        }
    }

    function normalizeCita(cita) {
        return {
            id: cita?.id ?? '',
            fecha: cita?.fecha ?? '',
            hora_inicio: cita?.hora_inicio ?? '',
            hora_fin: cita?.hora_fin ?? '',
            estado: cita?.estado || 'pendiente',
            motivo: cita?.motivo ?? '',
            paciente_nombre: cita?.paciente_nombre ?? '',
            paciente_apellido: cita?.paciente_apellido ?? '',
            numero_documento: cita?.numero_documento ?? '',
            odontologo_nombre: cita?.odontologo_nombre ?? '',
            odontologo_apellido: cita?.odontologo_apellido ?? '',
        };
    }

    function isValidId(value) {
        return /^[1-9]\d*$/.test(String(value || '').trim());
    }

    function getBadgeClass(estado) {
        return {
            pendiente: 'warning',
            confirmada: 'success',
            atendida: 'info',
            cancelada: 'danger',
            no_asistio: 'secondary',
        }[estado] || 'light';
    }

    function loadingRow() {
        return `
            <tr class="skeleton-row"><td colspan="7">
                <div class="skeleton-list" aria-label="Cargando citas">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </td></tr>`;
    }

    function errorRow(message) {
        return `
            <tr class="empty-row"><td colspan="7">
                <div class="empty-state empty-error">
                    <span><i class="bi bi-exclamation-triangle"></i></span>
                    <strong>No fue posible cargar las citas</strong>
                    <p>${escapeHtml(message)}</p>
                </div>
            </td></tr>`;
    }

    function emptyRow() {
        return `
            <tr class="empty-row"><td colspan="7">
                <div class="empty-state">
                    <span><i class="bi bi-calendar2-x"></i></span>
                    <strong>No se encontraron citas</strong>
                    <p>Ajusta los filtros o agenda una nueva cita para continuar.</p>
                </div>
            </td></tr>`;
    }

    function formatFecha(value) {
        if (!value) return '';
        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function formatHora(value) {
        if (!value) return '';
        const [hour, minute] = String(value).split(':');
        const hourNumber = Number.parseInt(hour, 10);
        const minuteNumber = Number.parseInt(minute, 10);
        if (Number.isNaN(hourNumber) || Number.isNaN(minuteNumber)) return '';

        const date = new Date();
        date.setHours(hourNumber, minuteNumber, 0, 0);
        return date.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
    }

    function formatEstado(estado) {
        if (estado === 'atendida') return 'Completada';
        if (estado === 'no_asistio') return 'No Asistio';

        return String(estado || 'pendiente')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, letter => letter.toUpperCase());
    }

    function joinName(first, last) {
        return [first, last].map(value => String(value || '').trim()).filter(Boolean).join(' ');
    }

    function getInitials(first, last) {
        const joined = joinName(first, last);
        if (!joined) return '--';
        return joined.split(/\s+/).slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('');
    }

    function mostrarAlertaSeguro(message, type = 'info', duration = 5000) {
        if (typeof mostrarAlerta === 'function') {
            mostrarAlerta(String(message ?? ''), type, duration);
            return;
        }

        alert(message);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    function escapeAttribute(text) {
        return escapeHtml(text).replace(/"/g, '&quot;');
    }

    function createAppointmentDetailModal() {
        const modal = document.createElement('div');
        modal.className = 'cita-detail-modal';
        modal.hidden = true;
        modal.innerHTML = `
            <div class="cita-detail-backdrop" data-modal-close></div>
            <section class="cita-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="citaDetailTitle" tabindex="-1">
                <button type="button" class="cita-detail-close" data-modal-close aria-label="Cerrar detalle de cita">
                    <i class="bi bi-x-lg"></i>
                </button>
                <div class="cita-detail-content"></div>
            </section>`;
        document.body.appendChild(modal);

        const dialog = modal.querySelector('.cita-detail-dialog');
        const content = modal.querySelector('.cita-detail-content');
        let previousFocus = null;

        modal.addEventListener('click', event => {
            if (event.target.closest('[data-modal-close]')) close();
        });

        document.addEventListener('keydown', event => {
            if (!isOpen()) return;
            if (event.key === 'Escape') close();
        });

        function open() {
            previousFocus = document.activeElement;
            modal.hidden = false;
            document.body.classList.add('cita-modal-open');
            window.requestAnimationFrame(() => {
                modal.classList.add('is-open');
                dialog?.focus();
            });
        }

        function close() {
            modal.classList.remove('is-open');
            document.body.classList.remove('cita-modal-open');
            window.setTimeout(() => {
                modal.hidden = true;
                content.innerHTML = '';
                activeDetailId = null;
                if (previousFocus?.focus) previousFocus.focus();
            }, 180);
        }

        function isOpen() {
            return !modal.hidden;
        }

        function openLoading() {
            content.innerHTML = `
                <div class="cita-detail-loading">
                    <span class="cita-detail-spinner"></span>
                    <strong>Cargando detalle de la cita</strong>
                    <small>Estamos consultando la informacion clinica registrada.</small>
                </div>`;
            open();
        }

        function render(cita) {
            content.innerHTML = renderAppointmentDetail(cita);
            open();
        }

        function renderError(message) {
            content.innerHTML = `
                <div class="cita-detail-error">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>No fue posible abrir la cita</strong>
                    <span>${escapeHtml(message)}</span>
                </div>`;
            open();
        }

        return { openLoading, render, renderError, close, isOpen };
    }

    function renderAppointmentDetail(cita) {
        const patientName = joinName(cita.paciente_nombre, cita.paciente_apellido) || 'Paciente no disponible';
        const doctorName = joinName(cita.odontologo_nombre, cita.odontologo_apellido) || 'Odontologo no disponible';
        const creatorName = joinName(cita.creador_nombre, cita.creador_apellido) || 'No registrado';
        const initials = getInitials(cita.paciente_nombre, cita.paciente_apellido);
        const status = String(cita.estado || 'pendiente');
        const statusClass = detailStatusClass(status);
        const statusText = formatEstado(status);
        const planName = cita.plan_nombre || (cita.plan_id ? `Plan #${cita.plan_id}` : 'Sin plan asociado');
        const sessionName = cita.sesion_numero
            ? `Sesion ${cita.sesion_numero}${cita.sesion_descripcion ? ` - ${cita.sesion_descripcion}` : ''}`
            : (cita.sesion_id ? `Sesion #${cita.sesion_id}` : 'Sin sesion asociada');
        const visualMode = status === 'cancelada' ? 'is-canceled' : (status === 'atendida' ? 'is-finished' : '');

        return `
            <div class="cita-detail-shell ${visualMode}">
                <header class="cita-detail-hero">
                    <div class="cita-detail-patient">
                        <div class="cita-detail-avatar">${escapeHtml(initials)}</div>
                        <div>
                            <span class="cita-detail-eyebrow"><i class="bi bi-calendar2-heart"></i> Informacion general</span>
                            <h2 id="citaDetailTitle">${escapeHtml(patientName)}</h2>
                            <p>${escapeHtml(cita.tipo_documento || 'Doc.')} ${escapeHtml(cita.numero_documento || 'Sin documento')}</p>
                        </div>
                    </div>
                    <span class="cita-status-pill ${statusClass}">
                        <i class="${statusIcon(status)}"></i>
                        ${escapeHtml(statusText)}
                    </span>
                </header>

                <section class="cita-detail-grid">
                    ${detailMetric('bi-calendar3', 'Fecha', formatFechaLarga(cita.fecha))}
                    ${detailMetric('bi-clock', 'Hora inicio', formatHora(cita.hora_inicio) || '--:--')}
                    ${detailMetric('bi-clock-history', 'Hora fin', formatHora(cita.hora_fin) || '--:--')}
                    ${detailMetric('bi-person-badge', 'Odontologo asignado', doctorName)}
                </section>

                <section class="cita-detail-body">
                    <article class="cita-detail-card cita-detail-card-main">
                        <header><i class="bi bi-chat-square-text"></i><div><span>Motivo de consulta</span><strong>Contexto clinico</strong></div></header>
                        <p>${escapeHtml(cita.motivo || 'Sin motivo registrado')}</p>
                    </article>
                    <article class="cita-detail-card">
                        <header><i class="bi bi-journal-medical"></i><div><span>Plan asociado</span><strong>${escapeHtml(planName)}</strong></div></header>
                        <p>${escapeHtml(cita.plan_descripcion || cita.plan_estado || 'No hay informacion adicional del plan.')}</p>
                    </article>
                    <article class="cita-detail-card">
                        <header><i class="bi bi-layers"></i><div><span>Sesion asociada</span><strong>${escapeHtml(sessionName)}</strong></div></header>
                        <p>${escapeHtml(cita.sesion_estado || 'No hay informacion adicional de la sesion.')}</p>
                    </article>
                    <article class="cita-detail-card cita-detail-card-main">
                        <header><i class="bi bi-pencil-square"></i><div><span>Observaciones</span><strong>Notas internas</strong></div></header>
                        <p>${escapeHtml(cita.notas || 'Sin observaciones registradas')}</p>
                    </article>
                </section>

                <section class="cita-detail-timeline" aria-label="Timeline de la cita">
                    ${timelineItem('bi-plus-circle', 'Registrada', formatDateTime(cita.created_at), `Usuario: ${creatorName}`, true)}
                    ${timelineItem(statusIcon(status), statusText, `${formatFechaLarga(cita.fecha)} · ${formatHora(cita.hora_inicio) || '--:--'}`, doctorName, status !== 'cancelada')}
                    ${timelineItem(status === 'atendida' ? 'bi-check2-circle' : 'bi-hourglass-split', status === 'atendida' ? 'Finalizada' : 'Seguimiento', status === 'atendida' ? 'Cita marcada como finalizada' : 'Pendiente de evolucion clinica', cita.plan_nombre || 'Agenda DentiSoft', status === 'atendida')}
                </section>

                <footer class="cita-detail-footer">
                    <div>
                        <span>Fecha de creacion</span>
                        <strong>${escapeHtml(formatDateTime(cita.created_at) || 'No registrada')}</strong>
                    </div>
                    <div>
                        <span>Registrada por</span>
                        <strong>${escapeHtml(creatorName)}</strong>
                    </div>
                    <a href="${editUrlBase}?id=${encodeURIComponent(cita.id)}" class="btn btn-primary">
                        <i class="bi bi-pencil-square"></i> Editar cita
                    </a>
                </footer>
            </div>`;
    }

    function detailMetric(icon, label, value) {
        return `
            <article class="cita-detail-metric">
                <i class="bi ${icon}"></i>
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value || 'No registrado')}</strong>
            </article>`;
    }

    function timelineItem(icon, title, meta, caption, active) {
        return `
            <article class="cita-timeline-item ${active ? 'is-active' : ''}">
                <span class="cita-timeline-dot"><i class="${icon.includes('bi ') ? icon : `bi ${icon}`}"></i></span>
                <div>
                    <strong>${escapeHtml(title)}</strong>
                    <span>${escapeHtml(meta || '')}</span>
                    <small>${escapeHtml(caption || '')}</small>
                </div>
            </article>`;
    }

    function detailStatusClass(estado) {
        return {
            pendiente: 'status-pendiente',
            confirmada: 'status-confirmada',
            atendida: 'status-atendida',
            cancelada: 'status-cancelada',
            no_asistio: 'status-no-asistio',
        }[estado] || 'status-pendiente';
    }

    function statusIcon(estado) {
        return {
            pendiente: 'bi bi-hourglass-split',
            confirmada: 'bi bi-check2-circle',
            atendida: 'bi bi-check-all',
            cancelada: 'bi bi-x-circle',
            no_asistio: 'bi bi-person-x',
        }[estado] || 'bi bi-calendar-check';
    }

    function formatFechaLarga(value) {
        if (!value) return '';
        const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleDateString('es-CO', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
    }

    function formatDateTime(value) {
        if (!value) return '';
        const normalized = String(value).replace(' ', 'T');
        const date = new Date(normalized);
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleString('es-CO', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function notifyCalendarChanged() {
        try {
            window.localStorage.setItem('dentisoft:citas:changed', String(Date.now()));
        } catch (error) {
            window.dispatchEvent(new CustomEvent('citas:changed'));
        }
    }
});
