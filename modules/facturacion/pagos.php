<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('facturacion.pagar');

$paginaTitulo = 'Registrar Pago';
$cssAdicional = 'facturacion-premium.css';
$jsAdicional = 'facturacion.js';
$facturaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$facturaId) {
    setAlerta('Factura no valida.', 'danger');
    header('Location: index.php');
    exit;
}

$errores = [];
$paymentMethods = [
    'efectivo' => ['label' => 'Efectivo', 'icon' => 'bi-cash-stack', 'storage' => 'efectivo'],
    'transferencia' => ['label' => 'Transferencia', 'icon' => 'bi-bank', 'storage' => 'transferencia'],
    'tarjeta_debito' => ['label' => 'Tarjeta', 'icon' => 'bi-credit-card-2-front', 'storage' => 'tarjeta_debito'],
    'nequi' => ['label' => 'Nequi', 'icon' => 'bi-phone', 'storage' => 'transferencia'],
    'daviplata' => ['label' => 'Daviplata', 'icon' => 'bi-phone-vibrate', 'storage' => 'transferencia'],
];

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

function cleanPaymentNote(string $note): string {
    return trim(preg_replace('/^Metodo seleccionado:\s*[^.]+.\s*/i', '', $note) ?? $note);
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT f.*, CONCAT(p.nombre, ' ', p.apellido) AS paciente, p.numero_documento,
               CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS odontologo
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

    $paymentsTable = tableExists($db, 'pagos_factura') ? 'pagos_factura' : 'pagos';
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
    $paidPercent = $total > 0 ? min(100, (int) round(($abonado / $total) * 100)) : 0;
    $estado = $saldo <= 0 ? 'pagada' : ($abonado > 0 ? 'parcial' : (string) ($factura['estado'] ?? 'pendiente'));
} catch (PDOException $e) {
    error_log('Facturacion Pagos carga error: ' . $e->getMessage());
    setAlerta('Error interno al cargar el pago.', 'danger');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $fechaPago = trim((string) ($_POST['fecha_pago'] ?? ''));
    $rawMonto = preg_replace('/\D/', '', (string) ($_POST['monto'] ?? '0'));
    $monto = filter_var($rawMonto, FILTER_VALIDATE_FLOAT);
    $metodoKey = trim((string) ($_POST['metodo'] ?? ''));
    $referencia = trim((string) ($_POST['referencia'] ?? ''));
    $nota = trim((string) ($_POST['nota'] ?? ''));
    $metodoData = $paymentMethods[$metodoKey] ?? null;

    if ($fechaPago === '') {
        $errores[] = 'La fecha de pago es obligatoria.';
    }
    if ($monto === false || $monto <= 0) {
        $errores[] = 'Ingresa un monto de pago valido.';
    }
    if (!$metodoData) {
        $errores[] = 'Selecciona un metodo de pago.';
    }
    if ($monto !== false && $monto > $saldo) {
        $errores[] = 'El monto no puede exceder el saldo pendiente.';
    }
    if ($saldo <= 0) {
        $errores[] = 'Esta factura ya se encuentra pagada.';
    }

    if (empty($errores)) {
        try {
            $db->beginTransaction();
            $storageMethod = $metodoData['storage'];
            $methodLabel = $metodoData['label'];
            $storedNote = trim('Metodo seleccionado: ' . $methodLabel . '. ' . $nota);

            if ($paymentsTable === 'pagos_factura') {
                $stmt = $db->prepare("INSERT INTO pagos_factura (factura_id, fecha_pago, monto, metodo, referencia, nota, registrado_por) VALUES (:factura_id, :fecha_pago, :monto, :metodo, :referencia, :nota, :registrado_por)");
                $stmt->execute([
                    ':factura_id' => $facturaId,
                    ':fecha_pago' => $fechaPago,
                    ':monto' => $monto,
                    ':metodo' => $storageMethod,
                    ':referencia' => $referencia ?: null,
                    ':nota' => $storedNote ?: null,
                    ':registrado_por' => $_SESSION['usuario_id'] ?? null,
                ]);
            } else {
                $stmt = $db->prepare("INSERT INTO pagos (factura_id, fecha_pago, monto, metodo_pago, referencia_pago, observaciones, registrado_por) VALUES (:factura_id, :fecha_pago, :monto, :metodo_pago, :referencia_pago, :observaciones, :registrado_por)");
                $stmt->execute([
                    ':factura_id' => $facturaId,
                    ':fecha_pago' => $fechaPago,
                    ':monto' => $monto,
                    ':metodo_pago' => $storageMethod,
                    ':referencia_pago' => $referencia ?: null,
                    ':observaciones' => $storedNote ?: null,
                    ':registrado_por' => $_SESSION['usuario_id'] ?? null,
                ]);
            }

            $nuevoAbonado = min($total, $abonado + (float) $monto);
            $nuevoSaldo = max(0, $total - $nuevoAbonado);
            $nuevoEstado = $nuevoSaldo <= 0 ? 'pagada' : ($nuevoAbonado > 0 ? 'parcial' : 'pendiente');
            $updateFactura = $db->prepare("UPDATE facturas SET total_pagado = :total_pagado, saldo_pendiente = :saldo_pendiente, estado = :estado WHERE id = :id");
            $updateFactura->execute([
                ':total_pagado' => $nuevoAbonado,
                ':saldo_pendiente' => $nuevoSaldo,
                ':estado' => $nuevoEstado,
                ':id' => $facturaId,
            ]);
            $db->commit();
            setAlerta('Pago registrado correctamente.');
            header('Location: pagos.php?id=' . urlencode((string) $facturaId));
            exit;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Facturacion Pagos insert error: ' . $e->getMessage());
            $errores[] = 'No fue posible registrar el pago. Intenta nuevamente.';
        }
    }
}

$canPay = $saldo > 0 && !in_array((string) ($factura['estado'] ?? ''), ['anulada'], true);
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="payment-page">
    <section class="payment-hero">
        <div>
            <span class="billing-kicker"><i class="bi bi-shield-check"></i> Registro financiero</span>
            <div class="payment-title-row">
                <h1>Registrar pago</h1>
                <span class="billing-badge badge-<?= esc(badgeClass($estado)) ?>"><i class="bi bi-circle-fill"></i> <?= esc(estadoTexto($estado)) ?></span>
            </div>
            <p>Factura <?= esc($factura['numero_factura']) ?> de <?= esc($factura['paciente']) ?>. Controla abonos, saldo y trazabilidad en una vista compacta.</p>
        </div>
        <a href="ver.php?id=<?= esc($facturaId) ?>" class="invoice-action-button"><i class="bi bi-arrow-left"></i><span>Volver</span></a>
    </section>

    <?php if (!empty($errores)): ?>
        <div class="payment-toast error" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>Revisa el pago</strong>
                <?php foreach ($errores as $error): ?>
                    <span><?= esc($error) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="payment-grid">
        <aside class="payment-summary-card status-<?= esc($estado) ?>">
            <div class="payment-summary-head">
                <span><i class="bi bi-receipt-cutoff"></i></span>
                <div>
                    <small>Resumen de factura</small>
                    <strong><?= esc($factura['numero_factura']) ?></strong>
                </div>
            </div>

            <div class="payment-patient">
                <span><?= esc(mb_strtoupper(mb_substr(trim((string) $factura['paciente']), 0, 1) ?: 'P')) ?></span>
                <div>
                    <small>Paciente</small>
                    <strong><?= esc($factura['paciente']) ?></strong>
                    <em>Documento <?= esc($factura['numero_documento'] ?: '-') ?></em>
                </div>
            </div>

            <dl class="payment-summary-list">
                <div><dt><i class="bi bi-calendar2-check"></i> Emision</dt><dd><?= esc(shortDate($factura['fecha_emision'])) ?></dd></div>
                <div><dt><i class="bi bi-person-badge"></i> Odontologo</dt><dd><?= esc(trim((string) ($factura['odontologo'] ?? '')) ?: 'No asignado') ?></dd></div>
                <div><dt><i class="bi bi-cash-coin"></i> Total facturado</dt><dd><?= money($total) ?></dd></div>
                <div><dt><i class="bi bi-check2-circle"></i> Total pagado</dt><dd class="ok"><?= money($abonado) ?></dd></div>
                <div class="pending"><dt><i class="bi bi-wallet2"></i> Saldo pendiente</dt><dd><?= money($saldo) ?></dd></div>
            </dl>

            <div class="payment-progress-block">
                <div><span>Progreso del pago</span><strong><?= $paidPercent ?>%</strong></div>
                <i><b style="width: <?= $paidPercent ?>%"></b></i>
            </div>
        </aside>

        <section class="payment-form-card">
            <div class="payment-card-header">
                <div>
                    <span>Nuevo pago</span>
                    <h2>Registrar abono</h2>
                </div>
                <strong><?= money($saldo) ?></strong>
            </div>

            <div class="payment-skeleton" aria-hidden="true">
                <span></span><span></span><span></span>
            </div>

            <form method="POST" class="payment-form" novalidate data-payment-form data-max-payment="<?= esc(number_format($saldo, 0, '.', '')) ?>">
                <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
                <div class="payment-fields">
                    <label class="payment-field">
                        <span>Fecha de pago</span>
                        <i class="bi bi-calendar2-week"></i>
                        <input type="date" name="fecha_pago" value="<?= esc($_POST['fecha_pago'] ?? date('Y-m-d')) ?>" required <?= !$canPay ? 'disabled' : '' ?>>
                    </label>
                    <label class="payment-field">
                        <span>Monto COP</span>
                        <i class="bi bi-currency-dollar"></i>
                        <input type="text" inputmode="numeric" name="monto" value="<?= esc($_POST['monto'] ?? '') ?>" placeholder="<?= esc(money($saldo)) ?>" required data-cop-input <?= !$canPay ? 'disabled' : '' ?>>
                        <small data-payment-feedback>Disponible: <?= money($saldo) ?></small>
                    </label>
                </div>

                <div class="payment-methods" role="radiogroup" aria-label="Metodo de pago">
                    <?php foreach ($paymentMethods as $key => $method): ?>
                        <label>
                            <input type="radio" name="metodo" value="<?= esc($key) ?>" <?= (($_POST['metodo'] ?? 'efectivo') === $key) ? 'checked' : '' ?> <?= !$canPay ? 'disabled' : '' ?>>
                            <span><i class="bi <?= esc($method['icon']) ?>"></i><?= esc($method['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="payment-fields">
                    <label class="payment-field">
                        <span>Referencia</span>
                        <i class="bi bi-hash"></i>
                        <input type="text" name="referencia" value="<?= esc($_POST['referencia'] ?? '') ?>" placeholder="Comprobante, transaccion o autorizacion" <?= !$canPay ? 'disabled' : '' ?>>
                    </label>
                    <label class="payment-field">
                        <span>Notas</span>
                        <i class="bi bi-chat-left-text"></i>
                        <input type="text" name="nota" value="<?= esc($_POST['nota'] ?? '') ?>" placeholder="Observaciones internas" <?= !$canPay ? 'disabled' : '' ?>>
                    </label>
                </div>

                <button type="submit" class="payment-submit" <?= !$canPay ? 'disabled' : '' ?>>
                    <i class="bi bi-check2-circle"></i>
                    <span><?= $canPay ? 'Registrar pago' : 'Factura sin saldo pendiente' ?></span>
                    <b class="payment-loader"></b>
                </button>
            </form>
        </section>
    </div>

    <section class="payment-history-card">
        <div class="payment-card-header">
            <div>
                <span>Pagos anteriores</span>
                <h2>Historial de pagos</h2>
            </div>
            <strong><?= count($pagos) ?> registros</strong>
        </div>

        <?php if (empty($pagos)): ?>
            <div class="payment-empty-state">
                <i class="bi bi-wallet2"></i>
                <strong>Sin pagos registrados</strong>
                <p>Cuando se registre el primer abono, aparecera aqui con metodo, referencia, usuario y estado.</p>
            </div>
        <?php else: ?>
            <div class="payment-table-wrap">
                <table class="payment-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Metodo</th>
                            <th>Referencia</th>
                            <th>Registrado por</th>
                            <th>Estado</th>
                            <th class="num">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $index => $pago): ?>
                            <?php
                                $method = pagoMetodo($pago);
                                $cleanNote = cleanPaymentNote(pagoNota($pago));
                            ?>
                            <tr style="--delay: <?= (int) $index * 40 ?>ms">
                                <td data-label="Fecha"><?= esc(shortDate($pago['fecha_pago'] ?? null)) ?></td>
                                <td data-label="Metodo"><span class="payment-method-badge"><i class="bi <?= esc(methodIcon($method)) ?>"></i><?= esc($method) ?></span></td>
                                <td data-label="Referencia">
                                    <strong><?= esc(pagoReferencia($pago) ?: '-') ?></strong>
                                    <?php if ($cleanNote !== ''): ?><small><?= esc($cleanNote) ?></small><?php endif; ?>
                                </td>
                                <td data-label="Registrado por"><?= esc(trim((string) ($pago['registrado_por_nombre'] ?? '')) ?: 'Sistema') ?></td>
                                <td data-label="Estado"><span class="billing-badge badge-success">Confirmado</span></td>
                                <td data-label="Valor" class="num"><?= money($pago['monto'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
