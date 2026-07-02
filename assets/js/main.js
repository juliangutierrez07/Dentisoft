/**
 * ============================================================
 * DentiSoft 1.0 — JavaScript Global
 * ============================================================
 */

// ─── AJAX Helper ─────────────────────────────────────────────
async function ajaxRequest(url, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
        }
    };

    if (data && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
        if (typeof data === 'object' && !(data instanceof FormData)) {
            data.csrf_token = typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '';
        }
        options.body = JSON.stringify(data);
    }

    try {
        const res = await fetch(url, options);
        if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        return await res.json();
    } catch (err) {
        console.error('AJAX Error:', err);
        mostrarAlerta('Error de conexión: ' + err.message, 'danger');
        return null;
    }
}

// ─── Alertas Bootstrap Programáticas ─────────────────────────
function mostrarAlerta(mensaje, tipo = 'success', duracion = 4000) {
    const container = document.getElementById('alert-container');
    if (!container) return;

    const iconos = {
        success: 'bi-check-circle-fill',
        danger:  'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        info:    'bi-info-circle-fill'
    };

    const id = 'alerta-' + Date.now();
    const icono = iconos[tipo] || iconos.info;
    const mensajeSeguro = escapeHtml(mensaje);

    container.insertAdjacentHTML('afterbegin', `
        <div id="${id}" class="alert alert-${tipo} alert-dismissible fade show animate-slideUp" role="alert">
            <i class="bi ${icono} me-2"></i>
            ${mensajeSeguro}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    `);

    if (duracion > 0) {
        setTimeout(() => {
            const el = document.getElementById(id);
            if (el) {
                el.classList.remove('show');
                setTimeout(() => el.remove(), 300);
            }
        }, duracion);
    }
}

// ─── Sidebar Toggle ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    function updateFieldValidation(field) {
        if (!field || field.disabled) return;
        const value = field.value.trim();
        if (value === '' && !field.required) {
            field.classList.remove('is-valid', 'is-invalid');
            return;
        }
        if (field.checkValidity()) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
        }
    }

    // ─── Validación de formularios Bootstrap ──────────────────
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const fields = form.querySelectorAll('input, select, textarea');
            fields.forEach(updateFieldValidation);

            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // ─── Prevenir doble envío de formularios ──────────────────
    document.querySelectorAll('form[data-prevent-double]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity() || e.defaultPrevented) {
                return;
            }

            const btn = form.querySelector('[type="submit"]');
            if (btn) {
                btn.disabled = true;
                const spinner = btn.querySelector('.spinner-border');
                if (spinner) spinner.classList.remove('d-none');
            }
        });
    });

    // ─── Cargar notificaciones ────────────────────────────────
    cargarNotificaciones();

    // ─── Marcar todas las notificaciones como leídas ──────────
    const btnMarcarTodas = document.getElementById('marcarTodasLeidas');
    if (btnMarcarTodas) {
        btnMarcarTodas.addEventListener('click', function(e) {
            e.preventDefault();
            marcarTodasNotificaciones();
        });
    }
});

// ─── Notificaciones AJAX ─────────────────────────────────────
async function cargarNotificaciones() {
    const lista = document.getElementById('listaNotificaciones');
    if (!lista) return;

    try {
        const data = await ajaxRequest(BASE_URL + '/api/notificaciones_api.php?action=listar');
        if (!data || !data.success) {
            lista.innerHTML = '<div class="text-center p-3 text-muted"><small>Sin notificaciones</small></div>';
            return;
        }

        if (data.notificaciones.length === 0) {
            lista.innerHTML = `
                <div class="text-center p-4 text-muted">
                    <i class="bi bi-bell-slash" style="font-size:1.5rem;opacity:0.3;"></i>
                    <small class="d-block mt-2">No hay notificaciones nuevas</small>
                </div>`;
            return;
        }

        let html = '';
        data.notificaciones.forEach(function(n) {
            const iconos = {
                cita:    'bi-calendar-check text-info',
                pago:    'bi-cash-coin text-success',
                sistema: 'bi-gear text-primary',
                alerta:  'bi-exclamation-triangle text-warning'
            };
            const icono = iconos[n.tipo] || iconos.sistema;
            const tiempo = tiempoRelativo(n.created_at);

            html += `
                <div class="dropdown-item notif-item ${n.leida ? '' : 'notif-unread'}" data-id="${n.id}">
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi ${icono} mt-1"></i>
                        <div class="flex-grow-1 min-width-0">
                            <div class="notif-titulo">${escapeHtml(n.titulo)}</div>
                            <div class="notif-msg">${escapeHtml(n.mensaje)}</div>
                            <small class="text-muted">${tiempo}</small>
                        </div>
                    </div>
                </div>`;
        });
        lista.innerHTML = html;

    } catch (err) {
        lista.innerHTML = '<div class="text-center p-3 text-muted"><small>Error al cargar</small></div>';
    }
}

async function marcarTodasNotificaciones() {
    const data = await ajaxRequest(BASE_URL + '/api/notificaciones_api.php?action=marcar_todas', 'POST', {});
    if (data && data.success) {
        const badge = document.querySelector('.badge-notif');
        if (badge) badge.remove();
        cargarNotificaciones();
    }
}

// ─── Utilidades ──────────────────────────────────────────────
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function tiempoRelativo(fecha) {
    const ahora = new Date();
    const entonces = new Date(fecha);
    const diffMs = ahora - entonces;
    const diffMin = Math.floor(diffMs / 60000);
    const diffHoras = Math.floor(diffMin / 60);
    const diffDias = Math.floor(diffHoras / 24);

    if (diffMin < 1) return 'Ahora mismo';
    if (diffMin < 60) return diffMin + ' min';
    if (diffHoras < 24) return diffHoras + 'h';
    if (diffDias < 7) return diffDias + 'd';
    return entonces.toLocaleDateString('es-CO', { day: '2-digit', month: 'short' });
}

function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(valor);
}

function formatearFecha(fecha) {
    if (!fecha) return '';
    return new Date(fecha).toLocaleDateString('es-CO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

// ─── Confirmar eliminación ───────────────────────────────────
function confirmarEliminacion(url, nombre) {
    if (confirm(`¿Estás seguro de que deseas eliminar "${nombre}"?\nEsta acción no se puede deshacer.`)) {
        window.location.href = url;
    }
}

// ─── Estilos dinámicos de notificaciones ─────────────────────
const styleNotif = document.createElement('style');
styleNotif.textContent = `
    .notif-item {
        padding: 12px 16px !important;
        border-bottom: 1px solid rgba(51,65,85,0.3);
        white-space: normal !important;
        cursor: pointer;
    }
    .notif-item:last-child { border-bottom: none; }
    .notif-unread { background: rgba(99,102,241,0.06); }
    .notif-titulo { font-size: 0.84rem; font-weight: 600; color: #e2e8f0; }
    .notif-msg { font-size: 0.78rem; color: #94a3b8; margin-top: 2px; line-height: 1.4; }
`;
document.head.appendChild(styleNotif);
