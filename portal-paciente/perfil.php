<?php
/**
 * Portal del Paciente - Mi Perfil
 * FASE 2: Módulo de perfil del paciente
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/patient_portal.php';

requirePatientPasswordReady();

$pacienteSesion = currentPatient();
$alertaPortal = $_SESSION['alerta_portal_paciente'] ?? null;
unset($_SESSION['alerta_portal_paciente']);

$paciente = null;
$errores = [];
$exito = false;

// Handle form submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCSRF();
    
    $telefono = trim((string) ($_POST['telefono'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $passwordActual = (string) ($_POST['password_actual'] ?? '');
    $passwordNueva = (string) ($_POST['password_nueva'] ?? '');
    $passwordConfirmacion = (string) ($_POST['password_confirmacion'] ?? '');
    
    // Validate phone
    if ($telefono !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $telefono)) {
        $errores[] = 'El teléfono no tiene un formato válido.';
    }
    
    // Validate email
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no es válido.';
    }
    
    // Validate password change if attempting to change
    if ($passwordNueva !== '' || $passwordConfirmacion !== '') {
        if ($passwordActual === '') {
            $errores[] = 'Debes ingresar tu contraseña actual para cambiarla.';
        } elseif (strlen($passwordNueva) < 8) {
            $errores[] = 'La nueva contraseña debe tener mínimo 8 caracteres.';
        } elseif ($passwordNueva !== $passwordConfirmacion) {
            $errores[] = 'La confirmación de la nueva contraseña no coincide.';
        } elseif ($passwordNueva === ($pacienteSesion['documento'] ?? '')) {
            $errores[] = 'La nueva contraseña no puede ser igual a tu documento.';
        }
    }
    
    if (empty($errores)) {
        try {
            $db = getDB();
            
            // Get current patient data
            $stmt = $db->prepare("
                SELECT p.id, p.telefono, p.email, pa.password
                FROM pacientes p
                INNER JOIN paciente_accesos pa ON pa.paciente_id = p.id
                WHERE pa.id = :acceso_id AND pa.paciente_id = :paciente_id
                LIMIT 1
            ");
            $stmt->execute([
                ':acceso_id' => $pacienteSesion['acceso_id'],
                ':paciente_id' => $pacienteSesion['id'],
            ]);
            $paciente = $stmt->fetch();
            
            // Verify current password if trying to change it
            if ($passwordNueva !== '') {
                $hashActual = (string) ($paciente['password'] ?? '');
                if ($hashActual === '' || !password_verify($passwordActual, $hashActual)) {
                    $errores[] = 'La contraseña actual no es correcta.';
                }
            }
            
            if (empty($errores)) {
                // Update patient data
                $updatePaciente = $db->prepare("
                    UPDATE pacientes
                    SET telefono = :telefono,
                        email = :email,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $updatePaciente->execute([
                    ':telefono' => $telefono ?: null,
                    ':email' => $email ?: null,
                    ':id' => $paciente['id'],
                ]);
                
                // Update password if changing
                if ($passwordNueva !== '') {
                    $updatePassword = $db->prepare("
                        UPDATE paciente_accesos
                        SET password = :password,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $updatePassword->execute([
                        ':password' => password_hash($passwordNueva, PASSWORD_DEFAULT),
                        ':id' => $pacienteSesion['acceso_id'],
                    ]);
                }
                
                $exito = true;
                $_SESSION['alerta_portal_paciente'] = 'Tu perfil ha sido actualizado correctamente.';
            }
        } catch (PDOException $e) {
            error_log('Portal paciente perfil error: ' . $e->getMessage());
            $errores[] = 'No fue posible actualizar tu perfil. Intenta nuevamente.';
        }
    }
}

// Get patient data for display
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT
            p.id,
            p.numero_documento,
            p.tipo_documento,
            p.nombre,
            p.apellido,
            p.fecha_nacimiento,
            p.genero,
            p.telefono,
            p.email,
            p.direccion,
            p.ciudad,
            p.eps,
            p.tipo_afiliacion,
            p.estado
        FROM pacientes p
        INNER JOIN paciente_accesos pa ON pa.paciente_id = p.id
        WHERE pa.id = :acceso_id AND pa.paciente_id = :paciente_id
        LIMIT 1
    ");
    $stmt->execute([
        ':acceso_id' => $pacienteSesion['acceso_id'],
        ':paciente_id' => $pacienteSesion['id'],
    ]);
    $paciente = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Portal paciente perfil load error: ' . $e->getMessage());
    $errores[] = 'No fue posible cargar tu perfil.';
}

function portalEsc(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function portalDate(?string $value, string $fallback = 'Sin registro'): string {
    if (!$value) {
        return $fallback;
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $fallback;
}

$nombrePaciente = trim((string) (($paciente['nombre'] ?? '') . ' ' . ($paciente['apellido'] ?? '')));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Portal del Paciente | DentiSoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/portal-paciente-premium.css?v=<?= filemtime(__DIR__ . '/../assets/css/portal-paciente-premium.css') ?>" rel="stylesheet">
</head>
<body>
    <div class="portal-shell">
        <header class="portal-topbar">
            <a href="<?= BASE_URL ?>/portal-paciente.php" class="portal-brand">
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="DentiSoft">
                <span>DentiSoft</span>
            </a>
            <nav class="portal-nav">
                <a href="<?= BASE_URL ?>/portal-paciente.php" class="portal-nav-link">
                    <i class="bi bi-house"></i> Inicio
                </a>
                <a href="<?= BASE_URL ?>/portal-paciente/citas.php" class="portal-nav-link">
                    <i class="bi bi-calendar3"></i> Mis Citas
                </a>
                <a href="<?= BASE_URL ?>/portal-paciente/facturas.php" class="portal-nav-link">
                    <i class="bi bi-receipt"></i> Mis Facturas
                </a>
                <a href="<?= BASE_URL ?>/portal-paciente/perfil.php" class="portal-nav-link active">
                    <i class="bi bi-person"></i> Mi Perfil
                </a>
            </nav>
            <a href="<?= BASE_URL ?>/portal-logout.php" class="portal-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span>Cerrar sesión</span>
            </a>
        </header>

        <main class="portal-main">
            <?php if ($alertaPortal): ?>
                <div class="portal-alert success"><?= portalEsc($alertaPortal) ?></div>
            <?php endif; ?>
            <?php if (!empty($errores)): ?>
                <div class="portal-alert error">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errores as $error): ?>
                            <li><?= portalEsc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Hero Section -->
            <section class="portal-hero">
                <div class="portal-hero-main">
                    <span class="portal-eyebrow"><i class="bi bi-person"></i> Mi Perfil</span>
                    <h1><?= portalEsc($nombrePaciente ?: 'Paciente') ?></h1>
                    <p>Actualiza tu información personal y contraseña.</p>
                </div>
            </section>

            <!-- Profile Information -->
            <section class="portal-content-section">
                <div class="portal-section-header">
                    <div>
                        <span class="portal-section-eyebrow"><i class="bi bi-info-circle"></i> Información personal</span>
                        <h2>Datos del paciente</h2>
                    </div>
                </div>
                <div class="portal-list">
                    <div class="portal-list-item">
                        <div class="portal-list-icon"><i class="bi bi-card-text"></i></div>
                        <div class="portal-list-content">
                            <div class="portal-list-title">Documento</div>
                            <div class="portal-list-subtitle">
                                <?= portalEsc($paciente['tipo_documento'] ?? '') ?> - <?= portalEsc($paciente['numero_documento'] ?? '') ?>
                            </div>
                        </div>
                    </div>
                    <div class="portal-list-item">
                        <div class="portal-list-icon"><i class="bi bi-person"></i></div>
                        <div class="portal-list-content">
                            <div class="portal-list-title">Nombre completo</div>
                            <div class="portal-list-subtitle">
                                <?= portalEsc($paciente['nombre'] ?? '') ?> <?= portalEsc($paciente['apellido'] ?? '') ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($paciente['fecha_nacimiento']): ?>
                    <div class="portal-list-item">
                        <div class="portal-list-icon"><i class="bi bi-calendar"></i></div>
                        <div class="portal-list-content">
                            <div class="portal-list-title">Fecha de nacimiento</div>
                            <div class="portal-list-subtitle"><?= portalDate($paciente['fecha_nacimiento']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($paciente['eps']): ?>
                    <div class="portal-list-item">
                        <div class="portal-list-icon"><i class="bi bi-hospital"></i></div>
                        <div class="portal-list-content">
                            <div class="portal-list-title">EPS</div>
                            <div class="portal-list-subtitle"><?= portalEsc($paciente['eps']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Edit Profile Form -->
            <section class="portal-form-section">
                <div class="portal-form-header">
                    <div class="portal-form-icon"><i class="bi bi-pencil-square"></i></div>
                    <div>
                        <span class="form-section-eyebrow">Actualizar datos</span>
                        <h3>Editar información de contacto</h3>
                    </div>
                </div>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <div class="portal-form-grid">
                        <div class="portal-form-field">
                            <label class="portal-label">Teléfono</label>
                            <div class="portal-input-wrapper">
                                <i class="bi bi-telephone"></i>
                                <input type="tel" class="portal-input" name="telefono" value="<?= portalEsc($paciente['telefono'] ?? '') ?>" placeholder="Ej. +57 300 123 4567">
                            </div>
                        </div>
                        <div class="portal-form-field">
                            <label class="portal-label">Correo electrónico</label>
                            <div class="portal-input-wrapper">
                                <i class="bi bi-envelope"></i>
                                <input type="email" class="portal-input" name="email" value="<?= portalEsc($paciente['email'] ?? '') ?>" placeholder="Ej. paciente@email.com">
                            </div>
                        </div>
                    </div>
                    
                    <div class="portal-form-header" style="margin-top: 24px;">
                        <div class="portal-form-icon"><i class="bi bi-shield-lock"></i></div>
                        <div>
                            <span class="form-section-eyebrow">Seguridad</span>
                            <h3>Cambiar contraseña</h3>
                        </div>
                    </div>
                    <div class="portal-form-grid">
                        <div class="portal-form-field full-width">
                            <label class="portal-label">Contraseña actual</label>
                            <div class="portal-input-wrapper">
                                <i class="bi bi-lock"></i>
                                <input type="password" class="portal-input" name="password_actual" placeholder="Ingresa tu contraseña actual">
                            </div>
                        </div>
                        <div class="portal-form-field">
                            <label class="portal-label">Nueva contraseña</label>
                            <div class="portal-input-wrapper">
                                <i class="bi bi-key"></i>
                                <input type="password" class="portal-input" name="password_nueva" placeholder="Mínimo 8 caracteres" minlength="8">
                            </div>
                        </div>
                        <div class="portal-form-field">
                            <label class="portal-label">Confirmar nueva contraseña</label>
                            <div class="portal-input-wrapper">
                                <i class="bi bi-key-fill"></i>
                                <input type="password" class="portal-input" name="password_confirmacion" placeholder="Repite la nueva contraseña" minlength="8">
                            </div>
                        </div>
                    </div>
                    
                    <div class="portal-form-actions">
                        <a href="<?= BASE_URL ?>/portal-paciente.php" class="portal-btn portal-btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="portal-btn portal-btn-primary">
                            <i class="bi bi-check-circle"></i> Guardar cambios
                        </button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
