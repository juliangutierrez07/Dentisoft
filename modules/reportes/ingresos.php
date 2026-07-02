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

$meses = array_map(fn($row) => $row['mes'], $ingresos);
$valores = array_map(fn($row) => (float) $row['total'], $ingresos);
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row align-items-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Ingresos</h1>
            <p class="text-muted mb-0">Analiza el comportamiento de los ingresos por periodos.</p>
        </div>
        <a href="index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <div class="card bg-dark border-secondary shadow-sm mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="GET" action="ingresos.php">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fecha inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?= esc($fechaInicio) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Fecha fin</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?= esc($fechaFin) ?>">
                </div>
                <div class="col-md-4 text-end">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card bg-dark border-secondary shadow-sm p-3">
                <h5 class="mb-3">Ingresos por mes</h5>
                <canvas id="ingresosChart" height="120"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card bg-dark border-secondary shadow-sm p-3 h-100">
                <h5 class="mb-3">Total facturas</h5>
                <p class="mb-2">Facturas listadas: <strong><?= count($facturas) ?></strong></p>
                <p class="mb-0">Periodo: <?= esc($fechaInicio) ?> → <?= esc($fechaFin) ?></p>
            </div>
        </div>
    </div>

    <div class="card bg-dark border-secondary shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Facturas en el periodo</h5>
            <div class="table-responsive">
                <table class="table table-dark table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Paciente</th>
                            <th>Fecha</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Saldo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($facturas)): ?>
                            <tr><td colspan="7" class="text-center text-muted">No hay facturas en este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach ($facturas as $factura): ?>
                                <tr>
                                    <td><?= esc($factura['numero_factura']) ?></td>
                                    <td><?= esc($factura['nombre'] . ' ' . $factura['apellido']) ?></td>
                                    <td><?= esc(date('d/m/Y', strtotime($factura['fecha_emision']))) ?></td>
                                    <td class="text-end">$<?= number_format($factura['total'], 0, ',', '.') ?></td>
                                    <td class="text-end">$<?= number_format($factura['total_pagado'], 0, ',', '.') ?></td>
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
        page: 'ingresos',
        ingresos: {
            labels: <?= json_encode($meses, JSON_UNESCAPED_UNICODE) ?>,
            valores: <?= json_encode($valores, JSON_UNESCAPED_UNICODE) ?>
        }
    };
</script>
<?php $jsAdicional = 'reportes.js'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>