<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('reportes.ver');

$paginaTitulo = 'Reportes';
$cssAdicional = 'reportes-premium.css';
$jsAdicional = 'reportes.js';

$quick = trim((string) ($_GET['quick'] ?? 'mes'));
$today = date('Y-m-d');
$ranges = [
    'hoy' => [$today, $today],
    'semana' => [date('Y-m-d', strtotime('monday this week')), $today],
    'mes' => [date('Y-m-01'), $today],
    'anio' => [date('Y-01-01'), $today],
];
[$defaultStart, $defaultEnd] = $ranges[$quick] ?? $ranges['mes'];

$fechaInicio = trim((string) ($_GET['fecha_inicio'] ?? $defaultStart));
$fechaFin = trim((string) ($_GET['fecha_fin'] ?? $defaultEnd));
$odontologoId = filter_input(INPUT_GET, 'odontologo_id', FILTER_VALIDATE_INT) ?: null;
$estado = trim((string) ($_GET['estado'] ?? ''));
$pacienteTerm = trim((string) ($_GET['paciente'] ?? ''));
$tipoTratamiento = trim((string) ($_GET['tipo_tratamiento'] ?? ''));
$vista = trim((string) ($_GET['vista'] ?? 'general'));

function esc(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(mixed $value): string {
    return '$' . number_format((float) ($value ?? 0), 0, ',', '.');
}

function shortDate(mixed $value): string {
    return $value ? date('d/m/Y', strtotime((string) $value)) : 'Sin fecha';
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
        'pagada', 'atendida', 'completado', 'realizada', 'activo' => 'success',
        'parcial', 'confirmada', 'en_curso' => 'info',
        'pendiente' => 'warning',
        'vencida', 'cancelada', 'no_asistio', 'anulada' => 'danger',
        default => 'muted',
    };
}

function trend(float $current, float $previous): array {
    if ($previous <= 0 && $current > 0) return ['label' => '+100%', 'tone' => 'up'];
    if ($previous <= 0) return ['label' => '0%', 'tone' => 'flat'];
    $value = (($current - $previous) / $previous) * 100;
    return ['label' => ($value >= 0 ? '+' : '') . number_format($value, 1, ',', '.') . '%', 'tone' => $value >= 0 ? 'up' : 'down'];
}

$kpis = [];
$chartData = [];
$tables = [];
$insights = [];
$odontologos = [];
$procedimientoOptions = [];

try {
    $db = getDB();
    $paymentsTable = tableExists($db, 'pagos_factura') ? 'pagos_factura' : 'pagos';
    $itemsTable = tableExists($db, 'factura_items') ? 'factura_items' : 'detalle_facturas';

    $odontologos = $db->query("
        SELECT u.id, CONCAT(u.nombre, ' ', u.apellido) AS nombre
        FROM usuarios u
        LEFT JOIN roles r ON r.id = u.rol_id
        WHERE u.estado = 'activo' AND r.nombre = 'odontologo'
        ORDER BY u.nombre, u.apellido
    ")->fetchAll();
} catch (PDOException $e) {
    try {
        $odontologos = $db->query("SELECT id, CONCAT(nombre, ' ', apellido) AS nombre FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido")->fetchAll();
    } catch (Throwable $ignored) {
        $odontologos = [];
    }
}

try {
    $db = $db ?? getDB();
    $paymentsTable = $paymentsTable ?? (tableExists($db, 'pagos_factura') ? 'pagos_factura' : 'pagos');
    $itemsTable = $itemsTable ?? (tableExists($db, 'factura_items') ? 'factura_items' : 'detalle_facturas');
    $methodExpr = $paymentsTable === 'pagos_factura' ? 'pg.metodo' : 'pg.metodo_pago';
    $referenceExpr = $paymentsTable === 'pagos_factura' ? 'pg.referencia' : 'pg.referencia_pago';

    $procedimientoOptions = $db->query("SELECT DISTINCT descripcion FROM {$itemsTable} WHERE descripcion IS NOT NULL AND descripcion <> '' ORDER BY descripcion LIMIT 80")->fetchAll();

    $invoiceWhere = ["f.fecha_emision BETWEEN :inicio AND :fin"];
    $invoiceParams = [':inicio' => $fechaInicio, ':fin' => $fechaFin];
    if ($odontologoId) {
        $invoiceWhere[] = 'f.odontologo_id = :odontologo_id';
        $invoiceParams[':odontologo_id'] = $odontologoId;
    }
    if ($estado !== '') {
        $invoiceWhere[] = 'f.estado = :estado';
        $invoiceParams[':estado'] = $estado;
    }
    if ($pacienteTerm !== '') {
        $invoiceWhere[] = "(p.nombre LIKE :paciente OR p.apellido LIKE :paciente OR p.numero_documento LIKE :paciente)";
        $invoiceParams[':paciente'] = '%' . $pacienteTerm . '%';
    }
    if ($tipoTratamiento !== '') {
        $invoiceWhere[] = "EXISTS (SELECT 1 FROM {$itemsTable} dfx WHERE dfx.factura_id = f.id AND dfx.descripcion LIKE :tipo_tratamiento)";
        $invoiceParams[':tipo_tratamiento'] = '%' . $tipoTratamiento . '%';
    }
    $invoiceWhereSql = implode(' AND ', $invoiceWhere);

    $periodStats = $db->prepare("
        SELECT COUNT(*) AS facturas, COALESCE(SUM(f.total), 0) AS ingresos, COALESCE(SUM(f.total_pagado), 0) AS cobrado,
               COALESCE(SUM(f.saldo_pendiente), 0) AS cartera
        FROM facturas f
        JOIN pacientes p ON p.id = f.paciente_id
        WHERE f.estado != 'anulada' AND {$invoiceWhereSql}
    ");
    $periodStats->execute($invoiceParams);
    $period = $periodStats->fetch() ?: ['facturas' => 0, 'ingresos' => 0, 'cobrado' => 0, 'cartera' => 0];

    $currentMonthStart = date('Y-m-01');
    $previousMonthStart = date('Y-m-01', strtotime('first day of previous month'));
    $previousMonthEnd = date('Y-m-t', strtotime('previous month'));

    $totalPacientes = (int) $db->query("SELECT COUNT(*) FROM pacientes")->fetchColumn();
    $totalCitas = (int) $db->query("SELECT COUNT(*) FROM citas")->fetchColumn();
    $citasMes = (int) $db->query("SELECT COUNT(*) FROM citas WHERE fecha BETWEEN '{$currentMonthStart}' AND '{$today}'")->fetchColumn();
    $citasMesAnterior = (int) $db->query("SELECT COUNT(*) FROM citas WHERE fecha BETWEEN '{$previousMonthStart}' AND '{$previousMonthEnd}'")->fetchColumn();
    $ingresosMes = (float) $db->query("SELECT COALESCE(SUM(total), 0) FROM facturas WHERE estado != 'anulada' AND fecha_emision BETWEEN '{$currentMonthStart}' AND '{$today}'")->fetchColumn();
    $ingresosMesAnterior = (float) $db->query("SELECT COALESCE(SUM(total), 0) FROM facturas WHERE estado != 'anulada' AND fecha_emision BETWEEN '{$previousMonthStart}' AND '{$previousMonthEnd}'")->fetchColumn();
    $ingresosTotales = (float) $db->query("SELECT COALESCE(SUM(total), 0) FROM facturas WHERE estado != 'anulada'")->fetchColumn();
    $tratamientosActivos = (int) $db->query("SELECT COUNT(*) FROM planes_tratamiento WHERE estado IN ('pendiente','en_curso')")->fetchColumn();
    $facturasPendientes = (int) $db->query("SELECT COUNT(*) FROM facturas WHERE estado IN ('pendiente','parcial','vencida')")->fetchColumn();
    $pagosRealizados = (int) $db->query("SELECT COUNT(*) FROM {$paymentsTable}")->fetchColumn();
    $pacientesNuevos = (int) $db->query("SELECT COUNT(*) FROM pacientes WHERE DATE(created_at) BETWEEN '{$currentMonthStart}' AND '{$today}'")->fetchColumn();
    $pacientesNuevosAnterior = (int) $db->query("SELECT COUNT(*) FROM pacientes WHERE DATE(created_at) BETWEEN '{$previousMonthStart}' AND '{$previousMonthEnd}'")->fetchColumn();
    $tratamientosFinalizados = (int) $db->query("SELECT COUNT(*) FROM planes_tratamiento WHERE estado = 'completado'")->fetchColumn();

    $kpis = [
        ['label' => 'Total pacientes', 'value' => $totalPacientes, 'type' => 'number', 'icon' => 'bi-people', 'tone' => 'blue', 'trend' => trend($pacientesNuevos, $pacientesNuevosAnterior), 'hint' => 'Base activa registrada'],
        ['label' => 'Total citas', 'value' => $totalCitas, 'type' => 'number', 'icon' => 'bi-calendar2-week', 'tone' => 'blue', 'trend' => trend($citasMes, $citasMesAnterior), 'hint' => 'Historial de agenda'],
        ['label' => 'Citas del mes', 'value' => $citasMes, 'type' => 'number', 'icon' => 'bi-calendar-check', 'tone' => 'yellow', 'trend' => trend($citasMes, $citasMesAnterior), 'hint' => 'Actividad mensual'],
        ['label' => 'Ingresos del mes', 'value' => $ingresosMes, 'type' => 'money', 'icon' => 'bi-graph-up-arrow', 'tone' => 'purple', 'trend' => trend($ingresosMes, $ingresosMesAnterior), 'hint' => 'Facturacion actual'],
        ['label' => 'Ingresos totales', 'value' => $ingresosTotales, 'type' => 'money', 'icon' => 'bi-cash-stack', 'tone' => 'cyan', 'trend' => ['label' => 'Global', 'tone' => 'flat'], 'hint' => 'Acumulado historico'],
        ['label' => 'Tratamientos activos', 'value' => $tratamientosActivos, 'type' => 'number', 'icon' => 'bi-clipboard2-pulse', 'tone' => 'yellow', 'trend' => ['label' => 'En curso', 'tone' => 'flat'], 'hint' => 'Planes pendientes o activos'],
        ['label' => 'Facturas pendientes', 'value' => $facturasPendientes, 'type' => 'number', 'icon' => 'bi-receipt', 'tone' => 'red', 'trend' => ['label' => 'Cartera', 'tone' => 'down'], 'hint' => 'Pendientes de cobro'],
        ['label' => 'Pagos realizados', 'value' => $pagosRealizados, 'type' => 'number', 'icon' => 'bi-wallet2', 'tone' => 'green', 'trend' => ['label' => 'Movimientos', 'tone' => 'flat'], 'hint' => 'Registros de pago'],
        ['label' => 'Pacientes nuevos', 'value' => $pacientesNuevos, 'type' => 'number', 'icon' => 'bi-person-plus', 'tone' => 'blue', 'trend' => trend($pacientesNuevos, $pacientesNuevosAnterior), 'hint' => 'Altas del mes'],
        ['label' => 'Tratamientos finalizados', 'value' => $tratamientosFinalizados, 'type' => 'number', 'icon' => 'bi-check2-circle', 'tone' => 'green', 'trend' => ['label' => 'Cerrados', 'tone' => 'up'], 'hint' => 'Planes completados'],
    ];

    $monthlyIncomeStmt = $db->prepare("
        SELECT DATE_FORMAT(f.fecha_emision, '%Y-%m') AS label, COALESCE(SUM(f.total), 0) AS value
        FROM facturas f JOIN pacientes p ON p.id = f.paciente_id
        WHERE f.estado != 'anulada' AND {$invoiceWhereSql}
        GROUP BY label ORDER BY label
    ");
    $monthlyIncomeStmt->execute($invoiceParams);
    $monthlyIncome = $monthlyIncomeStmt->fetchAll();

    $monthlyAppointmentsStmt = $db->prepare("
        SELECT DATE_FORMAT(c.fecha, '%Y-%m') AS label, COUNT(*) AS value
        FROM citas c JOIN pacientes p ON p.id = c.paciente_id
        WHERE c.fecha BETWEEN :inicio AND :fin
        " . ($odontologoId ? " AND c.odontologo_id = :odontologo_id" : "") . "
        " . ($estado !== '' ? " AND c.estado = :estado" : "") . "
        " . ($pacienteTerm !== '' ? " AND (p.nombre LIKE :paciente OR p.apellido LIKE :paciente OR p.numero_documento LIKE :paciente)" : "") . "
        GROUP BY label ORDER BY label
    ");
    $monthlyAppointmentsStmt->execute(array_intersect_key($invoiceParams, array_flip([':inicio', ':fin', ':odontologo_id', ':estado', ':paciente'])));
    $monthlyAppointments = $monthlyAppointmentsStmt->fetchAll();

    $monthlyPatients = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS label, COUNT(*) AS value FROM pacientes WHERE DATE(created_at) BETWEEN :inicio AND :fin GROUP BY label ORDER BY label");
    $monthlyPatients->execute([':inicio' => $fechaInicio, ':fin' => $fechaFin]);
    $monthlyPatientsRows = $monthlyPatients->fetchAll();

    $topTreatmentsStmt = $db->prepare("
        SELECT IFNULL(df.descripcion, 'Sin descripcion') AS label, SUM(df.subtotal) AS value, SUM(df.cantidad) AS cantidad
        FROM {$itemsTable} df
        JOIN facturas f ON f.id = df.factura_id
        JOIN pacientes p ON p.id = f.paciente_id
        WHERE f.estado != 'anulada' AND {$invoiceWhereSql}
        GROUP BY df.descripcion
        ORDER BY value DESC
        LIMIT 8
    ");
    $topTreatmentsStmt->execute($invoiceParams);
    $topTreatments = $topTreatmentsStmt->fetchAll();

    $appointmentStatesStmt = $db->prepare("SELECT c.estado AS label, COUNT(*) AS value FROM citas c JOIN pacientes p ON p.id = c.paciente_id WHERE c.fecha BETWEEN :inicio AND :fin GROUP BY c.estado");
    $appointmentStatesStmt->execute([':inicio' => $fechaInicio, ':fin' => $fechaFin]);
    $appointmentStates = $appointmentStatesStmt->fetchAll();

    $invoiceStatesStmt = $db->prepare("SELECT f.estado AS label, COUNT(*) AS value FROM facturas f JOIN pacientes p ON p.id = f.paciente_id WHERE {$invoiceWhereSql} GROUP BY f.estado");
    $invoiceStatesStmt->execute($invoiceParams);
    $invoiceStates = $invoiceStatesStmt->fetchAll();

    $doctorPerformanceStmt = $db->prepare("
        SELECT CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS label, COALESCE(SUM(f.total), 0) AS value
        FROM facturas f
        LEFT JOIN usuarios u ON u.id = f.odontologo_id
        JOIN pacientes p ON p.id = f.paciente_id
        WHERE f.estado != 'anulada' AND {$invoiceWhereSql}
        GROUP BY f.odontologo_id, label
        ORDER BY value DESC
        LIMIT 8
    ");
    $doctorPerformanceStmt->execute($invoiceParams);
    $doctorPerformance = $doctorPerformanceStmt->fetchAll();

    $latestPayments = $db->query("
        SELECT pg.fecha_pago, pg.monto, {$methodExpr} AS metodo, {$referenceExpr} AS referencia,
               CONCAT(p.nombre, ' ', p.apellido) AS paciente,
               CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS usuario
        FROM {$paymentsTable} pg
        JOIN facturas f ON f.id = pg.factura_id
        JOIN pacientes p ON p.id = f.paciente_id
        LEFT JOIN usuarios u ON u.id = pg.registrado_por
        ORDER BY pg.fecha_pago DESC, pg.id DESC
        LIMIT 8
    ")->fetchAll();

    $latestAppointments = $db->query("
        SELECT c.fecha, c.hora_inicio, c.estado, CONCAT(p.nombre, ' ', p.apellido) AS paciente,
               CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS odontologo
        FROM citas c
        JOIN pacientes p ON p.id = c.paciente_id
        LEFT JOIN usuarios u ON u.id = c.odontologo_id
        ORDER BY c.fecha DESC, c.hora_inicio DESC
        LIMIT 8
    ")->fetchAll();

    $recentTreatments = $db->query("
        SELECT pt.nombre_plan, pt.estado, pt.costo_total, CONCAT(p.nombre, ' ', p.apellido) AS paciente,
               CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')) AS odontologo
        FROM planes_tratamiento pt
        JOIN pacientes p ON p.id = pt.paciente_id
        LEFT JOIN usuarios u ON u.id = pt.odontologo_id
        ORDER BY pt.updated_at DESC, pt.created_at DESC
        LIMIT 8
    ")->fetchAll();

    $frequentPatients = $db->query("
        SELECT CONCAT(p.nombre, ' ', p.apellido) AS paciente, p.numero_documento, COUNT(c.id) AS citas, COALESCE(SUM(f.total), 0) AS facturado
        FROM pacientes p
        LEFT JOIN citas c ON c.paciente_id = p.id
        LEFT JOIN facturas f ON f.paciente_id = p.id AND f.estado != 'anulada'
        GROUP BY p.id
        ORDER BY citas DESC, facturado DESC
        LIMIT 8
    ")->fetchAll();

    $pendingInvoices = $db->query("
        SELECT f.numero_factura, f.fecha_emision, f.fecha_vencimiento, f.saldo_pendiente, f.estado,
               CONCAT(p.nombre, ' ', p.apellido) AS paciente
        FROM facturas f
        JOIN pacientes p ON p.id = f.paciente_id
        WHERE f.estado IN ('pendiente','parcial','vencida')
        ORDER BY f.fecha_vencimiento ASC, f.saldo_pendiente DESC
        LIMIT 8
    ")->fetchAll();

    $topDoctor = $doctorPerformance[0]['label'] ?? 'Sin datos';
    $topTreatment = $topTreatments[0]['label'] ?? 'Sin datos';
    $topPatient = $frequentPatients[0]['paciente'] ?? 'Sin datos';
    $cancelled = (float) $db->query("SELECT COUNT(*) FROM citas WHERE estado IN ('cancelada','no_asistio') AND fecha BETWEEN '{$fechaInicio}' AND '{$fechaFin}'")->fetchColumn();
    $totalPeriodAppointments = (float) $db->query("SELECT COUNT(*) FROM citas WHERE fecha BETWEEN '{$fechaInicio}' AND '{$fechaFin}'")->fetchColumn();
    $cancelRate = $totalPeriodAppointments > 0 ? ($cancelled / $totalPeriodAppointments) * 100 : 0;

    $insights = [
        ['icon' => 'bi-award', 'label' => 'Odontologo con mas ingresos', 'value' => trim((string) $topDoctor) ?: 'Sin datos', 'hint' => !empty($doctorPerformance[0]) ? money($doctorPerformance[0]['value']) : 'Sin facturacion'],
        ['icon' => 'bi-stars', 'label' => 'Tratamiento mas solicitado', 'value' => $topTreatment, 'hint' => !empty($topTreatments[0]) ? money($topTreatments[0]['value']) : 'Sin procedimientos'],
        ['icon' => 'bi-person-heart', 'label' => 'Paciente mas frecuente', 'value' => $topPatient, 'hint' => !empty($frequentPatients[0]) ? number_format((float) $frequentPatients[0]['citas']) . ' citas' : 'Sin citas'],
        ['icon' => 'bi-activity', 'label' => 'Cancelaciones', 'value' => number_format($cancelRate, 1, ',', '.') . '%', 'hint' => number_format($cancelled) . ' eventos del periodo'],
        ['icon' => 'bi-graph-up', 'label' => 'Crecimiento mensual', 'value' => trend($ingresosMes, $ingresosMesAnterior)['label'], 'hint' => 'Comparado con el mes anterior'],
        ['icon' => 'bi-bank', 'label' => 'Ingresos del filtro', 'value' => money($period['ingresos']), 'hint' => 'Cartera: ' . money($period['cartera'])],
    ];

    $chartData = [
        'ingresos' => $monthlyIncome,
        'citas' => $monthlyAppointments,
        'pacientes' => $monthlyPatientsRows,
        'tratamientos' => $topTreatments,
        'estadosCitas' => $appointmentStates,
        'estadosFacturas' => $invoiceStates,
        'odontologos' => $doctorPerformance,
    ];

    $tables = compact('latestPayments', 'latestAppointments', 'recentTreatments', 'frequentPatients', 'pendingInvoices');
} catch (PDOException $e) {
    error_log('Reportes Index premium error: ' . $e->getMessage());
    $period = ['ingresos' => 0, 'cartera' => 0, 'facturas' => 0, 'cobrado' => 0];
    $chartData = ['ingresos' => [], 'citas' => [], 'pacientes' => [], 'tratamientos' => [], 'estadosCitas' => [], 'estadosFacturas' => [], 'odontologos' => []];
    $tables = ['latestPayments' => [], 'latestAppointments' => [], 'recentTreatments' => [], 'frequentPatients' => [], 'pendingInvoices' => []];
    $insights = [];
}

$chartPayload = [];
foreach ($chartData as $key => $rows) {
    $chartPayload[$key] = [
        'labels' => array_map(static fn($row) => (string) ($row['label'] ?? 'Sin dato'), $rows),
        'values' => array_map(static fn($row) => (float) ($row['value'] ?? 0), $rows),
    ];
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="reports-page">
    <section class="reports-hero">
        <div>
            <span class="reports-kicker"><i class="bi bi-bar-chart-line"></i> Inteligencia clinica</span>
            <h1>Reportes ejecutivos</h1>
            <p>Supervisa crecimiento, agenda, cartera, tratamientos e ingresos con una vista corporativa lista para decisiones gerenciales.</p>
        </div>
        <div class="reports-hero-actions">
            <a href="exportar.php" class="report-action primary" data-export-loader><i class="bi bi-file-earmark-spreadsheet"></i><span>Exportar Excel</span></a>
            <button type="button" class="report-action" onclick="window.print()"><i class="bi bi-filetype-pdf"></i><span>Exportar PDF</span></button>
            <button type="button" class="report-action" onclick="window.print()"><i class="bi bi-printer"></i><span>Imprimir</span></button>
            <button type="button" class="report-action" data-report-share><i class="bi bi-share"></i><span>Compartir</span></button>
        </div>
    </section>

    <section class="reports-filter-card no-print">
        <form method="GET" class="reports-filter-grid">
            <label><span>Fecha inicio</span><input type="date" name="fecha_inicio" value="<?= esc($fechaInicio) ?>"></label>
            <label><span>Fecha fin</span><input type="date" name="fecha_fin" value="<?= esc($fechaFin) ?>"></label>
            <label><span>Odontologo</span><select name="odontologo_id"><option value="">Todos</option><?php foreach ($odontologos as $od): ?><option value="<?= esc($od['id']) ?>" <?= (int) $odontologoId === (int) $od['id'] ? 'selected' : '' ?>><?= esc($od['nombre']) ?></option><?php endforeach; ?></select></label>
            <label><span>Paciente</span><input type="search" name="paciente" value="<?= esc($pacienteTerm) ?>" placeholder="Nombre o documento"></label>
            <label><span>Estado</span><select name="estado"><option value="">Todos</option><?php foreach (['pendiente','parcial','pagada','vencida','anulada','confirmada','atendida','cancelada','no_asistio'] as $state): ?><option value="<?= esc($state) ?>" <?= $estado === $state ? 'selected' : '' ?>><?= esc(ucfirst(str_replace('_', ' ', $state))) ?></option><?php endforeach; ?></select></label>
            <label><span>Tipo tratamiento</span><select name="tipo_tratamiento"><option value="">Todos</option><?php foreach ($procedimientoOptions as $option): ?><option value="<?= esc($option['descripcion']) ?>" <?= $tipoTratamiento === $option['descripcion'] ? 'selected' : '' ?>><?= esc($option['descripcion']) ?></option><?php endforeach; ?></select></label>
            <label><span>Vista</span><select name="vista"><option value="general" <?= $vista === 'general' ? 'selected' : '' ?>>General</option><option value="ingresos" <?= $vista === 'ingresos' ? 'selected' : '' ?>>Ingresos</option><option value="citas" <?= $vista === 'citas' ? 'selected' : '' ?>>Citas</option><option value="facturacion" <?= $vista === 'facturacion' ? 'selected' : '' ?>>Facturacion</option></select></label>
            <button type="submit" class="report-action primary"><i class="bi bi-funnel"></i><span>Aplicar</span></button>
        </form>
        <div class="reports-quick-filters">
            <?php foreach (['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'anio' => 'Este ano'] as $key => $label): ?>
                <a href="?quick=<?= esc($key) ?>" class="<?= $quick === $key ? 'active' : '' ?>"><?= esc($label) ?></a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="reports-kpi-grid">
        <?php foreach ($kpis as $kpi): ?>
            <article class="report-kpi kpi-<?= esc($kpi['tone']) ?>">
                <span><i class="bi <?= esc($kpi['icon']) ?>"></i></span>
                <div>
                    <small><?= esc($kpi['label']) ?></small>
                    <strong class="mono" data-counter="<?= esc($kpi['value']) ?>" data-counter-type="<?= esc($kpi['type']) ?>"><?= $kpi['type'] === 'money' ? money($kpi['value']) : number_format((float) $kpi['value'], 0, ',', '.') ?></strong>
                    <em class="trend-<?= esc($kpi['trend']['tone']) ?>"><?= esc($kpi['trend']['label']) ?> · <?= esc($kpi['hint']) ?></em>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="reports-chart-grid">
        <article class="report-panel wide"><header><div><span>Ingresos</span><h2>Ingresos mensuales</h2></div><i class="bi bi-graph-up-arrow"></i></header><div class="chart-shell"><canvas id="reportsIncomeChart"></canvas></div></article>
        <article class="report-panel"><header><div><span>Agenda</span><h2>Citas por mes</h2></div><i class="bi bi-calendar2-week"></i></header><div class="chart-shell"><canvas id="reportsAppointmentsChart"></canvas></div></article>
        <article class="report-panel"><header><div><span>Crecimiento</span><h2>Pacientes registrados</h2></div><i class="bi bi-person-plus"></i></header><div class="chart-shell"><canvas id="reportsPatientsChart"></canvas></div></article>
        <article class="report-panel"><header><div><span>Procedimientos</span><h2>Tratamientos mas vendidos</h2></div><i class="bi bi-stars"></i></header><div class="chart-shell"><canvas id="reportsTreatmentsChart"></canvas></div></article>
        <article class="report-panel"><header><div><span>Estados</span><h2>Estados de citas</h2></div><i class="bi bi-pie-chart"></i></header><div class="chart-shell"><canvas id="reportsAppointmentStatesChart"></canvas></div></article>
        <article class="report-panel"><header><div><span>Facturacion</span><h2>Estados de facturas</h2></div><i class="bi bi-receipt"></i></header><div class="chart-shell"><canvas id="reportsInvoiceStatesChart"></canvas></div></article>
        <article class="report-panel wide"><header><div><span>Equipo clinico</span><h2>Rendimiento por odontologo</h2></div><i class="bi bi-award"></i></header><div class="chart-shell"><canvas id="reportsDoctorChart"></canvas></div></article>
    </section>

    <section class="reports-insights">
        <?php foreach ($insights as $item): ?>
            <article>
                <span><i class="bi <?= esc($item['icon']) ?>"></i></span>
                <div><small><?= esc($item['label']) ?></small><strong><?= esc($item['value']) ?></strong><p><?= esc($item['hint']) ?></p></div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="reports-table-tools no-print">
        <div><span>Tablas ejecutivas</span><h2>Operacion reciente</h2></div>
        <label><i class="bi bi-search"></i><input type="search" placeholder="Buscar en tablas..." data-report-search></label>
    </section>

    <section class="reports-tables-grid">
        <article class="report-panel"><header><div><span>Pagos</span><h2>Ultimos pagos</h2></div><i class="bi bi-wallet2"></i></header><?= renderPayments($tables['latestPayments'] ?? []) ?></article>
        <article class="report-panel"><header><div><span>Agenda</span><h2>Ultimas citas</h2></div><i class="bi bi-calendar-event"></i></header><?= renderAppointments($tables['latestAppointments'] ?? []) ?></article>
        <article class="report-panel"><header><div><span>Tratamientos</span><h2>Tratamientos recientes</h2></div><i class="bi bi-clipboard2-pulse"></i></header><?= renderTreatments($tables['recentTreatments'] ?? []) ?></article>
        <article class="report-panel"><header><div><span>Pacientes</span><h2>Pacientes frecuentes</h2></div><i class="bi bi-person-heart"></i></header><?= renderFrequentPatients($tables['frequentPatients'] ?? []) ?></article>
        <article class="report-panel wide"><header><div><span>Cartera</span><h2>Facturas pendientes</h2></div><i class="bi bi-receipt-cutoff"></i></header><?= renderPendingInvoices($tables['pendingInvoices'] ?? []) ?></article>
    </section>
</div>
<?php
function emptyState(string $text): string {
    return '<div class="reports-empty"><i class="bi bi-inbox"></i><strong>Sin datos</strong><p>' . esc($text) . '</p></div>';
}
function renderTable(array $headers, array $rows): string {
    if (empty($rows)) return emptyState('No hay registros para mostrar.');
    $html = '<div class="reports-table-wrap"><table class="reports-table" data-report-table><thead><tr>';
    foreach ($headers as $header) $html .= '<th>' . esc($header) . '</th>';
    $html .= '</tr></thead><tbody>' . implode('', $rows) . '</tbody></table></div>';
    return $html;
}
function renderPayments(array $rows): string {
    $trs = array_map(fn($r) => '<tr><td>' . esc(shortDate($r['fecha_pago'] ?? null)) . '</td><td>' . esc($r['paciente'] ?? '-') . '</td><td><span class="report-badge badge-info">' . esc(ucfirst(str_replace('_', ' ', (string) ($r['metodo'] ?? '-')))) . '</span></td><td>' . esc($r['referencia'] ?: '-') . '</td><td>' . esc(trim((string) ($r['usuario'] ?? '')) ?: 'Sistema') . '</td><td class="num">' . money($r['monto'] ?? 0) . '</td></tr>', $rows);
    return renderTable(['Fecha', 'Paciente', 'Metodo', 'Referencia', 'Usuario', 'Monto'], $trs);
}
function renderAppointments(array $rows): string {
    $trs = array_map(fn($r) => '<tr><td>' . esc(shortDate($r['fecha'] ?? null)) . '</td><td>' . esc(substr((string) ($r['hora_inicio'] ?? ''), 0, 5)) . '</td><td>' . esc($r['paciente'] ?? '-') . '</td><td>' . esc(trim((string) ($r['odontologo'] ?? '')) ?: '-') . '</td><td><span class="report-badge badge-' . esc(badgeClass((string) ($r['estado'] ?? ''))) . '">' . esc(ucfirst(str_replace('_', ' ', (string) ($r['estado'] ?? '-')))) . '</span></td></tr>', $rows);
    return renderTable(['Fecha', 'Hora', 'Paciente', 'Odontologo', 'Estado'], $trs);
}
function renderTreatments(array $rows): string {
    $trs = array_map(fn($r) => '<tr><td>' . esc($r['nombre_plan'] ?: 'Plan odontologico') . '</td><td>' . esc($r['paciente'] ?? '-') . '</td><td>' . esc(trim((string) ($r['odontologo'] ?? '')) ?: '-') . '</td><td><span class="report-badge badge-' . esc(badgeClass((string) ($r['estado'] ?? ''))) . '">' . esc(ucfirst(str_replace('_', ' ', (string) ($r['estado'] ?? '-')))) . '</span></td><td class="num">' . money($r['costo_total'] ?? 0) . '</td></tr>', $rows);
    return renderTable(['Plan', 'Paciente', 'Odontologo', 'Estado', 'Costo'], $trs);
}
function renderFrequentPatients(array $rows): string {
    $trs = array_map(fn($r) => '<tr><td>' . esc($r['paciente'] ?? '-') . '<small>' . esc($r['numero_documento'] ?? '-') . '</small></td><td class="num">' . number_format((float) ($r['citas'] ?? 0), 0, ',', '.') . '</td><td class="num">' . money($r['facturado'] ?? 0) . '</td></tr>', $rows);
    return renderTable(['Paciente', 'Citas', 'Facturado'], $trs);
}
function renderPendingInvoices(array $rows): string {
    $trs = array_map(fn($r) => '<tr><td>' . esc($r['numero_factura'] ?? '-') . '</td><td>' . esc($r['paciente'] ?? '-') . '</td><td>' . esc(shortDate($r['fecha_emision'] ?? null)) . '</td><td>' . esc(shortDate($r['fecha_vencimiento'] ?? null)) . '</td><td><span class="report-badge badge-' . esc(badgeClass((string) ($r['estado'] ?? ''))) . '">' . esc(ucfirst((string) ($r['estado'] ?? '-'))) . '</span></td><td class="num">' . money($r['saldo_pendiente'] ?? 0) . '</td></tr>', $rows);
    return renderTable(['Factura', 'Paciente', 'Emision', 'Vence', 'Estado', 'Saldo'], $trs);
}
?>
<script>
    window.REPORTES_DATA = {
        page: 'premium',
        charts: <?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>
    };
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
