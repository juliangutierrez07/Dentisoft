<?php
/**
 * Solicitud de recuperacion de contrasena del Equipo Clinico.
 * El usuario ingresa su correo; si existe una cuenta activa se envia un
 * enlace de restablecimiento. El mensaje es siempre generico para no
 * revelar si el correo esta registrado.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/usuarios_reset.php';
require_once __DIR__ . '/helpers/mailer.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
$enviado = false;
$email = trim((string) ($_POST['email'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCSRF();

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo electronico valido.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT id, nombre, apellido, email, estado
                FROM usuarios
                WHERE email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $usuario = $stmt->fetch();

            if ($usuario && ($usuario['estado'] ?? '') === 'activo') {
                $token = crearTokenResetUsuario($db, (int) $usuario['id']);
                $base = defined('APP_PUBLIC_URL') && APP_PUBLIC_URL !== '' ? APP_PUBLIC_URL : BASE_URL;
                $enlace = $base . '/restablecer-password.php?token=' . $token;
                $nombre = trim(((string) ($usuario['nombre'] ?? '')) . ' ' . ((string) ($usuario['apellido'] ?? '')));

                enviarCorreoHtml(
                    (string) $usuario['email'],
                    $nombre,
                    'Recupera tu contrasena - DentiSoft',
                    plantillaCorreoReset($nombre, $enlace, USUARIO_RESET_TOKEN_TTL_MINUTES, 'Equipo Clinico')
                );
            }

            // Respuesta generica siempre (evita enumeracion de correos).
            $enviado = true;
        } catch (PDOException $e) {
            error_log('Recuperar password staff error: ' . $e->getMessage());
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
    <title>Recuperar contrasena - DentiSoft</title>
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
        <p>Ingresa el correo de tu cuenta del equipo clinico y te enviaremos un enlace para restablecer tu contrasena.</p>

        <?php if ($enviado): ?>
            <div class="success">
                Si el correo esta registrado en una cuenta activa, te enviamos un enlace para restablecer tu contrasena.
                Revisa tu bandeja de entrada (y la carpeta de spam). El enlace vence en <?= USUARIO_RESET_TOKEN_TTL_MINUTES ?> minutos.
            </div>
            <a class="back" href="<?= BASE_URL ?>/login.php">&larr; Volver al inicio de sesion</a>
        <?php else: ?>
            <?php if ($error !== ''): ?>
                <div class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <label>
                    Correo electronico
                    <input type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" autocomplete="email" placeholder="correo@odontologia.com" required autofocus>
                </label>
                <button type="submit">Enviar enlace de recuperacion</button>
            </form>
            <a class="back" href="<?= BASE_URL ?>/login.php">&larr; Volver al inicio de sesion</a>
        <?php endif; ?>
    </main>
</body>
</html>
