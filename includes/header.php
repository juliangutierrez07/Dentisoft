<?php
/**
 * Header — DentiSoft 1.0
 * Incluye <head>, navbar superior y apertura del layout
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();

$usuario = currentUser();
$paginaTitulo = $paginaTitulo ?? 'Dashboard';

// Contar notificaciones no leídas
$totalNotificaciones = 0;
try {
    $db = getDB();
    $stmtNot = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE (usuario_id = :uid OR usuario_id IS NULL) AND leida = 0");
    $stmtNot->execute([':uid' => $usuario['id']]);
    $totalNotificaciones = (int) $stmtNot->fetchColumn();
} catch (PDOException $e) {
    error_log('Notificaciones error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($paginaTitulo) ?> — <?= APP_NAME ?></title>
    <meta name="description" content="<?= APP_DESCRIPTION ?> - <?= htmlspecialchars($paginaTitulo) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <?php if (isset($cssAdicional)): ?>
        <link href="<?= BASE_URL ?>/assets/css/<?= $cssAdicional ?>" rel="stylesheet">
    <?php endif; ?>
</head>
<body>
    <!-- ═══ NAVBAR SUPERIOR ═══ -->
    <nav class="navbar-top">
        <div class="navbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menú">
                <i class="bi bi-list"></i>
            </button>
            <div class="breadcrumb-nav">
                <span class="breadcrumb-item"><i class="bi bi-house-door"></i> <?= APP_NAME ?></span>
                <i class="bi bi-chevron-right breadcrumb-sep"></i>
                <span class="breadcrumb-item active"><?= htmlspecialchars($paginaTitulo) ?></span>
            </div>
        </div>
        <div class="navbar-right">
            <!-- Notificaciones (nuevo diseño) -->
            <div class="dropdown">
                <button class="navbar-icon-btn" data-bs-toggle="dropdown" aria-label="Notificaciones" id="btnNotificaciones">
                    <i class="bi bi-bell"></i>
                    <?php if ($totalNotificaciones > 0): ?>
                        <span class="badge-notif" id="notifBadge"><?= $totalNotificaciones > 9 ? '9+' : $totalNotificaciones ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end dropdown-notif">
                    <div class="dropdown-header-custom">
                        <div>
                            <strong>Centro de notificaciones</strong>
                            <div class="small text-muted">Últimas alertas y actividad</div>
                        </div>
                        <?php if ($totalNotificaciones > 0): ?>
                            <a href="#" id="marcarTodasLeidas" class="text-decoration-none">Marcar todas</a>
                        <?php endif; ?>
                    </div>
                    <div id="listaNotificaciones" class="notif-list">
                        <div class="notif-empty text-center p-4 text-muted">
                            <i class="bi bi-bell-slash" style="font-size:28px"></i>
                            <div class="mt-2">No hay notificaciones</div>
                        </div>
                        <div class="notif-loading d-none text-center p-3 text-muted">
                            <div class="spinner-border spinner-border-sm" role="status"></div>
                            <small class="d-block mt-1">Cargando...</small>
                        </div>
                        <!-- Items cargados vía JS -->
                    </div>
                    <div class="dropdown-footer text-center p-2 border-top">
                        <a href="<?= BASE_URL ?>/modules/notificaciones/index.php" class="small text-primary">Ver todas las notificaciones</a>
                    </div>
                </div>
            </div>

            <!-- Usuario (nuevo diseño) -->
            <div class="dropdown">
                <button class="navbar-user-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-avatar user-avatar-lg">
                        <?= strtoupper(mb_substr($usuario['nombre'], 0, 1) . mb_substr($usuario['apellido'], 0, 1)) ?>
                    </div>
                    <div class="user-info d-none d-md-block">
                        <span class="user-name"><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></span>
                        <span class="user-role-badge"><span class="role-badge"><?= htmlspecialchars(ucfirst($usuario['rol'])) ?></span></span>
                    </div>
                    <i class="bi bi-chevron-down ms-1 d-none d-md-inline"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end dropdown-profile p-0">
                    <div class="profile-head p-3 d-flex gap-3 align-items-center">
                        <div class="user-avatar user-avatar-xl"><?= strtoupper(mb_substr($usuario['nombre'], 0, 1) . mb_substr($usuario['apellido'], 0, 1)) ?></div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="profile-name"><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></div>
                                    <div class="profile-email small text-muted"><?= htmlspecialchars($usuario['email']) ?></div>
                                </div>
                                <div class="text-end">
                                    <span class="role-badge-lg"><?= htmlspecialchars(ucfirst($usuario['rol'])) ?></span>
                                </div>
                            </div>
                            <div class="mt-2 d-flex align-items-center gap-2 small text-muted">
                                <i class="bi bi-clock-history"></i>
                                <span>Últ. acceso: <strong class="text-primary-light"><?= htmlspecialchars($_SESSION['ultimo_acceso'] ?? 'Nunca') ?></strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="profile-perms px-3 pb-3">
                        <?php $perms = getUserPermissions(); $permCount = count($perms); ?>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="small text-muted">Permisos asignados</div>
                            <div class="small text-white fw-bold"><?= $permCount ?> permisos</div>
                        </div>
                        <div class="mt-2">
                            <a href="#" class="btn btn-sm btn-outline-primary view-perms">Ver detalles</a>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <ul class="list-unstyled m-0 p-2">
                        <li><a class="dropdown-item d-flex align-items-center" href="<?= BASE_URL ?>/modules/usuarios/editar.php?id=<?= $usuario['id'] ?>"><i class="bi bi-person-circle me-3"></i>Mi Perfil</a></li>
                        <li><a class="dropdown-item d-flex align-items-center" href="<?= BASE_URL ?>/modules/config/configuracion.php"><i class="bi bi-gear me-3"></i>Configuración</a></li>
                        <li><a class="dropdown-item d-flex align-items-center" href="<?= BASE_URL ?>/help.php"><i class="bi bi-question-circle me-3"></i>Ayuda</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item d-flex align-items-center text-danger" href="<?= BASE_URL ?>/logout.php"><i class="bi bi-box-arrow-right me-3"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <script>
        // Añade una animación sutil al badge cuando haya notificaciones
        (function(){
            try {
                var badge = document.getElementById('notifBadge');
                if (badge) {
                    // aplicar pulso solo si hay notificaciones
                    badge.classList.add('pulse');
                    badge.addEventListener('mouseover', function(){ badge.classList.remove('pulse'); });
                    badge.addEventListener('mouseout', function(){ badge.classList.add('pulse'); });
                }
            } catch (e) {
                console && console.warn && console.warn('Notif badge script error', e);
            }
        })();
    </script>

    <!-- ═══ SIDEBAR ═══ -->
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- ═══ CONTENIDO PRINCIPAL ═══ -->
    <main class="main-content" id="mainContent">
        <!-- Contenedor de alertas -->
        <div id="alert-container">
            <?php
            $alerta = getAlerta();
            if ($alerta): ?>
                <div class="alert alert-<?= $alerta['tipo'] ?> alert-dismissible fade show shadow-sm" role="alert">
                    <?php if ($alerta['tipo'] === 'success'): ?><i class="bi bi-check-circle me-2"></i>
                    <?php elseif ($alerta['tipo'] === 'danger'): ?><i class="bi bi-exclamation-triangle me-2"></i>
                    <?php elseif ($alerta['tipo'] === 'warning'): ?><i class="bi bi-exclamation-circle me-2"></i>
                    <?php else: ?><i class="bi bi-info-circle me-2"></i>
                    <?php endif; ?>
                    <?= htmlspecialchars($alerta['mensaje']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Componente de warning de email -->
        <?php require_once __DIR__ . '/email-warning.php'; ?>
