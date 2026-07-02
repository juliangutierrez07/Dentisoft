<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('tratamientos.crear');

$planId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$editing = false;
$plan = [];
$errores = [];

try {
    $db = getDB();

    if ($planId) {
        $stmt = $db->prepare("SELECT * FROM planes_tratamiento WHERE id = :id");
        $stmt->execute([':id' => $planId]);
        $loadedPlan = $stmt->fetch();
        if ($loadedPlan) {
            $plan = $loadedPlan;
            $editing = true;
        }
    }

    $pacientes = $db->query("SELECT id, numero_documento, nombre, apellido FROM pacientes WHERE estado = 'activo' ORDER BY nombre, apellido")->fetchAll();
    $odontologosStmt = $db->prepare("SELECT u.id, u.nombre, u.apellido FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'odontologo' AND u.estado = 'activo' ORDER BY u.nombre, u.apellido");
    $odontologosStmt->execute();
    $odontologos = $odontologosStmt->fetchAll();
    $historias = $db->query("SELECT hc.id, hc.numero_historia, hc.paciente_id, p.nombre, p.apellido FROM historias_clinicas hc INNER JOIN pacientes p ON p.id = hc.paciente_id ORDER BY hc.fecha_apertura DESC")->fetchAll();
} catch (PDOException $e) {
    error_log('Tratamientos Crear_plan carga error: ' . $e->getMessage());
    $pacientes = [];
    $odontologos = [];
    $historias = [];
}

$paginaTitulo = $editing ? 'Editar Plan de Tratamiento' : 'Crear Plan de Tratamiento';
$cssAdicional = 'tratamientos-premium.css';
$jsAdicional = 'tratamientos.js';

function h(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function value(array $data, string $name, mixed $fallback = ''): string {
    return h(trim((string) ($data[$name] ?? $fallback)));
}

function postArray(string $name): array {
    $value = $_POST[$name] ?? [];
    return is_array($value) ? $value : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $nombrePlan = trim((string) ($_POST['nombre_plan'] ?? ''));
    $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);
    $odontologoId = filter_input(INPUT_POST, 'odontologo_id', FILTER_VALIDATE_INT);
    $historiaId = filter_input(INPUT_POST, 'historia_id', FILTER_VALIDATE_INT);
    $fechaInicio = trim((string) ($_POST['fecha_inicio'] ?? ''));
    $fechaFin = trim((string) ($_POST['fecha_fin_estimada'] ?? ''));
    $costoTotal = filter_input(INPUT_POST, 'costo_total', FILTER_VALIDATE_FLOAT);
    $abonoInicial = filter_input(INPUT_POST, 'abono_inicial', FILTER_VALIDATE_FLOAT);
    $estado = trim((string) ($_POST['estado'] ?? 'pendiente'));
    $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
    $tipoTratamiento = trim((string) ($_POST['tipo_tratamiento'] ?? 'integral'));
    $prioridad = trim((string) ($_POST['prioridad'] ?? 'media'));

    if ($nombrePlan === '') {
        $errores[] = 'El nombre del tratamiento es obligatorio.';
    }
    if (!$pacienteId) {
        $errores[] = 'Selecciona un paciente valido.';
    }
    if (!$odontologoId) {
        $errores[] = 'Selecciona un odontologo responsable.';
    }
    if (!$historiaId) {
        $errores[] = 'Selecciona una historia clinica valida.';
    }
    if ($historiaId && $pacienteId) {
        $historiaValida = false;
        foreach ($historias as $historiaDisponible) {
            if ((int) $historiaDisponible['id'] === (int) $historiaId && (int) $historiaDisponible['paciente_id'] === (int) $pacienteId) {
                $historiaValida = true;
                break;
            }
        }
        if (!$historiaValida) {
            $errores[] = 'La historia clinica seleccionada no pertenece al paciente.';
        }
    }
    if ($fechaInicio === '') {
        $errores[] = 'La fecha de inicio es obligatoria.';
    }
    if ($costoTotal === false || $costoTotal < 0) {
        $costoTotal = 0.00;
    }
    if ($abonoInicial === false || $abonoInicial < 0) {
        $abonoInicial = 0.00;
    }
    if (!in_array($estado, ['pendiente', 'en_curso', 'completado', 'cancelado'], true)) {
        $estado = 'pendiente';
    }
    if (!in_array($prioridad, ['baja', 'media', 'alta', 'urgente'], true)) {
        $prioridad = 'media';
    }

    $procNombres = postArray('procedimiento_nombre');
    $procPiezas = postArray('procedimiento_pieza');
    $procCostos = postArray('procedimiento_costo');
    $procSesiones = postArray('procedimiento_sesiones');
    $procNotas = postArray('procedimiento_observaciones');
    $procedimientos = [];

    foreach ($procNombres as $index => $procNombre) {
        $procNombre = trim((string) $procNombre);
        if ($procNombre === '') {
            continue;
        }

        $procCosto = filter_var($procCostos[$index] ?? 0, FILTER_VALIDATE_FLOAT);
        $procSesionesEstimadas = filter_var($procSesiones[$index] ?? 1, FILTER_VALIDATE_INT);
        $procedimientos[] = [
            'nombre' => $procNombre,
            'pieza' => trim((string) ($procPiezas[$index] ?? '')),
            'costo' => $procCosto !== false && $procCosto >= 0 ? $procCosto : 0,
            'sesiones' => $procSesionesEstimadas !== false && $procSesionesEstimadas > 0 ? $procSesionesEstimadas : 1,
            'notas' => trim((string) ($procNotas[$index] ?? '')),
        ];
    }

    if (empty($errores)) {
        try {
            $descripcionExtendida = trim($descripcion . "\n\n" . 'Tipo: ' . ucfirst($tipoTratamiento) . ' | Prioridad: ' . ucfirst($prioridad) . ' | Abono inicial sugerido: $' . number_format($abonoInicial, 0, ',', '.'));

            $db->beginTransaction();

            if ($editing && $plan) {
                $stmt = $db->prepare("UPDATE planes_tratamiento
                    SET paciente_id = :paciente_id,
                        odontologo_id = :odontologo_id,
                        historia_id = :historia_id,
                        nombre_plan = :nombre_plan,
                        descripcion = :descripcion,
                        fecha_inicio = :fecha_inicio,
                        fecha_fin_estimada = :fecha_fin_estimada,
                        costo_total = :costo_total,
                        estado = :estado
                    WHERE id = :id");
                $stmt->execute([
                    ':paciente_id' => $pacienteId,
                    ':odontologo_id' => $odontologoId,
                    ':historia_id' => $historiaId,
                    ':nombre_plan' => $nombrePlan,
                    ':descripcion' => $descripcionExtendida,
                    ':fecha_inicio' => $fechaInicio,
                    ':fecha_fin_estimada' => $fechaFin ?: null,
                    ':costo_total' => $costoTotal,
                    ':estado' => $estado,
                    ':id' => $planId,
                ]);
                $savedPlanId = (int) $planId;
                setAlerta('Plan de tratamiento actualizado correctamente.');
            } else {
                $stmt = $db->prepare("INSERT INTO planes_tratamiento
                    (paciente_id, odontologo_id, historia_id, nombre_plan, descripcion, fecha_inicio, fecha_fin_estimada, costo_total, estado)
                    VALUES (:paciente_id, :odontologo_id, :historia_id, :nombre_plan, :descripcion, :fecha_inicio, :fecha_fin_estimada, :costo_total, :estado)");
                $stmt->execute([
                    ':paciente_id' => $pacienteId,
                    ':odontologo_id' => $odontologoId,
                    ':historia_id' => $historiaId,
                    ':nombre_plan' => $nombrePlan,
                    ':descripcion' => $descripcionExtendida,
                    ':fecha_inicio' => $fechaInicio,
                    ':fecha_fin_estimada' => $fechaFin ?: null,
                    ':costo_total' => $costoTotal,
                    ':estado' => $estado,
                ]);
                $savedPlanId = (int) $db->lastInsertId();
                setAlerta('Plan de tratamiento creado correctamente.');
            }

            if (!$editing && !empty($procedimientos)) {
                $sessionStmt = $db->prepare("INSERT INTO sesiones_tratamiento
                    (plan_id, numero_sesion, procedimiento_id, pieza_dental, descripcion, observaciones_sesion, costo_sesion, fecha_programada, estado, odontologo_id)
                    VALUES (:plan_id, :numero_sesion, NULL, :pieza_dental, :descripcion, :observaciones_sesion, :costo_sesion, NULL, 'pendiente', :odontologo_id)");

                $numeroSesion = 1;
                foreach ($procedimientos as $procedimiento) {
                    $descripcionSesion = $procedimiento['nombre'] . ' (' . $procedimiento['sesiones'] . ' sesiones estimadas)';
                    $sessionStmt->execute([
                        ':plan_id' => $savedPlanId,
                        ':numero_sesion' => $numeroSesion,
                        ':pieza_dental' => $procedimiento['pieza'] ?: null,
                        ':descripcion' => $descripcionSesion,
                        ':observaciones_sesion' => $procedimiento['notas'] ?: null,
                        ':costo_sesion' => $procedimiento['costo'],
                        ':odontologo_id' => $odontologoId ?: null,
                    ]);
                    $numeroSesion++;
                }
            }

            $db->commit();
            header('Location: sesiones.php?plan_id=' . urlencode((string) $savedPlanId));
            exit;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Tratamientos Crear_plan save error: ' . $e->getMessage());
            $errores[] = 'No fue posible guardar el plan. Intenta nuevamente.';
        }
    }

    $plan = array_merge($plan ?? [], [
        'nombre_plan' => $_POST['nombre_plan'] ?? '',
        'paciente_id' => $_POST['paciente_id'] ?? '',
        'odontologo_id' => $_POST['odontologo_id'] ?? '',
        'historia_id' => $_POST['historia_id'] ?? '',
        'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
        'fecha_fin_estimada' => $_POST['fecha_fin_estimada'] ?? '',
        'costo_total' => $_POST['costo_total'] ?? '',
        'estado' => $_POST['estado'] ?? 'pendiente',
        'descripcion' => $_POST['descripcion'] ?? '',
        'tipo_tratamiento' => $_POST['tipo_tratamiento'] ?? 'integral',
        'prioridad' => $_POST['prioridad'] ?? 'media',
    ]);
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 treatment-form-page treatment-wizard-page">
    <section class="treatment-form-hero mb-4">
        <div class="form-hero-main">
            <span class="treatment-eyebrow"><i class="bi bi-stars"></i> Plan odontologico premium</span>
            <h1><?= $editing ? 'Editar plan de tratamiento' : 'Crear plan de tratamiento' ?></h1>
            <p>Define el caso clinico, procedimientos, costos y resumen antes de guardar el plan.</p>
        </div>
        <a href="index.php" class="btn treatment-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </section>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger shadow-sm">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="treatment-wizard needs-validation" novalidate data-prevent-double data-treatment-wizard>
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

        <aside class="wizard-rail" aria-label="Pasos del plan">
            <button type="button" class="wizard-step is-active" data-wizard-target="1"><span>1</span><strong>Informacion</strong><small>Paciente y caso</small></button>
            <button type="button" class="wizard-step" data-wizard-target="2"><span>2</span><strong>Procedimientos</strong><small>Tarjetas dinamicas</small></button>
            <button type="button" class="wizard-step" data-wizard-target="3"><span>3</span><strong>Costos</strong><small>Pagos y saldo</small></button>
            <button type="button" class="wizard-step" data-wizard-target="4"><span>4</span><strong>Confirmacion</strong><small>Resumen final</small></button>
        </aside>

        <section class="wizard-stage">
            <article class="wizard-panel is-active" data-wizard-panel="1">
                <div class="wizard-panel-header">
                    <span>01</span>
                    <div><h2>Informacion general</h2><p>Selecciona paciente, responsable y contexto clinico del tratamiento.</p></div>
                </div>
                <div class="treatment-form-grid">
                    <div class="treatment-field full-width">
                        <label class="treatment-label">Nombre del tratamiento *</label>
                        <div class="treatment-input-wrapper">
                            <i class="bi bi-prescription2"></i>
                            <input type="text" class="treatment-input" name="nombre_plan" value="<?= value($plan, 'nombre_plan') ?>" placeholder="Ej. Rehabilitacion oral integral" required data-summary="plan">
                        </div>
                    </div>
                    <div class="treatment-field">
                        <label class="treatment-label">Paciente *</label>
                        <div class="treatment-input-wrapper">
                            <i class="bi bi-person"></i>
                            <select class="treatment-select" name="paciente_id" required data-summary="paciente">
                                <option value="">Selecciona un paciente</option>
                                <?php foreach ($pacientes as $paciente): ?>
                                    <option value="<?= h($paciente['id']) ?>" <?= value($plan, 'paciente_id') == $paciente['id'] ? 'selected' : '' ?>>
                                        <?= h($paciente['numero_documento'] . ' - ' . $paciente['nombre'] . ' ' . $paciente['apellido']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="treatment-field">
                        <label class="treatment-label">Odontologo responsable *</label>
                        <div class="treatment-input-wrapper">
                            <i class="bi bi-person-badge"></i>
                            <select class="treatment-select" name="odontologo_id" required data-summary="odontologo">
                                <option value="">Selecciona un odontologo</option>
                                <?php foreach ($odontologos as $odontologo): ?>
                                    <option value="<?= h($odontologo['id']) ?>" <?= value($plan, 'odontologo_id') == $odontologo['id'] ? 'selected' : '' ?>>
                                        <?= h($odontologo['nombre'] . ' ' . $odontologo['apellido']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="treatment-field">
                        <label class="treatment-label">Tipo de tratamiento</label>
                        <div class="treatment-input-wrapper">
                            <i class="bi bi-grid-1x2"></i>
                            <select class="treatment-select" name="tipo_tratamiento" data-summary="tipo">
                                <?php foreach (['integral' => 'Integral', 'ortodoncia' => 'Ortodoncia', 'endodoncia' => 'Endodoncia', 'periodoncia' => 'Periodoncia', 'estetica' => 'Estetica dental', 'rehabilitacion' => 'Rehabilitacion oral'] as $tipo => $label): ?>
                                    <option value="<?= h($tipo) ?>" <?= value($plan, 'tipo_tratamiento', 'integral') === $tipo ? 'selected' : '' ?>><?= h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="treatment-field">
                        <label class="treatment-label">Prioridad</label>
                        <div class="treatment-input-wrapper">
                            <i class="bi bi-flag"></i>
                            <select class="treatment-select" name="prioridad" data-summary="prioridad">
                                <?php foreach (['media' => 'Media', 'baja' => 'Baja', 'alta' => 'Alta', 'urgente' => 'Urgente'] as $prioridadOpcion => $label): ?>
                                    <option value="<?= h($prioridadOpcion) ?>" <?= value($plan, 'prioridad', 'media') === $prioridadOpcion ? 'selected' : '' ?>><?= h($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="treatment-field">
                        <label class="treatment-label">Estado inicial</label>
                        <div class="treatment-input-wrapper">
                            <i class="bi bi-activity"></i>
                            <select class="treatment-select" name="estado" data-summary="estado">
                                <?php foreach (['pendiente','en_curso','completado','cancelado'] as $estado): ?>
                                    <option value="<?= h($estado) ?>" <?= value($plan, 'estado', 'pendiente') === $estado ? 'selected' : '' ?>><?= h(ucfirst(str_replace('_', ' ', $estado))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="treatment-field">
                        <label class="treatment-label">Fecha inicio *</label>
                        <div class="treatment-input-wrapper">
                            <i class="bi bi-calendar-plus"></i>
                            <input type="date" class="treatment-input" name="fecha_inicio" value="<?= value($plan, 'fecha_inicio', date('Y-m-d')) ?>" required data-summary="inicio">
                        </div>
                    </div>
                    <div class="treatment-field">
                        <label class="treatment-label">Fecha fin estimada</label>
                        <div class="treatment-input-wrapper">
                            <i class="bi bi-calendar-check"></i>
                            <input type="date" class="treatment-input" name="fecha_fin_estimada" value="<?= value($plan, 'fecha_fin_estimada') ?>" data-summary="fin">
                        </div>
                    </div>
                    <div class="treatment-field full-width">
                        <label class="treatment-label">Historia clinica asociada *</label>
                        <div class="treatment-input-wrapper">
                            <i class="bi bi-journal-medical"></i>
                            <select class="treatment-select" name="historia_id" required>
                                <option value="">Selecciona una historia clinica</option>
                                <?php foreach ($historias as $historia): ?>
                                    <option value="<?= h($historia['id']) ?>" <?= value($plan, 'historia_id') == $historia['id'] ? 'selected' : '' ?>>
                                        <?= h($historia['numero_historia'] . ' - ' . $historia['nombre'] . ' ' . $historia['apellido']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="treatment-field full-width">
                        <label class="treatment-label">Objetivo clinico y observaciones</label>
                        <textarea class="treatment-textarea" name="descripcion" rows="4" placeholder="Describe diagnostico, objetivos, riesgos, consentimiento y consideraciones clinicas..."><?= value($plan, 'descripcion') ?></textarea>
                    </div>
                </div>
            </article>

            <article class="wizard-panel" data-wizard-panel="2">
                <div class="wizard-panel-header">
                    <span>02</span>
                    <div><h2>Procedimientos</h2><p>Agrega procedimientos como tarjetas. Al crear el plan se registran como sesiones iniciales.</p></div>
                </div>
                <div class="procedure-toolbar">
                    <button type="button" class="btn treatment-secondary" data-add-procedure><i class="bi bi-plus-circle"></i> Agregar procedimiento</button>
                    <small>Usa pieza dental, costo, sesiones estimadas y notas clinicas.</small>
                </div>
                <div class="procedure-list" data-procedure-list>
                    <article class="procedure-card">
                        <div class="procedure-card-head">
                            <span><i class="bi bi-heart-pulse"></i></span>
                            <strong>Procedimiento 1</strong>
                            <button type="button" class="procedure-remove" data-remove-procedure aria-label="Eliminar procedimiento"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="procedure-grid">
                            <label><span>Nombre</span><input type="text" name="procedimiento_nombre[]" placeholder="Ej. Endodoncia molar"></label>
                            <label><span>Pieza dental</span><input type="text" name="procedimiento_pieza[]" placeholder="Ej. 36"></label>
                            <label><span>Costo</span><input type="number" min="0" step="1000" name="procedimiento_costo[]" placeholder="0" data-cost-input></label>
                            <label><span>Sesiones estimadas</span><input type="number" min="1" step="1" name="procedimiento_sesiones[]" value="1"></label>
                            <label class="wide"><span>Observaciones</span><textarea name="procedimiento_observaciones[]" rows="2" placeholder="Materiales, indicaciones, riesgos o notas del procedimiento"></textarea></label>
                        </div>
                    </article>
                </div>
            </article>

            <article class="wizard-panel" data-wizard-panel="3">
                <div class="wizard-panel-header">
                    <span>03</span>
                    <div><h2>Costos y pagos</h2><p>Define costo total, abono sugerido y estado financiero del caso.</p></div>
                </div>
                <div class="finance-grid">
                    <section class="finance-editor">
                        <div class="treatment-form-grid">
                            <div class="treatment-field">
                                <label class="treatment-label">Costo total (COP)</label>
                                <div class="treatment-input-wrapper">
                                    <i class="bi bi-cash-coin"></i>
                                    <input type="number" step="1000" min="0" class="treatment-input" name="costo_total" value="<?= value($plan, 'costo_total', '0') ?>" placeholder="0" data-total-cost data-summary="costo">
                                </div>
                            </div>
                            <div class="treatment-field">
                                <label class="treatment-label">Abono inicial</label>
                                <div class="treatment-input-wrapper">
                                    <i class="bi bi-wallet2"></i>
                                    <input type="number" step="1000" min="0" class="treatment-input" name="abono_inicial" value="0" placeholder="0" data-initial-payment>
                                </div>
                            </div>
                        </div>
                    </section>
                    <aside class="finance-preview">
                        <span>Estado financiero</span>
                        <strong data-finance-paid>$0</strong>
                        <small>abonado de <b data-finance-total>$0</b></small>
                        <div class="finance-progress"><i data-finance-bar></i></div>
                        <p>Pendiente: <b data-finance-pending>$0</b></p>
                    </aside>
                </div>
            </article>

            <article class="wizard-panel" data-wizard-panel="4">
                <div class="wizard-panel-header">
                    <span>04</span>
                    <div><h2>Confirmacion</h2><p>Revisa el resumen del plan antes de guardarlo.</p></div>
                </div>
                <div class="wizard-summary">
                    <article><span>Tratamiento</span><strong data-summary-output="plan">Sin nombre</strong></article>
                    <article><span>Paciente</span><strong data-summary-output="paciente">No seleccionado</strong></article>
                    <article><span>Responsable</span><strong data-summary-output="odontologo">No seleccionado</strong></article>
                    <article><span>Tipo</span><strong data-summary-output="tipo">Integral</strong></article>
                    <article><span>Prioridad</span><strong data-summary-output="prioridad">Media</strong></article>
                    <article><span>Estado</span><strong data-summary-output="estado">Pendiente</strong></article>
                    <article><span>Inicio</span><strong data-summary-output="inicio">-</strong></article>
                    <article><span>Costo total</span><strong data-summary-output="costo">$0</strong></article>
                </div>
            </article>

            <div class="wizard-actions">
                <button type="button" class="btn treatment-secondary" data-wizard-prev><i class="bi bi-arrow-left"></i> Anterior</button>
                <button type="button" class="btn treatment-primary" data-wizard-next>Siguiente <i class="bi bi-arrow-right"></i></button>
                <button type="submit" class="btn treatment-primary d-none" data-wizard-submit>
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    <i class="bi bi-check2-circle"></i> <?= $editing ? 'Actualizar plan' : 'Guardar plan' ?>
                </button>
            </div>
        </section>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
