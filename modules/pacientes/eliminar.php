<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('pacientes.eliminar');

$accion = trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'inactivar'));
if (!in_array($accion, ['inactivar', 'eliminar'], true)) {
    $accion = 'inactivar';
}

$paginaTitulo = $accion === 'eliminar' ? 'Eliminar Paciente' : 'Inactivar Paciente';
$cssAdicional = 'pacientes-premium.css';
$pacienteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$usuarioActual = currentUser();
$esAdministrador = ($usuarioActual['rol'] ?? '') === 'administrador';

if (!$pacienteId) {
    setAlerta('Paciente no valido.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM pacientes WHERE id = :id");
    $stmt->execute([':id' => $pacienteId]);
    $paciente = $stmt->fetch();

    if (!$paciente) {
        setAlerta('Paciente no encontrado.', 'danger');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Pacientes accion carga error: ' . $e->getMessage());
    setAlerta('Error interno al cargar el paciente.', 'danger');
    header('Location: index.php');
    exit;
}

function esc(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function estadoPacienteLabel(string $estado): string {
    return match ($estado) {
        'activo' => 'Activo',
        default => 'Inactivo',
    };
}

function conteosRelacionesPaciente(PDO $db, int $pacienteId): array {
    $queries = [
        'citas' => "SELECT COUNT(*) FROM citas WHERE paciente_id = :id",
        'historias clinicas' => "SELECT COUNT(*) FROM historias_clinicas WHERE paciente_id = :id",
        'tratamientos' => "SELECT COUNT(*) FROM planes_tratamiento WHERE paciente_id = :id",
        'facturacion' => "SELECT COUNT(*) FROM facturas WHERE paciente_id = :id",
        'sesiones' => "SELECT COUNT(*)
            FROM sesiones_tratamiento st
            INNER JOIN planes_tratamiento pt ON pt.id = st.plan_id
            WHERE pt.paciente_id = :id",
    ];

    $conteos = [];
    foreach ($queries as $nombre => $sql) {
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $pacienteId]);
        $conteos[$nombre] = (int) $stmt->fetchColumn();
    }

    return $conteos;
}

function relacionesBloqueantes(array $conteos): array {
    return array_keys(array_filter($conteos, static fn (int $total): bool => $total > 0));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    if ($accion === 'eliminar' && !$esAdministrador) {
        setAlerta('Solo un administrador puede eliminar pacientes permanentemente.', 'danger');
        header('Location: index.php');
        exit;
    }

    try {
        if ($accion === 'inactivar') {
            $update = $db->prepare("UPDATE pacientes SET estado = 'inactivo', updated_at = NOW() WHERE id = :id");
            $update->execute([':id' => $pacienteId]);

            registrarAuditoria('inactivar', 'pacientes', $pacienteId, $paciente, ['estado' => 'inactivo']);
            setAlerta('Paciente inactivado correctamente. Su historial clinico y tratamientos se conservaron.', 'success');
            header('Location: index.php');
            exit;
        }

        $conteos = conteosRelacionesPaciente($db, $pacienteId);
        $bloqueantes = relacionesBloqueantes($conteos);

        if (!empty($bloqueantes)) {
            $detalle = implode(', ', $bloqueantes);
            setAlerta("No se puede eliminar este paciente porque tiene informacion asociada: {$detalle}. Usa Inactivar paciente para conservar su historial.", 'warning');
            header('Location: eliminar.php?id=' . urlencode((string) $pacienteId) . '&accion=eliminar');
            exit;
        }

        $delete = $db->prepare("DELETE FROM pacientes WHERE id = :id");
        $delete->execute([':id' => $pacienteId]);

        registrarAuditoria('eliminar_permanente', 'pacientes', $pacienteId, $paciente, null);
        setAlerta('Paciente eliminado definitivamente.', 'success');
        header('Location: index.php');
        exit;
    } catch (PDOException $e) {
        error_log('Pacientes accion error: ' . $e->getMessage());
        $mensaje = $accion === 'eliminar'
            ? 'No fue posible eliminar el paciente. Verifica que no tenga informacion asociada.'
            : 'No fue posible inactivar el paciente. Intenta nuevamente.';
        setAlerta($mensaje, 'danger');
        header('Location: index.php');
        exit;
    }
}

$conteosRelaciones = [];
$relacionesPaciente = [];
if ($accion === 'eliminar') {
    try {
        $conteosRelaciones = conteosRelacionesPaciente($db, $pacienteId);
        $relacionesPaciente = relacionesBloqueantes($conteosRelaciones);
    } catch (PDOException $e) {
        error_log('Pacientes relaciones error: ' . $e->getMessage());
        $conteosRelaciones = [];
        $relacionesPaciente = [];
    }
}

$nombreCompleto = trim(($paciente['nombre'] ?? '') . ' ' . ($paciente['apellido'] ?? ''));
$estadoActual = estadoPacienteLabel((string) ($paciente['estado'] ?? 'inactivo'));
$fechaRegistro = date('d/m/Y', strtotime((string) ($paciente['created_at'] ?? 'now')));
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<div class="delete-container">
    <div class="delete-content">
        <div class="delete-card">
            <div class="delete-header">
                <div class="delete-icon-wrapper <?= $accion === 'eliminar' ? 'delete-icon-danger' : 'delete-icon-warning' ?>">
                    <i class="bi <?= $accion === 'eliminar' ? 'bi-trash3' : 'bi-person-x' ?> delete-icon"></i>
                </div>

                <?php if ($accion === 'eliminar'): ?>
                    <h1 class="delete-title">Eliminar paciente permanentemente</h1>
                    <p class="delete-subtitle">Accion critica disponible solo para administradores y bloqueada si existen datos clinicos o administrativos.</p>
                    <div class="delete-alert-badge delete-alert-danger">
                        <i class="bi bi-shield-exclamation"></i>
                        <span>Eliminacion definitiva</span>
                    </div>
                <?php else: ?>
                    <h1 class="delete-title">Inactivar paciente</h1>
                    <p class="delete-subtitle">El paciente dejara de estar disponible para nuevas citas, pero conservara su historial clinico, tratamientos y facturacion.</p>
                    <div class="delete-alert-badge delete-alert-warning">
                        <i class="bi bi-pause-circle"></i>
                        <span>Cambio de estado</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="delete-patient-info">
                <div class="delete-patient-row">
                    <span class="delete-patient-label">Nombre completo</span>
                    <span class="delete-patient-value delete-patient-value-highlight"><?= esc($nombreCompleto ?: 'Paciente sin nombre') ?></span>
                </div>
                <div class="delete-patient-row">
                    <span class="delete-patient-label">Documento</span>
                    <span class="delete-patient-value"><?= esc(($paciente['tipo_documento'] ?? 'CC') . ' ' . ($paciente['numero_documento'] ?? '-')) ?></span>
                </div>
                <div class="delete-patient-row">
                    <span class="delete-patient-label">Estado actual</span>
                    <span class="delete-patient-value"><span class="delete-status-badge"><?= esc($estadoActual) ?></span></span>
                </div>
                <div class="delete-patient-row">
                    <span class="delete-patient-label">Registrado desde</span>
                    <span class="delete-patient-value"><?= esc($fechaRegistro) ?></span>
                </div>
            </div>

            <?php if ($accion === 'eliminar' && !$esAdministrador): ?>
                <div class="delete-message delete-message-danger">
                    <i class="bi bi-lock"></i>
                    <span>Solo administradores pueden eliminar pacientes permanentemente.</span>
                </div>
            <?php elseif ($accion === 'eliminar' && !empty($relacionesPaciente)): ?>
                <div class="delete-message delete-message-danger">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>No se puede eliminar este paciente porque tiene <?= esc(implode(', ', $relacionesPaciente)) ?> asociados. La accion recomendada es inactivar paciente.</span>
                </div>
            <?php elseif ($accion === 'eliminar'): ?>
                <div class="delete-message delete-message-danger">
                    <i class="bi bi-exclamation-octagon"></i>
                    <span>Esta accion eliminará completamente el paciente y no podrá recuperarse.</span>
                </div>
            <?php else: ?>
                <div class="delete-message">
                    <i class="bi bi-info-circle"></i>
                    <span>La inactivacion mantiene toda la informacion asociada y evita que se agenden citas nuevas para este paciente.</span>
                </div>
            <?php endif; ?>

            <div class="delete-actions">
                <a href="ver.php?id=<?= urlencode((string) $pacienteId) ?>" class="delete-btn-cancel">
                    <i class="bi bi-x-lg"></i>
                    <span>Cancelar</span>
                </a>

                <?php if ($accion === 'inactivar'): ?>
                    <form method="POST" data-prevent-double>
                        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= esc($pacienteId) ?>">
                        <input type="hidden" name="accion" value="inactivar">
                        <button type="submit" class="delete-btn-warning">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <i class="bi bi-person-x"></i>
                            <span>Inactivar paciente</span>
                        </button>
                    </form>
                <?php elseif ($esAdministrador && empty($relacionesPaciente)): ?>
                    <button type="button" class="delete-btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeletePatientModal">
                        <i class="bi bi-trash3"></i>
                        <span>Eliminar definitivamente</span>
                    </button>
                <?php else: ?>
                    <a href="eliminar.php?id=<?= urlencode((string) $pacienteId) ?>&accion=inactivar" class="delete-btn-warning">
                        <i class="bi bi-person-x"></i>
                        <span>Inactivar paciente</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($accion === 'eliminar' && $esAdministrador && empty($relacionesPaciente)): ?>
    <div class="modal fade patient-delete-modal" id="confirmDeletePatientModal" tabindex="-1" aria-labelledby="confirmDeletePatientTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="patient-delete-modal-icon"><i class="bi bi-trash3"></i></div>
                    <div>
                        <h2 class="modal-title" id="confirmDeletePatientTitle">¿Eliminar paciente permanentemente?</h2>
                        <p>Esta accion eliminará completamente el paciente y no podrá recuperarse.</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <dl class="patient-delete-summary">
                        <div><dt>Paciente</dt><dd><?= esc($nombreCompleto ?: 'Paciente sin nombre') ?></dd></div>
                        <div><dt>Documento</dt><dd><?= esc(($paciente['tipo_documento'] ?? 'CC') . ' ' . ($paciente['numero_documento'] ?? '-')) ?></dd></div>
                        <div><dt>Estado actual</dt><dd><?= esc($estadoActual) ?></dd></div>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="delete-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" data-prevent-double>
                        <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="id" value="<?= esc($pacienteId) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <button type="submit" class="delete-btn-danger">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            <i class="bi bi-trash3"></i>
                            <span>Eliminar definitivamente</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('confirmDeletePatientModal');
            if (modalElement && window.bootstrap) {
                window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        });
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
