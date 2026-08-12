<?php
/**
 * Solicitud de recuperacion de contrasena del Portal del Paciente.
 * El paciente ingresa su documento; si existe una cuenta activa con correo,
 * se envia un enlace de restablecimiento. El mensaje es siempre generico
 * para no revelar si el documento esta registrado.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/patient_portal.php';
require_once __DIR__ . '/helpers/mailer.php';

if (isPatientLoggedIn()) {
    header('Location: ' . BASE_URL . '/portal-paciente.php');
    exit;
}

$error = '';
$enviado = false;
$documento = trim((string) ($_POST['documento'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCSRF();

    if ($documento === '') {
        $error = 'Ingresa tu numero de documento.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT
                    pa.id AS acceso_id,
                    pa.estado AS cuenta_estado,
                    p.nombre,
                    p.apellido,
                    p.email,
                    p.estado AS paciente_estado
                FROM paciente_accesos pa
                INNER JOIN pacientes p ON p.id = pa.paciente_id
                WHERE pa.usuario_documento = :documento
                LIMIT 1
            ");
            $stmt->execute([':documento' => $documento]);
            $acceso = $stmt->fetch();

            if ($acceso
                && ($acceso['cuenta_estado'] ?? '') === 'activo'
                && ($acceso['paciente_estado'] ?? '') === 'activo'
                && filter_var($acceso['email'] ?? '', FILTER_VALIDATE_EMAIL)) {

                $token = crearTokenResetPortal($db, (int) $acceso['acceso_id']);
                $base = defined('APP_PUBLIC_URL') && APP_PUBLIC_URL !== '' ? APP_PUBLIC_URL : BASE_URL;
                $enlace = $base . '/portal-restablecer-password.php?token=' . $token;
                $nombre = trim(((string) ($acceso['nombre'] ?? '')) . ' ' . ((string) ($acceso['apellido'] ?? '')));

                enviarCorreoHtml(
                    (string) $acceso['email'],
                    $nombre,
                    'Recupera tu contrasena - DentiSoft',
                    plantillaCorreoResetPortal($nombre, $enlace, PORTAL_RESET_TOKEN_TTL_MINUTES)
                );
            }

            // Respuesta generica siempre (evita enumeracion de documentos).
            $enviado = true;
        } catch (PDOException $e) {
            error_log('Portal recuperar password error: ' . $e->getMessage());
            $error = 'No fue posible procesar la solicitud. Intenta nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contrasena - Portal del Paciente</title>
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
        .success{margin-bottom:16px;padding:12px;border-radius:10px;color:#9CE9CF;background:rgba(47,224,176,.12);border:1px solid rgba(47,224,176,.30);line-height:1.5}
        .back{display:inline-flex;align-items:center;gap:6px;margin-top:20px;color:#8B7EFF;text-decoration:none;font-weight:600;font-size:.9rem}
        .back:hover{text-decoration:underline}
    </style>
</head>
<body>
    <main class="portal-card">
        <h1>Recuperar contrasena</h1>
        <p>Ingresa tu numero de documento y te enviaremos un enlace para restablecer tu contrasena.</p>

        <?php if ($enviado): ?>
            <div class="success">
                Si el documento esta registrado y tiene un correo asociado, te enviamos un enlace para restablecer tu contrasena.
                Revisa tu bandeja de entrada (y la carpeta de spam). El enlace vence en <?= PORTAL_RESET_TOKEN_TTL_MINUTES ?> minutos.
            </div>
            <a class="back" href="<?= BASE_URL ?>/portal-login.php">&larr; Volver al inicio de sesion</a>
        <?php else: ?>
            <?php if ($error !== ''): ?>
                <div class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <label>
                    Documento de identidad
                    <input type="text" name="documento" value="<?= htmlspecialchars($documento, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" autocomplete="username" placeholder="Ingresa tu documento" required autofocus>
                </label>
                <button type="submit">Enviar enlace de recuperacion</button>
            </form>
            <a class="back" href="<?= BASE_URL ?>/portal-login.php">&larr; Volver al inicio de sesion</a>
        <?php endif; ?>
    </main>
</body>
</html>
