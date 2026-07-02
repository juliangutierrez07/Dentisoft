document.addEventListener('DOMContentLoaded', function () {
    const shell = document.getElementById('calendarShell');
    const calendarNode = document.getElementById('dentisoftFullCalendar');
    const searchInput = document.getElementById('calendarSearch');
    const dateInput = document.getElementById('calendarGoToDate');
    const refreshButton = document.getElementById('calendarRefresh');
    const filterButtons = document.querySelectorAll('[data-calendar-filter]');
    const statNodes = document.querySelectorAll('[data-calendar-stat]');
    const debugCalendar = isDebugEnabled();

    if (!shell || !calendarNode) return;

    if (window.dentisoftCalendar?.destroy) {
        window.dentisoftCalendar.destroy();
        window.dentisoftCalendar = null;
    }

    if (!window.FullCalendar) {
        setLoading(false);
        showCalendarError('No fue posible cargar FullCalendar. Revisa la conexion a internet o instala los assets locales.');
        return;
    }

    let activeFilter = 'all';
    let activeSearch = '';
    let refreshTimer = null;
    let isMounted = true;
    let calendar = null;

    const calendarState = ensureStatePanel();
    const detailModal = createCalendarAppointmentDetailModal();

    try {
        calendar = new FullCalendar.Calendar(calendarNode, {
            locale: 'es',
            timeZone: 'local',
            initialDate: shell.dataset.initialDate || todayIso(),
            initialView: getInitialView(),
            firstDay: 1,
            height: 'auto',
            expandRows: true,
            stickyHeaderDates: true,
            nowIndicator: true,
            navLinks: true,
            selectable: true,
            selectMirror: true,
            dayMaxEvents: 3,
            moreLinkClick: 'popover',
            slotMinTime: '06:00:00',
            slotMaxTime: '21:00:00',
            slotDuration: '00:30:00',
            eventTimeFormat: {
                hour: 'numeric',
                minute: '2-digit',
                meridiem: 'short',
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay',
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Dia',
            },
            loading(isLoading) {
                debugLog('Estado loading FullCalendar', isLoading);
                setLoading(isLoading);
            },
            events(fetchInfo, successCallback) {
                loadEventsForFullCalendar(fetchInfo, successCallback);
            },
            eventClassNames(info) {
                const estado = info.event.extendedProps?.estado || 'pendiente';
                return shouldShowEvent(info.event) ? [`status-${estado}`] : [`status-${estado}`, 'is-hidden'];
            },
            eventContent(info) {
                return { domNodes: [renderAppointmentEvent(info)] };
            },
            eventDidMount(info) {
                const props = info.event.extendedProps || {};
                info.el.dataset.eventId = info.event.id;
                info.el.classList.toggle('is-hidden', !shouldShowEvent(info.event));
                info.el.title = [
                    props.paciente || info.event.title,
                    props.horaInicio && props.horaFin ? `${props.horaInicio} - ${props.horaFin}` : '',
                    props.estadoTexto || '',
                    props.motivo || '',
                ].filter(Boolean).join(' - ');
            },
            eventClick(info) {
                info.jsEvent.preventDefault();
                openAppointmentDetail(info.event.id);
            },
            dateClick(info) {
                window.location.href = `crear.php?fecha=${encodeURIComponent(info.dateStr)}&volver=calendario`;
            },
            select(info) {
                const date = normalizeIsoDate(info.startStr);
                if (date) window.location.href = `crear.php?fecha=${encodeURIComponent(date)}&volver=calendario`;
            },
            datesSet() {
                syncCurrentDateInput();
                window.setTimeout(applyClientFilters, 0);
            },
            windowResize() {
                const view = getInitialView();
                if (window.innerWidth < 720 && calendar?.view?.type !== view) {
                    calendar.changeView(view);
                }
            },
        });

        setLoading(true);
        calendar.render();
        window.dentisoftCalendar = calendar;
        window.refreshCalendarWeek = refetchCalendar;
    } catch (error) {
        console.error('Error al inicializar FullCalendar:', error);
        debugLog('Error de inicializacion FullCalendar', error);
        setLoading(false);
        showCalendarError('No fue posible inicializar el calendario.');
        return;
    }

    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.calendarFilter || 'all';
            filterButtons.forEach(item => item.classList.toggle('active', item === button));
            applyClientFilters();
        });
    });

    searchInput?.addEventListener('input', debounce(() => {
        activeSearch = normalize(searchInput.value);
        applyClientFilters();
    }, 160));

    dateInput?.addEventListener('change', () => {
        const date = normalizeIsoDate(dateInput.value);
        if (date) calendar?.gotoDate(date);
    });

    refreshButton?.addEventListener('click', () => refetchCalendar({ force: true }));

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refetchCalendar({ silent: true });
    });
    window.addEventListener('focus', () => refetchCalendar({ silent: true }));
    window.addEventListener('pageshow', () => refetchCalendar({ silent: true }));
    window.addEventListener('citas:changed', () => refetchCalendar({ force: true }));
    window.addEventListener('storage', event => {
        if (event.key === 'dentisoft:citas:changed') refetchCalendar({ force: true });
    });
    window.addEventListener('beforeunload', () => {
        isMounted = false;
        if (refreshTimer) window.clearInterval(refreshTimer);
        if (window.dentisoftCalendar === calendar) window.dentisoftCalendar = null;
    });

    refreshTimer = window.setInterval(() => refetchCalendar({ silent: true }), 30000);

    if (new URLSearchParams(window.location.search).has('refresh')) {
        window.setTimeout(() => refetchCalendar({ force: true }), 250);
    }

    async function loadEventsForFullCalendar(fetchInfo, successCallback) {
        debugLog('Rango solicitado por FullCalendar', {
            start: fetchInfo.startStr,
            end: fetchInfo.endStr,
            timeZone: fetchInfo.timeZone,
            view: calendar?.view?.type,
        });

        setLoading(true);
        showCalendarState('');

        try {
            const payload = await loadAppointments(fetchInfo);
            const events = Array.isArray(payload.events) ? payload.events : [];

            updateStats(payload.stats || {});
            debugLog('Eventos enviados a FullCalendar', events);
            successCallback(events);

            window.setTimeout(() => {
                applyClientFilters();
                updateEmptyState();
            }, 0);
        } catch (error) {
            console.error('Error al cargar citas en FullCalendar:', error);
            debugLog('Error fetch/render calendario', {
                message: error?.message,
                stack: error?.stack,
            });
            updateStats({});
            successCallback([]);
            showCalendarError(error.message || 'No fue posible cargar las citas.');
        } finally {
            setLoading(false);
        }
    }

    async function loadAppointments(fetchInfo) {
        const params = new URLSearchParams({
            action: 'fullcalendar',
            start: fetchInfo.startStr,
            end: fetchInfo.endStr,
            _: String(Date.now()),
        });

        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), 15000);

        try {
            const response = await fetch(`${BASE_URL}/api/citas_api.php?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
                signal: controller.signal,
            });
            const rawText = await response.text();
            let data = null;

            try {
                data = rawText ? JSON.parse(rawText) : null;
            } catch (error) {
                debugLog('Respuesta no JSON calendario', rawText);
                throw new Error('La API del calendario devolvio una respuesta invalida.');
            }

            debugLog('Respuesta API calendario', data);

            if (!response.ok || !data?.success || !Array.isArray(data.events)) {
                throw new Error(data?.error || `No fue posible cargar el calendario. HTTP ${response.status}`);
            }

            const events = data.events.map(normalizeCalendarEvent).filter(Boolean);
            debugLog('Eventos transformados', events);

            return { ...data, events };
        } catch (error) {
            if (error.name === 'AbortError') {
                throw new Error('La carga del calendario tardo demasiado. Intenta actualizar.');
            }

            throw error;
        } finally {
            window.clearTimeout(timer);
        }
    }

    function normalizeCalendarEvent(rawEvent) {
        const id = String(rawEvent?.id || '').trim();
        const title = String(rawEvent?.title || rawEvent?.extendedProps?.paciente || 'Cita odontologica').trim();
        const start = normalizeDateTime(rawEvent?.start);
        const end = normalizeDateTime(rawEvent?.end) || addMinutesToDateTime(start, 30);

        if (!id || !start) {
            debugLog('Evento descartado por formato invalido', rawEvent);
            return null;
        }

        return {
            ...rawEvent,
            id,
            title,
            start,
            end,
            allDay: rawEvent?.allDay === true,
            extendedProps: {
                ...(rawEvent?.extendedProps || {}),
                estado: rawEvent?.extendedProps?.estado || 'pendiente',
                paciente: rawEvent?.extendedProps?.paciente || title,
            },
        };
    }

    function normalizeDateTime(value) {
        const text = String(value || '').trim();
        const match = text.match(/^(\d{4}-\d{2}-\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/);
        if (!match) return '';

        return `${match[1]}T${match[2]}:${match[3]}:${match[4] || '00'}`;
    }

    function addMinutesToDateTime(value, minutes) {
        const match = String(value || '').match(/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2}):(\d{2})$/);
        if (!match) return '';

        const date = new Date(
            Number(match[1].slice(0, 4)),
            Number(match[1].slice(5, 7)) - 1,
            Number(match[1].slice(8, 10)),
            Number(match[2]),
            Number(match[3]),
            Number(match[4]),
        );
        date.setMinutes(date.getMinutes() + minutes);
        return `${toIsoLocal(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    function refetchCalendar(options = {}) {
        if (!calendar) return;

        const now = Date.now();
        if (!options.force && options.silent && now - (refetchCalendar.lastRun || 0) < 1600) return;

        refetchCalendar.lastRun = now;
        showCalendarState('');
        setLoading(true);
        calendar.refetchEvents();
    }

    function shouldShowEvent(event) {
        const props = event.extendedProps || {};
        const matchesStatus = activeFilter === 'all' || props.estado === activeFilter;
        const haystack = normalize([
            event.title,
            props.paciente,
            props.odontologo,
            props.motivo,
            props.estadoTexto,
            props.documento,
        ].filter(Boolean).join(' '));

        return matchesStatus && (activeSearch === '' || haystack.includes(activeSearch));
    }

    function applyClientFilters() {
        if (!calendar) return;

        const eventsById = new Map(calendar.getEvents().map(event => [String(event.id), event]));
        calendarNode.querySelectorAll('.fc-event[data-event-id]').forEach(node => {
            const event = eventsById.get(String(node.dataset.eventId));
            node.classList.toggle('is-hidden', event ? !shouldShowEvent(event) : true);
        });
        updateEmptyState();
    }

    function updateEmptyState() {
        if (!calendar || shell.classList.contains('has-calendar-error')) return;

        const visibleCount = calendar.getEvents().filter(shouldShowEvent).length;
        if (visibleCount === 0) {
            showCalendarEmpty('No hay citas para los filtros o el rango seleccionado.');
            return;
        }

        showCalendarState('');
    }

    function renderAppointmentEvent(info) {
        const event = info.event;
        const props = event.extendedProps || {};
        const wrapper = document.createElement('div');
        wrapper.className = 'fc-cita-card';

        const time = document.createElement('span');
        time.className = 'fc-cita-time';
        time.textContent = props.horaInicio || info.timeText || '';

        const body = document.createElement('span');
        body.className = 'fc-cita-body';

        const patient = document.createElement('strong');
        patient.textContent = props.paciente || event.title || 'Paciente no disponible';

        const reason = document.createElement('small');
        reason.textContent = props.motivo || 'Sin motivo';

        const meta = document.createElement('span');
        meta.className = 'fc-cita-meta';

        const status = document.createElement('em');
        status.textContent = props.estadoTexto || 'Pendiente';

        const doctor = document.createElement('span');
        doctor.textContent = props.odontologo || '';

        body.append(patient, reason);
        meta.append(status, doctor);
        wrapper.append(time, body, meta);

        return wrapper;
    }

    async function openAppointmentDetail(citaId) {
        if (!/^[1-9]\d*$/.test(String(citaId || '').trim())) return;

        detailModal.openLoading();

        try {
            const response = await fetch(`${BASE_URL}/api/citas_api.php?action=detalle&id=${encodeURIComponent(citaId)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            });
            const data = await response.json();

            if (!response.ok || !data?.success || !data?.cita?.id) {
                throw new Error(data?.error || 'No fue posible cargar el detalle de la cita.');
            }

            detailModal.render(data.cita);
        } catch (error) {
            console.error('Error al abrir detalle desde calendario:', error);
            detailModal.renderError(error.message || 'No fue posible cargar el detalle de la cita.');
        }
    }

    function createCalendarAppointmentDetailModal() {
        const modal = document.createElement('div');
        modal.className = 'cita-detail-modal';
        modal.hidden = true;
        modal.innerHTML = `
            <div class="cita-detail-backdrop" data-modal-close></div>
            <section class="cita-detail-dialog" role="dialog" aria-modal="true" aria-labelledby="citaDetailTitle" tabindex="-1">
                <button type="button" class="cita-detail-close" data-modal-close aria-label="Cerrar detalle de cita"><i class="bi bi-x-lg"></i></button>
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
            if (!modal.hidden && event.key === 'Escape') close();
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
                if (previousFocus?.focus) previousFocus.focus();
            }, 180);
        }

        return {
            openLoading() {
                content.innerHTML = `
                    <div class="cita-detail-loading">
                        <span class="cita-detail-spinner"></span>
                        <strong>Cargando detalle de la cita</strong>
                        <small>Estamos consultando la informacion clinica registrada.</small>
                    </div>`;
                open();
            },
            render(cita) {
                content.innerHTML = renderCalendarAppointmentDetail(cita);
                open();
            },
            renderError(message) {
                content.innerHTML = `
                    <div class="cita-detail-error">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>No fue posible abrir la cita</strong>
                        <span>${escapeHtml(message)}</span>
                    </div>`;
                open();
            },
        };
    }

    function renderCalendarAppointmentDetail(cita) {
        const patientName = joinName(cita.paciente_nombre, cita.paciente_apellido) || 'Paciente no disponible';
        const doctorName = joinName(cita.odontologo_nombre, cita.odontologo_apellido) || 'Odontologo no disponible';
        const creatorName = joinName(cita.creador_nombre, cita.creador_apellido) || 'No registrado';
        const status = String(cita.estado || 'pendiente');
        const planName = cita.plan_nombre || (cita.plan_id ? `Plan #${cita.plan_id}` : 'Sin plan asociado');
        const sessionName = cita.sesion_numero ? `Sesion ${cita.sesion_numero}` : (cita.sesion_id ? `Sesion #${cita.sesion_id}` : 'Sin sesion asociada');
        const visualMode = status === 'cancelada' ? 'is-canceled' : (status === 'atendida' ? 'is-finished' : '');

        return `
            <div class="cita-detail-shell ${visualMode}">
                <header class="cita-detail-hero">
                    <div class="cita-detail-patient">
                        <div class="cita-detail-avatar">${escapeHtml(getInitials(cita.paciente_nombre, cita.paciente_apellido))}</div>
                        <div>
                            <span class="cita-detail-eyebrow"><i class="bi bi-calendar2-heart"></i> Informacion general</span>
                            <h2 id="citaDetailTitle">${escapeHtml(patientName)}</h2>
                            <p>${escapeHtml(cita.tipo_documento || 'Doc.')} ${escapeHtml(cita.numero_documento || 'Sin documento')}</p>
                        </div>
                    </div>
                    <span class="cita-status-pill ${detailStatusClass(status)}"><i class="${statusIcon(status)}"></i>${escapeHtml(formatEstado(status))}</span>
                </header>
                <section class="cita-detail-grid">
                    ${detailMetric('bi-calendar3', 'Fecha', formatFechaLarga(cita.fecha))}
                    ${detailMetric('bi-clock', 'Hora inicio', formatTime(cita.hora_inicio) || '--:--')}
                    ${detailMetric('bi-clock-history', 'Hora fin', formatTime(cita.hora_fin) || '--:--')}
                    ${detailMetric('bi-person-badge', 'Odontologo asignado', doctorName)}
                </section>
                <section class="cita-detail-body">
                    <article class="cita-detail-card cita-detail-card-main"><header><i class="bi bi-chat-square-text"></i><div><span>Motivo de consulta</span><strong>Contexto clinico</strong></div></header><p>${escapeHtml(cita.motivo || 'Sin motivo registrado')}</p></article>
                    <article class="cita-detail-card"><header><i class="bi bi-journal-medical"></i><div><span>Plan asociado</span><strong>${escapeHtml(planName)}</strong></div></header><p>${escapeHtml(cita.plan_descripcion || cita.plan_estado || 'No hay informacion adicional del plan.')}</p></article>
                    <article class="cita-detail-card"><header><i class="bi bi-layers"></i><div><span>Sesion asociada</span><strong>${escapeHtml(sessionName)}</strong></div></header><p>${escapeHtml(cita.sesion_descripcion || cita.sesion_estado || 'No hay informacion adicional de la sesion.')}</p></article>
                    <article class="cita-detail-card cita-detail-card-main"><header><i class="bi bi-pencil-square"></i><div><span>Observaciones</span><strong>Notas internas</strong></div></header><p>${escapeHtml(cita.notas || 'Sin observaciones registradas')}</p></article>
                </section>
                <section class="cita-detail-timeline" aria-label="Timeline de la cita">
                    ${timelineItem('bi-plus-circle', 'Registrada', formatDateTime(cita.created_at), `Usuario: ${creatorName}`, true)}
                    ${timelineItem(statusIcon(status), formatEstado(status), `${formatFechaLarga(cita.fecha)} · ${formatTime(cita.hora_inicio) || '--:--'}`, doctorName, status !== 'cancelada')}
                    ${timelineItem(status === 'atendida' ? 'bi-check2-circle' : 'bi-hourglass-split', status === 'atendida' ? 'Finalizada' : 'Seguimiento', status === 'atendida' ? 'Cita marcada como finalizada' : 'Pendiente de evolucion clinica', cita.plan_nombre || 'Agenda DentiSoft', status === 'atendida')}
                </section>
                <footer class="cita-detail-footer">
                    <div><span>Fecha de creacion</span><strong>${escapeHtml(formatDateTime(cita.created_at) || 'No registrada')}</strong></div>
                    <div><span>Registrada por</span><strong>${escapeHtml(creatorName)}</strong></div>
                    <a href="${BASE_URL}/modules/citas/editar.php?id=${encodeURIComponent(cita.id)}" class="btn btn-primary"><i class="bi bi-pencil-square"></i> Editar cita</a>
                </footer>
            </div>`;
    }

    function updateStats(stats) {
        statNodes.forEach(node => {
            const key = node.dataset.calendarStat;
            const value = Number(stats[key] || 0);
            node.textContent = new Intl.NumberFormat('es-CO').format(value);
        });
    }

    function syncCurrentDateInput() {
        if (!dateInput || !calendar) return;
        dateInput.value = toIsoLocal(calendar.getDate());
    }

    function setLoading(isLoading) {
        if (!isMounted) return;
        shell.classList.toggle('is-loading', Boolean(isLoading));
        debugLog('Estado loading UI', Boolean(isLoading));
    }

    function ensureStatePanel() {
        let node = shell.querySelector('.fullcalendar-state');
        if (!node) {
            node = document.createElement('div');
            node.className = 'fullcalendar-state';
            node.setAttribute('role', 'status');
            node.setAttribute('aria-live', 'polite');
            shell.appendChild(node);
        }

        return node;
    }

    function showCalendarError(message) {
        shell.classList.add('has-calendar-error');
        shell.classList.remove('has-calendar-empty');
        showCalendarState(`<i class="bi bi-exclamation-triangle"></i><strong>No fue posible cargar el calendario</strong><span>${escapeHtml(message)}</span>`);
    }

    function showCalendarEmpty(message) {
        shell.classList.add('has-calendar-empty');
        shell.classList.remove('has-calendar-error');
        showCalendarState(`<i class="bi bi-calendar2-x"></i><strong>No hay citas</strong><span>${escapeHtml(message)}</span>`);
    }

    function showCalendarState(html) {
        if (!calendarState) return;

        if (!html) {
            shell.classList.remove('has-calendar-error', 'has-calendar-empty');
            calendarState.innerHTML = '';
            return;
        }

        calendarState.innerHTML = html;
    }

    function getInitialView() {
        if (window.innerWidth < 720) return 'timeGridDay';
        if (window.innerWidth < 1024) return 'timeGridWeek';
        return 'dayGridMonth';
    }

    function todayIso() {
        return toIsoLocal(new Date());
    }

    function toIsoLocal(date) {
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function normalizeIsoDate(value) {
        const match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})/);
        return match ? `${match[1]}-${match[2]}-${match[3]}` : '';
    }

    function normalize(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function debounce(fn, delay) {
        let timer = null;
        return function (...args) {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
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

    function formatEstado(estado) {
        if (estado === 'atendida') return 'Finalizada';
        if (estado === 'no_asistio') return 'No Asistio';

        return String(estado || 'pendiente')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, letter => letter.toUpperCase());
    }

    function formatTime(value) {
        if (!value) return '';
        const [hour, minute] = String(value).split(':');
        const date = new Date();
        date.setHours(Number(hour), Number(minute), 0, 0);
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' });
    }

    function formatFechaLarga(value) {
        if (!value) return '';
        const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleDateString('es-CO', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
    }

    function formatDateTime(value) {
        if (!value) return '';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleString('es-CO', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function joinName(first, last) {
        return [first, last].map(value => String(value || '').trim()).filter(Boolean).join(' ');
    }

    function getInitials(first, last) {
        const joined = joinName(first, last);
        if (!joined) return '--';
        return joined.split(/\s+/).slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('');
    }

    function debugLog(label, value) {
        if (!debugCalendar) return;
        console.debug(`[DentiSoft calendario] ${label}:`, value);
    }

    function isDebugEnabled() {
        if (new URLSearchParams(window.location.search).has('debugCalendar')) return true;

        try {
            return window.localStorage.getItem('dentisoft:debug-calendar') === '1';
        } catch (error) {
            return false;
        }
    }
});
