<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('tratamientos.ver');

$paginaTitulo = 'Sesiones de Tratamiento';
$cssAdicional = 'tratamientos-premium.css';
$jsAdicional = 'tratamientos.js';
$planId = filter_input(INPUT_GET, 'plan_id', FILTER_VALIDATE_INT);
if (!$planId) {
    setAlerta('Plan de tratamiento no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$errores = [];
try {
    $db = getDB();
    $planStmt = $db->prepare("SELECT pt.*, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido FROM planes_tratamiento pt JOIN pacientes p ON pt.paciente_id = p.id WHERE pt.id = :id");
    $planStmt->execute([':id' => $planId]);
    $plan = $planStmt->fetch();
    if (!$plan) {
        setAlerta('Plan no encontrado.', 'danger');
        header('Location: index.php');
        exit;
    }
    $sesionesStmt = $db->prepare("SELECT s.*, pc.nombre AS procedimiento_nombre, u.nombre AS odontologo_nombre, u.apellido AS odontologo_apellido FROM sesiones_tratamiento s LEFT JOIN procedimientos_catalogo pc ON s.procedimiento_id = pc.id LEFT JOIN usuarios u ON s.odontologo_id = u.id WHERE s.plan_id = :plan_id ORDER BY s.numero_sesion ASC");
    $sesionesStmt->execute([':plan_id' => $planId]);
    $sesiones = $sesionesStmt->fetchAll();
    $procedimientos = $db->query("SELECT id, nombre, precio_base FROM procedimientos_catalogo WHERE activo = TRUE ORDER BY nombre")->fetchAll();
    $odontologosStmt = $db->prepare("SELECT u.id, u.nombre, u.apellido FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'odontologo' AND u.estado = 'activo' ORDER BY u.nombre, u.apellido");
    $odontologosStmt->execute();
    $odontologos = $odontologosStmt->fetchAll();

    $historia = null;
    if (!empty($plan['historia_id'])) {
        $historiaStmt = $db->prepare("SELECT numero_historia, diagnostico, observaciones, estado FROM historias_clinicas WHERE id = :id");
        $historiaStmt->execute([':id' => $plan['historia_id']]);
        $historia = $historiaStmt->fetch();
    }
} catch (PDOException $e) {
    error_log('Tratamientos Sesiones carga error: ' . $e->getMessage());
    setAlerta('Error interno al cargar las sesiones.', 'danger');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('tratamientos.crear');
    validarCSRF();
    $procedimientoId = filter_input(INPUT_POST, 'procedimiento_id', FILTER_VALIDATE_INT);
    $piezaDental = trim($_POST['pieza_dental'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $observaciones = trim($_POST['observaciones_sesion'] ?? '');
    $costoSesion = filter_input(INPUT_POST, 'costo_sesion', FILTER_VALIDATE_FLOAT);
    $fechaProgramada = trim($_POST['fecha_programada'] ?? '');
    $odontologoId = filter_input(INPUT_POST, 'odontologo_id', FILTER_VALIDATE_INT);
    $estado = trim($_POST['estado'] ?? 'pendiente');

    if ($fechaProgramada === '') {
        $errores[] = 'La fecha de la sesión es obligatoria.';
    }
    if (!in_array($estado, ['pendiente', 'realizada', 'cancelada'], true)) {
        $estado = 'pendiente';
    }
    if ($costoSesion === false || $costoSesion < 0) {
        $costoSesion = 0.00;
    }
    $numeroSesion = (int) ($sesiones ? max(array_column($sesiones, 'numero_sesion')) + 1 : 1);

    if (empty($errores)) {
        try {
            $stmt = $db->prepare("INSERT INTO sesiones_tratamiento (plan_id, numero_sesion, procedimiento_id, pieza_dental, descripcion, observaciones_sesion, costo_sesion, fecha_programada, estado, odontologo_id) VALUES (:plan_id, :numero_sesion, :procedimiento_id, :pieza_dental, :descripcion, :observaciones_sesion, :costo_sesion, :fecha_programada, :estado, :odontologo_id)");
            $stmt->execute([
                ':plan_id' => $planId,
                ':numero_sesion' => $numeroSesion,
                ':procedimiento_id' => $procedimientoId ?: null,
                ':pieza_dental' => $piezaDental ?: null,
                ':descripcion' => $descripcion,
                ':observaciones_sesion' => $observaciones,
                ':costo_sesion' => $costoSesion,
                ':fecha_programada' => $fechaProgramada,
                ':estado' => $estado,
                ':odontologo_id' => $odontologoId ?: null,
            ]);
            setAlerta('Sesión creada correctamente.');
            header('Location: sesiones.php?plan_id=' . $planId);
            exit;
        } catch (PDOException $e) {
            error_log('Tratamientos Sesiones insert error: ' . $e->getMessage());
            $errores[] = 'No fue posible guardar la sesión. Intenta nuevamente.';
        }
    }
}

function value(array $data, string $name): string {
    return htmlspecialchars(trim((string) ($data[$name] ?? '')), ENT_QUOTES, 'UTF-8');
}

function badgeClass(string $estado): string {
    return match ($estado) {
        'pendiente' => 'warning',
        'realizada' => 'success',
        'cancelada' => 'danger',
        default => 'secondary',
    };
}

$sesionesRealizadas = count(array_filter($sesiones, fn($s) => $s['estado'] === 'realizada'));
$sesionesPendientes = count(array_filter($sesiones, fn($s) => $s['estado'] === 'pendiente'));
$sesionesCanceladas = count(array_filter($sesiones, fn($s) => $s['estado'] === 'cancelada'));
$progresoTratamiento = count($sesiones) > 0 ? round(($sesionesRealizadas / count($sesiones)) * 100) : 0;
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 treatment-detail-page">
    <!-- Treatment Header -->
    <section class="treatment-detail-hero mb-4">
        <div class="treatment-hero-main">
            <span class="treatment-eyebrow"><i class="bi bi-clipboard2-pulse"></i> Detalle del tratamiento</span>
            <h1><?= htmlspecialchars($plan['nombre_plan']) ?></h1>
            <p>Paciente: <?= htmlspecialchars($plan['paciente_nombre'] . ' ' . $plan['paciente_apellido']) ?></p>
        </div>
        <div class="treatment-hero-actions">
            <span class="treatment-status-badge badge-<?= badgeClass($plan['estado']) ?>"><?= ucfirst(str_replace('_', ' ', $plan['estado'])) ?></span>
            <a href="index.php" class="btn treatment-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            <?php if (can('tratamientos.crear')): ?>
                <a href="crear_plan.php?id=<?= $planId ?>" class="btn treatment-primary"><i class="bi bi-pencil-square"></i> Editar plan</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Summary Cards -->
    <section class="treatment-summary-grid mb-4">
        <article class="summary-card summary-sessions">
            <div class="summary-icon"><i class="bi bi-layers"></i></div>
            <div>
                <span>Total sesiones</span>
                <strong><?= count($sesiones) ?></strong>
            </div>
        </article>
        <article class="summary-card summary-completed">
            <div class="summary-icon"><i class="bi bi-check2-circle"></i></div>
            <div>
                <span>Completadas</span>
                <strong><?= $sesionesRealizadas ?></strong>
            </div>
        </article>
        <article class="summary-card summary-pending">
            <div class="summary-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <span>Pendientes</span>
                <strong><?= $sesionesPendientes ?></strong>
            </div>
        </article>
        <article class="summary-card summary-cost">
            <div class="summary-icon"><i class="bi bi-cash-coin"></i></div>
            <div>
                <span>Costo total</span>
                <strong>$<?= number_format($plan['costo_total'], 0, ',', '.') ?></strong>
            </div>
        </article>
    </section>

    <!-- Progress Section -->
    <section class="treatment-progress-section mb-4">
        <div class="progress-header">
            <div>
                <span>Progreso del tratamiento</span>
                <small><?= $sesionesRealizadas ?> de <?= count($sesiones) ?> sesiones completadas</small>
            </div>
            <strong><?= $progresoTratamiento ?>%</strong>
        </div>
        <div class="progress-bar-container">
            <div class="progress-bar-fill" style="width: <?= $progresoTratamiento ?>%"></div>
        </div>
    </section>

    <section class="treatment-insight-grid mb-4">
        <article class="treatment-insight-card">
            <span><i class="bi bi-journal-medical"></i></span>
            <h2>Historial clinico</h2>
            <strong><?= htmlspecialchars($historia['numero_historia'] ?? 'Sin historia asociada') ?></strong>
            <p><?= htmlspecialchars($historia['diagnostico'] ?? 'Asocia una historia clinica para centralizar diagnostico, odontograma y notas relevantes.') ?></p>
        </article>
        <article class="treatment-insight-card">
            <span><i class="bi bi-chat-square-text"></i></span>
            <h2>Observaciones</h2>
            <strong>Plan clinico</strong>
            <p><?= htmlspecialchars($plan['descripcion'] ?: 'Sin observaciones registradas para este tratamiento.') ?></p>
        </article>
        <article class="treatment-insight-card">
            <span><i class="bi bi-images"></i></span>
            <h2>Archivos y radiografias</h2>
            <strong>Repositorio clinico</strong>
            <p>Centraliza fotografias, radiografias y soportes desde la historia clinica del paciente.</p>
        </article>
        <article class="treatment-insight-card">
            <span><i class="bi bi-activity"></i></span>
            <h2>Evolucion</h2>
            <strong><?= $sesionesCanceladas ?> canceladas</strong>
            <p><?= $sesionesPendientes ?> sesiones pendientes y <?= $sesionesRealizadas ?> completadas en la linea de tiempo del tratamiento.</p>
        </article>
    </section>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger shadow-sm mb-4">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (can('tratamientos.crear')): ?>
        <!-- Add Session Form (Collapsible) -->
        <section class="treatment-form-section mb-4">
            <button type="button" class="treatment-form-toggle" data-bs-toggle="collapse" data-bs-target="#addSessionForm">
                <i class="bi bi-plus-circle"></i>
                <span>Agregar nueva sesión</span>
                <i class="bi bi-chevron-down toggle-icon"></i>
            </button>
            <div class="collapse" id="addSessionForm">
                <div class="treatment-form-card">
                    <form method="POST" class="needs-validation" novalidate data-prevent-double>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="treatment-form-grid">
                        <div class="treatment-field">
                            <label class="treatment-label">Procedimiento</label>
                            <div class="treatment-input-wrapper">
                                <i class="bi bi-heart-pulse"></i>
                                <select class="treatment-select" name="procedimiento_id">
                                    <option value="">Selecciona un procedimiento</option>
                                    <?php foreach ($procedimientos as $procedimiento): ?>
                                        <option value="<?= $procedimiento['id'] ?>" <?= value($_POST, 'procedimiento_id') == $procedimiento['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($procedimiento['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="treatment-field">
                            <label class="treatment-label">Odontólogo</label>
                            <div class="treatment-input-wrapper">
                                <i class="bi bi-person-badge"></i>
                                <select class="treatment-select" name="odontologo_id">
                                    <option value="">Selecciona un odontólogo</option>
                                    <?php foreach ($odontologos as $odontologo): ?>
                                        <option value="<?= $odontologo['id'] ?>" <?= value($_POST, 'odontologo_id') == $odontologo['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($odontologo['nombre'] . ' ' . $odontologo['apellido']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="treatment-field">
                            <label class="treatment-label">Fecha programada *</label>
                            <div class="treatment-input-wrapper">
                                <i class="bi bi-calendar3"></i>
                                <input type="date" class="treatment-input" name="fecha_programada" value="<?= value($_POST, 'fecha_programada') ?>" required>
                            </div>
                        </div>
                        <div class="treatment-field">
                            <label class="treatment-label">Costo sesión (COP)</label>
                            <div class="treatment-input-wrapper">
                                <i class="bi bi-cash"></i>
                                <input type="number" step="1000" min="0" class="treatment-input" name="costo_sesion" value="<?= value($_POST, 'costo_sesion') ?>" placeholder="0">
                            </div>
                        </div>
                        <div class="treatment-field">
                            <label class="treatment-label">Pieza dental</label>
                            <div class="treatment-input-wrapper">
                                <i class="bi bi-grid-3x3"></i>
                                <input type="text" class="treatment-input" name="pieza_dental" value="<?= value($_POST, 'pieza_dental') ?>" placeholder="Ej. 16">
                            </div>
                        </div>
                        <div class="treatment-field">
                            <label class="treatment-label">Estado</label>
                            <div class="treatment-input-wrapper">
                                <i class="bi bi-activity"></i>
                                <select class="treatment-select" name="estado">
                                    <?php foreach (['pendiente','realizada','cancelada'] as $estado): ?>
                                        <option value="<?= $estado ?>" <?= value($_POST, 'estado') === $estado ? 'selected' : '' ?>><?= ucfirst($estado) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="treatment-form-grid mt-3">
                        <div class="treatment-field full-width">
                            <label class="treatment-label">Descripción</label>
                            <div class="treatment-input-wrapper textarea-wrapper">
                                <textarea class="treatment-textarea" name="descripcion" rows="2" placeholder="Describe el procedimiento..."><?= value($_POST, 'descripcion') ?></textarea>
                            </div>
                        </div>
                        <div class="treatment-field full-width">
                            <label class="treatment-label">Observaciones</label>
                            <div class="treatment-input-wrapper textarea-wrapper">
                                <textarea class="treatment-textarea" name="observaciones_sesion" rows="2" placeholder="Notas adicionales..."><?= value($_POST, 'observaciones_sesion') ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="treatment-form-actions">
                        <button type="submit" class="btn treatment-primary">
                            <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                            <i class="bi bi-plus-circle"></i> Agregar sesión
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Sessions Timeline -->
    <section class="treatment-sessions-section">
        <div class="sessions-header">
            <div>
                <span class="sessions-eyebrow"><i class="bi bi-list-check"></i> Sesiones registradas</span>
                <h2>Historial de sesiones</h2>
            </div>
            <span class="sessions-count"><?= count($sesiones) ?> sesiones</span>
        </div>
        
        <?php if (empty($sesiones)): ?>
            <div class="sessions-empty">
                <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                <strong>No hay sesiones registradas</strong>
                <p>Agrega la primera sesión para comenzar el seguimiento del tratamiento.</p>
            </div>
        <?php else: ?>
            <div class="sessions-timeline">
                <?php foreach ($sesiones as $index => $sesion): ?>
                    <article class="session-card session-<?= $sesion['estado'] ?>" data-session-id="<?= $sesion['id'] ?>">
                        <div class="session-timeline-line"></div>
                        <div class="session-timeline-dot"></div>
                        <div class="session-card-content">
                            <div class="session-card-header">
                                <div class="session-number">
                                    <span>Sesión <?= $sesion['numero_sesion'] ?></span>
                                    <span class="session-status badge-<?= badgeClass($sesion['estado']) ?>"><?= ucfirst($sesion['estado']) ?></span>
                                </div>
                                <div class="session-actions">
                                    <a href="avance.php?session_id=<?= $sesion['id'] ?>" class="session-action view" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button type="button" class="session-action more" title="Más opciones">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="session-card-body">
                                <div class="session-info-grid">
                                    <div class="session-info-item">
                                        <i class="bi bi-heart-pulse"></i>
                                        <div>
                                            <span>Procedimiento</span>
                                            <strong><?= htmlspecialchars($sesion['procedimiento_nombre'] ?: 'Sin procedimiento') ?></strong>
                                        </div>
                                    </div>
                                    <div class="session-info-item">
                                        <i class="bi bi-calendar3"></i>
                                        <div>
                                            <span>Fecha programada</span>
                                            <strong><?= $sesion['fecha_programada'] ? htmlspecialchars(date('d/m/Y', strtotime($sesion['fecha_programada']))) : 'Sin programar' ?></strong>
                                        </div>
                                    </div>
                                    <div class="session-info-item">
                                        <i class="bi bi-person-badge"></i>
                                        <div>
                                            <span>Odontólogo</span>
                                            <strong><?= htmlspecialchars($sesion['odontologo_nombre'] ? $sesion['odontologo_nombre'] . ' ' . $sesion['odontologo_apellido'] : 'No asignado') ?></strong>
                                        </div>
                                    </div>
                                    <div class="session-info-item">
                                        <i class="bi bi-cash-coin"></i>
                                        <div>
                                            <span>Costo</span>
                                            <strong>$<?= number_format($sesion['costo_sesion'] ?? 0, 0, ',', '.') ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($sesion['pieza_dental'] || $sesion['descripcion'] || $sesion['observaciones_sesion']): ?>
                                <div class="session-details">
                                    <?php if ($sesion['pieza_dental']): ?>
                                        <div class="session-detail-item">
                                            <i class="bi bi-grid-3x3"></i>
                                            <span>Pieza dental: <strong><?= htmlspecialchars($sesion['pieza_dental']) ?></strong></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($sesion['descripcion']): ?>
                                        <div class="session-detail-item">
                                            <i class="bi bi-chat-text"></i>
                                            <span><?= htmlspecialchars($sesion['descripcion']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($sesion['observaciones_sesion']): ?>
                                        <div class="session-detail-item">
                                            <i class="bi bi-sticky"></i>
                                            <span><?= htmlspecialchars($sesion['observaciones_sesion']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
