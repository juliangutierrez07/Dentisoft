<?php
/**
 * Cambio obligatorio de contrasena para pacientes.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

requirePatientLogin();

$paciente = currentPatient();
$error = '';

if (empty($paciente['debe_cambiar_password'])) {
    header('Location: ' . BASE_URL . '/portal-paciente.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCSRF();

    $actual = (string) ($_POST['password_actual'] ?? '');
    $nueva = (string) ($_POST['password_nueva'] ?? '');
    $confirmacion = (string) ($_POST['password_confirmacion'] ?? '');

    if ($actual === '' || $nueva === '' || $confirmacion === '') {
        $error = 'Completa todos los campos.';
    } elseif (strlen($nueva) < 8) {
        $error = 'La nueva contrasena debe tener minimo 8 caracteres.';
    } elseif ($nueva !== $confirmacion) {
        $error = 'La confirmacion no coincide.';
    } elseif ($nueva === ($paciente['documento'] ?? '')) {
        $error = 'La nueva contrasena no puede ser igual a tu documento.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT password FROM paciente_accesos WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $paciente['acceso_id']]);
            $hashActual = (string) ($stmt->fetchColumn() ?: '');

            if ($hashActual === '' || !password_verify($actual, $hashActual)) {
                $error = 'La contrasena actual no es correcta.';
            } else {
                $update = $db->prepare("
                    UPDATE paciente_accesos
                    SET password = :password,
                        debe_cambiar_password = 0,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $update->execute([
                    ':password' => password_hash($nueva, PASSWORD_DEFAULT),
                    ':id' => $paciente['acceso_id'],
                ]);

                $_SESSION['paciente_debe_cambiar_password'] = false;
                $_SESSION['alerta_portal_paciente'] = 'Contrasena actualizada correctamente.';
                header('Location: ' . BASE_URL . '/portal-paciente.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('Portal paciente cambio password error: ' . $e->getMessage());
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
    <title>Cambiar contrasena - Portal del Paciente</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
    </style>
</head>
<body>
    <main class="portal-card">
        <h1>Cambia tu contrasena</h1>
        <p>Por seguridad, debes definir una contrasena nueva antes de continuar.</p>

        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <label>
                Contrasena actual
                <input type="password" name="password_actual" autocomplete="current-password" required>
            </label>
            <label>
                Nueva contrasena
                <input type="password" name="password_nueva" autocomplete="new-password" minlength="8" required>
            </label>
            <label>
                Confirmar nueva contrasena
                <input type="password" name="password_confirmacion" autocomplete="new-password" minlength="8" required>
            </label>
            <button type="submit">Actualizar contrasena</button>
        </form>
    </main>
</body>
</html>
