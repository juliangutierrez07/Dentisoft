<?php
/**
 * Sidebar — DentiSoft 1.0
 * Menú lateral con navegación por rol
 */
$usuario = currentUser();
// Usar helpers `can()` para ocultar/mostrar módulos

// Determinar página activa
$paginaActual = $_SERVER['REQUEST_URI'] ?? '';

function menuActivo(string $ruta): string {
    global $paginaActual;
    return (strpos($paginaActual, $ruta) !== false) ? 'active' : '';
}
?>
<aside class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="logo-icon">🦷</div>
            <div class="logo-text">
                <span class="logo-title">DentiSoft</span>
                <span class="logo-subtitle">Odontología</span>
            </div>
        </div>
    </div>

    <!-- Navegación -->
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link <?= menuActivo('dashboard.php') ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Separador: Gestión Clínica -->
            <li class="nav-section">Gestión Clínica</li>

            <!-- Pacientes -->
            <?php if (can('pacientes.ver')): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/modules/pacientes/index.php" class="nav-link <?= menuActivo('/pacientes/') ?>">
                    <i class="bi bi-people-fill"></i>
                    <span>Pacientes</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Historia Clínica -->
            <?php if (can('historias.ver')): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/modules/historia_clinica/index.php" class="nav-link <?= menuActivo('/historia_clinica/') ?>">
                    <i class="bi bi-file-earmark-medical-fill"></i>
                    <span>Historia Clínica</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Citas -->
            <?php if (can('citas.ver')): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/modules/citas/index.php" class="nav-link <?= menuActivo('/citas/') ?>">
                    <i class="bi bi-calendar-check-fill"></i>
                    <span>Citas</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Tratamientos -->
            <?php if (can('tratamientos.ver')): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/modules/tratamientos/index.php" class="nav-link <?= menuActivo('/tratamientos/') ?>">
                    <i class="bi bi-clipboard2-pulse-fill"></i>
                    <span>Tratamientos</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Separador: Administración -->
            <?php if (can('facturacion.ver')): ?>
            <li class="nav-section">Administración</li>

            <!-- Facturación -->
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/modules/facturacion/index.php" class="nav-link <?= menuActivo('/facturacion/') ?>">
                    <i class="bi bi-receipt-cutoff"></i>
                    <span>Facturación</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Reportes -->
            <?php if (can('reportes.ver')): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/modules/reportes/index.php" class="nav-link <?= menuActivo('/reportes/') ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                    <span>Reportes</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Usuarios / Sistema -->
            <?php if (can('usuarios.ver') || can('sistema.config')): ?>
            <li class="nav-section">Sistema</li>
            <?php if (can('usuarios.ver')): ?>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>/modules/usuarios/index.php" class="nav-link <?= menuActivo('/usuarios/') ?>">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>Usuarios</span>
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Footer del sidebar -->
    <div class="sidebar-footer">
        <div class="sidebar-user-mini">
            <div class="user-avatar-sm">
                <?= strtoupper(mb_substr($usuario['nombre'], 0, 1)) ?>
            </div>
            <div class="user-info-sm">
                <span><?= htmlspecialchars($usuario['nombre']) ?></span>
                <small><?= htmlspecialchars(ucfirst($usuario['rol'])) ?></small>
            </div>
        </div>
    </div>
</aside>

<!-- Overlay para móvil -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
