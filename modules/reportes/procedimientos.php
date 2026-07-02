<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('reportes.ver');

$paginaTitulo = 'Procedimientos';
try {
    $db = getDB();
    $stmt = $db->query("SELECT IFNULL(df.descripcion, 'Sin descripción') AS descripcion, SUM(df.subtotal) AS total, SUM(df.cantidad) AS cantidad FROM detalle_facturas df JOIN facturas f ON df.factura_id = f.id WHERE f.estado != 'anulada' GROUP BY df.descripcion ORDER BY total DESC LIMIT 10");
    $procedimientos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Reportes Procedimientos error: ' . $e->getMessage());
    $procedimientos = [];
}

function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$labels = array_map(fn($row) => $row['descripcion'], $procedimientos);
$values = array_map(fn($row) => (float) $row['total'], $procedimientos);
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row align-items-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Procedimientos</h1>
            <p class="text-muted mb-0">Analiza los procedimientos con mayores ingresos facturados.</p>
        </div>
        <a href="index.php" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    <div class="card bg-dark border-secondary shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3">Distribución de procedimientos</h5>
            <canvas id="procedimientosChart" height="140"></canvas>
        </div>
    </div>

    <div class="card bg-dark border-secondary shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Top procedimientos</h5>
            <div class="table-responsive">
                <table class="table table-dark table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Procedimiento</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">Total facturado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($procedimientos)): ?>
                            <tr><td colspan="3" class="text-center text-muted">No hay procedimientos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($procedimientos as $procedimiento): ?>
                                <tr>
                                    <td><?= esc($procedimiento['descripcion']) ?></td>
                                    <td class="text-end"><?= number_format($procedimiento['cantidad'], 0, ',', '.') ?></td>
                                    <td class="text-end">$<?= number_format($procedimiento['total'], 0, ',', '.') ?></td>
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
        page: 'procedimientos',
        labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
        valores: <?= json_encode($values, JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<?php $jsAdicional = 'reportes.js'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>