<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('tratamientos.ver');

$paginaTitulo = 'Tratamientos';
$cssAdicional = 'tratamientos-premium.css';
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['estado'] ?? '');
$dateFilter = trim($_GET['fecha_filtro'] ?? '');
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$limit = REGISTROS_POR_PAGINA;
$offset = ($page - 1) * $limit;
$where = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where .= ' AND (pt.nombre_plan LIKE :term OR p.nombre LIKE :term OR p.apellido LIKE :term OR p.numero_documento LIKE :term OR u.nombre LIKE :term OR u.apellido LIKE :term)';
    $params[':term'] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $where .= ' AND pt.estado = :estado';
    $params[':estado'] = $statusFilter;
}
if ($dateFilter === 'hoy') {
    $where .= ' AND pt.fecha_inicio = CURDATE()';
} elseif ($dateFilter === 'mes') {
    $where .= ' AND YEAR(pt.fecha_inicio) = YEAR(CURDATE()) AND MONTH(pt.fecha_inicio) = MONTH(CURDATE())';
}

try {
    $db = getDB();

    $statsRow = $db->query("SELECT
            SUM(CASE WHEN estado = 'en_curso' THEN 1 ELSE 0 END) AS activos,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) AS finalizados,
            COALESCE(SUM(CASE WHEN estado <> 'cancelado' THEN costo_total ELSE 0 END), 0) AS ingresos,
            COUNT(DISTINCT CASE WHEN estado IN ('pendiente','en_curso') THEN paciente_id END) AS pacientes_tratamiento
        FROM planes_tratamiento")->fetch() ?: [];
    $stats = [
        'activos' => (int) ($statsRow['activos'] ?? 0),
        'pendientes' => (int) ($statsRow['pendientes'] ?? 0),
        'finalizados' => (int) ($statsRow['finalizados'] ?? 0),
        'ingresos' => (float) ($statsRow['ingresos'] ?? 0),
        'pacientes_tratamiento' => (int) ($statsRow['pacientes_tratamiento'] ?? 0),
    ];

    $countStmt = $db->prepare("SELECT COUNT(*)
        FROM planes_tratamiento pt
        JOIN pacientes p ON pt.paciente_id = p.id
        JOIN usuarios u ON pt.odontologo_id = u.id
        $where");
    $countStmt->execute($params);
    $totalRegistros = (int) $countStmt->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalRegistros / $limit));

    $stmt = $db->prepare("SELECT pt.id, pt.paciente_id, pt.nombre_plan, pt.fecha_inicio, pt.fecha_fin_estimada, pt.costo_total, pt.estado,
            p.numero_documento, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido,
            u.nombre AS odontologo_nombre, u.apellido AS odontologo_apellido,
            COALESCE(sa.total_sesiones, 0) AS total_sesiones,
            COALESCE(sa.sesiones_realizadas, 0) AS sesiones_realizadas
        FROM planes_tratamiento pt
        JOIN pacientes p ON pt.paciente_id = p.id
        JOIN usuarios u ON pt.odontologo_id = u.id
        LEFT JOIN (
            SELECT plan_id,
                COUNT(*) AS total_sesiones,
                SUM(CASE WHEN estado = 'realizada' THEN 1 ELSE 0 END) AS sesiones_realizadas
            FROM sesiones_tratamiento
            GROUP BY plan_id
        ) sa ON sa.plan_id = pt.id
        $where
        ORDER BY pt.fecha_inicio DESC, pt.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $planes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Tratamientos Index error: ' . $e->getMessage());
    $planes = [];
    $totalRegistros = 0;
    $totalPaginas = 1;
    $stats = ['activos' => 0, 'pendientes' => 0, 'finalizados' => 0, 'ingresos' => 0, 'pacientes_tratamiento' => 0];
}

function h(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badgeClass(string $estado): string {
    return match ($estado) {
        'pendiente' => 'warning',
        'en_curso' => 'info',
        'completado' => 'success',
        'cancelado' => 'danger',
        default => 'secondary',
    };
}

function estadoTexto(string $estado): string {
    return match ($estado) {
        'en_curso' => 'En curso',
        'completado' => 'Finalizado',
        'cancelado' => 'Cancelado',
        default => 'Pendiente',
    };
}

function progressForPlan(array $plan): int {
    $total = (int) ($plan['total_sesiones'] ?? 0);
    $done = (int) ($plan['sesiones_realizadas'] ?? 0);
    if ($total > 0) {
        return min(100, max(0, (int) round(($done / $total) * 100)));
    }

    return match ((string) ($plan['estado'] ?? 'pendiente')) {
        'pendiente' => 12,
        'en_curso' => 52,
        'completado' => 100,
        'cancelado' => 100,
        default => 0,
    };
}

function initials(string $first, string $last): string {
    return strtoupper(mb_substr(trim($first), 0, 1) . mb_substr(trim($last), 0, 1)) ?: '--';
}

function filterUrl(array $overrides = []): string {
    $query = array_merge([
        'search' => $_GET['search'] ?? '',
        'estado' => $_GET['estado'] ?? '',
        'fecha_filtro' => $_GET['fecha_filtro'] ?? '',
    ], $overrides, ['page' => 1]);
    $query = array_filter($query, static fn($value) => $value !== '' && $value !== null);
    return '?' . http_build_query($query);
}

function pageUrl(int $page): string {
    $query = [
        'search' => $_GET['search'] ?? '',
        'estado' => $_GET['estado'] ?? '',
        'fecha_filtro' => $_GET['fecha_filtro'] ?? '',
        'page' => $page,
    ];
    $query = array_filter($query, static fn($value) => $value !== '' && $value !== null);
    return '?' . http_build_query($query);
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 treatments-page">
    <section class="treatments-hero mb-4">
        <div>
            <span class="treatments-eyebrow"><i class="bi bi-clipboard2-pulse"></i> Gestion clinica premium</span>
            <h1>Tratamientos</h1>
            <p>Supervisa planes odontologicos, avances por sesion, costos e indicadores clinicos desde una vista moderna.</p>
        </div>
        <?php if (can('tratamientos.crear')): ?>
            <a href="crear_plan.php" class="btn treatment-primary"><i class="bi bi-plus-circle"></i> Nuevo plan</a>
        <?php endif; ?>
    </section>

    <section class="treatment-stats mb-4" aria-label="Resumen de tratamientos">
        <article class="treatment-stat stat-active">
            <span><i class="bi bi-activity"></i></span>
            <div><small>Tratamientos activos</small><strong><?= number_format($stats['activos']) ?></strong></div>
        </article>
        <article class="treatment-stat stat-pending">
            <span><i class="bi bi-hourglass-split"></i></span>
            <div><small>Pendientes</small><strong><?= number_format($stats['pendientes']) ?></strong></div>
        </article>
        <article class="treatment-stat stat-done">
            <span><i class="bi bi-check2-circle"></i></span>
            <div><small>Finalizados</small><strong><?= number_format($stats['finalizados']) ?></strong></div>
        </article>
        <article class="treatment-stat stat-money">
            <span><i class="bi bi-cash-coin"></i></span>
            <div><small>Ingresos estimados</small><strong>$<?= number_format($stats['ingresos'], 0, ',', '.') ?></strong></div>
        </article>
        <article class="treatment-stat stat-patients">
            <span><i class="bi bi-people"></i></span>
            <div><small>Pacientes en tratamiento</small><strong><?= number_format($stats['pacientes_tratamiento']) ?></strong></div>
        </article>
    </section>

    <section class="treatment-filter-panel mb-4">
        <form class="treatment-filter-grid" method="GET" action="index.php">
            <input type="hidden" name="fecha_filtro" value="<?= h($dateFilter) ?>">
            <div class="treatment-search">
                <i class="bi bi-search"></i>
                <input type="search" name="search" placeholder="Buscar por plan, paciente, documento u odontologo" value="<?= h($search) ?>">
            </div>
            <select name="estado" class="treatment-select">
                <option value="">Todos los estados</option>
                <option value="pendiente" <?= $statusFilter === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="en_curso" <?= $statusFilter === 'en_curso' ? 'selected' : '' ?>>En curso</option>
                <option value="completado" <?= $statusFilter === 'completado' ? 'selected' : '' ?>>Finalizado</option>
                <option value="cancelado" <?= $statusFilter === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
            </select>
            <button type="submit" class="btn treatment-primary"><i class="bi bi-funnel"></i> Filtrar</button>
        </form>
        <div class="treatment-chips" aria-label="Filtros rapidos">
            <a class="<?= $statusFilter === 'pendiente' ? 'active' : '' ?>" href="<?= filterUrl(['estado' => 'pendiente']) ?>">Pendientes</a>
            <a class="<?= $statusFilter === 'en_curso' ? 'active' : '' ?>" href="<?= filterUrl(['estado' => 'en_curso']) ?>">En curso</a>
            <a class="<?= $statusFilter === 'completado' ? 'active' : '' ?>" href="<?= filterUrl(['estado' => 'completado']) ?>">Finalizados</a>
            <a class="<?= $statusFilter === 'cancelado' ? 'active' : '' ?>" href="<?= filterUrl(['estado' => 'cancelado']) ?>">Cancelados</a>
            <a class="<?= $dateFilter === 'hoy' ? 'active' : '' ?>" href="<?= filterUrl(['fecha_filtro' => 'hoy']) ?>">Hoy</a>
            <a class="<?= $dateFilter === 'mes' ? 'active' : '' ?>" href="<?= filterUrl(['fecha_filtro' => 'mes']) ?>">Este mes</a>
            <?php if ($search !== '' || $statusFilter !== '' || $dateFilter !== ''): ?>
                <a class="clear" href="index.php"><i class="bi bi-x-lg"></i> Limpiar</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="treatment-board">
        <div class="treatment-head" aria-hidden="true">
            <span>Plan</span>
            <span>Paciente</span>
            <span>Odontologo</span>
            <span>Progreso</span>
            <span>Fechas</span>
            <span>Costo</span>
            <span>Estado</span>
            <span>Acciones</span>
        </div>

        <div class="treatment-list">
            <?php if (empty($planes)): ?>
                <div class="treatment-empty">
                    <i class="bi bi-clipboard2-x"></i>
                    <strong>No se encontraron tratamientos</strong>
                    <p>Ajusta los filtros o crea un nuevo plan para iniciar el seguimiento clinico.</p>
                </div>
            <?php else: ?>
                <?php foreach ($planes as $plan): ?>
                    <?php
                        $progress = progressForPlan($plan);
                        $patient = trim($plan['paciente_nombre'] . ' ' . $plan['paciente_apellido']);
                        $doctor = trim($plan['odontologo_nombre'] . ' ' . $plan['odontologo_apellido']);
                        $totalSessions = (int) ($plan['total_sesiones'] ?? 0);
                        $doneSessions = (int) ($plan['sesiones_realizadas'] ?? 0);
                    ?>
                    <article class="treatment-row status-<?= h($plan['estado']) ?>">
                        <div class="treatment-plan-cell" data-label="Plan">
                            <span class="plan-icon"><i class="bi bi-prescription2"></i></span>
                            <div>
                                <strong><?= h($plan['nombre_plan']) ?></strong>
                                <small><i class="bi bi-stars"></i> Plan odontologico</small>
                                <em><?= $totalSessions > 0 ? h((string) $totalSessions) . ' sesiones programadas' : 'Sin sesiones programadas' ?></em>
                            </div>
                        </div>

                        <div class="treatment-patient-cell" data-label="Paciente">
                            <span class="patient-avatar"><?= h(initials($plan['paciente_nombre'], $plan['paciente_apellido'])) ?></span>
                            <div>
                                <strong><?= h($patient) ?></strong>
                                <small><?= h($plan['numero_documento']) ?></small>
                            </div>
                        </div>

                        <div class="treatment-text-cell" data-label="Odontologo">
                            <strong><?= h($doctor) ?></strong>
                            <small>Responsable clinico</small>
                        </div>

                        <div class="treatment-progress-cell" data-label="Progreso">
                            <div class="progress-top">
                                <span><?= $doneSessions ?>/<?= $totalSessions ?> sesiones</span>
                                <strong><?= $progress ?>%</strong>
                            </div>
                            <div class="treatment-progress"><span style="width: <?= $progress ?>%"></span></div>
                            <div class="timeline-dots">
                                <span class="<?= $progress >= 10 ? 'done' : '' ?>"></span>
                                <span class="<?= $progress >= 45 ? 'done' : '' ?>"></span>
                                <span class="<?= $progress >= 75 ? 'done' : '' ?>"></span>
                                <span class="<?= $progress >= 100 ? 'done' : '' ?>"></span>
                            </div>
                        </div>

                        <div class="treatment-date-cell" data-label="Fechas">
                            <span><i class="bi bi-calendar3"></i> <?= h(date('d/m/Y', strtotime($plan['fecha_inicio']))) ?></span>
                            <small>Fin: <?= h($plan['fecha_fin_estimada'] ? date('d/m/Y', strtotime($plan['fecha_fin_estimada'])) : 'Sin definir') ?></small>
                        </div>

                        <div class="treatment-money-cell" data-label="Costo">
                            <strong>$<?= number_format((float) $plan['costo_total'], 0, ',', '.') ?></strong>
                            <small>COP estimado</small>
                        </div>

                        <div class="treatment-status-cell" data-label="Estado">
                            <span class="treatment-badge badge-<?= badgeClass($plan['estado']) ?>"><?= h(estadoTexto($plan['estado'])) ?></span>
                        </div>

                        <div class="treatment-actions" data-label="Acciones">
                            <a href="sesiones.php?plan_id=<?= h($plan['id']) ?>" class="treatment-action view" title="Ver detalles"><i class="bi bi-eye"></i></a>
                            <?php if (can('tratamientos.crear')): ?>
                                <a href="crear_plan.php?id=<?= h($plan['id']) ?>" class="treatment-action edit" title="Editar"><i class="bi bi-pencil-square"></i></a>
                            <?php endif; ?>
                            <a href="sesiones.php?plan_id=<?= h($plan['id']) ?>" class="treatment-action sessions" title="Ver sesiones"><i class="bi bi-list-check"></i></a>
                            <?php if (can('facturacion.crear')): ?>
                                <a href="../facturacion/crear.php" class="treatment-action bill" title="Facturar"><i class="bi bi-receipt"></i></a>
                            <?php endif; ?>
                            <?php if (can('tratamientos.crear')): ?>
                                <a href="crear_plan.php?id=<?= h($plan['id']) ?>" class="treatment-action finish" title="Cambiar estado"><i class="bi bi-check2-circle"></i></a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <nav class="mt-4" aria-label="Paginacion tratamientos">
        <ul class="pagination pagination-sm justify-content-end mb-0 treatment-pagination">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= pageUrl($page - 1) ?>">Anterior</a>
            </li>
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= pageUrl($i) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPaginas ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= pageUrl($page + 1) ?>">Siguiente</a>
            </li>
        </ul>
    </nav>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
