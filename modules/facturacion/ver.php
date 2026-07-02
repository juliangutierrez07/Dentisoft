<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('facturacion.ver');

$paginaTitulo = 'Ver Factura';
$cssAdicional = 'facturacion-premium.css';
$jsAdicional = 'facturacion.js';
$facturaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$facturaId) {
    setAlerta('Factura no valida.', 'danger');
    header('Location: index.php');
    exit;
}

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

function money(mixed $value): string {
    return '$' . number_format((float) ($value ?? 0), 0, ',', '.');
}

function shortDate(mixed $value): string {
    return $value ? date('d/m/Y', strtotime((string) $value)) : 'Sin fecha';
}

function pagoMetodo(array $pago): string {
    $method = (string) ($pago['metodo'] ?? $pago['metodo_pago'] ?? '-');
    return ucfirst(str_replace('_', ' ', $method));
}

function pagoReferencia(array $pago): string {
    return (string) ($pago['referencia'] ?? $pago['referencia_pago'] ?? '');
}

function pagoNota(array $pago): string {
    return (string) ($pago['nota'] ?? $pago['observaciones'] ?? '');
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

function estadoTexto(string $estado): string {
    return match ($estado) {
        'pagada' => 'Pagada',
        'parcial' => 'Parcial',
        'vencida' => 'Vencida',
        'anulada' => 'Anulada',
        default => 'Pendiente',
    };
}

function methodIcon(string $method): string {
    $method = strtolower($method);
    return match (true) {
        str_contains($method, 'tarjeta') => 'bi-credit-card-2-front',
        str_contains($method, 'transferencia') => 'bi-bank',
        str_contains($method, 'cheque') => 'bi-journal-check',
        str_contains($method, 'efectivo') => 'bi-cash-stack',
        default => 'bi-wallet2',
    };
}

try {
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado') {
        requirePermission('facturacion.pagar');
        validarCSRF();
        $nuevoEstado = trim((string) ($_POST['estado'] ?? ''));
        if (in_array($nuevoEstado, ['pendiente', 'pagada', 'parcial', 'vencida', 'anulada'], true)) {
            $stmtEstado = $db->prepare("UPDATE facturas SET estado = :estado WHERE id = :id");
            $stmtEstado->execute([':estado' => $nuevoEstado, ':id' => $facturaId]);
            setAlerta('Estado de factura actualizado correctamente.');
        } else {
            setAlerta('Estado de factura no valido.', 'danger');
        }
        header('Location: ver.php?id=' . urlencode((string) $facturaId));
        exit;
    }

    $stmt = $db->prepare("SELECT f.*, CONCAT(p.nombre, ' ', p.apellido) AS paciente, p.numero_documento
        FROM facturas f
        JOIN pacientes p ON f.paciente_id = p.id
        WHERE f.id = :id");
    $stmt->execute([':id' => $facturaId]);
    $factura = $stmt->fetch();
    if (!$factura) {
        setAlerta('Factura no encontrada.', 'danger');
        header('Location: index.php');
        exit;
    }

    $itemsTable = tableExists($db, 'factura_items') ? 'factura_items' : 'detalle_facturas';
    $paymentsTable = tableExists($db, 'pagos_factura') ? 'pagos_factura' : 'pagos';

    $itemsStmt = $db->prepare("SELECT * FROM {$itemsTable} WHERE factura_id = :factura_id ORDER BY id ASC");
    $itemsStmt->execute([':factura_id' => $facturaId]);
    $items = $itemsStmt->fetchAll();

    $pagosStmt = $db->prepare("SELECT pg.*, CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS registrado_por_nombre
        FROM {$paymentsTable} pg
        LEFT JOIN usuarios u ON u.id = pg.registrado_por
        WHERE pg.factura_id = :factura_id
        ORDER BY pg.fecha_pago DESC, pg.id DESC");
    $pagosStmt->execute([':factura_id' => $facturaId]);
    $pagos = $pagosStmt->fetchAll();

    $abonado = array_sum(array_map(static fn($p) => (float) ($p['monto'] ?? 0), $pagos));
    $total = (float) ($factura['total'] ?? 0);
    $saldo = max(0, $total - $abonado);
    $paidPercent = $total > 0 ? min(100, (int) round(($abonado / $total) * 100)) : 0;
    $metodoPrincipal = !empty($pagos) ? pagoMetodo($pagos[0]) : 'Sin pagos';
} catch (PDOException $e) {
    error_log('Facturacion Ver carga error: ' . $e->getMessage());
    setAlerta('Error interno al cargar la factura.', 'danger');
    header('Location: index.php');
    exit;
}

$estado = (string) ($factura['estado'] ?? 'pendiente');
$observaciones = trim((string) ($factura['observaciones'] ?? $factura['notas'] ?? ''));
$documentTitle = 'Factura ' . ($factura['numero_factura'] ?? '');
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 billing-page invoice-view-page">
    <section class="invoice-hero">
        <div class="invoice-hero-main">
            <span class="billing-kicker"><i class="bi bi-receipt-cutoff"></i> Factura clinica premium</span>
            <div class="invoice-title-row">
                <h1><?= esc($documentTitle) ?></h1>
                <span class="billing-badge badge-<?= badgeClass($estado) ?>"><i class="bi bi-circle-fill"></i> <?= esc(estadoTexto($estado)) ?></span>
            </div>
            <div class="invoice-meta-grid" aria-label="Datos principales de factura">
                <article><i class="bi bi-calendar2-check"></i><span>Emision</span><strong><?= esc(shortDate($factura['fecha_emision'])) ?></strong></article>
                <article><i class="bi bi-person-vcard"></i><span>Paciente</span><strong><?= esc($factura['paciente']) ?></strong></article>
                <article><i class="bi bi-fingerprint"></i><span>Documento</span><strong><?= esc($factura['numero_documento']) ?></strong></article>
                <article><i class="bi bi-wallet2"></i><span>Saldo</span><strong><?= money($saldo) ?></strong></article>
            </div>
        </div>
        <div class="invoice-hero-actions">
            <a href="index.php" class="billing-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            <?php if (can('facturacion.pagar')): ?>
                <a href="pagos.php?id=<?= esc($facturaId) ?>" class="billing-primary"><i class="bi bi-cash-stack"></i> Registrar pago</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="invoice-action-bar" aria-label="Acciones rapidas">
        <?php if (can('facturacion.pagar')): ?>
            <a href="pagos.php?id=<?= esc($facturaId) ?>" class="invoice-action-button primary"><i class="bi bi-plus-circle"></i><span>Registrar pago</span></a>
        <?php endif; ?>
        <?php if (can('facturacion.imprimir')): ?>
            <a href="imprimir.php?id=<?= esc($facturaId) ?>" class="invoice-action-button"><i class="bi bi-printer"></i><span>Imprimir factura</span></a>
            <a href="imprimir.php?id=<?= esc($facturaId) ?>" class="invoice-action-button"><i class="bi bi-filetype-pdf"></i><span>Descargar PDF</span></a>
        <?php endif; ?>
        <button type="button" class="invoice-action-button" data-billing-share data-share-title="<?= esc($documentTitle) ?>"><i class="bi bi-share"></i><span>Compartir</span></button>
        <?php if (can('facturacion.crear')): ?>
            <a href="crear.php" class="invoice-action-button"><i class="bi bi-pencil-square"></i><span>Nueva edicion</span></a>
        <?php endif; ?>
        <?php if (can('facturacion.pagar')): ?>
            <form method="POST" class="invoice-status-form">
                <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="accion" value="cambiar_estado">
                <label>
                    <i class="bi bi-sliders2"></i>
                    <select name="estado" onchange="this.form.submit()" aria-label="Cambiar estado de factura">
                        <?php foreach (['pendiente', 'parcial', 'pagada', 'vencida', 'anulada'] as $estadoOpcion): ?>
                            <option value="<?= esc($estadoOpcion) ?>" <?= $estado === $estadoOpcion ? 'selected' : '' ?>><?= esc(estadoTexto($estadoOpcion)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        <?php endif; ?>
    </section>

    <section class="invoice-overview-grid">
        <article class="invoice-status-card">
            <div class="invoice-card-head">
                <span><i class="bi bi-activity"></i></span>
                <div><small>Estado de la factura</small><strong><?= esc(estadoTexto($estado)) ?></strong></div>
            </div>
            <div class="invoice-balance">
                <small>Total pendiente</small>
                <strong><?= money($saldo) ?></strong>
            </div>
            <dl class="invoice-metrics-list">
                <div><dt>Metodo de pago</dt><dd><i class="bi <?= esc(methodIcon($metodoPrincipal)) ?>"></i> <?= esc($metodoPrincipal) ?></dd></div>
                <div><dt>Fecha limite</dt><dd><?= esc(shortDate($factura['fecha_vencimiento'])) ?></dd></div>
                <div><dt>Pagos registrados</dt><dd><?= number_format(count($pagos)) ?></dd></div>
            </dl>
            <div class="invoice-progress-block">
                <div><span>Progreso de pago</span><strong><?= $paidPercent ?>%</strong></div>
                <i><b style="width: <?= $paidPercent ?>%"></b></i>
            </div>
        </article>

        <article class="invoice-finance-card">
            <div class="invoice-card-head">
                <span><i class="bi bi-graph-up-arrow"></i></span>
                <div><small>Resumen financiero</small><strong><?= money($total) ?></strong></div>
            </div>
            <dl class="invoice-finance-list">
                <div><dt>Subtotal</dt><dd><?= money($factura['subtotal'] ?? $total) ?></dd></div>
                <div><dt>Descuento</dt><dd>- <?= money($factura['descuento'] ?? 0) ?></dd></div>
                <div><dt>Impuestos</dt><dd><?= money($factura['iva'] ?? 0) ?></dd></div>
                <div class="total"><dt>Total final</dt><dd><?= money($total) ?></dd></div>
                <div><dt>Pagado</dt><dd class="ok"><?= money($abonado) ?></dd></div>
                <div><dt>Pendiente</dt><dd class="<?= $saldo > 0 ? 'warn' : 'ok' ?>"><?= money($saldo) ?></dd></div>
            </dl>
        </article>
    </section>

    <section class="invoice-content-grid">
        <main class="invoice-main-column">
            <article class="invoice-panel invoice-detail-panel">
                <div class="invoice-section-header">
                    <div><span>Detalle facturado</span><h2>Procedimientos y conceptos</h2></div>
                    <strong><?= count($items) ?> items</strong>
                </div>
                <?php if (empty($items)): ?>
                    <div class="invoice-empty-state">
                        <i class="bi bi-clipboard2-x"></i>
                        <strong>No hay conceptos registrados</strong>
                        <p>Esta factura aun no tiene procedimientos asociados.</p>
                    </div>
                <?php else: ?>
                    <div class="invoice-table-wrap">
                        <table class="invoice-detail-table">
                            <thead>
                                <tr>
                                    <th>Concepto</th>
                                    <th>Pieza</th>
                                    <th class="num">Cantidad</th>
                                    <th class="num">Valor unitario</th>
                                    <th class="num">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $index => $item): ?>
                                    <tr>
                                        <td data-label="Concepto">
                                            <div class="invoice-item-name">
                                                <span><i class="bi bi-prescription2"></i></span>
                                                <div><strong><?= esc($item['descripcion'] ?? 'Concepto sin descripcion') ?></strong><small>Procedimiento <?= $index + 1 ?></small></div>
                                            </div>
                                        </td>
                                        <td data-label="Pieza"><?= esc($item['pieza_dental'] ?? '-') ?: '-' ?></td>
                                        <td data-label="Cantidad" class="num"><?= number_format((float) ($item['cantidad'] ?? 1), 0, ',', '.') ?></td>
                                        <td data-label="Valor unitario" class="num"><?= money($item['precio_unitario'] ?? 0) ?></td>
                                        <td data-label="Subtotal" class="num"><strong><?= money($item['subtotal'] ?? 0) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <article class="invoice-panel invoice-notes-panel">
                <div class="invoice-section-header">
                    <div><span>Notas administrativas</span><h2>Observaciones</h2></div>
                    <i class="bi bi-chat-square-text"></i>
                </div>
                <p><?= $observaciones !== '' ? nl2br(esc($observaciones)) : 'Sin observaciones registradas para esta factura.' ?></p>
            </article>
        </main>

        <aside class="invoice-side-column">
            <article class="invoice-panel invoice-payments-panel">
                <div class="invoice-section-header">
                    <div><span>Historial bancario</span><h2>Pagos registrados</h2></div>
                    <a href="pagos.php?id=<?= esc($facturaId) ?>" class="invoice-mini-action"><i class="bi bi-plus-lg"></i></a>
                </div>

                <?php if (empty($pagos)): ?>
                    <div class="invoice-empty-state compact">
                        <i class="bi bi-wallet2"></i>
                        <strong>Sin pagos aun</strong>
                        <p>Registra el primer abono para activar el timeline financiero.</p>
                    </div>
                <?php else: ?>
                    <div class="invoice-payment-timeline">
                        <?php foreach ($pagos as $pago): ?>
                            <?php $method = pagoMetodo($pago); ?>
                            <article class="invoice-payment-card">
                                <span class="payment-dot"><i class="bi <?= esc(methodIcon($method)) ?>"></i></span>
                                <div class="payment-card-body">
                                    <header>
                                        <div><strong><?= money($pago['monto'] ?? 0) ?></strong><small><?= esc(shortDate($pago['fecha_pago'])) ?></small></div>
                                        <span class="billing-badge badge-success">Aplicado</span>
                                    </header>
                                    <dl>
                                        <div><dt>Metodo</dt><dd><?= esc($method) ?></dd></div>
                                        <div><dt>Referencia</dt><dd><?= esc(pagoReferencia($pago) ?: '-') ?></dd></div>
                                        <div><dt>Registrado por</dt><dd><?= esc(trim((string) ($pago['registrado_por_nombre'] ?? '')) ?: 'Sistema') ?></dd></div>
                                    </dl>
                                    <?php if (pagoNota($pago) !== ''): ?>
                                        <p><?= esc(pagoNota($pago)) ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </aside>
    </section>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
