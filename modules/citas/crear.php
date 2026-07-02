<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../helpers/MailService.php';
requirePermission('citas.crear');

$paginaTitulo = 'Crear Cita';
$cssAdicional = 'citas-premium.css';
$errores = [];
$fechaSugerida = trim((string) ($_GET['fecha'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaSugerida)) {
    $fechaSugerida = '';
}
$volverCalendario = ($_GET['volver'] ?? '') === 'calendario';

try {
    $db = getDB();
    $pacientes = $db->query("SELECT id, numero_documento, nombre, apellido FROM pacientes WHERE estado = 'activo' ORDER BY nombre, apellido")->fetchAll();
    $odontologosStmt = $db->prepare("SELECT u.id, u.nombre, u.apellido FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'odontologo' AND u.estado = 'activo' ORDER BY u.nombre, u.apellido");
    $odontologosStmt->execute();
    $odontologos = $odontologosStmt->fetchAll();
    $planes = $db->query("SELECT id, nombre_plan FROM planes_tratamiento ORDER BY fecha_inicio DESC")->fetchAll();
    $sesiones = $db->query("SELECT id, numero_sesion, plan_id FROM sesiones_tratamiento ORDER BY fecha_programada DESC")->fetchAll();
} catch (PDOException $e) {
    error_log('Citas Crear carga error: ' . $e->getMessage());
    $pacientes = [];
    $odontologos = [];
    $planes = [];
    $sesiones = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);
    $odontologoId = filter_input(INPUT_POST, 'odontologo_id', FILTER_VALIDATE_INT);
    $fecha = trim($_POST['fecha'] ?? '');
    $horaInicioInput = trim($_POST['hora_inicio'] ?? '');
    $horaInicio = parseTimeToSql($horaInicioInput);
    $motivo = trim($_POST['motivo'] ?? '');
    $planId = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT);
    $sesionId = filter_input(INPUT_POST, 'sesion_id', FILTER_VALIDATE_INT);

    if (!$pacienteId) {
        $errores[] = 'Selecciona un paciente válido.';
    }
    if (!$odontologoId) {
        $errores[] = 'Selecciona un odontólogo válido.';
    }
    if ($fecha === '' || !validDateValue($fecha)) {
        $errores[] = 'La fecha de la cita es obligatoria y debe tener formato valido.';
    }
    if ($horaInicio === null) {
        $errores[] = 'La hora de inicio es obligatoria y debe tener formato valido.';
    }

    if (empty($errores)) {
        $horaFin = (new DateTime($horaInicio))->modify('+30 minutes')->format('H:i:s');

        try {
            $pacienteEstadoStmt = $db->prepare("SELECT estado FROM pacientes WHERE id = :id LIMIT 1");
            $pacienteEstadoStmt->execute([':id' => $pacienteId]);
            $pacienteEstado = (string) $pacienteEstadoStmt->fetchColumn();

            if ($pacienteEstado !== 'activo') {
                $errores[] = 'No se pueden agendar citas nuevas para pacientes inactivos. Reactiva el paciente o conserva su historial sin nueva cita.';
            }

            if (appointmentScheduleConflict($db, $odontologoId, $fecha, $horaInicio)) {
                $errores[] = 'Este odontologo ya tiene una cita agendada en esa fecha y hora. Cambia la hora o selecciona otro odontologo.';
            }

            if (!empty($errores)) {
                throw new RuntimeException('Conflicto de agenda detectado.');
            }

            $stmt = $db->prepare("INSERT INTO citas (paciente_id, odontologo_id, plan_id, sesion_id, fecha, hora_inicio, hora_fin, motivo, estado, created_by) VALUES (:paciente_id, :odontologo_id, :plan_id, :sesion_id, :fecha, :hora_inicio, :hora_fin, :motivo, 'pendiente', :created_by)");
            $stmt->execute([
                ':paciente_id' => $pacienteId,
                ':odontologo_id' => $odontologoId,
                ':plan_id' => $planId ?: null,
                ':sesion_id' => $sesionId ?: null,
                ':fecha' => $fecha,
                ':hora_inicio' => $horaInicio,
                ':hora_fin' => $horaFin,
                ':motivo' => $motivo,
                ':created_by' => $_SESSION['usuario_id'] ?? null,
            ]);
            
            $citaId = $db->lastInsertId();
            $usuarioId = $_SESSION['usuario_id'] ?? null;
            
            // Enviar notificación por correo al odontólogo
            $mailService = new MailService();
            $resultadoEmail = $mailService->enviarNotificacionCitaAsignada($citaId, $odontologoId, $usuarioId);
            
            if ($resultadoEmail['success']) {
                setAlerta('Cita creada correctamente y notificación enviada al odontólogo.');
            } else {
                setAlerta('Cita creada correctamente, pero no fue posible enviar la notificación al odontólogo: ' . $resultadoEmail['message']);
            }
            
            $calendarUrl = 'calendario.php?desde=' . urlencode($fecha) . '&refresh=' . time();
            header('Location: ' . ($volverCalendario ? $calendarUrl : 'index.php'));
            exit;
        } catch (RuntimeException $e) {
            if (empty($errores)) {
                $errores[] = 'No fue posible validar la disponibilidad de la agenda. Intenta nuevamente.';
            }
        } catch (PDOException $e) {
            error_log('Citas Crear insert error: ' . $e->getMessage());
            $errores[] = 'No fue posible crear la cita. Intenta nuevamente.';
        }
    }
}

function oldValue(string $name): string {
    return htmlspecialchars(trim($_POST[$name] ?? ''), ENT_QUOTES, 'UTF-8');
}

function dateValue(string $fallback): string {
    $value = trim((string) ($_POST['fecha'] ?? $fallback));
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function validDateValue(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}

function parseTimeToSql(string $time): ?string {
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
        return null;
    }

    $date = DateTime::createFromFormat(strlen($time) === 5 ? '!H:i' : '!H:i:s', $time);
    return $date ? $date->format('H:i:s') : null;
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
    error_log("=== VALIDACIÓN CONFLICTO CITA ===");
    error_log("Odontólogo ID: $odontologoId");
    error_log("Fecha: $fecha");
    error_log("Hora inicio: $horaInicio");
    error_log("Exclude ID: " . ($excludeId ?? 'NULL'));
    error_log("Estados activos: " . implode(', ', $activeStatuses));
    error_log("SQL: $sql");
    error_log("Citas encontradas: $count");
    error_log("¿Hay conflicto?: " . ($count > 0 ? 'SÍ' : 'NO'));
    error_log("================================");

    return $count > 0;
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 citas-page">
    <section class="citas-hero mb-4">
        <div class="citas-hero-main">
            <span class="citas-eyebrow"><i class="bi bi-calendar-plus"></i> Nueva cita</span>
            <h1>Agendar cita</h1>
            <p>Programa la atencion del paciente con datos clinicos claros y validaciones inmediatas.</p>
        </div>
        <div class="citas-hero-actions">
            <a href="index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </section>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger shadow-sm">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card bg-dark border-secondary shadow-sm citas-form-card">
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate data-prevent-double>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Paciente *</label>
                        <select class="form-select" name="paciente_id" required>
                            <option value="">Selecciona un paciente</option>
                            <?php foreach ($pacientes as $paciente): ?>
                                <option value="<?= $paciente['id'] ?>" <?= isset($_POST['paciente_id']) && $_POST['paciente_id'] == $paciente['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($paciente['numero_documento'] . ' - ' . $paciente['nombre'] . ' ' . $paciente['apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">El paciente es obligatorio.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Odontólogo *</label>
                        <select class="form-select" name="odontologo_id" required>
                            <option value="">Selecciona un odontólogo</option>
                            <?php foreach ($odontologos as $odontologo): ?>
                                <option value="<?= $odontologo['id'] ?>" <?= isset($_POST['odontologo_id']) && $_POST['odontologo_id'] == $odontologo['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($odontologo['nombre'] . ' ' . $odontologo['apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">El odontólogo es obligatorio.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Fecha *</label>
                        <input type="date" class="form-control" name="fecha" value="<?= dateValue($fechaSugerida) ?>" required>
                        <div class="invalid-feedback">La fecha es obligatoria.</div>
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Hora inicio *</label>
                        <input type="time" class="form-control" name="hora_inicio" value="<?= oldValue('hora_inicio') ?>" required>
                        <div class="invalid-feedback">La hora es obligatoria.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Plan asociado</label>
                        <select class="form-select" name="plan_id">
                            <option value="">Sin plan</option>
                            <?php foreach ($planes as $plan): ?>
                                <option value="<?= $plan['id'] ?>" <?= isset($_POST['plan_id']) && $_POST['plan_id'] == $plan['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($plan['nombre_plan']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Sesión asociada</label>
                        <select class="form-select" name="sesion_id">
                            <option value="">Sin sesión</option>
                            <?php foreach ($sesiones as $sesion): ?>
                                <option value="<?= $sesion['id'] ?>" <?= isset($_POST['sesion_id']) && $_POST['sesion_id'] == $sesion['id'] ? 'selected' : '' ?>>
                                    Plan #<?= $sesion['plan_id'] ?> - Sesión <?= $sesion['numero_sesion'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Motivo</label>
                        <textarea class="form-control" name="motivo" rows="3"><?= oldValue('motivo') ?></textarea>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <a href="index.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                        Guardar cita
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
