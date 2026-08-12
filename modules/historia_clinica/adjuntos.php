<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('historias.adjuntar');

$paginaTitulo = 'Adjuntos Historia Clinica';
$historiaId = filter_input(INPUT_GET, 'historia_id', FILTER_VALIDATE_INT);
if (!$historiaId) {
    setAlerta('Historia clinica no valida.', 'danger');
    header('Location: index.php');
    exit;
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fileSizeReadable(int $bytes): string {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function attachmentPublicUrl(string $path): string {
    return BASE_URL . str_replace('\\', '/', $path);
}

function attachmentKind(array $row): string {
    $extension = strtolower(pathinfo($row['nombre_archivo'] ?? '', PATHINFO_EXTENSION));
    if (($row['tipo'] ?? '') === 'radiografia') return 'radiografia';
    if (($row['tipo'] ?? '') === 'foto_clinica') return 'foto';
    if ($extension === 'pdf') return 'pdf';
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) return 'imagen';
    if (in_array($extension, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'], true)) return 'documento';
    return 'documento';
}

function attachmentIcon(string $kind): string {
    return [
        'radiografia' => 'bi-radioactive',
        'foto' => 'bi-camera',
        'pdf' => 'bi-file-earmark-pdf',
        'imagen' => 'bi-file-earmark-image',
        'documento' => 'bi-file-earmark-text',
    ][$kind] ?? 'bi-file-earmark-medical';
}

function attachmentLabel(string $kind, string $tipo): string {
    return [
        'radiografia' => 'Radiografia',
        'foto' => 'Foto clinica',
        'pdf' => 'PDF',
        'imagen' => 'Imagen',
        'documento' => 'Documento',
    ][$kind] ?? ucfirst(str_replace('_', ' ', $tipo ?: 'Adjunto'));
}

function normalizeAttachment(array $row): array {
    $kind = attachmentKind($row);
    $extension = strtolower(pathinfo($row['nombre_archivo'] ?? '', PATHINFO_EXTENSION));
    $url = attachmentPublicUrl($row['ruta_archivo']);
    return [
        'id' => (int) $row['id'],
        'pieza_dental' => $row['pieza_dental'] ?: '',
        'tipo' => $row['tipo'] ?: 'otro',
        'kind' => $kind,
        'label' => attachmentLabel($kind, $row['tipo'] ?? 'otro'),
        'icon' => attachmentIcon($kind),
        'nombre_archivo' => $row['nombre_archivo'],
        'ruta_archivo' => $row['ruta_archivo'],
        'url' => $url,
        'tamanio_bytes' => (int) ($row['tamanio_bytes'] ?? 0),
        'tamanio_legible' => fileSizeReadable((int) ($row['tamanio_bytes'] ?? 0)),
        'descripcion' => $row['descripcion'] ?: '',
        'created_at' => $row['created_at'],
        'fecha' => $row['created_at'] ? date('d/m/Y', strtotime($row['created_at'])) : '',
        'usuario' => trim(($row['usuario_nombre'] ?? '') . ' ' . ($row['usuario_apellido'] ?? '')) ?: 'Sistema',
        'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
    ];
}

function fetchHistoria(PDO $db, int $historiaId): ?array {
    $stmt = $db->prepare("SELECT hc.id, hc.numero_historia, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido
        FROM historias_clinicas hc
        JOIN pacientes p ON hc.paciente_id = p.id
        WHERE hc.id = :id");
    $stmt->execute([':id' => $historiaId]);
    $historia = $stmt->fetch();
    return $historia ?: null;
}

function fetchAdjuntos(PDO $db, int $historiaId): array {
    $stmt = $db->prepare("SELECT ic.id, ic.pieza_dental, ic.tipo, ic.nombre_archivo, ic.ruta_archivo,
            ic.tamanio_bytes, ic.descripcion, ic.created_at, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
        FROM imagenes_clinicas ic
        LEFT JOIN usuarios u ON ic.usuario_id = u.id
        WHERE ic.historia_id = :historia_id
        ORDER BY ic.created_at DESC, ic.id DESC");
    $stmt->execute([':historia_id' => $historiaId]);
    return array_map('normalizeAttachment', $stmt->fetchAll());
}

function jsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function allowedAttachmentTypes(): array {
    return [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    ];
}

function validateUpload(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['Selecciona un archivo valido para subir.'];
    }

    $errors = [];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $mime = mime_content_type($file['tmp_name']) ?: '';
    $allowed = allowedAttachmentTypes();
    $allowedExtensions = array_merge(...array_values($allowed));

    if (!isset($allowed[$mime]) && !in_array($extension, $allowedExtensions, true)) {
        $errors[] = 'Solo se permiten JPG, PNG, WEBP, PDF, DOC o DOCX.';
    }
    if (($file['size'] ?? 0) > MAX_FILE_SIZE) {
        $errors[] = 'El archivo supera el limite de 5 MB.';
    }

    return $errors;
}

function uploadAttachment(PDO $db, int $historiaId): array {
    $tipo = $_POST['tipo'] ?? 'foto_clinica';
    $tipo = in_array($tipo, ['radiografia', 'foto_clinica', 'otro'], true) ? $tipo : 'otro';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $piezaDental = trim($_POST['pieza_dental'] ?? '');
    $file = $_FILES['archivo'] ?? [];
    $errors = validateUpload($file);

    if ($errors) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
    $subdir = $tipo === 'radiografia'
        ? RADIOGRAFIAS_DIR
        : ($isImage ? FOTOS_DIR : UPLOADS_DIR . '/documentos');

    if (!is_dir($subdir) && !mkdir($subdir, 0755, true)) {
        throw new RuntimeException('No fue posible preparar la carpeta de adjuntos.');
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($file['name']));
    $storedName = uniqid('adj_', true) . '_' . $safeName;
    $destination = $subdir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('No fue posible guardar el archivo en el servidor.');
    }

    $relativePath = str_replace(ROOT_PATH, '', $destination);
    $stmt = $db->prepare("INSERT INTO imagenes_clinicas
        (historia_id, pieza_dental, tipo, nombre_archivo, ruta_archivo, tamanio_bytes, descripcion, usuario_id)
        VALUES (:historia_id, :pieza_dental, :tipo, :nombre_archivo, :ruta_archivo, :tamanio_bytes, :descripcion, :usuario_id)");
    $stmt->execute([
        ':historia_id' => $historiaId,
        ':pieza_dental' => $piezaDental ?: null,
        ':tipo' => $tipo,
        ':nombre_archivo' => $file['name'],
        ':ruta_archivo' => $relativePath,
        ':tamanio_bytes' => (int) $file['size'],
        ':descripcion' => $descripcion,
        ':usuario_id' => $_SESSION['usuario_id'] ?? null,
    ]);

    $id = (int) $db->lastInsertId();
    $stmt = $db->prepare("SELECT ic.id, ic.pieza_dental, ic.tipo, ic.nombre_archivo, ic.ruta_archivo,
            ic.tamanio_bytes, ic.descripcion, ic.created_at, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
        FROM imagenes_clinicas ic
        LEFT JOIN usuarios u ON ic.usuario_id = u.id
        WHERE ic.id = :id");
    $stmt->execute([':id' => $id]);
    return normalizeAttachment($stmt->fetch());
}

try {
    $db = getDB();
    $historia = fetchHistoria($db, $historiaId);
    if (!$historia) {
        setAlerta('Historia clinica no encontrada.', 'danger');
        header('Location: index.php');
        exit;
    }

    $action = $_GET['action'] ?? '';
    if ($action === 'list') {
        jsonResponse(['success' => true, 'adjuntos' => fetchAdjuntos($db, $historiaId)]);
    }

    if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        validarCSRF();
        jsonResponse([
            'success' => true,
            'message' => 'Adjunto cargado correctamente',
            'adjunto' => uploadAttachment($db, $historiaId),
        ]);
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        validarCSRF();
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            throw new RuntimeException('Adjunto no valido.');
        }

        $stmt = $db->prepare("SELECT id, ruta_archivo FROM imagenes_clinicas WHERE id = :id AND historia_id = :historia_id");
        $stmt->execute([':id' => $id, ':historia_id' => $historiaId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('El adjunto no existe o no pertenece a esta historia.');
        }

        $db->beginTransaction();
        $delete = $db->prepare("DELETE FROM imagenes_clinicas WHERE id = :id AND historia_id = :historia_id");
        $delete->execute([':id' => $id, ':historia_id' => $historiaId]);
        $db->commit();

        $absolutePath = ROOT_PATH . $row['ruta_archivo'];
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        jsonResponse(['success' => true, 'message' => 'Adjunto eliminado correctamente', 'id' => $id]);
    }

    $errores = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validarCSRF();
        try {
            uploadAttachment($db, $historiaId);
            setAlerta('Adjunto cargado correctamente.');
            header('Location: adjuntos.php?historia_id=' . $historiaId);
            exit;
        } catch (RuntimeException $e) {
            $errores[] = $e->getMessage();
        }
    }

    $adjuntos = fetchAdjuntos($db, $historiaId);
} catch (RuntimeException $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if (($_GET['action'] ?? '') !== '') {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 422);
    }
    $errores[] = $e->getMessage();
    $adjuntos = $adjuntos ?? [];
} catch (PDOException $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Historia Clinica Adjuntos error: ' . $e->getMessage());
    if (($_GET['action'] ?? '') !== '') {
        jsonResponse(['success' => false, 'error' => 'Error interno al procesar adjuntos.'], 500);
    }
    setAlerta('Error interno al cargar adjuntos.', 'danger');
    header('Location: index.php');
    exit;
}

$pacienteNombre = trim($historia['paciente_nombre'] . ' ' . $historia['paciente_apellido']);
$totalBytes = array_sum(array_column($adjuntos, 'tamanio_bytes'));
$totalRadiografias = count(array_filter($adjuntos, fn($item) => $item['kind'] === 'radiografia'));
$totalImagenes = count(array_filter($adjuntos, fn($item) => in_array($item['kind'], ['foto', 'imagen'], true)));
$cssAdicional = 'adjuntos.css';
$jsAdicional = 'adjuntos.js';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 adj-page" data-adjuntos-page data-historia-id="<?= (int) $historiaId ?>">
    <section class="adj-hero">
        <div>
            <span class="adj-kicker">Repositorio clinico</span>
            <h1>Adjuntos de Historia Clinica</h1>
            <p><?= h($pacienteNombre) ?> &middot; HC <?= h($historia['numero_historia']) ?> &middot; Evidencia diagnostica, imagenes y documentos asociados.</p>
        </div>
        <div class="adj-actions">
            <a href="ver.php?id=<?= (int) $historiaId ?>" class="adj-btn adj-btn-ghost"><i class="bi bi-arrow-left"></i>Volver</a>
            <a href="odontograma.php?historia_id=<?= (int) $historiaId ?>" class="adj-btn adj-btn-primary"><i class="bi bi-grid-3x3-gap"></i>Odontograma</a>
        </div>
    </section>

    <?php if (!empty($errores)): ?>
        <div class="adj-alert">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <?php foreach ($errores as $error): ?>
                    <p><?= h($error) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <section class="adj-stats">
        <article><span><i class="bi bi-files"></i></span><div><strong class="mono" data-adj-stat-total><?= count($adjuntos) ?></strong><small>Adjuntos</small></div></article>
        <article><span><i class="bi bi-radioactive"></i></span><div><strong class="mono" data-adj-stat-rx><?= $totalRadiografias ?></strong><small>Radiografias</small></div></article>
        <article><span><i class="bi bi-images"></i></span><div><strong class="mono" data-adj-stat-images><?= $totalImagenes ?></strong><small>Imagenes</small></div></article>
        <article><span><i class="bi bi-hdd"></i></span><div><strong class="mono" data-adj-stat-size><?= h(fileSizeReadable((int) $totalBytes)) ?></strong><small>Almacenado</small></div></article>
    </section>

    <div class="adj-layout">
        <section class="adj-panel adj-uploader-panel">
            <div class="adj-panel-head">
                <span><i class="bi bi-cloud-arrow-up"></i></span>
                <div>
                    <h2>Cargar adjunto</h2>
                    <p>Radiografias, fotografias clinicas, PDF y documentos.</p>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" novalidate class="adj-form needs-validation" data-adj-upload-form>
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">

                <label class="adj-dropzone" data-adj-dropzone>
                    <input type="file" name="archivo" accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx" required data-adj-file>
                    <span class="adj-drop-icon"><i class="bi bi-cloud-arrow-up"></i></span>
                    <strong>Arrastra tu archivo o haz clic para seleccionar</strong>
                    <small>JPG, PNG, WEBP, PDF, DOC o DOCX &middot; max. 5 MB</small>
                </label>

                <div class="adj-file-preview" data-adj-preview hidden></div>

                <div class="adj-progress" data-adj-progress hidden>
                    <div><span data-adj-progress-label>Subiendo</span><strong data-adj-progress-value>0%</strong></div>
                    <i data-adj-progress-bar></i>
                </div>

                <div class="adj-fields">
                    <label class="adj-floating">
                        <select name="tipo" required>
                            <option value="radiografia">Radiografia</option>
                            <option value="foto_clinica" selected>Foto clinica</option>
                            <option value="otro">Documento / otro</option>
                        </select>
                        <span>Tipo de adjunto</span>
                    </label>
                    <label class="adj-floating">
                        <input type="text" name="pieza_dental" placeholder=" ">
                        <span>Pieza dental relacionada</span>
                    </label>
                    <label class="adj-floating adj-floating-wide">
                        <textarea name="descripcion" rows="4" placeholder=" "></textarea>
                        <span>Descripcion clinica</span>
                    </label>
                </div>

                <button type="submit" class="adj-btn adj-btn-primary adj-submit">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    <i class="bi bi-cloud-check"></i>
                    <span data-submit-label>Subir archivo</span>
                </button>
            </form>
        </section>

        <section class="adj-panel adj-list-panel">
            <div class="adj-panel-head adj-list-head">
                <span><i class="bi bi-folder2-open"></i></span>
                <div>
                    <h2>Archivos cargados</h2>
                    <p>Vista rapida de evidencia clinica asociada.</p>
                </div>
            </div>

            <div class="adj-skeleton" data-adj-skeleton hidden>
                <i></i><i></i><i></i>
            </div>

            <div class="adj-list" data-adj-list>
                <?php if (empty($adjuntos)): ?>
                    <div class="adj-empty" data-adj-empty>
                        <span><i class="bi bi-folder-symlink"></i></span>
                        <h3>Sin adjuntos todavia</h3>
                        <p>Cuando cargues radiografias, fotos o documentos apareceran aqui con vista previa y acciones rapidas.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($adjuntos as $adjunto): ?>
                        <article class="adj-item" data-adj-id="<?= (int) $adjunto['id'] ?>" data-adj-kind="<?= h($adjunto['kind']) ?>" data-adj-size="<?= (int) $adjunto['tamanio_bytes'] ?>">
                            <div class="adj-thumb">
                                <?php if ($adjunto['is_image']): ?>
                                    <img src="<?= h($adjunto['url']) ?>" alt="<?= h($adjunto['nombre_archivo']) ?>">
                                <?php else: ?>
                                    <i class="bi <?= h($adjunto['icon']) ?>"></i>
                                <?php endif; ?>
                            </div>
                            <div class="adj-info">
                                <div class="adj-title-row">
                                    <h3><?= h($adjunto['nombre_archivo']) ?></h3>
                                    <span class="adj-badge"><i class="bi <?= h($adjunto['icon']) ?>"></i><?= h($adjunto['label']) ?></span>
                                </div>
                                <?php if ($adjunto['descripcion']): ?>
                                    <p><?= h($adjunto['descripcion']) ?></p>
                                <?php endif; ?>
                                <div class="adj-meta">
                                    <span><i class="bi bi-tooth"></i><?= h($adjunto['pieza_dental'] ?: 'Sin pieza') ?></span>
                                    <span class="mono"><i class="bi bi-calendar3"></i><?= h($adjunto['fecha']) ?></span>
                                    <span class="mono"><i class="bi bi-hdd"></i><?= h($adjunto['tamanio_legible']) ?></span>
                                    <span><i class="bi bi-person"></i><?= h($adjunto['usuario']) ?></span>
                                </div>
                            </div>
                            <div class="adj-item-actions">
                                <a href="<?= h($adjunto['url']) ?>" target="_blank" rel="noopener" class="adj-icon-btn" title="Ver"><i class="bi bi-eye"></i></a>
                                <a href="<?= h($adjunto['url']) ?>" download class="adj-icon-btn" title="Descargar"><i class="bi bi-download"></i></a>
                                <button type="button" class="adj-icon-btn adj-danger" data-adj-delete title="Eliminar"><i class="bi bi-trash3"></i></button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<div class="adj-confirm-backdrop" data-adj-confirm hidden>
    <div class="adj-confirm">
        <span><i class="bi bi-shield-exclamation"></i></span>
        <h2>Eliminar adjunto</h2>
        <p>Esta accion quitara el archivo de la historia clinica y no se puede deshacer.</p>
        <div>
            <button type="button" class="adj-btn adj-btn-ghost" data-adj-cancel>Cancelar</button>
            <button type="button" class="adj-btn adj-btn-danger" data-adj-confirm-delete>Eliminar</button>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
