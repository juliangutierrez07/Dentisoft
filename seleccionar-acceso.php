<?php
/**
 * Selector de Acceso - DentiSoft 1.0
 * Página intermedia para elegir entre Acceso Clínico y Portal del Paciente
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/session.php';

$siteUrl = BASE_URL . '/';
$loginClinico = BASE_URL . '/login.php';
$loginPaciente = BASE_URL . '/portal-login.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Tipo de Acceso - DentiSoft</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/seleccionar-acceso-premium.css" rel="stylesheet">
</head>
<body>
    <div class="access-selector-container">
        <!-- Background Effects -->
        <div class="access-bg-glow"></div>
        <div class="access-bg-particles"></div>

        <!-- Header -->
        <header class="access-header">
            <a href="<?= $siteUrl ?>" class="access-brand">
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="DentiSoft">
                <span>DentiSoft</span>
            </a>
        </header>

        <!-- Main Content -->
        <main class="access-main">
            <div class="access-content">
                <div class="access-title-section">
                    <p class="access-eyebrow">Bienvenido a DentiSoft</p>
                    <h1 class="access-title">Selecciona tu tipo de acceso</h1>
                    <p class="access-subtitle">Elige la opción correspondiente a tu rol para ingresar al sistema.</p>
                </div>

                <div class="access-options-grid">
                    <!-- Clinical Access Card -->
                    <article class="access-card access-card-clinico">
                        <div class="access-card-icon">
                            <i class="bi bi-hospital"></i>
                        </div>
                        <div class="access-card-content">
                            <h2 class="access-card-title">Acceso Clínico</h2>
                            <p class="access-card-description">
                                Para administradores, odontólogos y asistentes del consultorio.
                            </p>
                            <ul class="access-card-features">
                                <li><i class="bi bi-check-circle"></i> Gestión completa del consultorio</li>
                                <li><i class="bi bi-check-circle"></i> Agenda y citas</li>
                                <li><i class="bi bi-check-circle"></i> Historias clínicas</li>
                                <li><i class="bi bi-check-circle"></i> Facturación y reportes</li>
                            </ul>
                        </div>
                        <a href="<?= $loginClinico ?>" class="access-card-btn access-btn-primary">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span>Ingresar al sistema</span>
                        </a>
                    </article>

                    <!-- Patient Portal Card -->
                    <article class="access-card access-card-paciente">
                        <div class="access-card-icon">
                            <i class="bi bi-person-check"></i>
                        </div>
                        <div class="access-card-content">
                            <h2 class="access-card-title">Portal del Paciente</h2>
                            <p class="access-card-description">
                                Consulta tus citas, facturas, tratamientos e información personal.
                            </p>
                            <ul class="access-card-features">
                                <li><i class="bi bi-check-circle"></i> Ver tus citas programadas</li>
                                <li><i class="bi bi-check-circle"></i> Consultar facturas y pagos</li>
                                <li><i class="bi bi-check-circle"></i> Actualizar tu perfil</li>
                                <li><i class="bi bi-check-circle"></i> Historial de tratamientos</li>
                            </ul>
                        </div>
                        <a href="<?= $loginPaciente ?>" class="access-card-btn access-btn-secondary">
                            <i class="bi bi-person"></i>
                            <span>Ingresar como paciente</span>
                        </a>
                    </article>
                </div>

                <!-- Back to Home -->
                <div class="access-back">
                    <a href="<?= $siteUrl ?>">
                        <i class="bi bi-arrow-left"></i>
                        <span>Volver al inicio</span>
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
