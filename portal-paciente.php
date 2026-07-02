<?php
/**
 * Portal del Paciente - Dashboard Principal
 * FASE 2: Dashboard con estadísticas y navegación
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/patient_portal.php';

requirePatientPasswordReady();

$pacienteSesion = currentPatient();
$alertaPortal = $_SESSION['alerta_portal_paciente'] ?? null;
unset($_SESSION['alerta_portal_paciente']);

$paciente = null;
$proximaCita = null;
$totalCitas = 0;
$totalFacturas = 0;
$ultimoTratamiento = null;
$error = '';

try {
    $db = getDB();
    
    // Get patient data
    $stmt = $db->prepare("
        SELECT
            p.id,
            p.numero_documento,
            p.nombre,
            p.apellido,
            p.telefono,
            p.email,
            p.estado,
            pa.estado AS cuenta_estado,
            pa.ultimo_acceso
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
        // Get next appointment
        $stmtCita = $db->prepare("
            SELECT
                c.id,
                c.fecha,
                c.hora_inicio,
                c.motivo,
                c.estado,
                u.nombre AS odontologo_nombre,
                u.apellido AS odontologo_apellido
            FROM citas c
            INNER JOIN usuarios u ON u.id = c.odontologo_id
            WHERE c.paciente_id = :paciente_id
                AND c.fecha >= CURDATE()
                AND c.estado IN ('pendiente', 'confirmada')
            ORDER BY c.fecha ASC, c.hora_inicio ASC
            LIMIT 1
        ");
        $stmtCita->execute([':paciente_id' => $paciente['id']]);
        $proximaCita = $stmtCita->fetch();
        
        // Get total appointments
        $stmtTotalCitas = $db->prepare("
            SELECT COUNT(*) FROM citas WHERE paciente_id = :paciente_id
        ");
        $stmtTotalCitas->execute([':paciente_id' => $paciente['id']]);
        $totalCitas = (int) $stmtTotalCitas->fetchColumn();
        
        // Get total invoices
        $stmtTotalFacturas = $db->prepare("
            SELECT COUNT(*) FROM facturas WHERE paciente_id = :paciente_id
        ");
        $stmtTotalFacturas->execute([':paciente_id' => $paciente['id']]);
        $totalFacturas = (int) $stmtTotalFacturas->fetchColumn();
        
        // Get last treatment
        $stmtTratamiento = $db->prepare("
            SELECT
                pt.id,
                pt.nombre_plan,
                pt.estado,
                pt.fecha_inicio,
                pt.costo_total
            FROM planes_tratamiento pt
            WHERE pt.paciente_id = :paciente_id
            ORDER BY pt.created_at DESC
            LIMIT 1
        ");
        $stmtTratamiento->execute([':paciente_id' => $paciente['id']]);
        $ultimoTratamiento = $stmtTratamiento->fetch();
    }
} catch (PDOException $e) {
    error_log('Portal paciente dashboard error: ' . $e->getMessage());
    $error = 'No fue posible cargar la información del dashboard.';
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

function portalDateTime(?string $value, string $fallback = 'Sin registro'): string {
    if (!$value) {
        return $fallback;
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $fallback;
}

function portalCurrency(?float $value): string {
    return '$' . number_format($value ?? 0, 0, ',', '.');
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

function estadoFacturaLabel(?string $estado): string {
    return match ($estado) {
        'pendiente' => 'Pendiente',
        'pagada' => 'Pagada',
        'parcial' => 'Parcial',
        'vencida' => 'Vencida',
        'anulada' => 'Anulada',
        default => 'Sin estado',
    };
}

function estadoTratamientoLabel(?string $estado): string {
    return match ($estado) {
        'pendiente' => 'Pendiente',
        'en_curso' => 'En curso',
        'completado' => 'Completado',
        'cancelado' => 'Cancelado',
        default => 'Sin estado',
    };
}

$nombrePaciente = trim((string) (($paciente['nombre'] ?? '') . ' ' . ($paciente['apellido'] ?? '')));
$cuentaEstado = (string) ($paciente['cuenta_estado'] ?? 'activo');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Portal del Paciente | DentiSoft</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/portal-paciente-premium.css" rel="stylesheet">
</head>
<body>
    <div class="portal-shell">
        <header class="portal-topbar">
            <a href="<?= BASE_URL ?>/portal-paciente.php" class="portal-brand">
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="DentiSoft">
                <span>DentiSoft</span>
            </a>
            <nav class="portal-nav">
                <a href="<?= BASE_URL ?>/portal-paciente.php" class="portal-nav-link active">
                    <i class="bi bi-house"></i> Inicio
                </a>
                <a href="<?= BASE_URL ?>/portal-paciente/citas.php" class="portal-nav-link">
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
                    <span class="portal-eyebrow"><i class="bi bi-person-check"></i> Bienvenido de nuevo</span>
                    <h1><?= portalEsc($nombrePaciente ?: 'Paciente') ?></h1>
                    <p>Gestiona tus citas, facturas y perfil desde un solo lugar.</p>
                </div>
                <div class="portal-hero-actions">
                    <span class="portal-status-badge <?= $cuentaEstado === 'activo' ? 'active' : 'inactive' ?>">
                        <i class="bi bi-shield-check"></i> Cuenta <?= $cuentaEstado === 'activo' ? 'Activa' : 'Inactiva' ?>
                    </span>
                </div>
            </section>

            <!-- Summary Cards -->
            <section class="portal-summary-grid">
                <article class="portal-summary-card appointments">
                    <div class="portal-summary-icon"><i class="bi bi-calendar-check"></i></div>
                    <div>
                        <span>Total citas</span>
                        <strong><?= portalEsc($totalCitas) ?></strong>
                    </div>
                </article>
                <article class="portal-summary-card invoices">
                    <div class="portal-summary-icon"><i class="bi bi-receipt-cutoff"></i></div>
                    <div>
                        <span>Total facturas</span>
                        <strong><?= portalEsc($totalFacturas) ?></strong>
                    </div>
                </article>
                <article class="portal-summary-card treatments">
                    <div class="portal-summary-icon"><i class="bi bi-clipboard2-pulse"></i></div>
                    <div>
                        <span>Último tratamiento</span>
                        <strong><?= $ultimoTratamiento ? portalEsc(estadoTratamientoLabel($ultimoTratamiento['estado'])) : 'Sin tratamientos' ?></strong>
                    </div>
                </article>
                <article class="portal-summary-card status">
                    <div class="portal-summary-icon"><i class="bi bi-activity"></i></div>
                    <div>
                        <span>Estado cuenta</span>
                        <strong><?= portalEsc(ucfirst($cuentaEstado)) ?></strong>
                    </div>
                </article>
            </section>

            <!-- Next Appointment -->
            <section class="portal-content-section">
                <div class="portal-section-header">
                    <div>
                        <span class="portal-section-eyebrow"><i class="bi bi-calendar-event"></i> Próxima cita</span>
                        <h2>Tu próxima cita programada</h2>
                    </div>
                    <?php if ($proximaCita): ?>
                        <a href="<?= BASE_URL ?>/portal-paciente/citas.php" class="portal-btn portal-btn-secondary">Ver todas</a>
                    <?php endif; ?>
                </div>
                <?php if ($proximaCita): ?>
                    <div class="portal-list">
                        <div class="portal-list-item">
                            <div class="portal-list-icon"><i class="bi bi-calendar3"></i></div>
                            <div class="portal-list-content">
                                <div class="portal-list-title"><?= portalEsc($proximaCita['motivo'] ?: 'Consulta general') ?></div>
                                <div class="portal-list-subtitle">
                                    Dr(a). <?= portalEsc($proximaCita['odontologo_nombre'] . ' ' . $proximaCita['odontologo_apellido']) ?>
                                </div>
                                <div class="portal-list-meta">
                                    <span><i class="bi bi-calendar"></i> <?= portalDate($proximaCita['fecha']) ?></span>
                                    <span><i class="bi bi-clock"></i> <?= portalDateTime($proximaCita['hora_inicio']) ?></span>
                                    <span class="portal-list-badge <?= $proximaCita['estado'] ?>">
                                        <?= portalEsc(estadoCitaLabel($proximaCita['estado'])) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="portal-empty">
                        <div class="portal-empty-icon"><i class="bi bi-calendar-x"></i></div>
                        <strong>No tienes citas programadas</strong>
                        <p>Contacta al consultorio para agendar tu próxima cita.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Last Treatment -->
            <?php if ($ultimoTratamiento): ?>
            <section class="portal-content-section">
                <div class="portal-section-header">
                    <div>
                        <span class="portal-section-eyebrow"><i class="bi bi-clipboard2-pulse"></i> Tratamiento activo</span>
                        <h2>Tu último tratamiento</h2>
                    </div>
                </div>
                <div class="portal-list">
                    <div class="portal-list-item">
                        <div class="portal-list-icon"><i class="bi bi-clipboard2"></i></div>
                        <div class="portal-list-content">
                            <div class="portal-list-title"><?= portalEsc($ultimoTratamiento['nombre_plan'] ?: 'Sin nombre') ?></div>
                            <div class="portal-list-subtitle">Costo total: <?= portalCurrency($ultimoTratamiento['costo_total']) ?></div>
                            <div class="portal-list-meta">
                                <span><i class="bi bi-calendar-plus"></i> Inicio: <?= portalDate($ultimoTratamiento['fecha_inicio']) ?></span>
                                <span class="portal-list-badge <?= $ultimoTratamiento['estado'] ?>">
                                    <?= portalEsc(estadoTratamientoLabel($ultimoTratamiento['estado'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
