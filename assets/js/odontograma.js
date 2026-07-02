/**
 * Odontograma interactivo - DentiSoft 1.0
 */

document.addEventListener('DOMContentLoaded', function () {
    const svg = document.getElementById('odontogramaSvg');
    const board = document.getElementById('odontogramaBoard');
    const modalEl = document.getElementById('modalEditarPieza');
    const form = document.getElementById('formEditarPieza');
    const piezaInput = document.getElementById('piezaDental');
    const piezaLabel = document.getElementById('piezaLabel');
    const estadoSelect = document.getElementById('estadoPieza');
    const notasTextarea = document.getElementById('notasPieza');
    const historiaId = document.getElementById('historiaId')?.value;
    const checkboxes = Array.from(document.querySelectorAll('#formEditarPieza input[name="caras_afectadas[]"]'));
    const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;
    const tooltip = document.getElementById('toothTooltip');
    const editSelectedBtn = document.getElementById('editSelectedBtn');
    const zoomRange = document.getElementById('zoomRange');
    const viewToggleBtn = document.getElementById('viewToggleBtn');
    const resetViewBtn = document.getElementById('resetViewBtn');
    const submitBtn = document.querySelector('[type="submit"][form="formEditarPieza"]') || form.querySelector('[type="submit"]');

    const stateColors = {
        sano: '#10b981',
        caries: '#ef4444',
        obturado: '#3b82f6',
        extraccion_indicada: '#f59e0b',
        ausente: '#6b7280',
        corona: '#facc15',
        protesis: '#8b5cf6',
        implante: '#14b8a6',
        fractura: '#f97316',
        tratamiento_conductos: '#0ea5e9',
        otro: '#94a3b8'
    };

    let selectedTooth = null;
    let tiltX = 0;
    let tiltY = 0;
    let rotateZ = 0;
    let zoom = 1;

    if (!svg || !board || !form || !bsModal) return;

    const teeth = Array.from(svg.querySelectorAll('.tooth'));
    teeth.forEach(function (tooth) {
        tooth.addEventListener('click', function () {
            selectTooth(tooth, true);
        });

        tooth.addEventListener('mouseenter', function (event) {
            showTooltip(tooth, event);
        });

        tooth.addEventListener('mousemove', function (event) {
            moveTooltip(event);
        });

        tooth.addEventListener('mouseleave', hideTooltip);

        tooth.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectTooth(tooth, true);
            }
        });

        tooth.setAttribute('tabindex', '0');
        tooth.setAttribute('role', 'button');
        tooth.setAttribute('aria-label', `Pieza ${tooth.dataset.pieza}, ${formatEstado(tooth.dataset.estado || 'sano')}`);
    });

    if (teeth[0]) selectTooth(teeth[0], false);

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        const selectedPiece = piezaInput.value;
        const estado = estadoSelect.value;
        const notas = notasTextarea.value.trim();
        const caras = checkboxes.filter(chk => chk.checked).map(chk => chk.value);

        setSavingState(true);

        let result = null;
        try {
            const payload = {
                action: 'guardar',
                historia_id: parseInt(historiaId, 10),
                pieza_dental: selectedPiece,
                estado: estado,
                caras_afectadas: caras,
                notas: notas
            };

            result = await saveTooth(payload);
        } finally {
            setSavingState(false);
        }

        if (!result || !result.success) {
            mostrarAlerta(result?.error || 'No se pudo guardar la pieza.', 'danger');
            return;
        }

        const toothElement = svg.querySelector(`[data-pieza='${selectedPiece}']`);
        if (toothElement) {
            updateToothElement(toothElement, result);
            selectTooth(toothElement, false);
            refreshStats();
        }

        mostrarAlerta(result.message || 'Pieza dental actualizada correctamente', 'success');
        bsModal.hide();
    });

    editSelectedBtn?.addEventListener('click', function () {
        if (selectedTooth) openToothEditor(selectedTooth);
    });

    document.querySelectorAll('[data-view-mode]').forEach(function (button) {
        button.addEventListener('click', function () {
            setViewMode(button.dataset.viewMode);
        });
    });

    viewToggleBtn?.addEventListener('click', function () {
        setViewMode(board.classList.contains('is-3d') ? '2d' : '3d');
    });

    document.getElementById('zoomInBtn')?.addEventListener('click', function () {
        setZoom(Math.min(1.3, zoom + 0.08));
    });

    document.getElementById('zoomOutBtn')?.addEventListener('click', function () {
        setZoom(Math.max(0.8, zoom - 0.08));
    });

    zoomRange?.addEventListener('input', function () {
        setZoom(Number(zoomRange.value) / 100);
    });

    document.getElementById('tiltLeftBtn')?.addEventListener('click', function () {
        rotateZ = Math.max(-8, rotateZ - 3);
        applyTransform();
    });

    document.getElementById('tiltRightBtn')?.addEventListener('click', function () {
        rotateZ = Math.min(8, rotateZ + 3);
        applyTransform();
    });

    resetViewBtn?.addEventListener('click', function () {
        tiltX = 0;
        tiltY = 0;
        rotateZ = 0;
        setZoom(1);
        setViewMode('2d');
        clearLegendFilter();
        if (teeth[0]) selectTooth(teeth[0], false);
    });

    board.addEventListener('mousemove', function (event) {
        if (!board.classList.contains('is-3d')) return;
        const rect = board.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        tiltY = x * 10;
        tiltX = y * -8;
        applyTransform();
    });

    board.addEventListener('mouseleave', function () {
        if (!board.classList.contains('is-3d')) return;
        tiltX = -8;
        tiltY = 0;
        applyTransform();
    });

    document.querySelectorAll('[data-filter-state]').forEach(function (button) {
        button.addEventListener('click', function () {
            const isActive = button.classList.contains('is-active');
            clearLegendFilter();
            if (!isActive) {
                button.classList.add('is-active');
                filterTeeth(button.dataset.filterState);
            }
        });
    });

    function selectTooth(tooth, openEditor) {
        selectedTooth?.classList.remove('is-selected');
        selectedTooth = tooth;
        selectedTooth.classList.add('is-selected');
        updateSidePanel(tooth);
        if (editSelectedBtn) editSelectedBtn.disabled = false;
        if (openEditor) openToothEditor(tooth);
    }

    function openToothEditor(element) {
        const pieza = element.dataset.pieza;
        const estado = element.dataset.estado || 'sano';
        const notas = element.dataset.notas || '';
        const caras = safeJsonArray(element.dataset.caras);

        piezaInput.value = pieza;
        piezaLabel.value = pieza;
        estadoSelect.value = estado;
        notasTextarea.value = notas;

        checkboxes.forEach(function (checkbox) {
            checkbox.checked = caras.includes(checkbox.value);
        });

        bsModal.show();
    }

    function updateSidePanel(tooth) {
        const pieza = tooth.dataset.pieza || '--';
        const estado = tooth.dataset.estado || 'sano';
        const notas = tooth.dataset.notas || 'Sin observaciones registradas para esta pieza.';
        const caras = safeJsonArray(tooth.dataset.caras);
        const updated = tooth.dataset.updated ? formatDate(tooth.dataset.updated) : 'Sin registro';

        setText('selectedToothNumber', pieza);
        setText('selectedToothState', formatEstado(estado));
        setText('selectedToothName', getToothName(pieza));
        setText('selectedToothFaces', caras.length ? caras.map(formatEstado).join(', ') : 'Sin registro');
        setText('selectedToothUpdated', updated);
        setText('selectedToothTreatments', estado === 'sano' ? 'Sin procedimientos asociados' : formatEstado(estado));
        setText('selectedToothNotes', notas);
    }

    function updateToothElement(tooth, result) {
        const oldEstado = tooth.dataset.estado || 'sano';
        const newEstado = result.estado || 'sano';
        const color = result.color_estado || stateColors[newEstado] || stateColors.sano;

        tooth.dataset.estado = newEstado;
        tooth.dataset.color = color;
        tooth.dataset.notas = result.notas || '';
        tooth.dataset.caras = JSON.stringify(result.caras_afectadas || []);
        tooth.dataset.updated = result.updated_at || new Date().toISOString();
        tooth.classList.remove(`tooth-state-${oldEstado}`);
        tooth.classList.add(`tooth-state-${newEstado}`);
        tooth.setAttribute('aria-label', `Pieza ${tooth.dataset.pieza}, ${formatEstado(newEstado)}`);

        const marker = tooth.querySelector('.tooth-state-marker');
        const halo = tooth.querySelector('.tooth-state-halo');
        if (marker) marker.setAttribute('fill', color);
        if (halo) halo.style.setProperty('--state-color', color);

        const title = tooth.querySelector('title');
        if (title) title.textContent = `${tooth.dataset.pieza} - ${formatEstado(newEstado)}`;
    }

    async function saveTooth(payload) {
        try {
            const response = await fetch(BASE_URL + '/api/odontograma_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
                },
                body: JSON.stringify({
                    ...payload,
                    csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
                })
            });

            const text = await response.text();
            const data = text ? JSON.parse(text) : null;
            if (!response.ok) {
                console.error('Odontograma save failed:', response.status, data);
                return data || { success: false, error: `Error HTTP ${response.status}` };
            }
            return data;
        } catch (error) {
            console.error('Odontograma AJAX error:', error);
            return { success: false, error: 'No se pudo conectar con el servidor. Revisa la consola o los logs PHP.' };
        }
    }

    function setSavingState(isSaving) {
        if (!submitBtn) return;

        const spinner = submitBtn.querySelector('.spinner-border');
        const icon = submitBtn.querySelector('.bi');
        const label = submitBtn.querySelector('[data-submit-label]');

        submitBtn.disabled = isSaving;
        submitBtn.classList.toggle('is-loading', isSaving);
        if (spinner) spinner.classList.toggle('d-none', !isSaving);
        if (icon) icon.classList.toggle('d-none', isSaving);
        if (label) label.textContent = isSaving ? 'Guardando...' : 'Guardar cambio';
    }

    function refreshStats() {
        const counts = {};
        teeth.forEach(function (tooth) {
            const estado = tooth.dataset.estado || 'sano';
            counts[estado] = (counts[estado] || 0) + 1;
        });

        document.querySelectorAll('[data-stat]').forEach(function (card) {
            const key = card.dataset.stat;
            const count = counts[key] || 0;
            const percent = Math.round((count / Math.max(1, teeth.length)) * 100);
            const countEl = card.querySelector('[data-stat-count]');
            if (countEl) countEl.textContent = count;
            card.style.setProperty('--stat-percent', `${percent}%`);
        });

        const health = Math.round(((counts.sano || 0) / Math.max(1, teeth.length)) * 100);
        const healthScore = document.getElementById('healthScore');
        const healthRing = document.querySelector('.odo-health-ring');
        if (healthScore) healthScore.textContent = `${health}%`;
        if (healthRing) healthRing.style.setProperty('--health', health);
    }

    function setViewMode(mode) {
        const is3d = mode === '3d';
        board.classList.toggle('is-3d', is3d);
        document.querySelectorAll('[data-view-mode]').forEach(function (button) {
            button.classList.toggle('active', button.dataset.viewMode === mode);
        });
        if (viewToggleBtn) {
            viewToggleBtn.setAttribute('aria-pressed', String(is3d));
            viewToggleBtn.innerHTML = is3d ? '<i class="bi bi-grid-3x3-gap"></i>Vista 2D' : '<i class="bi bi-cube"></i>Vista 3D';
        }
        tiltX = is3d ? -8 : 0;
        tiltY = 0;
        applyTransform();
    }

    function setZoom(value) {
        zoom = value;
        if (zoomRange) zoomRange.value = String(Math.round(zoom * 100));
        applyTransform();
    }

    function applyTransform() {
        svg.style.setProperty('--odo-zoom', String(zoom));
        svg.style.setProperty('--odo-tilt-x', `${tiltX.toFixed(2)}deg`);
        svg.style.setProperty('--odo-tilt-y', `${tiltY.toFixed(2)}deg`);
        svg.style.setProperty('--odo-rotate', `${rotateZ.toFixed(2)}deg`);
    }

    function filterTeeth(state) {
        teeth.forEach(function (tooth) {
            tooth.classList.toggle('is-dimmed', (tooth.dataset.estado || 'sano') !== state);
        });
    }

    function clearLegendFilter() {
        document.querySelectorAll('[data-filter-state]').forEach(button => button.classList.remove('is-active'));
        teeth.forEach(tooth => tooth.classList.remove('is-dimmed'));
    }

    function showTooltip(tooth, event) {
        if (!tooltip) return;
        tooltip.innerHTML = `<strong>Pieza ${escapeHtml(tooth.dataset.pieza || '--')}</strong><span>${escapeHtml(formatEstado(tooth.dataset.estado || 'sano'))}</span>`;
        tooltip.classList.add('is-visible');
        tooltip.setAttribute('aria-hidden', 'false');
        moveTooltip(event);
    }

    function moveTooltip(event) {
        if (!tooltip) return;
        tooltip.style.left = `${event.clientX + 14}px`;
        tooltip.style.top = `${event.clientY + 14}px`;
    }

    function hideTooltip() {
        if (!tooltip) return;
        tooltip.classList.remove('is-visible');
        tooltip.setAttribute('aria-hidden', 'true');
    }

    function safeJsonArray(value) {
        try {
            const parsed = JSON.parse(value || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function formatEstado(estado) {
        return String(estado || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    function formatDate(value) {
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'Sin registro';
        return date.toLocaleDateString('es-CO', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    function getToothName(piece) {
        const quadrant = String(piece).charAt(0);
        const side = quadrant === '1' || quadrant === '4' ? 'derecha' : 'izquierda';
        const arch = quadrant === '1' || quadrant === '2' ? 'superior' : 'inferior';
        return `Arcada ${arch} ${side}`;
    }

    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }
});
