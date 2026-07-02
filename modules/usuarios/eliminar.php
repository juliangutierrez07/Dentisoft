<?php
/**
 * Eliminacion segura de usuarios.
 *
 * Solo administradores pueden acceder. Si el usuario tiene registros asociados,
 * se aplica baja logica para conservar la integridad clinica y administrativa.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

requirePermission('usuarios.eliminar');

$accion = trim((string) ($_GET['accion'] ?? $_POST['accion'] ?? 'eliminar'));
if (!in_array($accion, ['inactivar', 'eliminar'], true)) {
    $accion = 'eliminar';
}

$paginaTitulo = $accion === 'eliminar' ? 'Eliminar Usuario' : 'Inactivar Usuario';
$cssAdicional = 'usuarios-premium.css';
$usuarioId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$usuarioActual = currentUser();
$usuarioActualId = (int) ($usuarioActual['id'] ?? 0);

function esc(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function roleLabel(string $rol): string {
    return match ($rol) {
        'administrador' => 'Administrador',
        'odontologo' => 'Odontologo',
        'asistente' => 'Asistente',
        default => ucfirst($rol),
    };
}

function obtenerUsuarioGestionable(PDO $db, int $usuarioId): ?array {
    $stmt = $db->prepare("
        SELECT u.id, u.nombre, u.apellido, u.email, u.estado, u.created_at, u.updated_at, u.rol_id, r.nombre AS rol
        FROM usuarios u
        LEFT JOIN roles r ON u.rol_id = r.id
        WHERE u.id = :id
    ");
    $stmt->execute([':id' => $usuarioId]);
    $usuario = $stmt->fetch();
    return $usuario ?: null;
}

function conteosRelacionesUsuario(PDO $db, int $usuarioId): array {
    $queries = [
        'citas como odontologo' => "SELECT COUNT(*) FROM citas WHERE odontologo_id = :id",
        'citas creadas' => "SELECT COUNT(*) FROM citas WHERE created_by = :id",
        'historias clinicas' => "SELECT COUNT(*) FROM historias_clinicas WHERE odontologo_id = :id",
        'odontogramas' => "SELECT COUNT(*) FROM odontograma WHERE usuario_id = :id",
        'imagenes clinicas' => "SELECT COUNT(*) FROM imagenes_clinicas WHERE usuario_id = :id",
        'tratamientos' => "SELECT COUNT(*) FROM planes_tratamiento WHERE odontologo_id = :id",
        'sesiones de tratamiento' => "SELECT COUNT(*) FROM sesiones_tratamiento WHERE odontologo_id = :id",
        'facturas' => "SELECT COUNT(*) FROM facturas WHERE odontologo_id = :id OR created_by = :id",
        'pagos registrados' => "SELECT COUNT(*) FROM pagos WHERE registrado_por = :id",
        'notificaciones' => "SELECT COUNT(*) FROM notificaciones WHERE usuario_id = :id",
        'auditoria' => "SELECT COUNT(*) FROM audit_log WHERE usuario_id = :id",
    ];

    $conteos = [];
    foreach ($queries as $nombre => $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([':id' => $usuarioId]);
            $conteos[$nombre] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error al contar relaciones de usuario ({$nombre}): " . $e->getMessage());
            $conteos[$nombre] = 0;
        }
    }

    return $conteos;
}

function relacionesBloqueantes(array $conteos): array {
    return array_keys(array_filter($conteos, static fn (int $total): bool => $total > 0));
}

function contarAdministradoresActivos(PDO $db): int {
    $stmt = $db->prepare("
        SELECT COUNT(*)
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        WHERE r.nombre = 'administrador'
          AND u.estado = 'activo'
    ");
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function esUltimoAdministradorActivo(PDO $db, array $usuario): bool {
    return ($usuario['rol'] ?? '') === 'administrador'
        && ($usuario['estado'] ?? '') === 'activo'
        && contarAdministradoresActivos($db) <= 1;
}

function inactivarUsuario(PDO $db, int $usuarioId): void {
    $update = $db->prepare("UPDATE usuarios SET estado = 'inactivo', updated_at = NOW() WHERE id = :id");
    $update->execute([':id' => $usuarioId]);
}

if (!$usuarioId) {
    setAlerta('Usuario no valido.', 'danger');
    header('Location: index.php');
    exit;
}

if ((int) $usuarioId === $usuarioActualId) {
    setAlerta('No puedes eliminar tu propia cuenta. Solicita a otro administrador que lo haga.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    $usuarioSeleccionado = obtenerUsuarioGestionable($db, (int) $usuarioId);

    if (!$usuarioSeleccionado) {
        setAlerta('Usuario no encontrado.', 'danger');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Usuarios eliminar carga error: ' . $e->getMessage());
    setAlerta('Error interno al cargar el usuario.', 'danger');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    try {
        $db->beginTransaction();

        $usuarioSeleccionado = obtenerUsuarioGestionable($db, (int) $usuarioId);
        if (!$usuarioSeleccionado) {
            $db->rollBack();
            setAlerta('Usuario no encontrado.', 'danger');
            header('Location: index.php');
            exit;
        }

        if ((int) $usuarioId === $usuarioActualId) {
            $db->rollBack();
            setAlerta('No puedes eliminar tu propia cuenta.', 'danger');
            header('Location: index.php');
            exit;
        }

        if (esUltimoAdministradorActivo($db, $usuarioSeleccionado)) {
            $db->rollBack();
            setAlerta('No se puede inactivar ni eliminar el ultimo administrador activo del sistema.', 'danger');
            header('Location: eliminar.php?id=' . urlencode((string) $usuarioId) . '&accion=' . urlencode($accion));
            exit;
        }

        if ($accion === 'inactivar') {
            inactivarUsuario($db, (int) $usuarioId);
            registrarAuditoria('inactivar', 'usuarios', (int) $usuarioId, $usuarioSeleccionado, [
                'estado' => 'inactivo',
                'motivo' => 'Baja logica solicitada por administrador',
            ]);

            $db->commit();
            setAlerta('Usuario inactivado correctamente. Su informacion y registros se conservaron.', 'success');
            header('Location: index.php');
            exit;
        }

        $conteos = conteosRelacionesUsuario($db, (int) $usuarioId);
        $bloqueantes = relacionesBloqueantes($conteos);

        if (!empty($bloqueantes)) {
            inactivarUsuario($db, (int) $usuarioId);
            registrarAuditoria('inactivar_por_relaciones', 'usuarios', (int) $usuarioId, $usuarioSeleccionado, [
                'estado' => 'inactivo',
                'motivo' => 'Usuario con registros relacionados',
                'relaciones' => $bloqueantes,
                'conteos' => array_filter($conteos, static fn (int $total): bool => $total > 0),
            ]);

            $db->commit();
            setAlerta('Usuario inactivado automaticamente porque tiene informacion asociada. Los registros se conservaron.', 'warning');
            header('Location: index.php');
            exit;
        }

        $delete = $db->prepare("DELETE FROM usuarios WHERE id = :id");
        $delete->execute([':id' => $usuarioId]);

        registrarAuditoria('eliminar_permanente', 'usuarios', (int) $usuarioId, $usuarioSeleccionado, [
            'motivo' => 'Usuario sin registros relacionados',
        ]);

        $db->commit();
        setAlerta('Usuario eliminado definitivamente.', 'success');
        header('Location: index.php');
        exit;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Usuarios eliminar error: ' . $e->getMessage());
        $mensaje = $accion === 'eliminar'
            ? 'No fue posible eliminar el usuario. Verifica que no tenga informacion asociada.'
            : 'No fue posible inactivar el usuario. Intenta nuevamente.';
        setAlerta($mensaje, 'danger');
        header('Location: index.php');
        exit;
    }
}

$conteosRelaciones = [];
$relacionesUsuario = [];
if ($accion === 'eliminar') {
    $conteosRelaciones = conteosRelacionesUsuario($db, (int) $usuarioId);
    $relacionesUsuario = relacionesBloqueantes($conteosRelaciones);
}

$esUltimoAdmin = esUltimoAdministradorActivo($db, $usuarioSeleccionado);
$nombreCompleto = trim(($usuarioSeleccionado['nombre'] ?? '') . ' ' . ($usuarioSeleccionado['apellido'] ?? ''));
$estadoActual = ucfirst((string) ($usuarioSeleccionado['estado'] ?? 'inactivo'));
$fechaRegistro = date('d/m/Y', strtotime((string) ($usuarioSeleccionado['created_at'] ?? 'now')));
$rol = roleLabel((string) ($usuarioSeleccionado['rol'] ?? 'desconocido'));
$requiereInactivacion = $accion === 'eliminar' && !empty($relacionesUsuario);
$modalId = $accion === 'eliminar' && !$requiereInactivacion ? 'confirmDeleteUserModal' : 'confirmDeactivateUserModal';
$accionFinalTexto = $accion === 'eliminar' && !$requiereInactivacion ? 'Eliminar definitivamente' : 'Inactivar usuario';
$accionFinalIcono = $accion === 'eliminar' && !$requiereInactivacion ? 'bi-trash3' : 'bi-pause-circle';
$botonFinalClase = $accion === 'eliminar' && !$requiereInactivacion ? 'delete-btn-danger' : 'delete-btn-warning';
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
                    <h1 class="delete-title">Eliminar usuario</h1>
                    <p class="delete-subtitle">Accion critica disponible solo para administradores. Si hay registros asociados, el sistema aplicara inactivacion para conservar la integridad de los datos.</p>
                    <div class="delete-alert-badge delete-alert-danger">
                        <i class="bi bi-shield-exclamation"></i>
                        <span>Revision de seguridad</span>
                    </div>
                <?php else: ?>
                    <h1 class="delete-title">Inactivar usuario</h1>
                    <p class="delete-subtitle">El usuario dejara de poder acceder al sistema, pero su informacion y registros se conservaran.</p>
                    <div class="delete-alert-badge delete-alert-warning">
                        <i class="bi bi-pause-circle"></i>
                        <span>Cambio de estado</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="delete-user-info">
                <div class="delete-user-row">
                    <span class="delete-user-label">Nombre completo</span>
                    <span class="delete-user-value delete-user-value-highlight"><?= esc($nombreCompleto ?: 'Usuario sin nombre') ?></span>
                </div>
                <div class="delete-user-row">
                    <span class="delete-user-label">Email</span>
                    <span class="delete-user-value"><?= esc($usuarioSeleccionado['email'] ?? '-') ?></span>
                </div>
                <div class="delete-user-row">
                    <span class="delete-user-label">Rol</span>
                    <span class="delete-user-value"><span class="delete-role-badge"><?= esc($rol) ?></span></span>
                </div>
                <div class="delete-user-row">
                    <span class="delete-user-label">Estado actual</span>
                    <span class="delete-user-value"><span class="delete-status-badge"><?= esc($estadoActual) ?></span></span>
                </div>
                <div class="delete-user-row">
                    <span class="delete-user-label">Registrado desde</span>
                    <span class="delete-user-value"><?= esc($fechaRegistro) ?></span>
                </div>
            </div>

            <?php if ($esUltimoAdmin): ?>
                <div class="delete-message delete-message-danger">
                    <i class="bi bi-lock"></i>
                    <span>No se puede inactivar ni eliminar el ultimo administrador activo del sistema. Crea otro administrador activo primero.</span>
                </div>
            <?php elseif ($requiereInactivacion): ?>
                <div class="delete-message delete-message-warning">
                    <i class="bi bi-info-circle"></i>
                    <span>Este usuario tiene <?= esc(implode(', ', array_slice($relacionesUsuario, 0, 3))) ?><?= count($relacionesUsuario) > 3 ? ' y mas' : '' ?> asociados. Se inactivara automaticamente en lugar de eliminarse.</span>
                </div>
            <?php elseif ($accion === 'eliminar'): ?>
                <div class="delete-message delete-message-info">
                    <i class="bi bi-info-circle"></i>
                    <span>Este usuario no tiene informacion asociada detectada y puede eliminarse permanentemente.</span>
                </div>
            <?php endif; ?>

            <?php if ($accion === 'eliminar' && !empty($relacionesUsuario)): ?>
                <div class="delete-relations">
                    <h3>Informacion asociada del usuario</h3>
                    <ul>
                        <?php foreach ($conteosRelaciones as $nombre => $cantidad): ?>
                            <?php if ($cantidad > 0): ?>
                                <li>
                                    <strong><?= esc($nombre) ?></strong>
                                    <span class="relation-count"><?= esc($cantidad) ?></span>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="delete-actions">
                <a href="index.php" class="delete-btn-cancel">
                    <i class="bi bi-x-lg"></i>
                    Cancelar
                </a>

                <?php if (!$esUltimoAdmin): ?>
                    <button type="button" class="<?= esc($botonFinalClase) ?>" data-bs-toggle="modal" data-bs-target="#<?= esc($modalId) ?>">
                        <i class="bi <?= esc($accionFinalIcono) ?>"></i>
                        <?= esc($accionFinalTexto) ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$esUltimoAdmin): ?>
<div class="modal fade user-delete-modal" id="<?= esc($modalId) ?>" tabindex="-1" aria-labelledby="<?= esc($modalId) ?>Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <div>
                        <span class="modal-kicker">Confirmacion requerida</span>
                        <h2 class="modal-title" id="<?= esc($modalId) ?>Label"><?= esc($accionFinalTexto) ?></h2>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-user-summary">
                        <span class="modal-user-avatar"><?= esc(mb_strtoupper(mb_substr($usuarioSeleccionado['nombre'] ?? 'U', 0, 1) . mb_substr($usuarioSeleccionado['apellido'] ?? 'S', 0, 1))) ?></span>
                        <div>
                            <strong><?= esc($nombreCompleto ?: 'Usuario sin nombre') ?></strong>
                            <small><?= esc($usuarioSeleccionado['email'] ?? '-') ?></small>
                        </div>
                    </div>
                    <div class="modal-user-details">
                        <div>
                            <span>Rol</span>
                            <strong><?= esc($rol) ?></strong>
                        </div>
                        <div>
                            <span>Estado</span>
                            <strong><?= esc($estadoActual) ?></strong>
                        </div>
                        <div>
                            <span>Fecha de registro</span>
                            <strong><?= esc($fechaRegistro) ?></strong>
                        </div>
                    </div>
                    <?php if ($accion === 'eliminar' && !$requiereInactivacion): ?>
                        <p>Esta accion eliminara el usuario de forma permanente porque no se encontraron registros relacionados.</p>
                    <?php else: ?>
                        <p>Esta accion inactivara el usuario. No podra iniciar sesion, pero sus registros historicos seguiran disponibles.</p>
                    <?php endif; ?>
                    <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="id" value="<?= esc($usuarioId) ?>">
                    <input type="hidden" name="accion" value="<?= esc($accion) ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="delete-btn-cancel" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="<?= esc($botonFinalClase) ?>">
                        <i class="bi <?= esc($accionFinalIcono) ?>"></i>
                        Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
