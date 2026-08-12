<?php
/**
 * Restablecimiento de contrasena del Portal del Paciente mediante token.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/patient_portal.php';

if (isPatientLoggedIn()) {
    header('Location: ' . BASE_URL . '/portal-paciente.php');
    exit;
}

$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$error = '';

try {
    $db = getDB();
    $reset = obtenerResetPortalValido($db, $token);
} catch (PDOException $e) {
    error_log('Portal restablecer password (validacion) error: ' . $e->getMessage());
    $reset = null;
    $error = 'No fue posible validar el enlace. Intenta nuevamente.';
}

$tokenValido = $reset !== null;

if ($tokenValido && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCSRF();

    $nueva = (string) ($_POST['password_nueva'] ?? '');
    $confirmacion = (string) ($_POST['password_confirmacion'] ?? '');

    if ($nueva === '' || $confirmacion === '') {
        $error = 'Completa ambos campos.';
    } elseif (strlen($nueva) < 8) {
        $error = 'La contrasena debe tener minimo 8 caracteres.';
    } elseif ($nueva !== $confirmacion) {
        $error = 'La confirmacion no coincide.';
    } elseif ($nueva === ((string) ($reset['usuario_documento'] ?? ''))) {
        $error = 'La contrasena no puede ser igual a tu documento.';
    } else {
        try {
            restablecerPasswordPortal($db, (int) $reset['reset_id'], (int) $reset['acceso_id'], $nueva);
            $_SESSION['alerta_portal_paciente'] = 'Tu contrasena fue actualizada. Ya puedes iniciar sesion.';
            header('Location: ' . BASE_URL . '/portal-login.php');
            exit;
        } catch (PDOException $e) {
            error_log('Portal restablecer password (update) error: ' . $e->getMessage());
            $error = 'No fue posible actualizar la contrasena. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contrasena - Portal del Paciente</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:linear-gradient(135deg,#080B10,#161D28);color:#eaf3ff}
        .portal-card{width:min(480px,calc(100% - 32px));padding:34px;border:1px solid rgba(255,255,255,.14);border-radius:14px;background:rgba(15,20,28,.78);box-shadow:0 28px 70px rgba(0,0,0,.35);backdrop-filter:blur(18px)}
        h1{margin:0 0 8px;font-family:'Fraunces',serif;font-weight:500;font-size:1.8rem;line-height:1.1}
        p{margin:0 0 24px;color:#a9b9d0}
        label{display:grid;gap:8px;margin-bottom:16px;color:#d7e5f8;font-weight:700;font-size:.92rem}
        input{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.14);border-radius:10px;background:rgba(255,255,255,.06);color:#fff;padding:13px 14px;font:inherit}
        input:focus{outline:none;border-color:#2FE0B0;box-shadow:0 0 0 3px rgba(47,224,176,.16)}
        button{width:100%;min-height:46px;border:0;border-radius:10px;background:#2FE0B0;color:#04211A;font-weight:800;cursor:pointer;transition:filter .2s ease}
        button:hover{filter:brightness(1.08)}
        .error{margin-bottom:16px;padding:12px;border-radius:10px;color:#E6AEB8;background:rgba(217,97,95,.12);border:1px solid rgba(217,97,95,.28)}
        .warn{margin-bottom:16px;padding:12px;border-radius:10px;color:#EED9A6;background:rgba(224,181,79,.12);border:1px solid rgba(224,181,79,.30);line-height:1.5}
        .back{display:inline-flex;align-items:center;gap:6px;margin-top:20px;color:#8B7EFF;text-decoration:none;font-weight:600;font-size:.9rem}
        .back:hover{text-decoration:underline}
    </style>
</head>
<body>
    <main class="portal-card">
        <?php if (!$tokenValido): ?>
            <h1>Enlace no valido</h1>
            <p>Este enlace de recuperacion es invalido, ya fue usado o expiro.</p>
            <div class="warn">
                Por seguridad los enlaces vencen a los <?= PORTAL_RESET_TOKEN_TTL_MINUTES ?> minutos y solo pueden usarse una vez.
                Solicita uno nuevo para continuar.
            </div>
            <a class="back" href="<?= BASE_URL ?>/portal-recuperar-password.php">&larr; Solicitar un nuevo enlace</a>
        <?php else: ?>
            <h1>Define tu nueva contrasena</h1>
            <p>Ingresa una contrasena nueva para tu cuenta del portal.</p>

            <?php if ($error !== ''): ?>
                <div class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <label>
                    Nueva contrasena
                    <input type="password" name="password_nueva" autocomplete="new-password" minlength="8" required autofocus>
                </label>
                <label>
                    Confirmar nueva contrasena
                    <input type="password" name="password_confirmacion" autocomplete="new-password" minlength="8" required>
                </label>
                <button type="submit">Actualizar contrasena</button>
            </form>
            <a class="back" href="<?= BASE_URL ?>/portal-login.php">&larr; Volver al inicio de sesion</a>
        <?php endif; ?>
    </main>
</body>
</html>
