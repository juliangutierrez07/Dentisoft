<?php
/**
 * Logout — DentiSoft 1.0
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';

// Registrar en auditoría antes de destruir sesión
if (isLoggedIn()) {
    registrarAuditoria('logout', 'usuarios', $_SESSION['usuario_id'] ?? null);
}

// Destruir sesión
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

header('Location: ' . '/DentiSoft1.0/login.php');
exit;
