<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('reportes.ver');

$paginaTitulo = 'Cuentas por Cobrar';
try {
    $db = getDB();
    $summaryStmt = $db->query("SELECT estado, COUNT(*) AS cantidad, SUM(saldo_pendiente) AS saldo_total FROM facturas WHERE estado IN ('pendiente','parcial','vencida') GROUP BY estado");
    $summary = $summaryStmt->fetchAll();

    $facturasStmt = $db->query("SELECT f.id, f.numero_factura, p.nombre, p.apellido, f.fecha_emision, f.total, f.total_pagado, f.saldo_pendiente, f.estado FROM facturas f JOIN pacientes p ON f.paciente_id = p.id WHERE f.estado IN ('pendiente','parcial','vencida') ORDER BY f.fecha_vencimiento ASC");
    $facturas = $facturasStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Reportes Cuentas por Cobrar error: ' . $e->getMessage());
    $summary = [];
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

$labels = array_map(fn($row) => ucfirst($row['estado']), $summary);
$values = array_map(fn($row) => (float) $row['saldo_total'], $summary);
$cssAdicional = 'reportes-premium.css';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="reports-page">
    <section class="reports-hero">
        <div>
            <span class="reports-kicker"><i class="bi bi-cash-coin"></i> Reportes financieros</span>
            <h1>Cuentas por cobrar</h1>
            <p>Consulta facturas con saldo pendiente y su distribucion por estado.</p>
        </div>
        <div class="reports-hero-actions">
            <a href="index.php" class="report-action"><i class="bi bi-arrow-left"></i><span>Volver</span></a>
        </div>
    </section>

    <section class="reports-tables-grid">
        <article class="report-panel">
            <header>
                <div><span>Distribucion</span><h2>Saldo por estado</h2></div>
                <i class="bi bi-pie-chart"></i>
            </header>
            <div class="chart-shell"><canvas id="cuentasCobrarChart"></canvas></div>
        </article>

        <article class="report-panel">
            <header>
                <div><span>Resumen</span><h2>Totales por estado</h2></div>
                <i class="bi bi-list-check"></i>
            </header>
            <?php if (empty($summary)): ?>
                <?= emptyState('No hay facturas pendientes por cobrar.') ?>
            <?php else: ?>
                <dl class="report-summary-list">
                    <?php foreach ($summary as $item): ?>
                        <div>
                            <dt><span class="report-badge badge-<?= esc(badgeClass($item['estado'])) ?>"><?= esc(ucfirst($item['estado'])) ?></span></dt>
                            <dd>$<?= number_format((float) $item['saldo_total'], 0, ',', '.') ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </article>
    </section>

    <article class="report-panel wide">
        <header>
            <div><span>Detalle</span><h2>Facturas pendientes</h2></div>
            <i class="bi bi-receipt-cutoff"></i>
        </header>
        <?php if (empty($facturas)): ?>
            <?= emptyState('No hay facturas pendientes.') ?>
        <?php else: ?>
            <div class="reports-table-wrap">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Paciente</th>
                            <th>Fecha emision</th>
                            <th class="num">Total</th>
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
        page: 'cuentas_cobrar',
        labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
        valores: <?= json_encode($values, JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<?php $jsAdicional = 'reportes.js'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
