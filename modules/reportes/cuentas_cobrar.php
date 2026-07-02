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

$labels = array_map(fn($row) => ucfirst($row['estado']), $summary);
$values = array_map(fn($row) => (float) $row['saldo_total'], $summary);
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row align-items-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Cuentas por cobrar</h1>
            <p class="text-muted mb-0">Consulta facturas con saldo pendiente y su distribución por estado.</p>
        </div>
        <a href="index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card bg-dark border-secondary shadow-sm p-3 h-100">
                <h5 class="mb-3">Saldo por estado</h5>
                <canvas id="cuentasCobrarChart" height="220"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card bg-dark border-secondary shadow-sm p-3 h-100">
                <h5 class="mb-3">Resumen</h5>
                <?php if (empty($summary)): ?>
                    <p class="text-muted">No hay facturas pendientes por cobrar.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($summary as $item): ?>
                            <li class="list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center">
                                <span><?= esc(ucfirst($item['estado'])) ?></span>
                                <span>$<?= number_format($item['saldo_total'], 0, ',', '.') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card bg-dark border-secondary shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Facturas pendientes</h5>
            <div class="table-responsive">
                <table class="table table-dark table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Paciente</th>
                            <th>Fecha emisión</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Saldo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($facturas)): ?>
                            <tr><td colspan="6" class="text-center text-muted">No hay facturas pendientes.</td></tr>
                        <?php else: ?>
                            <?php foreach ($facturas as $factura): ?>
                                <tr>
                                    <td><?= esc($factura['numero_factura']) ?></td>
                                    <td><?= esc($factura['nombre'] . ' ' . $factura['apellido']) ?></td>
                                    <td><?= esc(date('d/m/Y', strtotime($factura['fecha_emision']))) ?></td>
                                    <td class="text-end">$<?= number_format($factura['total'], 0, ',', '.') ?></td>
                                    <td class="text-end">$<?= number_format($factura['saldo_pendiente'], 0, ',', '.') ?></td>
                                    <td><?= esc(ucfirst($factura['estado'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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