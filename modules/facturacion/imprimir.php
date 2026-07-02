<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('facturacion.imprimir');

$paginaTitulo = 'Imprimir Factura';
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

function estadoTexto(string $estado): string {
    return match ($estado) {
        'pagada' => 'Pagada',
        'parcial' => 'Parcial',
        'vencida' => 'Vencida',
        'anulada' => 'Anulada',
        default => 'Pendiente',
    };
}

function badgeClass(string $estado): string {
    return match ($estado) {
        'pendiente' => 'warning',
        'parcial' => 'info',
        'pagada' => 'success',
        'vencida' => 'danger',
        'anulada' => 'muted',
        default => 'muted',
    };
}

function pagoReferencia(array $pago): string {
    return (string) ($pago['referencia'] ?? $pago['referencia_pago'] ?? '');
}

function pagoNota(array $pago): string {
    return (string) ($pago['nota'] ?? $pago['observaciones'] ?? '');
}

function pagoMetodo(array $pago): string {
    $nota = pagoNota($pago);
    if (preg_match('/Metodo seleccionado:\s*([^.\n]+)/i', $nota, $matches)) {
        return trim($matches[1]);
    }
    $method = (string) ($pago['metodo'] ?? $pago['metodo_pago'] ?? '-');
    return ucfirst(str_replace('_', ' ', $method));
}

function methodIcon(string $method): string {
    $method = strtolower($method);
    return match (true) {
        str_contains($method, 'tarjeta') => 'bi-credit-card-2-front',
        str_contains($method, 'transferencia') => 'bi-bank',
        str_contains($method, 'nequi') => 'bi-phone',
        str_contains($method, 'daviplata') => 'bi-phone-vibrate',
        str_contains($method, 'efectivo') => 'bi-cash-stack',
        default => 'bi-wallet2',
    };
}

function validationUrl(int $facturaId): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return 'http://' . $host . BASE_URL . '/modules/facturacion/ver.php?id=' . urlencode((string) $facturaId);
}

function qrCells(string $seed): array {
    $hash = hash('sha256', $seed);
    $cells = [];
    for ($row = 0; $row < 13; $row++) {
        for ($col = 0; $col < 13; $col++) {
            $finder = ($row < 4 && $col < 4) || ($row < 4 && $col > 8) || ($row > 8 && $col < 4);
            $index = ($row * 13 + $col) % strlen($hash);
            $cells[] = $finder || (hexdec($hash[$index]) % 3 === 0);
        }
    }
    return $cells;
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT f.*, CONCAT(p.nombre, ' ', p.apellido) AS paciente, p.numero_documento, p.telefono,
               p.email AS paciente_email,
               CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS odontologo,
               u.email AS odontologo_email, u.telefono AS odontologo_telefono
        FROM facturas f
        JOIN pacientes p ON f.paciente_id = p.id
        LEFT JOIN usuarios u ON f.odontologo_id = u.id
        WHERE f.id = :id
    ");
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

    $pagosStmt = $db->prepare("
        SELECT pg.*, CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS registrado_por_nombre
        FROM {$paymentsTable} pg
        LEFT JOIN usuarios u ON pg.registrado_por = u.id
        WHERE pg.factura_id = :factura_id
        ORDER BY pg.fecha_pago DESC, pg.id DESC
    ");
    $pagosStmt->execute([':factura_id' => $facturaId]);
    $pagos = $pagosStmt->fetchAll();
    $abonado = array_sum(array_map(static fn($p) => (float) ($p['monto'] ?? 0), $pagos));
    $total = (float) ($factura['total'] ?? 0);
    $saldo = max(0, $total - $abonado);
    $estado = $saldo <= 0 ? 'pagada' : ($abonado > 0 ? 'parcial' : (string) ($factura['estado'] ?? 'pendiente'));
    $validationUrl = validationUrl($facturaId);
    $qrCells = qrCells($validationUrl . ($factura['numero_factura'] ?? ''));
} catch (PDOException $e) {
    error_log('Facturacion Imprimir carga error: ' . $e->getMessage());
    setAlerta('Error interno al cargar la factura.', 'danger');
    header('Location: index.php');
    exit;
}

$subtotal = (float) ($factura['subtotal'] ?? $total);
$descuento = (float) ($factura['descuento'] ?? 0);
$iva = (float) ($factura['iva'] ?? 0);
$observaciones = trim((string) ($factura['observaciones'] ?? $factura['notas'] ?? ''));
$metodoPrincipal = !empty($pagos) ? pagoMetodo($pagos[0]) : 'Sin pagos';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="print-invoice-page">
    <section class="print-actions no-print">
        <a href="ver.php?id=<?= esc($facturaId) ?>" class="invoice-action-button"><i class="bi bi-arrow-left"></i><span>Volver</span></a>
        <button type="button" class="invoice-action-button primary" onclick="window.print();"><i class="bi bi-printer"></i><span>Imprimir</span></button>
        <button type="button" class="invoice-action-button" onclick="window.print();"><i class="bi bi-filetype-pdf"></i><span>Descargar PDF</span></button>
        <button type="button" class="invoice-action-button" data-billing-share data-share-title="Factura <?= esc($factura['numero_factura']) ?>"><i class="bi bi-share"></i><span>Compartir</span></button>
    </section>

    <article class="print-invoice-sheet">
        <header class="print-invoice-header">
            <div class="print-brand">
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="DentiSoft">
                <div>
                    <span>Factura electronica</span>
                    <h1><?= esc(CLINICA_NOMBRE) ?></h1>
                    <p><?= esc(APP_DESCRIPTION) ?></p>
                </div>
            </div>
            <div class="print-invoice-id">
                <span class="billing-badge badge-<?= esc(badgeClass($estado)) ?>"><i class="bi bi-circle-fill"></i> <?= esc(estadoTexto($estado)) ?></span>
                <strong><?= esc($factura['numero_factura']) ?></strong>
                <small>Emitida <?= esc(shortDate($factura['fecha_emision'])) ?></small>
            </div>
        </header>

        <section class="print-contact-strip">
            <div><i class="bi bi-telephone"></i><span>Telefono</span><strong><?= esc(CLINICA_TELEFONO) ?></strong></div>
            <div><i class="bi bi-envelope"></i><span>Correo</span><strong><?= esc(CLINICA_EMAIL) ?></strong></div>
            <div><i class="bi bi-geo-alt"></i><span>Direccion</span><strong><?= esc(CLINICA_DIRECCION . ', ' . CLINICA_CIUDAD) ?></strong></div>
            <div><i class="bi bi-building-check"></i><span>NIT</span><strong><?= esc(CLINICA_NIT) ?></strong></div>
        </section>

        <section class="print-meta-grid">
            <article>
                <i class="bi bi-person-heart"></i>
                <span>Paciente</span>
                <strong><?= esc($factura['paciente']) ?></strong>
                <p>Documento <?= esc($factura['numero_documento'] ?: '-') ?> · Tel. <?= esc($factura['telefono'] ?: '-') ?></p>
            </article>
            <article>
                <i class="bi bi-person-badge"></i>
                <span>Odontologo responsable</span>
                <strong><?= esc(trim((string) ($factura['odontologo'] ?? '')) ?: 'No asignado') ?></strong>
                <p><?= esc($factura['odontologo_email'] ?: 'Sin correo registrado') ?></p>
            </article>
            <article>
                <i class="bi bi-calendar2-check"></i>
                <span>Datos de factura</span>
                <strong><?= esc(shortDate($factura['fecha_emision'])) ?></strong>
                <p>Vence <?= esc(shortDate($factura['fecha_vencimiento'] ?? null)) ?></p>
            </article>
            <article>
                <i class="bi <?= esc(methodIcon($metodoPrincipal)) ?>"></i>
                <span>Metodo de pago</span>
                <strong><?= esc($metodoPrincipal) ?></strong>
                <p><?= count($pagos) ?> pago(s) registrados</p>
            </article>
        </section>

        <section class="print-main-grid">
            <div class="print-left-column">
                <section class="print-panel">
                    <div class="print-section-head">
                        <div><span>Detalle facturado</span><h2>Procedimientos y conceptos</h2></div>
                        <strong><?= count($items) ?> items</strong>
                    </div>
                    <div class="print-table-wrap">
                        <table class="print-detail-table">
                            <thead>
                                <tr>
                                    <th>Procedimiento</th>
                                    <th class="num">Cantidad</th>
                                    <th class="num">Valor unitario</th>
                                    <th class="num">Descuento</th>
                                    <th class="num">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr><td colspan="5" class="empty">No hay conceptos registrados.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?= esc($item['descripcion'] ?? 'Procedimiento') ?></strong>
                                                <?php if (!empty($item['pieza_dental'])): ?><small>Pieza dental <?= esc($item['pieza_dental']) ?></small><?php endif; ?>
                                            </td>
                                            <td class="num"><?= number_format((float) ($item['cantidad'] ?? 1), 0, ',', '.') ?></td>
                                            <td class="num"><?= money($item['precio_unitario'] ?? 0) ?></td>
                                            <td class="num"><?= money($item['descuento_item'] ?? 0) ?></td>
                                            <td class="num"><strong><?= money($item['subtotal'] ?? 0) ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="print-panel">
                    <div class="print-section-head">
                        <div><span>Historial</span><h2>Pagos realizados</h2></div>
                        <strong><?= count($pagos) ?> registros</strong>
                    </div>
                    <?php if (empty($pagos)): ?>
                        <div class="print-empty">
                            <i class="bi bi-wallet2"></i>
                            <strong>Sin pagos registrados</strong>
                            <p>La factura aun no tiene movimientos de pago asociados.</p>
                        </div>
                    <?php else: ?>
                        <div class="print-payment-list">
                            <?php foreach ($pagos as $pago): ?>
                                <?php $method = pagoMetodo($pago); ?>
                                <article>
                                    <span><i class="bi <?= esc(methodIcon($method)) ?>"></i></span>
                                    <div>
                                        <strong><?= money($pago['monto'] ?? 0) ?></strong>
                                        <small><?= esc(shortDate($pago['fecha_pago'] ?? null)) ?> · <?= esc($method) ?></small>
                                    </div>
                                    <dl>
                                        <div><dt>Referencia</dt><dd><?= esc(pagoReferencia($pago) ?: '-') ?></dd></div>
                                        <div><dt>Registrado por</dt><dd><?= esc(trim((string) ($pago['registrado_por_nombre'] ?? '')) ?: 'Sistema') ?></dd></div>
                                    </dl>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="print-finance-panel">
                <span class="print-stamp"><i class="bi bi-patch-check"></i> Factura electronica</span>
                <div class="print-total-hero">
                    <small>Total final</small>
                    <strong><?= money($total) ?></strong>
                </div>
                <dl>
                    <div><dt>Subtotal</dt><dd><?= money($subtotal) ?></dd></div>
                    <div><dt>Descuentos</dt><dd>- <?= money($descuento) ?></dd></div>
                    <div><dt>Impuestos</dt><dd><?= money($iva) ?></dd></div>
                    <div><dt>Total pagado</dt><dd class="ok"><?= money($abonado) ?></dd></div>
                    <div><dt>Saldo pendiente</dt><dd class="<?= $saldo > 0 ? 'warn' : 'ok' ?>"><?= money($saldo) ?></dd></div>
                </dl>

                <div class="print-qr-block">
                    <div class="print-qr-frame" aria-label="QR de validacion">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=8&data=<?= urlencode($validationUrl) ?>" alt="QR de validacion de factura">
                        <div class="print-qr" aria-hidden="true">
                            <?php foreach ($qrCells as $filled): ?><i class="<?= $filled ? 'filled' : '' ?>"></i><?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <strong>Validacion de factura</strong>
                        <small><?= esc($validationUrl) ?></small>
                    </div>
                </div>

                <div class="print-signature">
                    <span></span>
                    <strong><?= esc(trim((string) ($factura['odontologo'] ?? '')) ?: CLINICA_NOMBRE) ?></strong>
                    <small>Firma digital autorizada</small>
                </div>
            </aside>
        </section>

        <footer class="print-footer">
            <section>
                <span>Observaciones</span>
                <p><?= $observaciones !== '' ? nl2br(esc($observaciones)) : 'Factura generada desde DentiSoft. No se registraron observaciones adicionales para este documento.' ?></p>
            </section>
            <section>
                <span>Terminos y condiciones</span>
                <p>Documento equivalente generado electronicamente. Los servicios odontologicos se prestan bajo la historia clinica y autorizaciones registradas por la clinica. Conserva este comprobante para soporte administrativo.</p>
            </section>
        </footer>
    </article>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
