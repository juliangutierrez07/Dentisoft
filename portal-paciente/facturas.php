<?php
/**
 * Portal del Paciente - Mis Facturas
 * FASE 2: Módulo de facturas del paciente
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
$facturas = [];
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
        // Get invoices
        $stmtFacturas = $db->prepare("
            SELECT
                f.id,
                f.numero_factura,
                f.fecha_emision,
                f.fecha_vencimiento,
                f.subtotal,
                f.descuento,
                f.iva,
                f.total,
                f.total_pagado,
                f.saldo_pendiente,
                f.estado,
                f.notas,
                u.nombre AS odontologo_nombre,
                u.apellido AS odontologo_apellido
            FROM facturas f
            INNER JOIN usuarios u ON u.id = f.odontologo_id
            WHERE f.paciente_id = :paciente_id
            ORDER BY f.fecha_emision DESC
            LIMIT 50
        ");
        $stmtFacturas->execute([':paciente_id' => $paciente['id']]);
        $facturas = $stmtFacturas->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Portal paciente facturas error: ' . $e->getMessage());
    $error = 'No fue posible cargar tus facturas.';
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

function portalCurrency(?float $value): string {
    return '$' . number_format($value ?? 0, 0, ',', '.');
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

function estadoFacturaClass(?string $estado): string {
    return match ($estado) {
        'pendiente' => 'unpaid',
        'pagada' => 'paid',
        'parcial' => 'partial',
        'vencida' => 'unpaid',
        'anulada' => 'cancelled',
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
    <title>Mis Facturas - Portal del Paciente | DentiSoft</title>
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
                <a href="<?= BASE_URL ?>/portal-paciente/citas.php" class="portal-nav-link">
                    <i class="bi bi-calendar3"></i> Mis Citas
                </a>
                <a href="<?= BASE_URL ?>/portal-paciente/facturas.php" class="portal-nav-link active">
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
                    <span class="portal-eyebrow"><i class="bi bi-receipt"></i> Mis Facturas</span>
                    <h1><?= portalEsc($nombrePaciente ?: 'Paciente') ?></h1>
                    <p>Consulta el estado de tus facturas y pagos.</p>
                </div>
            </section>

            <!-- Invoices List -->
            <section class="portal-content-section">
                <div class="portal-section-header">
                    <div>
                        <span class="portal-section-eyebrow"><i class="bi bi-file-earmark-text"></i> Facturas</span>
                        <h2>Listado de facturas</h2>
                    </div>
                </div>
                <?php if (!empty($facturas)): ?>
                    <div class="portal-list">
                        <?php foreach ($facturas as $factura): ?>
                            <div class="portal-list-item">
                                <div class="portal-list-icon"><i class="bi bi-receipt-cutoff"></i></div>
                                <div class="portal-list-content">
                                    <div class="portal-list-title">Factura #<?= portalEsc($factura['numero_factura']) ?></div>
                                    <div class="portal-list-subtitle">
                                        Dr(a). <?= portalEsc($factura['odontologo_nombre'] . ' ' . $factura['odontologo_apellido']) ?>
                                    </div>
                                    <div class="portal-list-meta">
                                        <span class="mono"><i class="bi bi-calendar"></i> <?= portalDate($factura['fecha_emision']) ?></span>
                                        <span class="mono"><i class="bi bi-cash-coin"></i> <?= portalCurrency($factura['total']) ?></span>
                                        <span class="portal-list-badge <?= estadoFacturaClass($factura['estado']) ?>">
                                            <?= portalEsc(estadoFacturaLabel($factura['estado'])) ?>
                                        </span>
                                    </div>
                                    <div class="portal-list-meta" style="margin-top: 8px;">
                                        <span class="mono"><i class="bi bi-cash"></i> Pagado: <?= portalCurrency($factura['total_pagado']) ?></span>
                                        <span class="mono"><i class="bi bi-exclamation-circle"></i> Saldo: <?= portalCurrency($factura['saldo_pendiente']) ?></span>
                                        <?php if ($factura['fecha_vencimiento']): ?>
                                            <span class="mono"><i class="bi bi-calendar-x"></i> Vence: <?= portalDate($factura['fecha_vencimiento']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($factura['notas']): ?>
                                        <div class="portal-list-subtitle" style="margin-top: 8px; color: var(--portal-muted); font-size: 0.85rem;">
                                            <i class="bi bi-chat-text"></i> <?= portalEsc($factura['notas']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="portal-list-actions">
                                    <a href="<?= BASE_URL ?>/portal-paciente/descargar-factura.php?id=<?= $factura['id'] ?>" class="portal-action-btn portal-action-download" target="_blank" rel="noopener noreferrer" data-invoice-id="<?= $factura['id'] ?>">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                        <span>Descargar PDF</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="portal-empty">
                        <div class="portal-empty-icon"><i class="bi bi-receipt-x"></i></div>
                        <strong>No tienes facturas registradas</strong>
                        <p>Aún no se han generado facturas a tu nombre.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        // Loading states for PDF download buttons
        document.querySelectorAll('.portal-action-download').forEach(button => {
            button.addEventListener('click', function(e) {
                const icon = this.querySelector('i');
                const span = this.querySelector('span');
                const originalIconClass = icon.className;
                const originalText = span.textContent;
                
                // Show loading state
                icon.className = 'bi bi-arrow-repeat';
                icon.style.animation = 'spin 1s linear infinite';
                span.textContent = 'Generando...';
                this.style.pointerEvents = 'none';
                this.style.opacity = '0.7';
                
                // Reset after a short delay (PDF will open in new tab)
                setTimeout(() => {
                    icon.className = originalIconClass;
                    icon.style.animation = '';
                    span.textContent = originalText;
                    this.style.pointerEvents = '';
                    this.style.opacity = '';
                }, 3000);
            });
        });

        // Add spin animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
