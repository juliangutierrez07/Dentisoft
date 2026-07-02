<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/patient_portal.php';
requirePermission('pacientes.ver');

$paginaTitulo = 'Ver Paciente';
$cssAdicional = 'pacientes-premium.css';
$usuarioActual = currentUser();
$puedeEliminarPacientes = can('pacientes.eliminar');
$pacienteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$pacienteId) {
    setAlerta('Paciente no válido.', 'danger');
    header('Location: index.php');
    exit;
}

$pageError = null;
$paciente = [];
$citasRecientes = [];
$nextCita = null;
$stats = [];

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT
            p.*,
            pa.id AS portal_acceso_id,
            pa.usuario_documento AS portal_usuario_documento,
            pa.estado AS portal_estado,
            pa.debe_cambiar_password AS portal_debe_cambiar_password,
            pa.ultimo_acceso AS portal_ultimo_acceso,
            pa.created_at AS portal_created_at
        FROM pacientes p
        LEFT JOIN paciente_accesos pa ON pa.paciente_id = p.id
        WHERE p.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $pacienteId]);
    $paciente = $stmt->fetch();

    if (!$paciente) {
        setAlerta('Paciente no encontrado.', 'danger');
        header('Location: index.php');
        exit;
    }

    $citasRecientesStmt = $db->prepare("SELECT fecha, hora_inicio, estado, motivo FROM citas WHERE paciente_id = :id ORDER BY fecha DESC, hora_inicio DESC LIMIT 4");
    $citasRecientesStmt->execute([':id' => $pacienteId]);
    $citasRecientes = $citasRecientesStmt->fetchAll();

    $nextCitaStmt = $db->prepare("SELECT fecha, hora_inicio, estado, motivo FROM citas WHERE paciente_id = :id AND fecha >= CURDATE() ORDER BY fecha ASC, hora_inicio ASC LIMIT 1");
    $nextCitaStmt->execute([':id' => $pacienteId]);
    $nextCita = $nextCitaStmt->fetch();

    $statsStmt = $db->prepare("SELECT
            (SELECT COUNT(*) FROM citas WHERE paciente_id = :id_citas) AS total_citas,
            (SELECT COUNT(*) FROM historias_clinicas WHERE paciente_id = :id_historias) AS historias_clinicas,
            (SELECT COUNT(*) FROM planes_tratamiento WHERE paciente_id = :id_planes) AS tratamientos_activos");
    $statsStmt->execute([
        ':id_citas' => $pacienteId,
        ':id_historias' => $pacienteId,
        ':id_planes' => $pacienteId,
    ]);
    $stats = $statsStmt->fetch() ?: [];
} catch (PDOException $e) {
    error_log(sprintf('Pacientes Ver carga error [%s]: %s', $e->getCode(), $e->getMessage()));
    $pageError = 'No se pudieron cargar los datos del paciente. Intenta recargar la página o vuelve a la lista.';
    $paciente = [];
    $citasRecientes = [];
    $nextCita = null;
    $stats = [];
}

function h(string $value = ''): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function emptyText($value): string {
    $value = trim((string) $value);
    return $value === '' ? 'No registrado' : h($value);
}

function formatDate(string $value = null, string $fallback = 'No registrado'): string {
    if (!$value) {
        return $fallback;
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $fallback;
}

function calculateAge(?string $birthDate): string {
    if (!$birthDate) {
        return 'Edad no registrada';
    }
    try {
        $birth = new DateTime($birthDate);
        $diff = $birth->diff(new DateTime());
        return $diff->y . ' años';
    } catch (Exception $e) {
        return 'Edad no registrada';
    }
}

function getInitials(string $first, string $last): string {
    return strtoupper(mb_substr(trim($first), 0, 1) . mb_substr(trim($last), 0, 1));
}

$pacienteNombre = trim(($paciente['nombre'] ?? '') . ' ' . ($paciente['apellido'] ?? ''));
$pacienteInitials = getInitials($paciente['nombre'] ?? '', $paciente['apellido'] ?? '');
$edad = calculateAge($paciente['fecha_nacimiento'] ?? '');
$registroFecha = formatDate($paciente['created_at'] ?? $paciente['fecha_registro'] ?? null, 'No registrado');
$estadoOpciones = [
    'activo' => 'Activo',
    'inactivo' => 'Inactivo',
    'suspendido' => 'Suspendido',
];
$estadoActual = strtolower(trim((string) ($paciente['estado'] ?? 'activo')));
if (!array_key_exists($estadoActual, $estadoOpciones)) {
    $estadoActual = 'activo';
}
$estadoPaciente = $estadoOpciones[$estadoActual];
$pacienteActivo = $estadoActual === 'activo';
$tipoSangre = trim($paciente['grupo_sanguineo'] ?? '') ?: 'N/D';
$estadoCivil = trim($paciente['estado_civil'] ?? '') ?: 'No registrado';
$direccion = trim($paciente['direccion'] ?? '') ?: 'No registrado';
$ciudad = trim($paciente['ciudad'] ?? '') ?: 'No registrado';
$tipoAfiliacion = trim($paciente['tipo_afiliacion'] ?? '') ?: 'No registrado';
$responsable = trim($paciente['responsable'] ?? '') ?: 'No registrado';
$proximaCitaLabel = $nextCita ? (h(date('d/m/Y', strtotime($nextCita['fecha']))) . ' • ' . h(substr($nextCita['hora_inicio'], 0, 5))) : 'No agendada';
$ultimaCita = $citasRecientes[0] ?? null;
$ultimaCitaLabel = $ultimaCita ? (h(date('d/m/Y', strtotime($ultimaCita['fecha']))) . ' • ' . h(substr($ultimaCita['hora_inicio'], 0, 5))) : 'Sin registros';
$estadisticas = [
    'total_citas' => (int) ($stats['total_citas'] ?? 0),
    'tratamientos_activos' => (int) ($stats['tratamientos_activos'] ?? 0),
    'historias_clinicas' => (int) ($stats['historias_clinicas'] ?? 0),
];
$portalTieneAcceso = !empty($paciente['portal_acceso_id']);
$portalEstado = $paciente['portal_estado'] ?? null;
$portalEstadoLabel = estadoCuentaPacienteLabel($portalEstado);
$portalDebeCambiar = $portalTieneAcceso && (int) ($paciente['portal_debe_cambiar_password'] ?? 0) === 1;
$portalCreado = formatDate($paciente['portal_created_at'] ?? null, 'Sin registro');
$portalUltimoAcceso = formatDate($paciente['portal_ultimo_acceso'] ?? null, 'Nunca');
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 pacientes-page">
    <?php if ($pageError): ?>
        <section class="patient-error-shell">
            <div class="patient-error-card">
                <div class="patient-error-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
                <div>
                    <h2>Error al cargar el paciente</h2>
                    <p><?= h($pageError) ?></p>
                    <div class="patient-error-actions">
                        <a href="ver.php?id=<?= $pacienteId ?>" class="btn patient-btn-primary">Reintentar</a>
                        <a href="index.php" class="btn patient-btn-outline">Volver a la lista</a>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="patient-hero-card">
        <div class="patient-hero-body">
            <div class="patient-hero-profile">
                <div class="patient-avatar patient-avatar-xl"><?= h($pacienteInitials ?: 'PT') ?></div>
                <div class="patient-hero-info">
                    <span class="patient-kicker">Perfil del paciente</span>
                    <h1><?= h($pacienteNombre ?: 'Paciente sin nombre') ?></h1>
                    <p class="patient-description">Registro premium para seguimiento clínico, odontológico y administrativo.</p>

                    <div class="patient-hero-tags">
                        <span class="patient-chip"><i class="bi bi-person-badge"></i><?= h($paciente['tipo_documento'] ?? '-') ?> <?= h($paciente['numero_documento'] ?? '-') ?></span>
                        <span class="patient-chip"><i class="bi bi-gender-ambiguous"></i><?= h($paciente['genero'] ?? 'No definido') ?></span>
                        <span class="patient-chip"><i class="bi bi-droplet-half"></i><?= h($tipoSangre) ?></span>
                    </div>

                    <div class="patient-hero-stats">
                        <div>
                            <small>Edad</small>
                            <strong><?= h($edad) ?></strong>
                        </div>
                        <div>
                            <small>Estado</small>
                            <strong><?= h($estadoPaciente) ?></strong>
                        </div>
                        <div>
                            <small>Registrado</small>
                            <strong><?= h($registroFecha) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="patient-hero-counters">
                <article class="patient-hero-counter patient-counter-accent">
                    <span>Última cita</span>
                    <strong><?= $ultimaCitaLabel ?></strong>
                </article>
                <article class="patient-hero-counter patient-counter-accent-secondary">
                    <span>Próxima cita</span>
                    <strong><?= h($proximaCitaLabel) ?></strong>
                </article>
                <article class="patient-hero-counter">
                    <span>Tratamientos activos</span>
                    <strong><?= h((string) $estadisticas['tratamientos_activos']) ?></strong>
                </article>
                <article class="patient-hero-counter">
                    <span>Historias clínicas</span>
                    <strong><?= h((string) $estadisticas['historias_clinicas']) ?></strong>
                </article>
            </div>
        </div>

        <div class="patient-hero-actions">
            <a href="index.php" class="btn patient-btn-outline"><i class="bi bi-arrow-left"></i>Volver</a>
            <?php if (can('pacientes.editar')): ?>
                <a href="editar.php?id=<?= $pacienteId ?>" class="btn patient-btn-ghost"><i class="bi bi-pencil-square"></i>Editar</a>
                <a href="eliminar.php?id=<?= $pacienteId ?>&accion=inactivar" class="btn patient-btn-warning"><i class="bi bi-person-x"></i>Inactivar</a>
            <?php endif; ?>
            <?php if ($puedeEliminarPacientes): ?>
                <a href="eliminar.php?id=<?= $pacienteId ?>&accion=eliminar" class="btn patient-btn-danger"><i class="bi bi-trash3"></i>Eliminar</a>
            <?php endif; ?>
            <?php if ($pacienteActivo && can('citas.crear')): ?>
                <a href="../citas/crear.php?paciente_id=<?= $pacienteId ?>" class="btn patient-btn-primary"><i class="bi bi-calendar-plus"></i> Nueva cita</a>
            <?php endif; ?>
            <?php if (can('historias.crear')): ?>
                <a href="../historia_clinica/crear.php?paciente_id=<?= $pacienteId ?>" class="btn patient-btn-primary"><i class="bi bi-journal-medical"></i> Nueva historia</a>
            <?php endif; ?>
            <?php if (can('historias.ver')): ?>
                <a href="../historia_clinica/index.php" class="btn patient-btn-secondary"><i class="bi bi-grid-3x3-gap"></i> Ver odontograma</a>
            <?php endif; ?>
            <a href="javascript:window.print()" class="btn patient-btn-gradient"><i class="bi bi-download"></i> Descargar PDF</a>
        </div>
    </section>

    <div class="patient-grid-layout">
        <main class="patient-main-column">
            <section class="patient-cards-row">
                <article class="patient-metric-card">
                    <div>
                        <span>Total citas</span>
                        <strong><?= h((string) $estadisticas['total_citas']) ?></strong>
                    </div>
                    <div class="patient-metric-icon"><i class="bi bi-calendar-check"></i></div>
                </article>
                <article class="patient-metric-card">
                    <div>
                        <span>Tratamientos</span>
                        <strong><?= h((string) $estadisticas['tratamientos_activos']) ?></strong>
                    </div>
                    <div class="patient-metric-icon"><i class="bi bi-activity"></i></div>
                </article>
                <article class="patient-metric-card">
                    <div>
                        <span>Historias</span>
                        <strong><?= h((string) $estadisticas['historias_clinicas']) ?></strong>
                    </div>
                    <div class="patient-metric-icon"><i class="bi bi-file-earmark-medical"></i></div>
                </article>
                <article class="patient-metric-card">
                    <div>
                        <span>Alertas</span>
                        <strong><?= h(!empty($paciente['alergias'] ?? '') ? 'Activa' : 'Ninguna') ?></strong>
                    </div>
                    <div class="patient-metric-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                </article>
            </section>

            <section class="patient-info-grid">
                <article class="patient-info-card patient-info-card-alt">
                    <div class="patient-card-label"><i class="bi bi-person-circle"></i>Datos personales</div>
                    <ul>
                        <li><strong><?= h($pacienteNombre) ?></strong><span>Nombre completo</span></li>
                        <li><strong><?= h($paciente['numero_documento'] ?? '-') ?></strong><span>Documento</span></li>
                        <li><strong><?= h(!empty($paciente['fecha_nacimiento'] ?? '') ? date('d/m/Y', strtotime($paciente['fecha_nacimiento'])) : '-') ?></strong><span>Fecha de nacimiento</span></li>
                        <li><strong><?= h($edad) ?></strong><span>Edad</span></li>
                        <li><strong><?= h($paciente['genero'] ?? '-') ?></strong><span>Género</span></li>
                        <li><strong><?= h($estadoCivil) ?></strong><span>Estado civil</span></li>
                    </ul>
                </article>

                <article class="patient-info-card">
                    <div class="patient-card-label"><i class="bi bi-telephone-fill"></i>Contacto</div>
                    <ul>
                        <li><strong><?= h($paciente['telefono'] ?? '-') ?></strong><span>Teléfono</span></li>
                        <li><strong><?= h($paciente['email'] ?? '-') ?></strong><span>Correo</span></li>
                        <li><strong><?= h($direccion) ?></strong><span>Dirección</span></li>
                        <li><strong><?= h($ciudad) ?></strong><span>Ciudad</span></li>
                    </ul>
                </article>

                <article class="patient-info-card patient-info-card-alt">
                    <div class="patient-card-label"><i class="bi bi-briefcase-medical"></i>Afiliación</div>
                    <ul>
                        <li><strong><?= h($paciente['eps'] ?? '-') ?></strong><span>EPS</span></li>
                        <li><strong><?= h($tipoAfiliacion) ?></strong><span>Tipo afiliación</span></li>
                        <li><strong><?= h($paciente['responsable'] ?? '-') ?></strong><span>Responsable</span></li>
                    </ul>
                </article>

                <article class="patient-info-card">
                    <div class="patient-card-label"><i class="bi bi-heart-pulse"></i>Informacion médica</div>
                    <ul>
                        <li><strong><?= h($tipoSangre) ?></strong><span>Tipo de sangre</span></li>
                        <li><strong><?= h($paciente['alergias'] ?? '-') ?></strong><span>Alergias</span></li>
                        <li><strong><?= h($paciente['enfermedades'] ?? '-') ?></strong><span>Enfermedades</span></li>
                        <li><strong><?= h($paciente['medicamentos'] ?? '-') ?></strong><span>Medicamentos</span></li>
                    </ul>
                </article>

                <article class="patient-info-card patient-info-card-alt">
                    <div class="patient-card-label"><i class="bi bi-file-medical-fill"></i>Informacion odontológica</div>
                    <ul>
                        <li><strong><?= $ultimaCitaLabel ?></strong><span>Última cita</span></li>
                        <li><strong><?= h($nextCita ? 'Agendada' : 'Sin cita') ?></strong><span>Próxima cita</span></li>
                        <li><strong><?= h($paciente['riesgos'] ?? '-') ?></strong><span>Riesgos</span></li>
                        <li><strong><?= h($paciente['observaciones'] ?? '-') ?></strong><span>Observaciones</span></li>
                    </ul>
                </article>
            </section>

            <section class="patient-timeline-card">
                <div class="patient-section-header">
                    <div>
                        <span>Timeline Médico</span>
                        <h2>Actividad clínica reciente</h2>
                    </div>
                </div>

                <div class="patient-timeline-list">
                    <article class="timeline-item">
                        <span class="timeline-step"></span>
                        <div>
                            <h3>Registro del paciente</h3>
                            <p>Perfil creado el <strong><?= h(formatDate($paciente['created_at'] ?? null, 'No registrado')) ?></strong>.</p>
                        </div>
                    </article>
                    <?php if ($ultimaCita): ?>
                        <article class="timeline-item">
                            <span class="timeline-step timeline-step-accent"></span>
                            <div>
                                <h3>Última cita registrada</h3>
                                <p><?= h(date('d/m/Y', strtotime($ultimaCita['fecha']))) ?> a las <?= h(substr($ultimaCita['hora_inicio'], 0, 5)) ?> — <?= h($ultimaCita['motivo'] ?? 'Sin motivo registrado') ?>.</p>
                            </div>
                        </article>
                    <?php endif; ?>
                    <?php if ($nextCita): ?>
                        <article class="timeline-item">
                            <span class="timeline-step timeline-step-primary"></span>
                            <div>
                                <h3>Próxima cita</h3>
                                <p>Programada para el <strong><?= h(date('d/m/Y', strtotime($nextCita['fecha']))) ?></strong> a las <?= h(substr($nextCita['hora_inicio'], 0, 5)) ?>.</p>
                            </div>
                        </article>
                    <?php endif; ?>
                    <article class="timeline-item">
                        <span class="timeline-step"></span>
                        <div>
                            <h3>Riesgos médicos</h3>
                            <p><?= h($paciente['alergias'] ?? 'Ninguna alergia registrada') ?> / <?= h($paciente['enfermedades'] ?? 'Sin enfermedades registradas') ?>.</p>
                        </div>
                    </article>
                </div>
            </section>
        </main>

        <aside class="patient-side-panel">
            <section class="patient-panel patient-panel-highlight">
                <div class="patient-panel-header">
                    <span>Estado del paciente</span>
                    <strong><?= h($estadoPaciente) ?></strong>
                </div>
                <div class="patient-panel-badges">
                    <span class="status-badge status-badge-<?= h($estadoActual) ?>"><?= h($estadoPaciente) ?></span>
                    <span class="status-badge status-badge-soft"><?= h($tipoSangre) ?></span>
                    <span class="status-badge status-badge-soft"><?= h($paciente['tipo_afiliacion'] ?? 'Sin afiliación') ?></span>
                </div>
                <div class="patient-panel-list">
                    <div><span>Próxima cita</span><strong><?= h($proximaCitaLabel) ?></strong></div>
                    <div><span>Total citas</span><strong><?= h((string) $estadisticas['total_citas']) ?></strong></div>
                    <div><span>Tratamientos</span><strong><?= h((string) $estadisticas['tratamientos_activos']) ?></strong></div>
                    <div><span>Historias clínicas</span><strong><?= h((string) $estadisticas['historias_clinicas']) ?></strong></div>
                </div>
            </section>

            <section class="patient-panel">
                <div class="patient-panel-header">
                    <span>Portal del paciente</span>
                    <strong><?= h($portalTieneAcceso ? 'Con acceso' : 'Sin acceso') ?></strong>
                </div>
                <div class="patient-panel-badges">
                    <span class="status-badge status-badge-<?= h($portalTieneAcceso ? 'activo' : 'inactivo') ?>"><?= h($portalTieneAcceso ? 'Acceso activo' : 'Sin cuenta') ?></span>
                    <span class="status-badge status-badge-soft"><?= h($portalEstadoLabel) ?></span>
                </div>
                <div class="patient-panel-list">
                    <div><span>Usuario</span><strong><?= h($paciente['portal_usuario_documento'] ?? $paciente['numero_documento'] ?? '-') ?></strong></div>
                    <div><span>Cuenta creada</span><strong><?= h($portalCreado) ?></strong></div>
                    <div><span>Ultimo acceso</span><strong><?= h($portalUltimoAcceso) ?></strong></div>
                    <div><span>Cambio de contrasena</span><strong><?= h($portalDebeCambiar ? 'Pendiente' : 'No requerido') ?></strong></div>
                </div>
            </section>

            <section class="patient-panel">
                <div class="patient-panel-header">
                    <span>Indicadores clínicos</span>
                    <strong>Insights rápidos</strong>
                </div>
                <div class="indicator-row">
                    <div>
                        <span>Citas totales</span>
                        <strong><?= h((string) $estadisticas['total_citas']) ?></strong>
                    </div>
                    <div>
                        <span>Historias</span>
                        <strong><?= h((string) $estadisticas['historias_clinicas']) ?></strong>
                    </div>
                    <div>
                        <span>Tratamientos</span>
                        <strong><?= h((string) $estadisticas['tratamientos_activos']) ?></strong>
                    </div>
                </div>
                <div class="progress-block">
                    <span>Progreso seguimiento</span>
                    <div class="progress progress-pill">
                        <div class="progress-bar" role="progressbar" style="width: <?= min(100, max(10, (int) $estadisticas['total_citas'] * 5)) ?>%;"></div>
                    </div>
                </div>
            </section>

            <section class="patient-panel patient-panel-fullglass">
                <div class="patient-panel-header">
                    <span>Riesgos y alertas</span>
                    <strong>Gestión clínica</strong>
                </div>
                <ul class="alert-list">
                    <li><span>Alergias</span><strong><?= h($paciente['alergias'] ?? 'No registradas') ?></strong></li>
                    <li><span>Enfermedades</span><strong><?= h($paciente['enfermedades'] ?? 'No registradas') ?></strong></li>
                    <li><span>Medicamentos</span><strong><?= h($paciente['medicamentos'] ?? 'No registrados') ?></strong></li>
                    <li><span>Observaciones</span><strong><?= h($paciente['observaciones'] ?? 'Sin notas') ?></strong></li>
                </ul>
            </section>
        </aside>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
