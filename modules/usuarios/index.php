<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('usuarios.ver');

$paginaTitulo = 'Usuarios';
$cssAdicional = 'usuarios-premium.css';
$usuarioActual = currentUser();
$usuarioActualId = (int) ($usuarioActual['id'] ?? 0);

try {
    $db = getDB();
    $stmt = $db->query("SELECT u.id, u.nombre, u.apellido, u.email, u.telefono, u.estado, u.ultimo_acceso, r.nombre AS rol
        FROM usuarios u
        JOIN roles r ON u.rol_id = r.id
        ORDER BY u.estado = 'activo' DESC, u.nombre, u.apellido");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Usuarios Index error: ' . $e->getMessage());
    $usuarios = [];
}

function esc(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatDateTime(mixed $dateTime, string $default = 'Sin registro'): string {
    $dateTime = trim((string) ($dateTime ?? ''));
    if ($dateTime === '') {
        return $default;
    }
    $timestamp = strtotime($dateTime);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $default;
}

function initials(array $usuario): string {
    $initials = mb_substr(trim((string) ($usuario['nombre'] ?? '')), 0, 1) . mb_substr(trim((string) ($usuario['apellido'] ?? '')), 0, 1);
    return esc(mb_strtoupper($initials ?: 'US'));
}

function roleLabel(string $rol): string {
    return match ($rol) {
        'administrador' => 'Administrador',
        'odontologo' => 'Odontologo',
        'asistente' => 'Asistente',
        default => ucfirst($rol),
    };
}

$stats = [
    'total' => count($usuarios),
    'activos' => count(array_filter($usuarios, fn($u) => ($u['estado'] ?? '') === 'activo')),
    'admins' => count(array_filter($usuarios, fn($u) => ($u['rol'] ?? '') === 'administrador')),
    'clinicos' => count(array_filter($usuarios, fn($u) => in_array(($u['rol'] ?? ''), ['odontologo', 'asistente'], true))),
];
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 users-page">
    <section class="users-hero">
        <div>
            <span class="users-kicker"><i class="bi bi-shield-lock"></i> Equipo y permisos</span>
            <h1>Usuarios</h1>
            <p>Gestiona accesos, roles operativos y estado del equipo clinico desde una experiencia segura y moderna.</p>
        </div>
        <?php if (can('usuarios.crear')): ?>
        <a href="crear.php" class="users-primary"><i class="bi bi-person-plus-fill"></i> Nuevo usuario</a>
        <?php endif; ?>
    </section>

    <section class="users-kpis">
        <article><span><i class="bi bi-people"></i></span><div><small>Total usuarios</small><strong class="mono"><?= number_format($stats['total']) ?></strong></div></article>
        <article><span><i class="bi bi-person-check"></i></span><div><small>Activos</small><strong class="mono"><?= number_format($stats['activos']) ?></strong></div></article>
        <article><span><i class="bi bi-stars"></i></span><div><small>Administradores</small><strong class="mono"><?= number_format($stats['admins']) ?></strong></div></article>
        <article><span><i class="bi bi-heart-pulse"></i></span><div><small>Equipo clinico</small><strong class="mono"><?= number_format($stats['clinicos']) ?></strong></div></article>
    </section>

    <section class="users-grid" aria-label="Lista de usuarios">
        <?php if (empty($usuarios)): ?>
            <div class="users-empty"><i class="bi bi-person-x"></i><strong>No hay usuarios registrados</strong><span>Crea el primer usuario para operar el sistema.</span></div>
        <?php else: ?>
            <?php foreach ($usuarios as $usuario): ?>
                <article class="user-card user-role-<?= esc($usuario['rol'] ?? 'desconocido') ?>">
                    <div class="user-card-top">
                        <div class="user-avatar-premium"><?= initials($usuario) ?></div>
                        <div class="user-card-actions">
                            <?php if (can('usuarios.editar')): ?>
                            <a href="editar.php?id=<?= esc($usuario['id'] ?? '') ?>" data-tooltip="Editar usuario"><i class="bi bi-pencil-square"></i></a>
                            <?php endif; ?>
                            <?php if (can('usuarios.eliminar') && (int) ($usuario['id'] ?? 0) !== $usuarioActualId): ?>
                            <a href="eliminar.php?id=<?= esc($usuario['id'] ?? '') ?>&accion=eliminar" class="user-card-delete" data-tooltip="Eliminar usuario"><i class="bi bi-trash3"></i></a>
                            <?php elseif (can('usuarios.eliminar')): ?>
                            <span class="user-card-action-disabled" data-tooltip="No puedes eliminar tu propia cuenta"><i class="bi bi-shield-lock"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="user-card-main">
                        <h2><?= esc(trim((string) (($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? '')))) ?></h2>
                        <p><?= esc($usuario['email'] ?? '') ?></p>
                    </div>
                    <div class="user-tags">
                        <span class="role-badge"><?= esc(roleLabel((string) ($usuario['rol'] ?? ''))) ?></span>
                        <span class="status-badge status-<?= esc($usuario['estado'] ?? 'desconocido') ?>"><?= esc(ucfirst((string) ($usuario['estado'] ?? 'desconocido'))) ?></span>
                    </div>
                    <dl class="user-meta">
                        <div><dt>Telefono</dt><dd><?= esc(($usuario['telefono'] ?? '') ?: 'No registrado') ?></dd></div>
                        <div><dt>Especialidad</dt><dd><?= esc(($usuario['rol'] ?? '') === 'odontologo' ? 'Odontologia general' : roleLabel((string) ($usuario['rol'] ?? '')) ) ?></dd></div>
                        <div><dt>Ultimo acceso</dt><dd><?= esc(formatDateTime($usuario['ultimo_acceso'] ?? null)) ?></dd></div>
                    </dl>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
