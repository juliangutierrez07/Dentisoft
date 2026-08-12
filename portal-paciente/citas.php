<?php
/**
 * Portal del Paciente - Mis Citas
 * FASE 2: Módulo de citas del paciente
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/patient_portal.php';

requirePatientPasswordReady();

$pacienteSesion = currentPatient();
$alertaPortal = $_SESSION['alerta_portal_paciente'] ?? null;
unset($_SESSION['alerta_portal_paciente']);

$paciente = null;
$citasProximas = [];
$citasHistorial = [];
$error = '';

try {
    $db = getDB();
    
    // Get patient data
    $stmt = $db->prepare("
        SELECT p.id, p.nombre, p.apellido, p.estado
        FROM pacientes p
        INNER JOIN paciente_accesos pa ON pa.paciente_id = p.id
        WHERE pa.id = :acceso_id AND pa.paciente_id = :paciente_id
        LIMIT 1
    ");
    $stmt->execute([
        ':acceso_id' => $pacienteSesion['acceso_id'],
        ':paciente_id' => $pacienteSesion['id'],
    ]);
    $paciente = $stmt->fetch();
    
    if ($paciente) {
        // Get upcoming appointments
        $stmtProximas = $db->prepare("
            SELECT
                c.id,
                c.fecha,
                c.hora_inicio,
                c.hora_fin,
                c.motivo,
                c.estado,
                c.notas,
                u.nombre AS odontologo_nombre,
                u.apellido AS odontologo_apellido
            FROM citas c
            INNER JOIN usuarios u ON u.id = c.odontologo_id
            WHERE c.paciente_id = :paciente_id
                AND c.fecha >= CURDATE()
                AND c.estado IN ('pendiente', 'confirmada')
            ORDER BY c.fecha ASC, c.hora_inicio ASC
        ");
        $stmtProximas->execute([':paciente_id' => $paciente['id']]);
        $citasProximas = $stmtProximas->fetchAll();
        
        // Get appointment history
        $stmtHistorial = $db->prepare("
            SELECT
                c.id,
                c.fecha,
                c.hora_inicio,
                c.hora_fin,
                c.motivo,
                c.estado,
                c.notas,
                u.nombre AS odontologo_nombre,
                u.apellido AS odontologo_apellido
            FROM citas c
            INNER JOIN usuarios u ON u.id = c.odontologo_id
            WHERE c.paciente_id = :paciente_id
                AND (c.fecha < CURDATE() OR c.estado IN ('atendida', 'cancelada', 'no_asistio'))
            ORDER BY c.fecha DESC, c.hora_inicio DESC
            LIMIT 50
        ");
        $stmtHistorial->execute([':paciente_id' => $paciente['id']]);
        $citasHistorial = $stmtHistorial->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Portal paciente citas error: ' . $e->getMessage());
    $error = 'No fue posible cargar tus citas.';
}

function portalEsc(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function portalDate(?string $value, string $fallback = 'Sin registro'): string {
    if (!$value) {
        return $fallback;
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $fallback;
}

function portalTime(?string $value, string $fallback = 'Sin registro'): string {
    if (!$value) {
        return $fallback;
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('H:i', $timestamp) : $fallback;
}

function estadoCitaLabel(?string $estado): string {
    return match ($estado) {
        'pendiente' => 'Pendiente',
        'confirmada' => 'Confirmada',
        'atendida' => 'Atendida',
        'cancelada' => 'Cancelada',
        'no_asistio' => 'No asistió',
        default => 'Sin estado',
    };
}

function estadoCitaClass(?string $estado): string {
    return match ($estado) {
        'pendiente' => 'pending',
        'confirmada' => 'confirmed',
        'atendida' => 'completed',
        'cancelada' => 'cancelled',
        'no_asistio' => 'cancelled',
        default => '',
    };
}

$nombrePaciente = trim((string) (($paciente['nombre'] ?? '') . ' ' . ($paciente['apellido'] ?? '')));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Citas - Portal del Paciente | DentiSoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/portal-paciente-premium.css?v=<?= filemtime(__DIR__ . '/../assets/css/portal-paciente-premium.css') ?>" rel="stylesheet">
</head>
<body>
    <div class="portal-shell">
        <header class="portal-topbar">
            <a href="<?= BASE_URL ?>/portal-paciente.php" class="portal-brand">
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="DentiSoft">
                <span>DentiSoft</span>
            </a>
            <nav class="portal-nav">
                <a href="<?= BASE_URL ?>/portal-paciente.php" class="portal-nav-link">
                    <i class="bi bi-house"></i> Inicio
                </a>
                <a href="<?= BASE_URL ?>/portal-paciente/citas.php" class="portal-nav-link active">
                    <i class="bi bi-calendar3"></i> Mis Citas
                </a>
                <a href="<?= BASE_URL ?>/portal-paciente/facturas.php" class="portal-nav-link">
                    <i class="bi bi-receipt"></i> Mis Facturas
                </a>
                <a href="<?= BASE_URL ?>/portal-paciente/perfil.php" class="portal-nav-link">
                    <i class="bi bi-person"></i> Mi Perfil
                </a>
            </nav>
            <a href="<?= BASE_URL ?>/portal-logout.php" class="portal-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar sesión</span>
            </a>
        </header>

        <main class="portal-main">
            <?php if ($alertaPortal): ?>
                <div class="portal-alert success"><?= portalEsc($alertaPortal) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="portal-alert error"><?= portalEsc($error) ?></div>
            <?php endif; ?>

            <!-- Hero Section -->
            <section class="portal-hero">
                <div class="portal-hero-main">
                    <span class="portal-eyebrow"><i class="bi bi-calendar3"></i> Mis Citas</span>
                    <h1><?= portalEsc($nombrePaciente ?: 'Paciente') ?></h1>
                    <p>Consulta tus próximas citas y el historial de atendimientos.</p>
                </div>
            </section>

            <!-- Upcoming Appointments -->
            <section class="portal-content-section">
                <div class="portal-section-header">
                    <div>
                        <span class="portal-section-eyebrow"><i class="bi bi-calendar-check"></i> Próximas citas</span>
                        <h2>Citas programadas</h2>
                    </div>
                </div>
                <?php if (!empty($citasProximas)): ?>
                    <div class="portal-list">
                        <?php foreach ($citasProximas as $cita): ?>
                            <div class="portal-list-item">
                                <div class="portal-list-icon"><i class="bi bi-calendar-event"></i></div>
                                <div class="portal-list-content">
                                    <div class="portal-list-title"><?= portalEsc($cita['motivo'] ?: 'Consulta general') ?></div>
                                    <div class="portal-list-subtitle">
                                        Dr(a). <?= portalEsc($cita['odontologo_nombre'] . ' ' . $cita['odontologo_apellido']) ?>
                                    </div>
                                    <div class="portal-list-meta">
                                        <span class="mono"><i class="bi bi-calendar"></i> <?= portalDate($cita['fecha']) ?></span>
                                        <span><i class="bi bi-clock"></i> <?= portalTime($cita['hora_inicio']) ?> - <?= portalTime($cita['hora_fin']) ?></span>
                                        <span class="portal-list-badge <?= estadoCitaClass($cita['estado']) ?>">
                                            <?= portalEsc(estadoCitaLabel($cita['estado'])) ?>
                                        </span>
                                    </div>
                                    <?php if ($cita['notas']): ?>
                                        <div class="portal-list-subtitle" style="margin-top: 8px; color: var(--portal-muted); font-size: 0.85rem;">
                                            <i class="bi bi-chat-text"></i> <?= portalEsc($cita['notas']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="portal-empty">
                        <div class="portal-empty-icon"><i class="bi bi-calendar-x"></i></div>
                        <strong>No tienes citas programadas</strong>
                        <p>Contacta al consultorio para agendar tu próxima cita.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Appointment History -->
            <section class="portal-content-section">
                <div class="portal-section-header">
                    <div>
                        <span class="portal-section-eyebrow"><i class="bi bi-clock-history"></i> Historial</span>
                        <h2>Citas anteriores</h2>
                    </div>
                </div>
                <?php if (!empty($citasHistorial)): ?>
                    <div class="portal-list">
                        <?php foreach ($citasHistorial as $cita): ?>
                            <div class="portal-list-item">
                                <div class="portal-list-icon"><i class="bi bi-calendar-check"></i></div>
                                <div class="portal-list-content">
                                    <div class="portal-list-title"><?= portalEsc($cita['motivo'] ?: 'Consulta general') ?></div>
                                    <div class="portal-list-subtitle">
                                        Dr(a). <?= portalEsc($cita['odontologo_nombre'] . ' ' . $cita['odontologo_apellido']) ?>
                                    </div>
                                    <div class="portal-list-meta">
                                        <span class="mono"><i class="bi bi-calendar"></i> <?= portalDate($cita['fecha']) ?></span>
                                        <span><i class="bi bi-clock"></i> <?= portalTime($cita['hora_inicio']) ?> - <?= portalTime($cita['hora_fin']) ?></span>
                                        <span class="portal-list-badge <?= estadoCitaClass($cita['estado']) ?>">
                                            <?= portalEsc(estadoCitaLabel($cita['estado'])) ?>
                                        </span>
                                    </div>
                                    <?php if ($cita['notas']): ?>
                                        <div class="portal-list-subtitle" style="margin-top: 8px; color: var(--portal-muted); font-size: 0.85rem;">
                                            <i class="bi bi-chat-text"></i> <?= portalEsc($cita['notas']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="portal-empty">
                        <div class="portal-empty-icon"><i class="bi bi-clock-history"></i></div>
                        <strong>Sin historial de citas</strong>
                        <p>Aún no tienes citas registradas en el sistema.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
