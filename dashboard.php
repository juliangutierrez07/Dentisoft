<?php
/**
 * Dashboard — DentiSoft 1.0
 */
$paginaTitulo = 'Dashboard';
$cssAdicional = 'dashboard.css';

require_once __DIR__ . '/includes/header.php';

// ── header() ya NO va aquí — el HTML ya empezó en header.php ──

$db      = getDB();
$usuario = currentUser();

// ─── KPIs ────────────────────────────────────────────────────
$totalPacientes = (int) $db->query("SELECT COUNT(*) FROM pacientes WHERE estado = 'activo'")->fetchColumn();
$citasHoy       = (int) $db->query("SELECT COUNT(*) FROM citas WHERE fecha = CURDATE()")->fetchColumn();
$ingresosMes    = (float) $db->query("SELECT COALESCE(SUM(monto),0) FROM pagos WHERE MONTH(fecha_pago)=MONTH(CURDATE()) AND YEAR(fecha_pago)=YEAR(CURDATE())")->fetchColumn();
$cartera        = (float) $db->query("SELECT COALESCE(SUM(saldo_pendiente),0) FROM facturas WHERE estado IN ('pendiente','parcial','vencida')")->fetchColumn();

// ─── Localización de fechas (es) ───────────────────────────────
$diasEs  = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
$mesesAbrevEs = ['Jan' => 'ene', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'abr', 'May' => 'may', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'ago', 'Sep' => 'sep', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'dic'];
$mesesEs = ['January' => 'enero', 'February' => 'febrero', 'March' => 'marzo', 'April' => 'abril', 'May' => 'mayo', 'June' => 'junio', 'July' => 'julio', 'August' => 'agosto', 'September' => 'septiembre', 'October' => 'octubre', 'November' => 'noviembre', 'December' => 'diciembre'];

$fechaHoyEs  = $diasEs[date('l')] . ', ' . date('d') . ' ' . $mesesAbrevEs[date('M')] . ' ' . date('Y');
$mesActualEs = ucfirst($mesesEs[date('F')]) . ' ' . date('Y');

// ─── Citas del día ───────────────────────────────────────────
$citasDelDia = $db->query("
    SELECT c.hora_inicio, c.hora_fin, c.motivo, c.estado,
           p.nombre AS pac_nombre, p.apellido AS pac_apellido
    FROM citas c
    INNER JOIN pacientes p ON c.paciente_id = p.id
    WHERE c.fecha = CURDATE()
    ORDER BY c.hora_inicio ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ─── Últimas citas asignadas (para odontólogos) ───────────────
$usuarioRol = $usuario['rol_nombre'] ?? '';
$esOdontologo = $usuarioRol === 'odontologo';
$citasAsignadas = [];

if ($esOdontologo) {
    $citasAsignadas = $db->prepare("
        SELECT c.id, c.fecha, c.hora_inicio, c.hora_fin, c.motivo, c.estado,
               p.nombre AS pac_nombre, p.apellido AS pac_apellido,
               p.numero_documento AS pac_documento
        FROM citas c
        INNER JOIN pacientes p ON c.paciente_id = p.id
        WHERE c.odontologo_id = :odontologo_id
          AND c.fecha >= CURDATE()
        ORDER BY c.fecha ASC, c.hora_inicio ASC
        LIMIT 5
    ");
    $citasAsignadas->execute([':odontologo_id' => $usuario['id']]);
    $citasAsignadas = $citasAsignadas->fetchAll(PDO::FETCH_ASSOC);
}

// ─── Ingresos últimos 6 meses ────────────────────────────────
$mesesLabels = [];
$mesesBase   = [];
for ($i = 5; $i >= 0; $i--) {
    $dt  = new DateTime("first day of -$i month");
    $key = $dt->format('Y-m');
    $mesesBase[$key] = 0;
    $mesesLabels[]   = $dt->format('M Y');
}

$pagos = $db->query("
    SELECT DATE_FORMAT(fecha_pago,'%Y-%m') AS mes, SUM(monto) AS total
    FROM pagos
    GROUP BY DATE_FORMAT(fecha_pago,'%Y-%m')
    ORDER BY mes ASC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($pagos as $row) {
    if (isset($mesesBase[$row['mes']])) {
        $mesesBase[$row['mes']] = (float) $row['total'];
    }
}
$mesesValues = array_values($mesesBase);

// ─── Citas de la semana ──────────────────────────────────────
$diasNombres = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
$diasValues  = array_fill(0, 7, 0);
$semana = $db->query("
    SELECT DAYOFWEEK(fecha) AS dia_num, COUNT(*) AS total
    FROM citas
    WHERE fecha BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                     AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)
    GROUP BY DAYOFWEEK(fecha)
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($semana as $row) {
    $idx = ($row['dia_num'] + 5) % 7;
    $diasValues[$idx] = (int) $row['total'];
}
?>

<!-- Hero -->
<div class="dashboard-hero animate-slideUp">
    <div class="hero-info">
        <div>
            <div class="hero-overline">Visión general</div>
            <h1 class="hero-title">Hola, <?= htmlspecialchars($usuario['nombre']) ?>.</h1>
            <p class="hero-text">Revisa tus estadisticas clave, próximas citas y el desempeño de la clínica en un solo lugar.</p>
        </div>
        <div class="hero-actions">
            <a href="<?= BASE_URL ?>/modules/citas/crear.php" class="btn btn-primary btn-hero">Programar cita</a>
            <a href="<?= BASE_URL ?>/modules/pacientes/crear.php" class="btn btn-secondary btn-hero-outline">Agregar paciente</a>
        </div>
    </div>
</div>

<!-- KPI CARDS -->
<div class="kpi-grid animate-slideUp">
    <div class="kpi-card kpi-patients delay-1">
        <div class="kpi-info">
            <div class="kpi-label">Total Pacientes</div>
            <div class="kpi-value"><?= number_format($totalPacientes) ?></div>
            <div class="kpi-sub">Pacientes activos registrados</div>
        </div>
        <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
    </div>
    <div class="kpi-card kpi-citas delay-2">
        <div class="kpi-info">
            <div class="kpi-label">Citas Hoy</div>
            <div class="kpi-value"><?= number_format($citasHoy) ?></div>
            <div class="kpi-sub"><?= $fechaHoyEs ?></div>
        </div>
        <div class="kpi-icon"><i class="bi bi-calendar-check-fill"></i></div>
    </div>
    <div class="kpi-card kpi-ingresos delay-3">
        <div class="kpi-info">
            <div class="kpi-label">Ingresos del Mes</div>
            <div class="kpi-value">$<?= number_format($ingresosMes, 0, ',', '.') ?></div>
            <div class="kpi-sub"><?= $mesActualEs ?></div>
        </div>
        <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
    </div>
    <div class="kpi-card kpi-cartera delay-4">
        <div class="kpi-info">
            <div class="kpi-label">Cuentas por Cobrar</div>
            <div class="kpi-value">$<?= number_format($cartera, 0, ',', '.') ?></div>
            <div class="kpi-sub">Saldo pendiente total</div>
        </div>
        <div class="kpi-icon"><i class="bi bi-wallet2"></i></div>
    </div>
</div>

<!-- GRÁFICAS + CITAS -->
<div class="dashboard-grid">
    <div class="chart-card animate-slideUp delay-2">
        <div class="chart-header">
            <div class="chart-title"><i class="bi bi-graph-up-arrow"></i> Ingresos Mensuales</div>
        </div>
        <div style="position:relative;height:280px;">
            <canvas id="chartIngresos"></canvas>
        </div>
    </div>

    <div class="chart-card animate-slideUp delay-3">
        <div class="chart-header">
            <div class="chart-title"><i class="bi bi-calendar-day"></i> Citas de Hoy</div>
            <span class="badge-ds badge-primary"><?= count($citasDelDia) ?> citas</span>
        </div>
        <?php if (empty($citasDelDia)): ?>
            <div class="actividad-vacia">
                <i class="bi bi-calendar-x d-block"></i>
                <p>No hay citas programadas para hoy</p>
            </div>
        <?php else: ?>
            <?php
            $badges = [
                'pendiente'  => 'badge-warning',
                'confirmada' => 'badge-success',
                'atendida'   => 'badge-info',
                'cancelada'  => 'badge-danger',
                'no_asistio' => 'badge-danger',
            ];
            foreach ($citasDelDia as $cita): ?>
                <div class="cita-item">
                    <div class="cita-hora"><?= date('g:i a', strtotime($cita['hora_inicio'])) ?></div>
                    <div class="cita-linea <?= $cita['estado'] ?>"></div>
                    <div class="cita-detalle">
                        <div class="cita-paciente"><?= htmlspecialchars($cita['pac_nombre'].' '.$cita['pac_apellido'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="cita-motivo"><?= htmlspecialchars($cita['motivo'] ?? 'Sin motivo', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="cita-estado">
                        <span class="badge-ds <?= $badges[$cita['estado']] ?? 'badge-primary' ?>">
                            <?= ucfirst(str_replace('_', ' ', $cita['estado'])) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($esOdontologo): ?>
<div class="chart-card animate-slideUp delay-4" style="margin-top:1.5rem;">
    <div class="chart-header">
        <div class="chart-title"><i class="bi bi-calendar-event"></i> Últimas Citas Asignadas</div>
        <span class="badge-ds badge-primary"><?= count($citasAsignadas) ?> citas</span>
    </div>
    <?php if (empty($citasAsignadas)): ?>
        <div class="actividad-vacia">
            <i class="bi bi-calendar-x d-block"></i>
            <p>No tienes citas asignadas próximas</p>
        </div>
    <?php else: ?>
        <?php
        $badges = [
            'pendiente'  => 'badge-warning',
            'confirmada' => 'badge-success',
            'atendida'   => 'badge-info',
            'cancelada'  => 'badge-danger',
            'no_asistio' => 'badge-danger',
        ];
        foreach ($citasAsignadas as $cita): ?>
            <div class="cita-item">
                <div class="cita-hora">
                    <?= date('d/m', strtotime($cita['fecha'])) ?> - <?= date('g:i a', strtotime($cita['hora_inicio'])) ?>
                </div>
                <div class="cita-linea <?= $cita['estado'] ?>"></div>
                <div class="cita-detalle">
                    <div class="cita-paciente"><?= htmlspecialchars($cita['pac_nombre'].' '.$cita['pac_apellido'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="cita-motivo"><?= htmlspecialchars($cita['motivo'] ?? 'Sin motivo', ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="cita-estado">
                    <span class="badge-ds <?= $badges[$cita['estado']] ?? 'badge-primary' ?>">
                        <?= ucfirst(str_replace('_', ' ', $cita['estado'])) ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="chart-card animate-slideUp delay-4" style="max-width:600px;margin-top:1.5rem;">
    <div class="chart-header">
        <div class="chart-title"><i class="bi bi-bar-chart-fill"></i> Citas de la Semana</div>
    </div>
    <div style="position:relative;height:200px;">
        <canvas id="chartSemana"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.color       = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(51,65,85,0.3)';
    Chart.defaults.font.family = "'Inter', sans-serif";

    // Gráfica Ingresos
    new Chart(document.getElementById('chartIngresos'), {
        type: 'line',
        data: {
            labels: <?= json_encode($mesesLabels, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Ingresos (COP)',
                data: <?= json_encode($mesesValues) ?>,
                borderColor: '#2FE0B0',
                backgroundColor: 'rgba(47,224,176,0.12)',
                borderWidth: 2.5,
                pointBackgroundColor: '#2FE0B0',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#161D28',
                    titleColor: '#EEF2F4',
                    bodyColor: '#8A93A3',
                    callbacks: { label: ctx => '$ ' + ctx.parsed.y.toLocaleString('es-CO') }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => v >= 1000 ? '$'+(v/1000)+'k' : '$'+v }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // Gráfica Semana
    new Chart(document.getElementById('chartSemana'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($diasNombres, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{
                label: 'Citas',
                data: <?= json_encode($diasValues) ?>,
                backgroundColor: [
                    'rgba(99,102,241,0.7)','rgba(0,191,166,0.7)',
                    'rgba(139,92,246,0.7)','rgba(59,130,246,0.7)',
                    'rgba(16,185,129,0.7)','rgba(245,158,11,0.5)',
                    'rgba(100,116,139,0.4)'
                ],
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 40
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>