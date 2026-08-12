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

function emptyState(string $text): string {
    return '<div class="reports-empty"><i class="bi bi-inbox"></i><strong>Sin datos</strong><p>' . esc($text) . '</p></div>';
}

$labels = array_map(fn($row) => $row['descripcion'], $procedimientos);
$values = array_map(fn($row) => (float) $row['total'], $procedimientos);
$cssAdicional = 'reportes-premium.css';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="reports-page">
    <section class="reports-hero">
        <div>
            <span class="reports-kicker"><i class="bi bi-clipboard2-pulse"></i> Reportes clinicos</span>
            <h1>Procedimientos</h1>
            <p>Analiza los procedimientos con mayores ingresos facturados.</p>
        </div>
        <div class="reports-hero-actions">
            <a href="index.php" class="report-action"><i class="bi bi-arrow-left"></i><span>Volver</span></a>
        </div>
    </section>

    <article class="report-panel">
        <header>
            <div><span>Distribucion</span><h2>Procedimientos mas facturados</h2></div>
            <i class="bi bi-bar-chart"></i>
        </header>
        <div class="chart-shell"><canvas id="procedimientosChart"></canvas></div>
    </article>

    <article class="report-panel">
        <header>
            <div><span>Ranking</span><h2>Top procedimientos</h2></div>
            <i class="bi bi-trophy"></i>
        </header>
        <?php if (empty($procedimientos)): ?>
            <?= emptyState('No hay procedimientos registrados.') ?>
        <?php else: ?>
            <div class="reports-table-wrap">
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Procedimiento</th>
                            <th class="num">Cantidad</th>
                            <th class="num">Total facturado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedimientos as $procedimiento): ?>
                            <tr>
                                <td><?= esc($procedimiento['descripcion']) ?></td>
                                <td class="num mono"><?= number_format((float) $procedimiento['cantidad'], 0, ',', '.') ?></td>
                                <td class="num mono">$<?= number_format((float) $procedimiento['total'], 0, ',', '.') ?></td>
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
        page: 'procedimientos',
        labels: <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
        valores: <?= json_encode($values, JSON_UNESCAPED_UNICODE) ?>
    };
</script>
<?php $jsAdicional = 'reportes.js'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
