<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('reportes.ver');

$paginaTitulo = 'Reportes de Ingresos';
$fechaInicio = trim($_GET['fecha_inicio'] ?? date('Y-m-01'));
$fechaFin = trim($_GET['fecha_fin'] ?? date('Y-m-d'));

$modelos = [];
try {
    $db = getDB();
    $ingresosStmt = $db->prepare("SELECT DATE_FORMAT(fecha_emision, '%Y-%m') AS mes, SUM(total) AS total FROM facturas WHERE estado != 'anulada' AND fecha_emision BETWEEN :inicio AND :fin GROUP BY mes ORDER BY mes");
    $ingresosStmt->execute([':inicio' => $fechaInicio, ':fin' => $fechaFin]);
    $ingresos = $ingresosStmt->fetchAll();

    $facturasStmt = $db->prepare("SELECT f.numero_factura, p.nombre, p.apellido, f.fecha_emision, f.total, f.total_pagado, f.saldo_pendiente, f.estado FROM facturas f JOIN pacientes p ON f.paciente_id = p.id WHERE f.fecha_emision BETWEEN :inicio AND :fin ORDER BY f.fecha_emision DESC");
    $facturasStmt->execute([':inicio' => $fechaInicio, ':fin' => $fechaFin]);
    $facturas = $facturasStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Reportes Ingresos error: ' . $e->getMessage());
    $ingresos = [];
    $facturas = [];
}

function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function emptyState(string $text): string {
    return '<div class="reports-empty"><i class="bi bi-inbox"></i><strong>Sin datos</strong><p>' . esc($text) . '</p></div>';
}

function badgeClass(string $estado): string {
    return match ($estado) {
        'pagada', 'atendida', 'completado', 'realizada', 'activo' => 'success',
        'parcial', 'confirmada', 'en_curso' => 'info',
        'pendiente' => 'warning',
        'vencida', 'cancelada', 'no_asistio', 'anulada' => 'danger',
        default => 'muted',
    };
}

$meses = array_map(fn($row) => $row['mes'], $ingresos);
$valores = array_map(fn($row) => (float) $row['total'], $ingresos);
$cssAdicional = 'reportes-premium.css';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="reports-page">
    <section class="reports-hero">
        <div>
            <span class="reports-kicker"><i class="bi bi-graph-up-arrow"></i> Reportes financieros</span>
            <h1>Ingresos</h1>
            <p>Analiza el comportamiento de los ingresos por periodos.</p>
        </div>
        <div class="reports-hero-actions">
            <a href="index.php" class="report-action"><i class="bi bi-arrow-left"></i><span>Volver</span></a>
        </div>
    </section>

    <section class="reports-filter-card">
        <form method="GET" action="ingresos.php" class="reports-filter-grid">
            <label><span>Fecha inicio</span><input type="date" name="fecha_inicio" value="<?= esc($fechaInicio) ?>"></label>
            <label><span>Fecha fin</span><input type="date" name="fecha_fin" value="<?= esc($fechaFin) ?>"></label>
            <button type="submit" class="report-action primary"><i class="bi bi-funnel"></i><span>Filtrar</span></button>
        </form>
    </section>

    <article class="report-panel">
        <header>
            <div><span>Tendencia</span><h2>Ingresos por mes</h2></div>
            <i class="bi bi-graph-up-arrow"></i>
        </header>
        <div class="chart-shell"><canvas id="ingresosChart"></canvas></div>
    </article>

    <article class="report-panel">
        <header>
            <div><span>Detalle</span><h2>Facturas en el periodo</h2></div>
            <i class="bi bi-receipt-cutoff"></i>
        </header>
        <p class="report-hint">Facturas listadas: <strong class="mono"><?= count($facturas) ?></strong> &middot; Periodo: <strong class="mono"><?= esc($fechaInicio) ?> &rarr; <?= esc($fechaFin) ?></strong></p>
        <?php if (empty($facturas)): ?>
            <?= emptyState('No hay facturas en este periodo.') ?>
        <?php else: ?>
            <div class="reports-table-wrap">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Paciente</th>
                            <th>Fecha</th>
                            <th class="num">Total</th>
                            <th class="num">Pagado</th>
                            <th class="num">Saldo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($facturas as $factura): ?>
                            <tr>
                                <td><?= esc($factura['numero_factura']) ?></td>
                                <td><?= esc($factura['nombre'] . ' ' . $factura['apellido']) ?></td>
                                <td class="mono"><?= esc(date('d/m/Y', strtotime($factura['fecha_emision']))) ?></td>
                                <td class="num mono">$<?= number_format((float) $factura['total'], 0, ',', '.') ?></td>
                                <td class="num mono">$<?= number_format((float) $factura['total_pagado'], 0, ',', '.') ?></td>
                                <td class="num mono">$<?= number_format((float) $factura['saldo_pendiente'], 0, ',', '.') ?></td>
                                <td><span class="report-badge badge-<?= esc(badgeClass($factura['estado'])) ?>"><?= esc(ucfirst($factura['estado'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>
</div>
<script>
    window.REPORTES_DATA = {
        page: 'ingresos',
        ingresos: {
            labels: <?= json_encode($meses, JSON_UNESCAPED_UNICODE) ?>,
            valores: <?= json_encode($valores, JSON_UNESCAPED_UNICODE) ?>
        }
    };
</script>
<?php $jsAdicional = 'reportes.js'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
