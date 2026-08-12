<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/patient_portal.php';
requirePermission('pacientes.ver');

$paginaTitulo = 'Pacientes';
$cssAdicional = 'pacientes-premium.css';
$usuarioActual = currentUser();
$puedeEliminarPacientes = ($usuarioActual['rol'] ?? '') === 'administrador';
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$limit = REGISTROS_POR_PAGINA;
$offset = ($page - 1) * $limit;
$where = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where = 'WHERE (
        numero_documento LIKE :term_documento
        OR nombre LIKE :term_nombre
        OR apellido LIKE :term_apellido
        OR email LIKE :term_email
        OR telefono LIKE :term_telefono
        OR ciudad LIKE :term_ciudad
    )';
    $term = '%' . $search . '%';
    $params = [
        ':term_documento' => $term,
        ':term_nombre' => $term,
        ':term_apellido' => $term,
        ':term_email' => $term,
        ':term_telefono' => $term,
        ':term_ciudad' => $term,
    ];
}

try {
    $db = getDB();
    $countStmt = $db->prepare("SELECT COUNT(*) FROM pacientes $where");
    $countStmt->execute($params);
    $totalRegistros = (int) $countStmt->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalRegistros / $limit));
    $page = min($page, $totalPaginas);
    $offset = ($page - 1) * $limit;

    $stmt = $db->prepare("SELECT
            p.id,
            p.numero_documento,
            p.tipo_documento,
            p.nombre,
            p.apellido,
            p.telefono,
            p.email,
            p.ciudad,
            p.eps,
            p.estado,
            pa.id AS portal_acceso_id,
            pa.estado AS portal_estado,
            pa.ultimo_acceso AS portal_ultimo_acceso,
            pa.debe_cambiar_password AS portal_debe_cambiar_password
        FROM pacientes p
        LEFT JOIN paciente_accesos pa ON pa.paciente_id = p.id
        $where
        ORDER BY p.created_at DESC, p.id DESC
        LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pacientes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Pacientes Index error: ' . $e->getMessage());
    $pacientes = [];
    $totalRegistros = 0;
    $totalPaginas = 1;
}

function esc(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pacienteIniciales(array $paciente): string {
    $nombre = trim((string) ($paciente['nombre'] ?? ''));
    $apellido = trim((string) ($paciente['apellido'] ?? ''));
    $iniciales = mb_substr($nombre, 0, 1) . mb_substr($apellido, 0, 1);
    return esc(mb_strtoupper($iniciales !== '' ? $iniciales : '--'));
}

function estadoLabel(string $estado): string {
    return match ($estado) {
        'activo' => 'Activo',
        'suspendido' => 'Suspendido',
        default => 'Inactivo',
    };
}

function formatPortalDate(?string $date): string {
    if (!$date) {
        return 'Nunca';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : 'Nunca';
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="pacientes-page">
    <section class="patients-header">
        <div>
            <span class="patients-kicker">Gestión clínica</span>
            <h1>Pacientes</h1>
            <p>Administra perfiles, datos de contacto y estado operativo de cada paciente.</p>
        </div>
        <?php if (can('pacientes.crear')): ?>
        <a href="crear.php" class="patients-primary-action">
            <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
            <span>Nuevo paciente</span>
        </a>
        <?php endif; ?>
    </section>

    <section class="patients-toolbar" aria-label="Filtros de pacientes">
        <form id="pacientesSearchForm" method="GET" action="index.php" class="patients-search-form">
            <div class="patients-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input
                    id="searchPacientes"
                    type="search"
                    name="search"
                    autocomplete="off"
                    placeholder="Buscar por documento, nombre, email, teléfono o ciudad"
                    value="<?= esc($search) ?>"
                    aria-label="Buscar pacientes">
                <button type="button" id="clearPacientesSearch" class="patients-search-clear" aria-label="Limpiar búsqueda">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <button type="submit" class="patients-filter-button">
                <i class="bi bi-arrow-return-left" aria-hidden="true"></i>
                <span>Buscar</span>
            </button>
        </form>

        <div class="patients-count" id="pacientesResultCount" aria-live="polite">
            <?= number_format($totalRegistros) ?> pacientes
        </div>
    </section>

    <section class="patients-table-shell" aria-label="Listado de pacientes">
        <div class="patients-table-scroll">
            <table class="patients-table">
                <thead>
                    <tr>
                        <th class="col-doc">Documento</th>
                        <th class="col-name">Nombre</th>
                        <th class="col-phone">Teléfono</th>
                        <th class="col-email">Email</th>
                        <th class="col-city">Ciudad</th>
                        <th class="col-eps">EPS</th>
                        <th class="col-status">Estado</th>
                        <th class="col-portal">Portal</th>
                        <th class="col-portal-last">Ultimo acceso</th>
                        <th class="col-account">Cuenta</th>
                        <th class="col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="pacientesTableBody" data-can-delete="<?= $puedeEliminarPacientes ? '1' : '0' ?>">
                    <?php if (empty($pacientes)): ?>
                        <tr>
                            <td colspan="11">
                                <div class="patients-empty">
                                    <i class="bi bi-person-vcard" aria-hidden="true"></i>
                                    <strong>No se encontraron pacientes</strong>
                                    <span>Prueba con otro documento, nombre, teléfono o ciudad.</span>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pacientes as $paciente): ?>
                            <tr data-paciente-id="<?= esc($paciente['id']) ?>">
                                <td class="col-doc">
                                    <span class="patient-doc"><?= esc($paciente['numero_documento']) ?></span>
                                    <small><?= esc($paciente['tipo_documento'] ?: 'CC') ?></small>
                                </td>
                                <td class="col-name">
                                    <div class="patient-cell">
                                        <div class="patient-avatar"><?= pacienteIniciales($paciente) ?></div>
                                        <div class="patient-copy">
                                            <strong><?= esc(trim(($paciente['nombre'] ?? '') . ' ' . ($paciente['apellido'] ?? ''))) ?></strong>
                                            <span>ID #<?= esc($paciente['id']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="col-phone">
                                    <span class="patient-phone"><i class="bi bi-telephone" aria-hidden="true"></i><?= esc($paciente['telefono'] ?: '-') ?></span>
                                </td>
                                <td class="col-email"><span class="patient-email" title="<?= esc($paciente['email'] ?: '-') ?>"><?= esc($paciente['email'] ?: '-') ?></span></td>
                                <td class="col-city"><?= esc($paciente['ciudad'] ?: '-') ?></td>
                                <td class="col-eps"><?= esc($paciente['eps'] ?: 'Sin EPS') ?></td>
                                <td class="col-status">
                                    <span class="patient-status patient-status-<?= esc($paciente['estado'] ?: 'inactivo') ?>">
                                        <span></span><?= esc(estadoLabel((string) ($paciente['estado'] ?? 'inactivo'))) ?>
                                    </span>
                                </td>
                                <td class="col-portal">
                                    <?php if (!empty($paciente['portal_acceso_id'])): ?>
                                        <i class="bi bi-check-circle-fill patient-portal-icon patient-portal-icon-active" title="Con acceso al portal" aria-label="Con acceso al portal"></i>
                                    <?php else: ?>
                                        <i class="bi bi-dash-circle patient-portal-icon patient-portal-icon-inactive" title="Sin acceso al portal" aria-label="Sin acceso al portal"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="col-portal-last"><?= esc(formatPortalDate($paciente['portal_ultimo_acceso'] ?? null)) ?></td>
                                <td class="col-account">
                                    <?php
                                    $portalEstado = estadoCuentaPacienteLabel($paciente['portal_estado'] ?? null);
                                    if (!empty($paciente['portal_acceso_id']) && (int) ($paciente['portal_debe_cambiar_password'] ?? 0) === 1) {
                                        $portalEstado .= ' - cambio pendiente';
                                    }
                                    ?>
                                    <span class="patient-chip portal-account-<?= esc($paciente['portal_estado'] ?? 'none') ?>"><?= esc($portalEstado) ?></span>
                                </td>
                                <td class="col-actions">
                                    <div class="patient-actions">
                                        <a href="ver.php?id=<?= urlencode((string) $paciente['id']) ?>" class="patient-action patient-action-view" data-tooltip="Ver paciente" aria-label="Ver paciente"><i class="bi bi-eye" aria-hidden="true"></i></a>
                                        <?php if (can('pacientes.editar')): ?>
                                            <a href="editar.php?id=<?= urlencode((string) $paciente['id']) ?>" class="patient-action patient-action-edit" data-tooltip="Editar paciente" aria-label="Editar paciente"><i class="bi bi-pencil-square" aria-hidden="true"></i></a>
                                        <?php endif; ?>
                                        <?php if (can('pacientes.editar')): ?>
                                            <a href="eliminar.php?id=<?= urlencode((string) $paciente['id']) ?>&accion=inactivar" class="patient-action patient-action-inactivate" data-tooltip="Inactivar paciente" aria-label="Inactivar paciente"><i class="bi bi-person-x" aria-hidden="true"></i></a>
                                        <?php endif; ?>
                                        <?php if (can('pacientes.eliminar')): ?>
                                            <a href="eliminar.php?id=<?= urlencode((string) $paciente['id']) ?>&accion=eliminar" class="patient-action patient-action-delete" data-tooltip="Eliminar definitivamente" aria-label="Eliminar definitivamente"><i class="bi bi-trash3" aria-hidden="true"></i></a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <nav class="patients-pagination" id="pacientesPagination" aria-label="Paginación pacientes">
        <ul>
            <li class="<?= $page <= 1 ? 'disabled' : '' ?>">
                <a href="?search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" data-page="<?= max(1, $page - 1) ?>">Anterior</a>
            </li>
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="<?= $i === $page ? 'active' : '' ?>">
                    <a href="?search=<?= urlencode($search) ?>&page=<?= $i ?>" data-page="<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <li class="<?= $page >= $totalPaginas ? 'disabled' : '' ?>">
                <a href="?search=<?= urlencode($search) ?>&page=<?= min($totalPaginas, $page + 1) ?>" data-page="<?= min($totalPaginas, $page + 1) ?>">Siguiente</a>
            </li>
        </ul>
    </nav>
</div>

<?php $jsAdicional = 'pacientes.js'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
