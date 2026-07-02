<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('facturacion.crear');

$paginaTitulo = 'Crear Factura';
$cssAdicional = 'facturacion-premium.css';
$jsAdicional = 'facturacion.js';
$errores = [];

function esc(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function old(string $name, mixed $default = ''): string {
    return esc(trim((string) ($_POST[$name] ?? $default)));
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

try {
    $db = getDB();
    $pacientes = $db->query("SELECT id, numero_documento, nombre, apellido FROM pacientes WHERE estado = 'activo' ORDER BY nombre, apellido")->fetchAll();
    $odontologosStmt = $db->prepare("SELECT u.id, u.nombre, u.apellido FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'odontologo' AND u.estado = 'activo' ORDER BY u.nombre, u.apellido");
    $odontologosStmt->execute();
    $odontologos = $odontologosStmt->fetchAll();
    $planes = $db->query("SELECT id, paciente_id, nombre_plan, costo_total FROM planes_tratamiento ORDER BY created_at DESC, id DESC")->fetchAll();
} catch (PDOException $e) {
    error_log('Facturacion Crear carga error: ' . $e->getMessage());
    $pacientes = [];
    $odontologos = [];
    $planes = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);
    $odontologoId = filter_input(INPUT_POST, 'odontologo_id', FILTER_VALIDATE_INT) ?: ($_SESSION['usuario_id'] ?? null);
    $planId = filter_input(INPUT_POST, 'plan_id', FILTER_VALIDATE_INT) ?: null;
    $fechaEmision = trim((string) ($_POST['fecha_emision'] ?? ''));
    $fechaVencimiento = trim((string) ($_POST['fecha_vencimiento'] ?? ''));
    $metodoPago = trim((string) ($_POST['metodo_pago'] ?? 'efectivo'));
    $abonoInicial = filter_input(INPUT_POST, 'abono_inicial', FILTER_VALIDATE_FLOAT);
    $descuento = filter_input(INPUT_POST, 'descuento', FILTER_VALIDATE_FLOAT);
    $observaciones = trim((string) ($_POST['observaciones'] ?? ''));

    $items = [];
    $subtotal = 0.0;
    $descripciones = $_POST['item_descripcion'] ?? [];
    $cantidades = $_POST['item_cantidad'] ?? [];
    $precios = $_POST['item_precio'] ?? [];

    if (is_array($descripciones)) {
        foreach ($descripciones as $i => $descripcion) {
            $descripcion = trim((string) $descripcion);
            if ($descripcion === '') {
                continue;
            }
            $cantidad = filter_var($cantidades[$i] ?? 1, FILTER_VALIDATE_FLOAT);
            $precio = filter_var($precios[$i] ?? 0, FILTER_VALIDATE_FLOAT);
            $cantidad = $cantidad !== false && $cantidad > 0 ? $cantidad : 1;
            $precio = $precio !== false && $precio >= 0 ? $precio : 0;
            $itemSubtotal = $cantidad * $precio;
            $items[] = [
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'subtotal' => $itemSubtotal,
            ];
            $subtotal += $itemSubtotal;
        }
    }

    $descuento = $descuento !== false && $descuento > 0 ? min($descuento, $subtotal) : 0;
    $total = max(0, $subtotal - $descuento);
    $abonoInicial = $abonoInicial !== false && $abonoInicial > 0 ? min($abonoInicial, $total) : 0;
    $saldo = max(0, $total - $abonoInicial);
    $estado = $abonoInicial <= 0 ? 'pendiente' : ($saldo > 0 ? 'parcial' : 'pagada');

    if (!$pacienteId) {
        $errores[] = 'Selecciona un paciente valido.';
    }
    if (!$odontologoId) {
        $errores[] = 'Selecciona un odontologo responsable.';
    }
    if ($fechaEmision === '') {
        $errores[] = 'La fecha de emision es obligatoria.';
    }
    if (empty($items)) {
        $errores[] = 'Agrega al menos un servicio o procedimiento.';
    }

    if (empty($errores)) {
        try {
            $db->beginTransaction();

            $numeroFactura = 'F' . date('YmdHis');
            $stmt = $db->prepare("INSERT INTO facturas
                (numero_factura, paciente_id, odontologo_id, plan_id, fecha_emision, fecha_vencimiento, subtotal, descuento, iva, total, total_pagado, saldo_pendiente, estado, notas, created_by)
                VALUES (:numero_factura, :paciente_id, :odontologo_id, :plan_id, :fecha_emision, :fecha_vencimiento, :subtotal, :descuento, 0, :total, :total_pagado, :saldo_pendiente, :estado, :notas, :created_by)");
            $stmt->execute([
                ':numero_factura' => $numeroFactura,
                ':paciente_id' => $pacienteId,
                ':odontologo_id' => $odontologoId,
                ':plan_id' => $planId,
                ':fecha_emision' => $fechaEmision,
                ':fecha_vencimiento' => $fechaVencimiento ?: null,
                ':subtotal' => $subtotal,
                ':descuento' => $descuento,
                ':total' => $total,
                ':total_pagado' => $abonoInicial,
                ':saldo_pendiente' => $saldo,
                ':estado' => $estado,
                ':notas' => $observaciones ?: null,
                ':created_by' => $_SESSION['usuario_id'] ?? null,
            ]);
            $facturaId = (int) $db->lastInsertId();

            $detailTable = tableExists($db, 'factura_items') ? 'factura_items' : 'detalle_facturas';
            if ($detailTable === 'factura_items') {
                $detailStmt = $db->prepare("INSERT INTO factura_items (factura_id, descripcion, cantidad, precio_unitario, subtotal) VALUES (:factura_id, :descripcion, :cantidad, :precio_unitario, :subtotal)");
            } else {
                $detailStmt = $db->prepare("INSERT INTO detalle_facturas (factura_id, descripcion, cantidad, precio_unitario, subtotal) VALUES (:factura_id, :descripcion, :cantidad, :precio_unitario, :subtotal)");
            }

            foreach ($items as $item) {
                $detailStmt->execute([
                    ':factura_id' => $facturaId,
                    ':descripcion' => $item['descripcion'],
                    ':cantidad' => $item['cantidad'],
                    ':precio_unitario' => $item['precio'],
                    ':subtotal' => $item['subtotal'],
                ]);
            }

            if ($abonoInicial > 0) {
                if (tableExists($db, 'pagos_factura')) {
                    $payStmt = $db->prepare("INSERT INTO pagos_factura (factura_id, fecha_pago, monto, metodo, referencia, nota, registrado_por) VALUES (:factura_id, :fecha_pago, :monto, :metodo, :referencia, :nota, :registrado_por)");
                    $payStmt->execute([
                        ':factura_id' => $facturaId,
                        ':fecha_pago' => $fechaEmision,
                        ':monto' => $abonoInicial,
                        ':metodo' => $metodoPago,
                        ':referencia' => 'Abono inicial',
                        ':nota' => 'Registrado al crear la factura',
                        ':registrado_por' => $_SESSION['usuario_id'] ?? null,
                    ]);
                } elseif (tableExists($db, 'pagos')) {
                    $payStmt = $db->prepare("INSERT INTO pagos (factura_id, fecha_pago, monto, metodo_pago, referencia_pago, observaciones, registrado_por) VALUES (:factura_id, :fecha_pago, :monto, :metodo_pago, :referencia_pago, :observaciones, :registrado_por)");
                    $payStmt->execute([
                        ':factura_id' => $facturaId,
                        ':fecha_pago' => $fechaEmision,
                        ':monto' => $abonoInicial,
                        ':metodo_pago' => $metodoPago,
                        ':referencia_pago' => 'Abono inicial',
                        ':observaciones' => 'Registrado al crear la factura',
                        ':registrado_por' => $_SESSION['usuario_id'] ?? null,
                    ]);
                }
            }

            $db->commit();
            setAlerta('Factura creada correctamente.');
            header('Location: ver.php?id=' . urlencode((string) $facturaId));
            exit;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Facturacion Crear insert error: ' . $e->getMessage());
            $errores[] = 'No fue posible guardar la factura. Intenta nuevamente.';
        }
    }
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 billing-page billing-create-page">
    <section class="billing-hero">
        <div>
            <span class="billing-kicker"><i class="bi bi-receipt"></i> Nueva factura</span>
            <h1>Crear factura</h1>
            <p>Registra servicios, tratamientos asociados, pagos iniciales y un resumen financiero en tiempo real.</p>
        </div>
        <a href="index.php" class="billing-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
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

    <form method="POST" class="billing-create-grid needs-validation" novalidate data-prevent-double data-billing-form>
        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">

        <main class="billing-form-card">
            <section class="billing-form-section">
                <div class="billing-section-title"><span><i class="bi bi-person-vcard"></i></span><div><h2>Paciente y contexto</h2><p>Define responsable, tratamiento asociado y fechas de cobro.</p></div></div>
                <div class="billing-fields">
                    <label class="wide"><span>Paciente *</span><select name="paciente_id" required><option value="">Selecciona un paciente</option><?php foreach ($pacientes as $paciente): ?><option value="<?= esc($paciente['id']) ?>" <?= old('paciente_id') == $paciente['id'] ? 'selected' : '' ?>><?= esc($paciente['numero_documento'] . ' - ' . $paciente['nombre'] . ' ' . $paciente['apellido']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Odontologo *</span><select name="odontologo_id" required><option value="">Selecciona</option><?php foreach ($odontologos as $odontologo): ?><option value="<?= esc($odontologo['id']) ?>" <?= old('odontologo_id') == $odontologo['id'] ? 'selected' : '' ?>><?= esc($odontologo['nombre'] . ' ' . $odontologo['apellido']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Tratamiento asociado</span><select name="plan_id"><option value="">Sin tratamiento</option><?php foreach ($planes as $plan): ?><option value="<?= esc($plan['id']) ?>" data-plan-cost="<?= esc($plan['costo_total']) ?>" <?= old('plan_id') == $plan['id'] ? 'selected' : '' ?>><?= esc($plan['nombre_plan']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Fecha emision *</span><input type="date" name="fecha_emision" value="<?= old('fecha_emision', date('Y-m-d')) ?>" required></label>
                    <label><span>Vencimiento</span><input type="date" name="fecha_vencimiento" value="<?= old('fecha_vencimiento') ?>"></label>
                </div>
            </section>

            <section class="billing-form-section">
                <div class="billing-section-title"><span><i class="bi bi-list-check"></i></span><div><h2>Servicios y procedimientos</h2><p>Agrega conceptos facturables con cantidad y valor unitario.</p></div></div>
                <div class="invoice-items" data-invoice-items>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <article class="invoice-item">
                            <label class="item-description"><span>Servicio</span><input type="text" name="item_descripcion[]" value="<?= esc($_POST['item_descripcion'][$i] ?? '') ?>" placeholder="Ej. Consulta general"></label>
                            <label><span>Cantidad</span><input type="number" min="1" step="1" name="item_cantidad[]" value="<?= esc($_POST['item_cantidad'][$i] ?? '1') ?>" data-item-qty></label>
                            <label><span>Precio</span><input type="number" min="0" step="100" name="item_precio[]" value="<?= esc($_POST['item_precio'][$i] ?? '0') ?>" data-item-price></label>
                            <button type="button" data-remove-item aria-label="Quitar concepto"><i class="bi bi-x-lg"></i></button>
                        </article>
                    <?php endfor; ?>
                </div>
                <button type="button" class="billing-secondary" data-add-item><i class="bi bi-plus-circle"></i> Agregar servicio</button>
            </section>

            <section class="billing-form-section">
                <div class="billing-section-title"><span><i class="bi bi-credit-card"></i></span><div><h2>Pagos y descuentos</h2><p>Calcula abonos, descuentos y saldo pendiente.</p></div></div>
                <div class="billing-fields">
                    <label><span>Descuento</span><input type="number" min="0" step="100" name="descuento" value="<?= old('descuento', '0') ?>" data-discount></label>
                    <label><span>Abono inicial</span><input type="number" min="0" step="100" name="abono_inicial" value="<?= old('abono_inicial', '0') ?>" data-initial-payment></label>
                    <label><span>Metodo de pago</span><select name="metodo_pago"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="tarjeta_debito">Tarjeta debito</option><option value="tarjeta_credito">Tarjeta credito</option><option value="otro">Otro</option></select></label>
                    <label class="wide"><span>Observaciones</span><textarea name="observaciones" rows="3" placeholder="Notas administrativas, acuerdos de pago o autorizaciones"><?= old('observaciones') ?></textarea></label>
                </div>
            </section>
        </main>

        <aside class="invoice-summary-card">
            <span>Resumen en tiempo real</span>
            <h2 data-summary-total>$0</h2>
            <dl>
                <div><dt>Subtotal</dt><dd data-summary-subtotal>$0</dd></div>
                <div><dt>Descuento</dt><dd data-summary-discount>$0</dd></div>
                <div><dt>Abono inicial</dt><dd data-summary-paid>$0</dd></div>
                <div><dt>Pendiente</dt><dd data-summary-pending>$0</dd></div>
            </dl>
            <div class="invoice-payment-progress"><i data-summary-bar></i></div>
            <button type="submit" class="billing-primary">
                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                <i class="bi bi-check2-circle"></i> Guardar factura
            </button>
        </aside>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
