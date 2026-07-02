<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('citas.ver');

$paginaTitulo = 'Citas';
$cssAdicional = 'citas-premium.css';
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['estado'] ?? '');
$dateFilter = trim($_GET['fecha_filtro'] ?? '');
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$limit = REGISTROS_POR_PAGINA;
$offset = ($page - 1) * $limit;
$where = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where .= ' AND (p.nombre LIKE :term_paciente_nombre OR p.apellido LIKE :term_paciente_apellido OR p.numero_documento LIKE :term_documento OR u.nombre LIKE :term_odontologo_nombre OR u.apellido LIKE :term_odontologo_apellido OR c.motivo LIKE :term_motivo)';
    $term = '%' . $search . '%';
    $params[':term_paciente_nombre'] = $term;
    $params[':term_paciente_apellido'] = $term;
    $params[':term_documento'] = $term;
    $params[':term_odontologo_nombre'] = $term;
    $params[':term_odontologo_apellido'] = $term;
    $params[':term_motivo'] = $term;
}
if ($statusFilter !== '') {
    $where .= ' AND c.estado = :estado';
    $params[':estado'] = $statusFilter;
}
if ($dateFilter === 'hoy') {
    $where .= ' AND c.fecha = CURDATE()';
} elseif ($dateFilter === 'manana') {
    $where .= ' AND c.fecha = DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
} elseif ($dateFilter === 'semana') {
    $where .= ' AND c.fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)';
}

try {
    $db = getDB();
    $stats = [
        'hoy' => 0,
        'confirmada' => 0,
        'pendiente' => 0,
        'cancelada' => 0,
    ];
    $statsStmt = $db->query("SELECT
            SUM(CASE WHEN fecha = CURDATE() THEN 1 ELSE 0 END) AS hoy,
            SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) AS confirmada,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) AS pendiente,
            SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) AS cancelada
        FROM citas");
    $statsRow = $statsStmt->fetch() ?: [];
    foreach ($stats as $key => $value) {
        $stats[$key] = (int) ($statsRow[$key] ?? 0);
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM citas c
        JOIN pacientes p ON c.paciente_id = p.id
        JOIN usuarios u ON c.odontologo_id = u.id
        $where");
    $countStmt->execute($params);
    $totalRegistros = (int) $countStmt->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalRegistros / $limit));

    $stmt = $db->prepare("SELECT c.id, c.fecha, c.hora_inicio, c.hora_fin, c.estado, c.motivo,
            p.numero_documento, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido,
            u.nombre AS odontologo_nombre, u.apellido AS odontologo_apellido
        FROM citas c
        JOIN pacientes p ON c.paciente_id = p.id
        JOIN usuarios u ON c.odontologo_id = u.id
        $where
        ORDER BY c.fecha DESC, c.hora_inicio DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $citas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Citas Index error: ' . $e->getMessage());
    $citas = [];
    $totalRegistros = 0;
    $totalPaginas = 1;
    $stats = [
        'hoy' => 0,
        'confirmada' => 0,
        'pendiente' => 0,
        'cancelada' => 0,
    ];
}

function obtenerBadge(string $estado): string {
    return match ($estado) {
        'pendiente' => 'warning',
        'confirmada' => 'success',
        'atendida' => 'info',
        'cancelada' => 'danger',
        'no_asistio' => 'secondary',
        default => 'light',
    };
}

function formatoHora(string $hora): string {
    return date('g:i a', strtotime($hora));
}

function estadoTexto(string $estado): string {
    return match ($estado) {
        'atendida' => 'Completada',
        'no_asistio' => 'No asistio',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function filtroUrl(array $overrides = []): string {
    $query = array_merge([
        'search' => $_GET['search'] ?? '',
        'estado' => $_GET['estado'] ?? '',
        'fecha_filtro' => $_GET['fecha_filtro'] ?? '',
    ], $overrides, ['page' => 1]);

    $query = array_filter($query, static fn($value) => $value !== '' && $value !== null);
    return '?' . http_build_query($query);
}

function paginaUrl(int $page): string {
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
<div class="container-fluid py-4 citas-page">
    <section class="citas-hero mb-4">
        <div class="citas-hero-main">
            <span class="citas-eyebrow"><i class="bi bi-calendar2-pulse"></i> Agenda clinica</span>
            <h1>Citas</h1>
            <p>Administra la agenda de pacientes con busqueda rapida, estados visuales y acciones inmediatas.</p>
        </div>
        <div class="citas-hero-actions">
            <?php if (can('citas.crear')): ?>
            <a href="crear.php" class="btn btn-primary shadow-sm"><i class="bi bi-plus-circle"></i> Nueva cita</a>
            <?php endif; ?>
            <a href="calendario.php" class="btn btn-outline-light"><i class="bi bi-calendar3"></i> Calendario</a>
        </div>
    </section>

    <section class="stats-grid mb-4" aria-label="Resumen de citas">
        <article class="stat-card stat-today">
            <span class="stat-icon"><i class="bi bi-calendar-day"></i></span>
            <div>
                <span class="stat-label">Total citas hoy</span>
                <strong><?= number_format($stats['hoy']) ?></strong>
            </div>
        </article>
        <article class="stat-card stat-confirmed">
            <span class="stat-icon"><i class="bi bi-check2-circle"></i></span>
            <div>
                <span class="stat-label">Confirmadas</span>
                <strong><?= number_format($stats['confirmada']) ?></strong>
            </div>
        </article>
        <article class="stat-card stat-pending">
            <span class="stat-icon"><i class="bi bi-hourglass-split"></i></span>
            <div>
                <span class="stat-label">Pendientes</span>
                <strong><?= number_format($stats['pendiente']) ?></strong>
            </div>
        </article>
        <article class="stat-card stat-canceled">
            <span class="stat-icon"><i class="bi bi-x-circle"></i></span>
            <div>
                <span class="stat-label">Canceladas</span>
                <strong><?= number_format($stats['cancelada']) ?></strong>
            </div>
        </article>
    </section>

    <div class="card bg-dark border-secondary shadow-sm mb-4 citas-filter-card">
        <div class="card-body">
            <form class="citas-filter-form" id="formBuscarCitas" method="GET" action="index.php">
                <input type="hidden" id="fechaFiltro" name="fecha_filtro" value="<?= htmlspecialchars($dateFilter, ENT_QUOTES, 'UTF-8') ?>">
                <div class="filter-search">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="searchCitas" type="search" name="search" class="form-control" placeholder="Buscar paciente, documento, odontologo o motivo" value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="filter-state">
                    <select id="filtroEstado" name="estado" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="pendiente" <?= $statusFilter === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="confirmada" <?= $statusFilter === 'confirmada' ? 'selected' : '' ?>>Confirmada</option>
                        <option value="atendida" <?= $statusFilter === 'atendida' ? 'selected' : '' ?>>Completada</option>
                        <option value="cancelada" <?= $statusFilter === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                        <option value="no_asistio" <?= $statusFilter === 'no_asistio' ? 'selected' : '' ?>>No asistio</option>
                    </select>
                </div>
                <div class="filter-submit">
                    <button type="submit" class="btn btn-outline-primary w-100"><i class="bi bi-funnel"></i> Filtrar</button>
                </div>
            </form>
            <div class="quick-filters" aria-label="Filtros rapidos">
                <a class="filter-chip <?= $dateFilter === 'hoy' ? 'active' : '' ?>" href="<?= filtroUrl(['fecha_filtro' => 'hoy']) ?>">Hoy</a>
                <a class="filter-chip <?= $dateFilter === 'manana' ? 'active' : '' ?>" href="<?= filtroUrl(['fecha_filtro' => 'manana']) ?>">Manana</a>
                <a class="filter-chip <?= $dateFilter === 'semana' ? 'active' : '' ?>" href="<?= filtroUrl(['fecha_filtro' => 'semana']) ?>">Esta semana</a>
                <a class="filter-chip <?= $statusFilter === 'pendiente' ? 'active' : '' ?>" href="<?= filtroUrl(['estado' => 'pendiente']) ?>">Pendientes</a>
                <a class="filter-chip <?= $statusFilter === 'confirmada' ? 'active' : '' ?>" href="<?= filtroUrl(['estado' => 'confirmada']) ?>">Confirmadas</a>
                <?php if ($search !== '' || $statusFilter !== '' || $dateFilter !== ''): ?>
                    <a class="filter-chip clear" href="index.php"><i class="bi bi-x-lg"></i> Limpiar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card bg-dark border-secondary shadow-sm citas-list-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark align-middle mb-0 citas-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Paciente</th>
                            <th>Odontologo</th>
                            <th>Estado</th>
                            <th>Motivo</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="citasTableBody">
                        <?php if (empty($citas)): ?>
                            <tr class="empty-row"><td colspan="7">
                                <div class="empty-state">
                                    <span><i class="bi bi-calendar2-x"></i></span>
                                    <strong>No se encontraron citas</strong>
                                    <p>Ajusta los filtros o agenda una nueva cita para continuar.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($citas as $cita): ?>
                                <tr data-cita-id="<?= htmlspecialchars((string) $cita['id'], ENT_QUOTES, 'UTF-8') ?>" data-estado="<?= htmlspecialchars($cita['estado'], ENT_QUOTES, 'UTF-8') ?>">
                                    <td data-label="Fecha"><span class="date-pill"><?= htmlspecialchars(date('d/m/Y', strtotime($cita['fecha']))) ?></span></td>
                                    <td data-label="Hora"><span class="time-range"><?= htmlspecialchars(formatoHora($cita['hora_inicio'])) ?> - <?= htmlspecialchars(formatoHora($cita['hora_fin'])) ?></span></td>
                                    <td data-label="Paciente">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="patient-avatar"><?= strtoupper(mb_substr($cita['paciente_nombre'], 0, 1) . mb_substr($cita['paciente_apellido'], 0, 1)) ?></div>
                                            <div class="patient-info">
                                                <div class="patient-name"><?= htmlspecialchars($cita['paciente_nombre'] . ' ' . $cita['paciente_apellido']) ?></div>
                                                <div class="patient-doc"><?= htmlspecialchars($cita['numero_documento']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Odontologo"><span class="doctor-name"><?= htmlspecialchars($cita['odontologo_nombre'] . ' ' . $cita['odontologo_apellido']) ?></span></td>
                                    <td data-label="Estado"><span class="badge bg-<?= obtenerBadge($cita['estado']) ?>"><?= estadoTexto($cita['estado']) ?></span></td>
                                    <td data-label="Motivo"><span class="reason-text"><?= htmlspecialchars($cita['motivo'] ?: 'Sin motivo') ?></span></td>
                                    <td class="text-end" data-label="Acciones">
                                        <div class="actions-group">
                                            <button type="button" class="action-btn action-view js-view-cita" data-action="ver-cita" data-id="<?= htmlspecialchars((string) $cita['id'], ENT_QUOTES, 'UTF-8') ?>" title="Ver cita" aria-label="Ver cita <?= htmlspecialchars((string) $cita['id'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-eye"></i></button>
                                            <?php if (can('citas.editar')): ?>
                                                <a href="editar.php?id=<?= urlencode((string) $cita['id']) ?>" class="action-btn action-edit js-edit-cita" data-action="editar-cita" data-id="<?= htmlspecialchars((string) $cita['id'], ENT_QUOTES, 'UTF-8') ?>" title="Editar cita" aria-label="Editar cita <?= htmlspecialchars((string) $cita['id'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil-square"></i></a>
                                                <button type="button" class="action-btn action-confirm cambiar-estado" data-action="cambiar-estado" data-id="<?= htmlspecialchars((string) $cita['id'], ENT_QUOTES, 'UTF-8') ?>" data-estado="confirmada" title="Confirmar cita"><i class="bi bi-check-circle"></i></button>
                                                <button type="button" class="action-btn action-done cambiar-estado" data-action="cambiar-estado" data-id="<?= htmlspecialchars((string) $cita['id'], ENT_QUOTES, 'UTF-8') ?>" data-estado="atendida" title="Marcar como completada"><i class="bi bi-check-all"></i></button>
                                                <button type="button" class="action-btn action-cancel cambiar-estado" data-action="cambiar-estado" data-id="<?= htmlspecialchars((string) $cita['id'], ENT_QUOTES, 'UTF-8') ?>" data-estado="cancelada" title="Cancelar cita"><i class="bi bi-x-circle"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <nav class="mt-4" aria-label="Paginacion citas">
        <ul class="pagination pagination-sm justify-content-end mb-0">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= paginaUrl($page - 1) ?>">Anterior</a>
            </li>
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= paginaUrl($i) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $totalPaginas ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= paginaUrl($page + 1) ?>">Siguiente</a>
            </li>
        </ul>
    </nav>
</div>

<?php $jsAdicional = 'citas.js'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
