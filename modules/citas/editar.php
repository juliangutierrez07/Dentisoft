<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('citas.editar');

$paginaTitulo = 'Editar Cita';
$cssAdicional = 'citas-premium.css';
$errores = [];
$avisos = [];
$estadosValidos = ['pendiente', 'confirmada', 'atendida', 'cancelada', 'no_asistio'];

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$volverCalendario = ($_GET['volver'] ?? '') === 'calendario';

if (!$id) {
    setAlerta('Cita no valida. Abre la cita desde el listado para asegurar un identificador correcto.', 'danger');
    header('Location: index.php');
    exit;
}

function h(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function selected(mixed $current, mixed $candidate): string {
    return (string) $current === (string) $candidate ? 'selected' : '';
}

function value(array $cita, string $name): string {
    return h(trim((string) ($cita[$name] ?? '')));
}

function estadoTexto(string $estado): string {
    return match ($estado) {
        'atendida' => 'Finalizada',
        'no_asistio' => 'No asistio',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function badgeClass(string $estado): string {
    return match ($estado) {
        'pendiente' => 'warning',
        'confirmada' => 'success',
        'atendida' => 'info',
        'cancelada' => 'danger',
        'no_asistio' => 'secondary',
        default => 'light',
    };
}

function fetchAllSafe(PDO $db, string $sql, array $params, string $context, array &$avisos): array {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Citas Editar catalogo error [$context]: " . $e->getMessage());
        $avisos[] = "No se pudo cargar el catalogo de $context. Puedes guardar la cita si los datos actuales son validos.";
        return [];
    }
}

function parseTimeToSql(string $time): ?string {
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
        return null;
    }

    $date = DateTime::createFromFormat(strlen($time) === 5 ? '!H:i' : '!H:i:s', $time);
    return $date ? $date->format('H:i:s') : null;
}

function appointmentBlocksSchedule(string $estado): bool {
    return in_array($estado, ['pendiente', 'confirmada', 'en_curso'], true);
}

function appointmentScheduleConflict(PDO $db, int $odontologoId, string $fecha, string $horaInicio, ?int $excludeId = null): bool {
    $activeStatuses = ['pendiente', 'confirmada', 'en_curso'];
    $placeholders = [];
    $params = [
        ':odontologo_id' => $odontologoId,
        ':fecha' => $fecha,
        ':hora_inicio' => $horaInicio,
    ];

    foreach ($activeStatuses as $index => $estado) {
        $key = ':estado_' . $index;
        $placeholders[] = $key;
        $params[$key] = $estado;
    }

    $sql = "SELECT COUNT(*)
        FROM citas
        WHERE odontologo_id = :odontologo_id
          AND fecha = :fecha
          AND hora_inicio = :hora_inicio
          AND estado IN (" . implode(', ', $placeholders) . ")";

    if ($excludeId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $excludeId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $count = (int) $stmt->fetchColumn();

    // LOG TEMPORAL: Verificar los parámetros de validación
    error_log("=== VALIDACIÓN CONFLICTO CITA (EDITAR) ===");
    error_log("Odontólogo ID: $odontologoId");
    error_log("Fecha: $fecha");
    error_log("Hora inicio: $horaInicio");
    error_log("Exclude ID: " . ($excludeId ?? 'NULL'));
    error_log("Estados activos: " . implode(', ', $activeStatuses));
    error_log("SQL: $sql");
    error_log("Citas encontradas: $count");
    error_log("¿Hay conflicto?: " . ($count > 0 ? 'SÍ' : 'NO'));
    error_log("==========================================");

    return $count > 0;
}

try {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM citas WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $cita = $stmt->fetch();

    if (!$cita) {
        setAlerta('Cita no encontrada. Es posible que haya sido eliminada o movida.', 'warning');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $traceId = bin2hex(random_bytes(4));
    error_log("Citas Editar carga cita error [$traceId]: " . $e->getMessage());
    setAlerta("Error interno al cargar la cita. Codigo de soporte: $traceId.", 'danger');
    header('Location: index.php');
    exit;
}

$pacientes = fetchAllSafe(
    $db,
    "SELECT id, numero_documento, nombre, apellido
     FROM pacientes
     WHERE estado = 'activo' OR id = :paciente_id
     ORDER BY nombre, apellido",
    [':paciente_id' => $cita['paciente_id']],
    'pacientes',
    $avisos
);

$odontologos = fetchAllSafe(
    $db,
    "SELECT u.id, u.nombre, u.apellido
     FROM usuarios u
     INNER JOIN roles r ON r.id = u.rol_id
     WHERE r.nombre = 'odontologo' AND (u.estado = 'activo' OR u.id = :odontologo_id)
     ORDER BY u.nombre, u.apellido",
    [':odontologo_id' => $cita['odontologo_id']],
    'odontologos',
    $avisos
);

$planes = fetchAllSafe(
    $db,
    "SELECT id, nombre_plan FROM planes_tratamiento ORDER BY fecha_inicio DESC",
    [],
    'planes',
    $avisos
);

$sesiones = fetchAllSafe(
    $db,
    "SELECT id, numero_sesion, plan_id FROM sesiones_tratamiento ORDER BY fecha_programada DESC",
    [],
    'sesiones',
    $avisos
);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCSRF();

    $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $odontologoId = filter_input(INPUT_POST, 'odontologo_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $planId = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]) ?: null;
    $sesionId = filter_input(INPUT_POST, 'sesion_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]) ?: null;

    $fecha = trim((string) ($_POST['fecha'] ?? ''));
    $horaInicioInput = trim((string) ($_POST['hora_inicio'] ?? ''));
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    $estado = trim((string) ($_POST['estado'] ?? 'pendiente'));
    $horaInicio = parseTimeToSql($horaInicioInput);

    if (!$pacienteId) {
        $errores[] = 'Selecciona un paciente valido.';
    }

    if (!$odontologoId) {
        $errores[] = 'Selecciona un odontologo valido.';
    }

    if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $errores[] = 'La fecha de la cita es obligatoria y debe tener formato valido.';
    }

    if ($horaInicio === null) {
        $errores[] = 'La hora de inicio es obligatoria y debe tener formato valido.';
    }

    if (!in_array($estado, $estadosValidos, true)) {
        $errores[] = 'El estado seleccionado no es valido.';
    }

    if (empty($errores)) {
        $horaFin = (new DateTime($horaInicio))->modify('+30 minutes')->format('H:i:s');

        try {
            $existsStmt = $db->prepare("SELECT COUNT(*) FROM citas WHERE id = :id");
            $existsStmt->execute([':id' => $id]);
            if ((int) $existsStmt->fetchColumn() === 0) {
                throw new RuntimeException('La cita ya no existe.');
            }

            if (appointmentBlocksSchedule($estado) && appointmentScheduleConflict($db, $odontologoId, $fecha, $horaInicio, $id)) {
                $errores[] = 'Este odontologo ya tiene una cita agendada en esa fecha y hora. Cambia la hora o selecciona otro odontologo.';
                throw new RuntimeException('SCHEDULE_CONFLICT');
            }

            $stmt = $db->prepare("UPDATE citas
                SET paciente_id = :paciente_id,
                    odontologo_id = :odontologo_id,
                    plan_id = :plan_id,
                    sesion_id = :sesion_id,
                    fecha = :fecha,
                    hora_inicio = :hora_inicio,
                    hora_fin = :hora_fin,
                    motivo = :motivo,
                    estado = :estado,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id");
            $stmt->execute([
                ':paciente_id' => $pacienteId,
                ':odontologo_id' => $odontologoId,
                ':plan_id' => $planId,
                ':sesion_id' => $sesionId,
                ':fecha' => $fecha,
                ':hora_inicio' => $horaInicio,
                ':hora_fin' => $horaFin,
                ':motivo' => $motivo,
                ':estado' => $estado,
                ':id' => $id,
            ]);

            setAlerta('Cita actualizada correctamente.');
            $calendarUrl = 'calendario.php?desde=' . urlencode($fecha) . '&refresh=' . time();
            header('Location: ' . ($volverCalendario ? $calendarUrl : 'index.php'));
            exit;
        } catch (RuntimeException $e) {
            error_log('Citas Editar validacion update error: ' . $e->getMessage());
            if ($e->getMessage() !== 'SCHEDULE_CONFLICT') {
                $errores[] = 'La cita ya no esta disponible. Regresa al listado y vuelve a intentarlo.';
            }
        } catch (PDOException $e) {
            $traceId = bin2hex(random_bytes(4));
            error_log("Citas Editar update error [$traceId]: " . $e->getMessage());
            $errores[] = "No fue posible actualizar la cita. Codigo de soporte: $traceId.";
        }
    }

    $cita = array_merge($cita, [
        'paciente_id' => $_POST['paciente_id'] ?? $cita['paciente_id'],
        'odontologo_id' => $_POST['odontologo_id'] ?? $cita['odontologo_id'],
        'fecha' => $_POST['fecha'] ?? $cita['fecha'],
        'hora_inicio' => $_POST['hora_inicio'] ?? $cita['hora_inicio'],
        'motivo' => $_POST['motivo'] ?? $cita['motivo'],
        'plan_id' => $_POST['plan_id'] ?? $cita['plan_id'],
        'sesion_id' => $_POST['sesion_id'] ?? $cita['sesion_id'],
        'estado' => $_POST['estado'] ?? $cita['estado'],
    ]);
}

$pacienteActual = null;
foreach ($pacientes as $paciente) {
    if ((string) ($paciente['id'] ?? '') === (string) ($cita['paciente_id'] ?? '')) {
        $pacienteActual = $paciente;
        break;
    }
}

$odontologoActual = null;
foreach ($odontologos as $odontologo) {
    if ((string) ($odontologo['id'] ?? '') === (string) ($cita['odontologo_id'] ?? '')) {
        $odontologoActual = $odontologo;
        break;
    }
}

$pacienteNombre = trim((string) (($pacienteActual['nombre'] ?? '') . ' ' . ($pacienteActual['apellido'] ?? '')));
$pacienteDocumento = trim((string) ($pacienteActual['numero_documento'] ?? 'Sin documento'));
$odontologoNombre = trim((string) (($odontologoActual['nombre'] ?? '') . ' ' . ($odontologoActual['apellido'] ?? '')));
$pacienteIniciales = strtoupper(mb_substr((string) ($pacienteActual['nombre'] ?? 'P'), 0, 1) . mb_substr((string) ($pacienteActual['apellido'] ?? 'C'), 0, 1));
$motivoLength = mb_strlen(trim((string) ($cita['motivo'] ?? '')));
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 citas-page edit-cita-page">
    <section class="citas-hero edit-cita-hero mb-4">
        <div class="citas-hero-main">
            <span class="citas-eyebrow"><i class="bi bi-calendar2-check"></i> Gestion de agenda</span>
            <h1>Editar Cita</h1>
            <p>Actualiza la programacion, estado clinico y observaciones de la cita sin perder el contexto del paciente.</p>
        </div>
        <div class="citas-hero-actions">
            <a href="calendario.php?desde=<?= h((string) ($cita['fecha'] ?? date('Y-m-d'))) ?>&refresh=<?= h((string) time()) ?>" class="btn btn-outline-light"><i class="bi bi-calendar3-week"></i> Calendario</a>
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </section>

    <section class="edit-summary-grid mb-4">
        <article class="edit-patient-card">
            <div class="edit-avatar"><?= h($pacienteIniciales) ?></div>
            <div>
                <span>Paciente</span>
                <strong><?= h($pacienteNombre ?: 'Paciente seleccionado') ?></strong>
                <small><?= h($pacienteDocumento) ?></small>
            </div>
        </article>
        <article class="edit-mini-card">
            <i class="bi bi-clock-history"></i>
            <div><span>Horario</span><strong><?= h(substr((string) ($cita['hora_inicio'] ?? ''), 0, 5) ?: '--:--') ?></strong></div>
        </article>
        <article class="edit-mini-card">
            <i class="bi bi-person-vcard"></i>
            <div><span>Odontologo</span><strong><?= h($odontologoNombre ?: 'Sin asignar') ?></strong></div>
        </article>
        <article class="edit-mini-card">
            <i class="bi bi-activity"></i>
            <div><span>Estado</span><strong><span class="badge bg-<?= badgeClass((string) ($cita['estado'] ?? 'pendiente')) ?>"><?= h(estadoTexto((string) ($cita['estado'] ?? 'pendiente'))) ?></span></strong></div>
        </article>
    </section>

    <?php if (!empty($avisos)): ?>
        <div class="alert alert-warning shadow-sm">
            <i class="bi bi-exclamation-circle me-2"></i>
            <?= h(implode(' ', $avisos)) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger shadow-sm">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card bg-dark border-secondary shadow-sm edit-cita-card">
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate data-prevent-double>
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                <div class="edit-form-grid">
                    <section class="edit-form-section section-patient">
                        <header><i class="bi bi-person-heart"></i><div><span>Informacion del paciente</span><strong>Paciente y responsable clinico</strong></div></header>
                        <div class="edit-fields two-cols">
                            <div class="premium-field">
                                <label class="form-label fw-semibold">Paciente *</label>
                                <div class="field-shell has-icon">
                                    <i class="bi bi-person"></i>
                                    <select class="form-select" name="paciente_id" required>
                                        <option value="">Selecciona un paciente</option>
                                        <?php foreach ($pacientes as $paciente): ?>
                                            <option value="<?= h($paciente['id']) ?>" <?= selected($cita['paciente_id'] ?? '', $paciente['id']) ?>>
                                                <?= h(($paciente['numero_documento'] ?? 'Sin documento') . ' - ' . ($paciente['nombre'] ?? '') . ' ' . ($paciente['apellido'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="invalid-feedback">El paciente es obligatorio.</div>
                            </div>
                            <div class="premium-field">
                                <label class="form-label fw-semibold">Odontologo *</label>
                                <div class="field-shell has-icon">
                                    <i class="bi bi-person-badge"></i>
                                    <select class="form-select" name="odontologo_id" required>
                                        <option value="">Selecciona un odontologo</option>
                                        <?php foreach ($odontologos as $odontologo): ?>
                                            <option value="<?= h($odontologo['id']) ?>" <?= selected($cita['odontologo_id'] ?? '', $odontologo['id']) ?>>
                                                <?= h(($odontologo['nombre'] ?? '') . ' ' . ($odontologo['apellido'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="invalid-feedback">El odontologo es obligatorio.</div>
                            </div>
                        </div>
                    </section>

                    <section class="edit-form-section section-schedule">
                        <header><i class="bi bi-calendar2-week"></i><div><span>Programacion</span><strong>Fecha, hora y estado operativo</strong></div></header>
                        <div class="edit-fields three-cols">
                            <div class="premium-field">
                                <label class="form-label fw-semibold">Fecha *</label>
                                <div class="field-shell has-icon">
                                    <i class="bi bi-calendar3"></i>
                                    <input type="date" class="form-control" name="fecha" value="<?= value($cita, 'fecha') ?>" required>
                                </div>
                                <div class="invalid-feedback">La fecha es obligatoria.</div>
                            </div>
                            <div class="premium-field">
                                <label class="form-label fw-semibold">Hora inicio *</label>
                                <div class="field-shell has-icon">
                                    <i class="bi bi-clock"></i>
                                    <input type="time" class="form-control" name="hora_inicio" value="<?= h(substr((string) ($cita['hora_inicio'] ?? ''), 0, 5)) ?>" required>
                                </div>
                                <div class="invalid-feedback">La hora es obligatoria.</div>
                            </div>
                            <div class="premium-field">
                                <label class="form-label fw-semibold">Estado</label>
                                <div class="field-shell has-icon">
                                    <i class="bi bi-activity"></i>
                                    <select class="form-select" name="estado">
                                        <?php foreach ($estadosValidos as $estado): ?>
                                            <option value="<?= h($estado) ?>" <?= selected($cita['estado'] ?? '', $estado) ?>><?= h(estadoTexto($estado)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="edit-form-section section-medical">
                        <header><i class="bi bi-clipboard2-pulse"></i><div><span>Informacion medica</span><strong>Plan y sesion asociada</strong></div></header>
                        <div class="edit-fields two-cols">
                            <div class="premium-field">
                                <label class="form-label fw-semibold">Plan asociado</label>
                                <div class="field-shell has-icon">
                                    <i class="bi bi-journal-medical"></i>
                                    <select class="form-select" name="plan_id">
                                        <option value="">Sin plan</option>
                                        <?php foreach ($planes as $plan): ?>
                                            <option value="<?= h($plan['id']) ?>" <?= selected($cita['plan_id'] ?? '', $plan['id']) ?>>
                                                <?= h($plan['nombre_plan'] ?? 'Plan sin nombre') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="premium-field">
                                <label class="form-label fw-semibold">Sesion asociada</label>
                                <div class="field-shell has-icon">
                                    <i class="bi bi-layers"></i>
                                    <select class="form-select" name="sesion_id">
                                        <option value="">Sin sesion</option>
                                        <?php foreach ($sesiones as $sesion): ?>
                                            <option value="<?= h($sesion['id']) ?>" <?= selected($cita['sesion_id'] ?? '', $sesion['id']) ?>>
                                                Plan #<?= h($sesion['plan_id'] ?? '') ?> - Sesion <?= h($sesion['numero_sesion'] ?? '') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="edit-form-section section-notes">
                        <header><i class="bi bi-chat-square-text"></i><div><span>Observaciones</span><strong>Motivo y contexto de la cita</strong></div></header>
                        <div class="premium-field">
                            <label class="form-label fw-semibold">Motivo</label>
                            <div class="field-shell textarea-shell">
                                <textarea class="form-control" name="motivo" rows="5" maxlength="600" placeholder="Describe el motivo clinico o administrativo de la cita..."><?= value($cita, 'motivo') ?></textarea>
                            </div>
                            <div class="field-helper"><span>Panel clinico de observaciones</span><span><?= h((string) $motivoLength) ?>/600</span></div>
                        </div>
                    </section>
                </div>

                <div class="edit-actions">
                    <a href="index.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-save2"></i>
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
