<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('facturacion.ver');

$paginaTitulo = 'Facturacion';
$cssAdicional = 'facturacion-premium.css';
$search = trim((string) ($_GET['search'] ?? ''));
$estadoFiltro = trim((string) ($_GET['estado'] ?? ''));

function esc(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tableExists(PDO $db, string $table): bool {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE :table_name");
        $stmt->execute([':table_name' => $table]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function badgeClass(string $estado): string {
    return match ($estado) {
        'pendiente' => 'warning',
        'parcial' => 'info',
        'pagada' => 'success',
        'vencida' => 'danger',
        'anulada' => 'muted',
        default => 'secondary',
    };
}

try {
    $db = getDB();
    $paymentTable = tableExists($db, 'pagos_factura') ? 'pagos_factura' : (tableExists($db, 'pagos') ? 'pagos' : null);
    $paymentSubquery = $paymentTable
        ? "(SELECT factura_id, SUM(monto) AS abonado, COUNT(*) AS pagos_count FROM {$paymentTable} GROUP BY factura_id)"
        : "(SELECT NULL AS factura_id, 0 AS abonado, 0 AS pagos_count)";

    $where = 'WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $where .= ' AND (f.numero_factura LIKE :term OR p.nombre LIKE :term OR p.apellido LIKE :term OR p.numero_documento LIKE :term)';
        $params[':term'] = '%' . $search . '%';
    }
    if ($estadoFiltro !== '') {
        $where .= ' AND f.estado = :estado';
        $params[':estado'] = $estadoFiltro;
    }

    $stats = $db->query("SELECT
            COALESCE(SUM(CASE WHEN MONTH(fecha_emision) = MONTH(CURDATE()) AND YEAR(fecha_emision) = YEAR(CURDATE()) AND estado <> 'anulada' THEN total ELSE 0 END), 0) AS ingresos_mes,
            SUM(CASE WHEN estado IN ('pendiente','parcial','vencida') THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado = 'pagada' THEN 1 ELSE 0 END) AS pagadas,
            COALESCE(SUM(CASE WHEN estado IN ('pendiente','parcial','vencida') THEN saldo_pendiente ELSE 0 END), 0) AS cartera
        FROM facturas")->fetch() ?: [];

    $stmt = $db->prepare("SELECT
            f.id,
            f.numero_factura,
            f.fecha_emision,
            f.fecha_vencimiento,
            f.estado,
            f.total,
            COALESCE(f.total_pagado, pay.abonado, 0) AS abonado,
            COALESCE(NULLIF(f.saldo_pendiente, 0), GREATEST(f.total - COALESCE(pay.abonado, f.total_pagado, 0), 0)) AS saldo,
            COALESCE(pay.pagos_count, 0) AS pagos_count,
            CONCAT(COALESCE(p.nombre, ''), ' ', COALESCE(p.apellido, '')) AS paciente,
            p.numero_documento
        FROM facturas f
        LEFT JOIN pacientes p ON f.paciente_id = p.id
        LEFT JOIN {$paymentSubquery} pay ON pay.factura_id = f.id
        {$where}
        ORDER BY f.fecha_emision DESC, f.id DESC");
    $stmt->execute($params);
    $facturas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Facturacion Index carga error: ' . $e->getMessage());
    $facturas = [];
    $stats = ['ingresos_mes' => 0, 'pendientes' => 0, 'pagadas' => 0, 'cartera' => 0];
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 billing-page">
    <section class="billing-hero">
        <div>
            <span class="billing-kicker"><i class="bi bi-receipt-cutoff"></i> Finanzas clinicas</span>
            <h1>Facturacion</h1>
            <p>Controla ingresos, cartera, pagos y estados de cobro con una vista ejecutiva y accionable.</p>
        </div>
        <?php if (can('facturacion.crear')): ?>
            <a href="crear.php" class="billing-primary"><i class="bi bi-plus-circle"></i> Nueva factura</a>
        <?php endif; ?>
    </section>

    <section class="billing-kpis" aria-label="Indicadores de facturacion">
        <article class="billing-kpi kpi-income"><span><i class="bi bi-graph-up-arrow"></i></span><div><small>Ingresos del mes</small><strong class="mono">$<?= number_format((float) ($stats['ingresos_mes'] ?? 0), 0, ',', '.') ?></strong></div></article>
        <article class="billing-kpi kpi-pending"><span><i class="bi bi-hourglass-split"></i></span><div><small>Facturas pendientes</small><strong class="mono"><?= number_format((int) ($stats['pendientes'] ?? 0)) ?></strong></div></article>
        <article class="billing-kpi kpi-paid"><span><i class="bi bi-check2-circle"></i></span><div><small>Facturas pagadas</small><strong class="mono"><?= number_format((int) ($stats['pagadas'] ?? 0)) ?></strong></div></article>
        <article class="billing-kpi kpi-wallet"><span><i class="bi bi-wallet2"></i></span><div><small>Cartera por cobrar</small><strong class="mono">$<?= number_format((float) ($stats['cartera'] ?? 0), 0, ',', '.') ?></strong></div></article>
    </section>

    <section class="billing-toolbar">
        <form method="GET" class="billing-filters">
            <label class="billing-search">
                <i class="bi bi-search"></i>
                <input type="search" name="search" value="<?= esc($search) ?>" placeholder="Buscar factura, paciente o documento">
            </label>
            <select name="estado">
                <option value="">Todos los estados</option>
                <?php foreach (['pendiente','parcial','pagada','vencida','anulada'] as $estado): ?>
                    <option value="<?= esc($estado) ?>" <?= $estadoFiltro === $estado ? 'selected' : '' ?>><?= esc(ucfirst($estado)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="billing-secondary"><i class="bi bi-funnel"></i> Filtrar</button>
            <?php if ($search !== '' || $estadoFiltro !== ''): ?>
                <a href="index.php" class="billing-clear"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </form>
    </section>

    <section class="billing-table-card">
        <div class="billing-table-scroll">
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Paciente</th>
                        <th>Emision</th>
                        <th>Vencimiento</th>
                        <th>Total</th>
                        <th>Pagado</th>
                        <th>Saldo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($facturas)): ?>
                        <tr><td colspan="9"><div class="billing-empty"><i class="bi bi-receipt"></i><strong>No hay facturas registradas</strong><span>Crea una factura para iniciar el control financiero.</span></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($facturas as $factura): ?>
                            <?php
                                $total = (float) ($factura['total'] ?? 0);
                                $abonado = (float) ($factura['abonado'] ?? 0);
                                $saldo = max(0, (float) ($factura['saldo'] ?? ($total - $abonado)));
                                $paidPercent = $total > 0 ? min(100, (int) round(($abonado / $total) * 100)) : 0;
                            ?>
                            <tr>
                                <td data-label="Factura"><strong><?= esc($factura['numero_factura']) ?></strong><small><?= (int) $factura['pagos_count'] ?> pagos</small></td>
                                <td data-label="Paciente"><div class="billing-patient"><span><?= esc(mb_strtoupper(mb_substr(trim($factura['paciente']), 0, 1) ?: 'P')) ?></span><div><strong><?= esc(trim($factura['paciente']) ?: 'Paciente no disponible') ?></strong><small><?= esc($factura['numero_documento'] ?: '-') ?></small></div></div></td>
                                <td data-label="Emision"><?= esc(date('d/m/Y', strtotime((string) $factura['fecha_emision']))) ?></td>
                                <td data-label="Vencimiento"><?= esc($factura['fecha_vencimiento'] ? date('d/m/Y', strtotime((string) $factura['fecha_vencimiento'])) : 'Sin fecha') ?></td>
                                <td data-label="Total"><strong>$<?= number_format($total, 0, ',', '.') ?></strong></td>
                                <td data-label="Pagado"><div class="billing-paid"><span>$<?= number_format($abonado, 0, ',', '.') ?></span><i><b style="width: <?= $paidPercent ?>%"></b></i></div></td>
                                <td data-label="Saldo"><strong class="<?= $saldo > 0 ? 'billing-debt' : 'billing-ok' ?>">$<?= number_format($saldo, 0, ',', '.') ?></strong></td>
                                <td data-label="Estado"><span class="billing-badge badge-<?= badgeClass((string) $factura['estado']) ?>"><?= esc(ucfirst((string) $factura['estado'])) ?></span></td>
                                <td data-label="Acciones">
                                    <div class="billing-actions">
                                        <a href="ver.php?id=<?= esc($factura['id']) ?>" data-tooltip="Ver factura"><i class="bi bi-eye"></i></a>
                                        <a href="pagos.php?id=<?= esc($factura['id']) ?>" data-tooltip="Registrar pago"><i class="bi bi-cash-stack"></i></a>
                                        <a href="imprimir.php?id=<?= esc($factura['id']) ?>" data-tooltip="Imprimir"><i class="bi bi-printer"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
