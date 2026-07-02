<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('historias.ver');

$paginaTitulo = 'Historia Clínica';
$cssAdicional = 'historia-premium.css';
$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
$limit = REGISTROS_POR_PAGINA;
$offset = ($page - 1) * $limit;
$where = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where = 'WHERE (
        p.nombre LIKE :term_paciente_nombre
        OR p.apellido LIKE :term_paciente_apellido
        OR hc.numero_historia LIKE :term_numero
        OR p.numero_documento LIKE :term_documento
    )';
    $term = '%' . $search . '%';
    $params = [
        ':term_paciente_nombre' => $term,
        ':term_paciente_apellido' => $term,
        ':term_numero' => $term,
        ':term_documento' => $term,
    ];
}

try {
    $db = getDB();
    $countSql = "SELECT COUNT(*) FROM historias_clinicas hc
        JOIN pacientes p ON hc.paciente_id = p.id
        JOIN usuarios u ON hc.odontologo_id = u.id
        $where";
    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute($params);
    $totalRegistros = (int) $stmtCount->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalRegistros / $limit));
    $page = min($page, $totalPaginas);
    $offset = ($page - 1) * $limit;

    $sql = "SELECT hc.id, hc.numero_historia, hc.fecha_apertura, hc.estado,
            p.numero_documento, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido,
            u.nombre AS odontologo_nombre, u.apellido AS odontologo_apellido
        FROM historias_clinicas hc
        JOIN pacientes p ON hc.paciente_id = p.id
        JOIN usuarios u ON hc.odontologo_id = u.id
        $where
        ORDER BY hc.fecha_apertura DESC, hc.id DESC
        LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $historias = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Historia Clínica Index error: ' . $e->getMessage());
    $historias = [];
    $totalRegistros = 0;
    $totalPaginas = 1;
}

function h(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function initials(array $historia): string {
    $first = trim((string) ($historia['paciente_nombre'] ?? ''));
    $last = trim((string) ($historia['paciente_apellido'] ?? ''));
    $value = mb_substr($first, 0, 1) . mb_substr($last, 0, 1);
    return h(mb_strtoupper($value !== '' ? $value : '--'));
}

function estadoHistoria(string $estado): string {
    return $estado === 'activa' ? 'Activa' : 'Archivada';
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="historia-page">
    <section class="historia-hero">
        <div>
            <span class="historia-kicker">Expedientes clínicos</span>
            <h1>Historia Clínica</h1>
            <p>Consulta, actualiza y complementa la información clínica de tus pacientes con una vista clara y profesional.</p>
        </div>
        <?php if (can('historias.crear')): ?>
            <a href="crear.php" class="historia-primary-btn">
                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                <span>Nueva Historia Clínica</span>
            </a>
        <?php endif; ?>
    </section>

    <section class="historia-toolbar">
        <form id="historiaSearchForm" class="historia-search-form" method="GET" action="index.php">
            <div class="historia-search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <input id="searchHistoria" type="search" name="search" autocomplete="off" placeholder="Buscar paciente, documento o número de historia" value="<?= h($search) ?>" aria-label="Buscar historias clínicas">
                <button type="button" id="clearHistoriaSearch" class="historia-search-clear" aria-label="Limpiar búsqueda">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <button type="submit" class="historia-search-submit">
                <i class="bi bi-arrow-return-left" aria-hidden="true"></i>
                <span>Buscar</span>
            </button>
        </form>
        <div class="historia-count" id="historiaResultCount"><?= number_format($totalRegistros) ?> registros</div>
    </section>

    <section class="historia-table-shell">
        <div class="historia-table-scroll">
            <table class="historia-table">
                <thead>
                    <tr>
                        <th class="col-code">Número</th>
                        <th class="col-patient">Paciente</th>
                        <th class="col-doctor">Odontólogo</th>
                        <th class="col-date">Fecha apertura</th>
                        <th class="col-status">Estado</th>
                        <th class="col-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody id="historiaTableBody">
                    <?php if (empty($historias)): ?>
                        <tr><td colspan="6"><div class="historia-empty"><i class="bi bi-journal-medical"></i><strong>No se encontraron historias clínicas</strong><span>Prueba con otro paciente, documento o número de historia.</span></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($historias as $historia): ?>
                            <tr data-historia-id="<?= h($historia['id']) ?>">
                                <td class="col-code"><span class="history-code"><?= h($historia['numero_historia']) ?></span></td>
                                <td class="col-patient">
                                    <div class="history-patient">
                                        <div class="history-avatar"><?= initials($historia) ?></div>
                                        <div>
                                            <strong><?= h(trim(($historia['paciente_nombre'] ?? '') . ' ' . ($historia['paciente_apellido'] ?? ''))) ?></strong>
                                            <span><?= h($historia['numero_documento']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="col-doctor">
                                    <strong><?= h(trim(($historia['odontologo_nombre'] ?? '') . ' ' . ($historia['odontologo_apellido'] ?? ''))) ?></strong>
                                    <span>Odontólogo tratante</span>
                                </td>
                                <td class="col-date"><span><i class="bi bi-calendar3"></i><?= h(date('d/m/Y', strtotime($historia['fecha_apertura']))) ?></span></td>
                                <td class="col-status"><span class="history-status history-status-<?= h($historia['estado']) ?>"><i></i><?= h(estadoHistoria((string) $historia['estado'])) ?></span></td>
                                <td class="col-actions">
                                    <div class="history-actions">
                                        <a href="ver.php?id=<?= urlencode((string) $historia['id']) ?>" class="history-action" title="Ver"><i class="bi bi-eye"></i></a>
                                        <?php if (can('historias.editar')): ?>
                                            <a href="editar.php?id=<?= urlencode((string) $historia['id']) ?>" class="history-action history-action-edit" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                        <?php endif; ?>
                                        <?php if (can('historias.ver')): ?>
                                            <a href="odontograma.php?historia_id=<?= urlencode((string) $historia['id']) ?>" class="history-action history-action-chart" title="Seguimiento"><i class="bi bi-graph-up"></i></a>
                                        <?php endif; ?>
                                        <?php if (can('historias.adjuntar')): ?>
                                            <a href="adjuntos.php?historia_id=<?= urlencode((string) $historia['id']) ?>" class="history-action history-action-clip" title="Adjuntos"><i class="bi bi-paperclip"></i></a>
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

    <nav class="historia-pagination" id="paginacionHistoria" aria-label="Paginación historia clínica">
        <ul>
            <li class="<?= $page <= 1 ? 'disabled' : '' ?>"><a href="?search=<?= urlencode($search) ?>&page=<?= max(1, $page - 1) ?>" data-page="<?= max(1, $page - 1) ?>">Anterior</a></li>
            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <li class="<?= $i === $page ? 'active' : '' ?>"><a href="?search=<?= urlencode($search) ?>&page=<?= $i ?>" data-page="<?= $i ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <li class="<?= $page >= $totalPaginas ? 'disabled' : '' ?>"><a href="?search=<?= urlencode($search) ?>&page=<?= min($totalPaginas, $page + 1) ?>" data-page="<?= min($totalPaginas, $page + 1) ?>">Siguiente</a></li>
        </ul>
    </nav>
</div>
<?php $jsAdicional = 'historia_clinica.js'; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
