<?php
/**
 * Login del Portal del Paciente.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

if (isPatientLoggedIn()) {
    $redirect = !empty($_SESSION['paciente_debe_cambiar_password'])
        ? BASE_URL . '/portal-cambiar-password.php'
        : BASE_URL . '/portal-paciente.php';
    header('Location: ' . $redirect);
    exit;
}

$error = '';
$documento = trim((string) ($_POST['documento'] ?? ''));
$alertaPortal = $_SESSION['alerta_portal_paciente'] ?? null;
unset($_SESSION['alerta_portal_paciente']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCSRF();

    $password = (string) ($_POST['password'] ?? '');

    if ($documento === '' || $password === '') {
        $error = 'Ingresa tu documento y contrasena.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT
                    pa.id AS acceso_id,
                    pa.paciente_id,
                    pa.usuario_documento,
                    pa.password,
                    pa.estado AS cuenta_estado,
                    pa.debe_cambiar_password,
                    p.nombre,
                    p.apellido,
                    p.estado AS paciente_estado
                FROM paciente_accesos pa
                INNER JOIN pacientes p ON p.id = pa.paciente_id
                WHERE pa.usuario_documento = :documento
                LIMIT 1
            ");
            $stmt->execute([':documento' => $documento]);
            $acceso = $stmt->fetch();

            if (!$acceso || !password_verify($password, $acceso['password'])) {
                $error = 'Documento o contrasena incorrectos.';
            } elseif (($acceso['cuenta_estado'] ?? '') !== 'activo' || ($acceso['paciente_estado'] ?? '') !== 'activo') {
                $error = 'Tu cuenta no esta activa. Contacta al consultorio.';
            } else {
                session_regenerate_id(true);
                $_SESSION['paciente_acceso_id'] = (int) $acceso['acceso_id'];
                $_SESSION['paciente_id'] = (int) $acceso['paciente_id'];
                $_SESSION['paciente_nombre'] = (string) $acceso['nombre'];
                $_SESSION['paciente_apellido'] = (string) $acceso['apellido'];
                $_SESSION['paciente_documento'] = (string) $acceso['usuario_documento'];
                $_SESSION['paciente_debe_cambiar_password'] = (int) $acceso['debe_cambiar_password'] === 1;

                $update = $db->prepare("UPDATE paciente_accesos SET ultimo_acceso = NOW() WHERE id = :id");
                $update->execute([':id' => $acceso['acceso_id']]);

                if (!empty($_SESSION['paciente_debe_cambiar_password'])) {
                    header('Location: ' . BASE_URL . '/portal-cambiar-password.php');
                    exit;
                }

                $redirect = $_SESSION['patient_redirect_after_login'] ?? BASE_URL . '/portal-paciente.php';
                unset($_SESSION['patient_redirect_after_login']);
                header('Location: ' . $redirect);
                exit;
            }
        } catch (PDOException $e) {
            error_log('Portal paciente login error: ' . $e->getMessage());
            $error = 'No fue posible iniciar sesion. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Paciente - DentiSoft</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/portal-login-premium.css?v=<?= filemtime(__DIR__ . '/assets/css/portal-login-premium.css') ?>" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <!-- Left Side - Cinematic Background -->
        <div class="login-left">
            <div class="login-left-content">
                <div class="login-brand">
                    <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="DentiSoft">
                    <span>DentiSoft</span>
                </div>
                <p class="login-slogan">Tecnología dental avanzada para una sonrisa perfecta</p>
                
                <div class="login-stats">
                    <div class="login-stat">
                        <div class="login-stat-icon"><i class="bi bi-people"></i></div>
                        <div class="login-stat-value">5,000+</div>
                        <div class="login-stat-label">Pacientes</div>
                    </div>
                    <div class="login-stat">
                        <div class="login-stat-icon"><i class="bi bi-shield-check"></i></div>
                        <div class="login-stat-value">100%</div>
                        <div class="login-stat-label">Seguro</div>
                    </div>
                    <div class="login-stat">
                        <div class="login-stat-icon"><i class="bi bi-clock"></i></div>
                        <div class="login-stat-value">24/7</div>
                        <div class="login-stat-label">Disponible</div>
                    </div>
                    <div class="login-stat">
                        <div class="login-stat-icon"><i class="bi bi-award"></i></div>
                        <div class="login-stat-value">Premium</div>
                        <div class="login-stat-label">Servicio</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Glassmorphism Card -->
        <div class="login-right">
            <div class="login-card">
                <div class="login-card-header">
                    <h1 class="login-card-title">Portal del Paciente</h1>
                    <p class="login-card-subtitle">Accede a tus citas, facturas e historial clínico desde cualquier lugar</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="login-alert error">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <?php if ($alertaPortal): ?>
                    <div class="login-alert success">
                        <i class="bi bi-check-circle"></i>
                        <?= htmlspecialchars($alertaPortal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form class="login-form" method="POST" novalidate id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    
                    <div class="login-form-group">
                        <label class="login-label">Documento de identidad</label>
                        <div class="login-input-wrapper">
                            <i class="bi bi-person"></i>
                            <input type="text" class="login-input" name="documento" value="<?= htmlspecialchars($documento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" autocomplete="username" placeholder="Ingresa tu documento" required>
                        </div>
                    </div>

                    <div class="login-form-group">
                        <label class="login-label">Contraseña</label>
                        <div class="login-input-wrapper">
                            <i class="bi bi-lock"></i>
                            <input type="password" class="login-input" name="password" id="passwordInput" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>
                            <button type="button" class="login-toggle-password" id="togglePassword" aria-label="Mostrar contraseña">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="login-extras">
                        <label class="login-checkbox">
                            <input type="checkbox" name="recordarme">
                            <span>Recordarme</span>
                        </label>
                        <a href="#" class="login-forgot">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" class="login-btn" id="loginBtn">
                        <span class="login-btn-content">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Ingresar al Portal
                        </span>
                    </button>

                    <div class="login-security">
                        <i class="bi bi-shield-lock"></i>
                        <span>Conexión cifrada y segura</span>
                    </div>
                </form>

                <a class="login-back" href="<?= BASE_URL ?>/login.php">
                    <i class="bi bi-arrow-left"></i>
                    Acceso equipo clínico
                </a>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });

        // Form submission loading state
        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');
        
        loginForm.addEventListener('submit', function() {
            loginBtn.classList.add('loading');
        });
    </script>
</body>
</html>
