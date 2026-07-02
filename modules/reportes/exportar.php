<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('reportes.ver');

$paginaTitulo = 'Exportar Reportes';
$cssAdicional = 'reportes-premium.css';
$jsAdicional = 'reportes.js';
$errores = [];
$tipos = [
    'ingresos' => 'Ingresos',
    'cuentas_cobrar' => 'Cuentas por cobrar',
    'procedimientos' => 'Procedimientos',
];

function esc(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $tipo = trim((string) ($_POST['tipo'] ?? ''));
    $fechaInicio = trim((string) ($_POST['fecha_inicio'] ?? date('Y-m-01')));
    $fechaFin = trim((string) ($_POST['fecha_fin'] ?? date('Y-m-d')));

    if (!array_key_exists($tipo, $tipos)) {
        $errores[] = 'Selecciona un tipo de reporte valido.';
    }

    if (empty($errores)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_' . $tipo . '_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Reporte', $tipos[$tipo]]);

        $db = getDB();
        if ($tipo === 'ingresos') {
            fputcsv($output, ['Fecha inicio', $fechaInicio]);
            fputcsv($output, ['Fecha fin', $fechaFin]);
            fputcsv($output, []);
            fputcsv($output, ['Mes', 'Total']);

            $stmt = $db->prepare("SELECT DATE_FORMAT(fecha_emision, '%Y-%m') AS mes, SUM(total) AS total FROM facturas WHERE estado != 'anulada' AND fecha_emision BETWEEN :inicio AND :fin GROUP BY mes ORDER BY mes");
            $stmt->execute([':inicio' => $fechaInicio, ':fin' => $fechaFin]);
            while ($row = $stmt->fetch()) {
                fputcsv($output, [$row['mes'], $row['total']]);
            }
        } elseif ($tipo === 'cuentas_cobrar') {
            fputcsv($output, []);
            fputcsv($output, ['Factura', 'Paciente', 'Fecha emision', 'Total', 'Saldo pendiente', 'Estado']);

            $stmt = $db->query("SELECT f.numero_factura, CONCAT(p.nombre, ' ', p.apellido) AS paciente, f.fecha_emision, f.total, f.saldo_pendiente, f.estado FROM facturas f JOIN pacientes p ON f.paciente_id = p.id WHERE f.estado IN ('pendiente','parcial','vencida') ORDER BY f.fecha_vencimiento ASC");
            while ($row = $stmt->fetch()) {
                fputcsv($output, [$row['numero_factura'], $row['paciente'], $row['fecha_emision'], $row['total'], $row['saldo_pendiente'], $row['estado']]);
            }
        } else {
            fputcsv($output, []);
            fputcsv($output, ['Procedimiento', 'Cantidad', 'Total facturado']);

            $stmt = $db->query("SELECT IFNULL(df.descripcion, 'Sin descripcion') AS descripcion, SUM(df.cantidad) AS cantidad, SUM(df.subtotal) AS total FROM detalle_facturas df JOIN facturas f ON df.factura_id = f.id WHERE f.estado != 'anulada' GROUP BY df.descripcion ORDER BY total DESC");
            while ($row = $stmt->fetch()) {
                fputcsv($output, [$row['descripcion'], $row['cantidad'], $row['total']]);
            }
        }
        fclose($output);
        exit;
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="reports-page">
    <section class="reports-hero">
        <div>
            <span class="reports-kicker"><i class="bi bi-cloud-arrow-down"></i> Exportacion ejecutiva</span>
            <h1>Exportar reportes</h1>
            <p>Descarga informacion financiera, cartera y procedimientos en formato compatible con Excel para analisis externo.</p>
        </div>
        <div class="reports-hero-actions">
            <a href="index.php" class="report-action"><i class="bi bi-arrow-left"></i><span>Volver</span></a>
            <button type="button" class="report-action" onclick="window.print()"><i class="bi bi-filetype-pdf"></i><span>Exportar PDF</span></button>
            <button type="button" class="report-action" onclick="window.print()"><i class="bi bi-printer"></i><span>Imprimir</span></button>
        </div>
    </section>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger shadow-sm">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="report-panel">
        <header>
            <div><span>Archivo CSV</span><h2>Configurar descarga</h2></div>
            <i class="bi bi-file-earmark-spreadsheet"></i>
        </header>
        <form method="POST" class="export-premium-form">
            <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
            <label>
                <span>Tipo de reporte</span>
                <select name="tipo" required>
                    <option value="">Selecciona un reporte</option>
                    <?php foreach ($tipos as $key => $label): ?>
                        <option value="<?= esc($key) ?>" <?= isset($_POST['tipo']) && $_POST['tipo'] === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Fecha inicio</span>
                <input type="date" name="fecha_inicio" value="<?= esc($_POST['fecha_inicio'] ?? date('Y-m-01')) ?>">
            </label>
            <label>
                <span>Fecha fin</span>
                <input type="date" name="fecha_fin" value="<?= esc($_POST['fecha_fin'] ?? date('Y-m-d')) ?>">
            </label>
            <button type="submit" class="report-action primary" data-export-loader><i class="bi bi-download"></i><span>Exportar Excel</span></button>
        </form>
    </section>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
