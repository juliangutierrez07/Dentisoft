document.addEventListener('DOMContentLoaded', function () {
    const page = document.querySelector('[data-adjuntos-page]');
    if (!page) return;

    const historiaId = page.dataset.historiaId;
    const form = document.querySelector('[data-adj-upload-form]');
    const dropzone = document.querySelector('[data-adj-dropzone]');
    const fileInput = document.querySelector('[data-adj-file]');
    const preview = document.querySelector('[data-adj-preview]');
    const list = document.querySelector('[data-adj-list]');
    const skeleton = document.querySelector('[data-adj-skeleton]');
    const progress = document.querySelector('[data-adj-progress]');
    const progressBar = document.querySelector('[data-adj-progress-bar]');
    const progressLabel = document.querySelector('[data-adj-progress-label]');
    const progressValue = document.querySelector('[data-adj-progress-value]');
    const confirmBackdrop = document.querySelector('[data-adj-confirm]');
    const cancelDeleteBtn = document.querySelector('[data-adj-cancel]');
    const confirmDeleteBtn = document.querySelector('[data-adj-confirm-delete]');
    const submitBtn = form?.querySelector('.adj-submit');
    const submitLabel = submitBtn?.querySelector('[data-submit-label]');

    let pendingDeleteId = null;
    let currentItems = [];

    if (!form || !dropzone || !fileInput || !list) return;

    bindDropzone();
    bindUpload();
    bindDeleteActions();
    bindConfirm();
    loadAttachments();

    function bindDropzone() {
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
            if (!event.dataTransfer?.files?.length) return;
            fileInput.files = event.dataTransfer.files;
            renderFilePreview(fileInput.files[0]);
        });

        fileInput.addEventListener('change', () => {
            renderFilePreview(fileInput.files[0]);
        });
    }

    function bindUpload() {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            event.stopPropagation();

            const file = fileInput.files?.[0];
            const error = validateFile(file);
            if (error) {
                showToast(error, 'danger');
                renderProgress('Error', 100, 'error');
                return;
            }

            uploadFile();
        });
    }

    function uploadFile() {
        const xhr = new XMLHttpRequest();
        const formData = new FormData(form);
        formData.set('csrf_token', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');

        setSavingState(true);
        renderProgress('Subiendo', 0);

        xhr.open('POST', `${BASE_URL}/modules/historia_clinica/adjuntos.php?historia_id=${encodeURIComponent(historiaId)}&action=upload`);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');

        xhr.upload.addEventListener('progress', event => {
            if (!event.lengthComputable) return;
            const percent = Math.max(5, Math.round((event.loaded / event.total) * 100));
            renderProgress('Subiendo', percent);
        });

        xhr.addEventListener('load', () => {
            setSavingState(false);
            let data = null;
            try {
                data = JSON.parse(xhr.responseText || '{}');
            } catch (error) {
                console.error('Adjuntos upload parse error:', xhr.responseText);
            }

            if (xhr.status < 200 || xhr.status >= 300 || !data?.success) {
                renderProgress('Error', 100, 'error');
                showToast(data?.error || 'No fue posible subir el archivo.', 'danger');
                return;
            }

            renderProgress('Completado', 100, 'complete');
            form.reset();
            clearPreview();
            currentItems.unshift(data.adjunto);
            renderList(currentItems);
            updateStats(currentItems);
            showToast(data.message || 'Adjunto cargado correctamente', 'success');

            setTimeout(() => {
                if (progress) progress.hidden = true;
            }, 1200);
        });

        xhr.addEventListener('error', () => {
            setSavingState(false);
            renderProgress('Error', 100, 'error');
            showToast('Error de conexion al subir el adjunto.', 'danger');
        });

        xhr.send(formData);
    }

    function bindDeleteActions() {
        list.addEventListener('click', event => {
            const button = event.target.closest('[data-adj-delete]');
            if (!button) return;
            const item = button.closest('[data-adj-id]');
            pendingDeleteId = item?.dataset.adjId || null;
            if (!pendingDeleteId) return;
            if (confirmBackdrop) confirmBackdrop.hidden = false;
        });
    }

    function bindConfirm() {
        cancelDeleteBtn?.addEventListener('click', closeConfirm);
        confirmBackdrop?.addEventListener('click', event => {
            if (event.target === confirmBackdrop) closeConfirm();
        });
        confirmDeleteBtn?.addEventListener('click', deletePendingAttachment);
    }

    async function deletePendingAttachment() {
        if (!pendingDeleteId) return;
        confirmDeleteBtn.disabled = true;

        try {
            const response = await fetch(`${BASE_URL}/modules/historia_clinica/adjuntos.php?historia_id=${encodeURIComponent(historiaId)}&action=delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
                },
                body: JSON.stringify({
                    id: Number(pendingDeleteId),
                    csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : ''
                })
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'No fue posible eliminar el adjunto.');
            }

            currentItems = currentItems.filter(item => String(item.id) !== String(data.id));
            renderList(currentItems);
            updateStats(currentItems);
            showToast(data.message || 'Adjunto eliminado correctamente', 'success');
            closeConfirm();
        } catch (error) {
            console.error('Adjuntos delete error:', error);
            showToast(error.message || 'Error al eliminar el adjunto.', 'danger');
        } finally {
            confirmDeleteBtn.disabled = false;
        }
    }

    async function loadAttachments() {
        showSkeleton(true);
        try {
            const response = await fetch(`${BASE_URL}/modules/historia_clinica/adjuntos.php?historia_id=${encodeURIComponent(historiaId)}&action=list`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'No fue posible cargar los adjuntos.');
            }
            currentItems = data.adjuntos || [];
            renderList(currentItems);
            updateStats(currentItems);
        } catch (error) {
            console.error('Adjuntos list error:', error);
            showToast(error.message || 'Error al cargar adjuntos.', 'danger');
        } finally {
            showSkeleton(false);
        }
    }

    function renderFilePreview(file) {
        if (!preview) return;
        if (!file) {
            clearPreview();
            return;
        }

        const error = validateFile(file);
        if (error) {
            showToast(error, 'danger');
            fileInput.value = '';
            clearPreview();
            return;
        }

        const meta = `${file.type || fileKindFromName(file.name)} - ${formatBytes(file.size)}`;
        const info = `
            <div>
                <strong>${escapeHtml(file.name)}</strong>
                <small>${escapeHtml(meta)}</small>
            </div>
        `;

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = event => {
                preview.innerHTML = `<img src="${event.target.result}" alt="">${info}`;
                preview.hidden = false;
            };
            reader.readAsDataURL(file);
            return;
        }

        preview.innerHTML = `<span><i class="bi ${iconForFile(file.name, file.type)}"></i></span>${info}`;
        preview.hidden = false;
    }

    function clearPreview() {
        if (!preview) return;
        preview.innerHTML = '';
        preview.hidden = true;
    }

    function renderProgress(label, percent, state = '') {
        if (!progress || !progressBar || !progressLabel || !progressValue) return;
        progress.hidden = false;
        progress.classList.toggle('is-error', state === 'error');
        progress.classList.toggle('is-complete', state === 'complete');
        progressBar.style.setProperty('--progress', `${percent}%`);
        progressLabel.textContent = label;
        progressValue.textContent = `${percent}%`;
    }

    function renderList(items) {
        if (!items.length) {
            list.innerHTML = `
                <div class="adj-empty" data-adj-empty>
                    <span><i class="bi bi-folder-symlink"></i></span>
                    <h3>Sin adjuntos todavia</h3>
                    <p>Cuando cargues radiografias, fotos o documentos apareceran aqui con vista previa y acciones rapidas.</p>
                </div>
            `;
            return;
        }

        list.innerHTML = items.map(renderItem).join('');
    }

    function renderItem(item) {
        const thumb = item.is_image
            ? `<img src="${escapeAttribute(item.url)}" alt="${escapeAttribute(item.nombre_archivo)}">`
            : `<i class="bi ${escapeAttribute(item.icon)}"></i>`;

        const description = item.descripcion ? `<p>${escapeHtml(item.descripcion)}</p>` : '';
        return `
            <article class="adj-item" data-adj-id="${escapeAttribute(item.id)}" data-adj-kind="${escapeAttribute(item.kind)}" data-adj-size="${escapeAttribute(item.tamanio_bytes)}">
                <div class="adj-thumb">${thumb}</div>
                <div class="adj-info">
                    <div class="adj-title-row">
                        <h3>${escapeHtml(item.nombre_archivo)}</h3>
                        <span class="adj-badge"><i class="bi ${escapeAttribute(item.icon)}"></i>${escapeHtml(item.label)}</span>
                    </div>
                    ${description}
                    <div class="adj-meta">
                        <span><i class="bi bi-tooth"></i>${escapeHtml(item.pieza_dental || 'Sin pieza')}</span>
                        <span><i class="bi bi-calendar3"></i>${escapeHtml(item.fecha || '')}</span>
                        <span><i class="bi bi-hdd"></i>${escapeHtml(item.tamanio_legible || formatBytes(item.tamanio_bytes || 0))}</span>
                        <span><i class="bi bi-person"></i>${escapeHtml(item.usuario || 'Sistema')}</span>
                    </div>
                </div>
                <div class="adj-item-actions">
                    <a href="${escapeAttribute(item.url)}" target="_blank" rel="noopener" class="adj-icon-btn" title="Ver"><i class="bi bi-eye"></i></a>
                    <a href="${escapeAttribute(item.url)}" download class="adj-icon-btn" title="Descargar"><i class="bi bi-download"></i></a>
                    <button type="button" class="adj-icon-btn adj-danger" data-adj-delete title="Eliminar"><i class="bi bi-trash3"></i></button>
                </div>
            </article>
        `;
    }

    function updateStats(items) {
        const total = items.length;
        const rx = items.filter(item => item.kind === 'radiografia').length;
        const images = items.filter(item => ['foto', 'imagen'].includes(item.kind)).length;
        const bytes = items.reduce((sum, item) => sum + Number(item.tamanio_bytes || 0), 0);

        setText('[data-adj-stat-total]', total);
        setText('[data-adj-stat-rx]', rx);
        setText('[data-adj-stat-images]', images);
        setText('[data-adj-stat-size]', formatBytes(bytes));
    }

    function validateFile(file) {
        if (!file) return 'Selecciona un archivo para subir.';
        const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx'];
        const extension = file.name.split('.').pop().toLowerCase();
        if (!allowedExtensions.includes(extension)) {
            return 'Solo se permiten JPG, PNG, WEBP, PDF, DOC o DOCX.';
        }
        if (file.size > 5 * 1024 * 1024) {
            return 'El archivo supera el limite de 5 MB.';
        }
        return '';
    }

    function setSavingState(isSaving) {
        if (!submitBtn) return;
        const spinner = submitBtn.querySelector('.spinner-border');
        const icon = submitBtn.querySelector('.bi');
        submitBtn.disabled = isSaving;
        submitBtn.classList.toggle('is-loading', isSaving);
        spinner?.classList.toggle('d-none', !isSaving);
        icon?.classList.toggle('d-none', isSaving);
        if (submitLabel) submitLabel.textContent = isSaving ? 'Subiendo...' : 'Subir archivo';
    }

    function showSkeleton(show) {
        if (!skeleton) return;
        skeleton.hidden = !show;
        list.hidden = show;
    }

    function closeConfirm() {
        pendingDeleteId = null;
        if (confirmBackdrop) confirmBackdrop.hidden = true;
    }

    function showToast(message, type = 'success') {
        if (typeof mostrarAlerta === 'function') {
            mostrarAlerta(message, type, 4200);
        }
    }

    function fileKindFromName(name) {
        const extension = String(name).split('.').pop().toLowerCase();
        if (extension === 'pdf') return 'PDF';
        if (['doc', 'docx'].includes(extension)) return 'Documento';
        return 'Archivo';
    }

    function iconForFile(name, mime) {
        const extension = String(name).split('.').pop().toLowerCase();
        if (mime === 'application/pdf' || extension === 'pdf') return 'bi-file-earmark-pdf';
        if (['doc', 'docx'].includes(extension)) return 'bi-file-earmark-text';
        return 'bi-file-earmark-medical';
    }

    function formatBytes(bytes) {
        const value = Number(bytes || 0);
        if (value >= 1048576) return `${(value / 1048576).toFixed(2)} MB`;
        if (value >= 1024) return `${(value / 1024).toFixed(2)} KB`;
        return `${value} bytes`;
    }

    function setText(selector, value) {
        const element = document.querySelector(selector);
        if (element) element.textContent = value;
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
