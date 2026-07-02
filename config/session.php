<?php
/**
 * ============================================================
 * Gestión de Sesiones y Autenticación
 * DentiSoft 1.0 — Sistema de Gestión Odontológica
 * ============================================================
 */

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_name('DENTISOFT_SESSID');
    session_start([
        'cookie_lifetime' => 0,           // Expira al cerrar navegador
        'cookie_httponly'  => true,        // No accesible desde JS
        'cookie_samesite'  => 'Strict',   // Protección CSRF
        'use_strict_mode'  => true,       // Rechaza IDs no generados por el servidor
    ]);
}

// ─── Generar CSRF Token ────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Verifica que el usuario tenga sesión activa.
 * Si no, redirige al login.
 */
function requireLogin(): void {
    if (!isset($_SESSION['usuario_id'])) {
        // Guardar URL actual para redirigir después del login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: /DentiSoft1.0/login.php');
        exit;
    }
}

/**
 * Verifica que el usuario tenga uno de los roles permitidos.
 * Primero verifica que esté logueado.
 *
 * @param array $roles_permitidos Ej: ['administrador', 'odontologo']
 */
function requireRole(array $roles_permitidos): void {
    requireLogin();
    if (!in_array($_SESSION['rol'], $roles_permitidos, true)) {
        $_SESSION['alerta'] = [
            'tipo'    => 'danger',
            'mensaje' => 'No tienes permisos para acceder a esta sección.'
        ];
        header('Location: /DentiSoft1.0/dashboard.php');
        exit;
    }
}

/**
 * Verifica si hay un usuario logueado.
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['usuario_id']);
}

function isPatientLoggedIn(): bool {
    return isset($_SESSION['paciente_acceso_id'], $_SESSION['paciente_id']);
}

function clearPatientSession(): void {
    unset(
        $_SESSION['paciente_acceso_id'],
        $_SESSION['paciente_id'],
        $_SESSION['paciente_nombre'],
        $_SESSION['paciente_apellido'],
        $_SESSION['paciente_documento'],
        $_SESSION['paciente_debe_cambiar_password'],
        $_SESSION['patient_redirect_after_login'],
        $_SESSION['alerta_portal_paciente']
    );
}

function requirePatientLogin(): void {
    if (!isPatientLoggedIn()) {
        $_SESSION['patient_redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: /DentiSoft1.0/portal-login.php');
        exit;
    }

    if (function_exists('getDB')) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT
                    pa.id AS acceso_id,
                    pa.paciente_id,
                    pa.usuario_documento,
                    pa.estado AS cuenta_estado,
                    pa.debe_cambiar_password,
                    p.nombre,
                    p.apellido,
                    p.estado AS paciente_estado
                FROM paciente_accesos pa
                INNER JOIN pacientes p ON p.id = pa.paciente_id
                WHERE pa.id = :acceso_id AND pa.paciente_id = :paciente_id
                LIMIT 1
            ");
            $stmt->execute([
                ':acceso_id' => $_SESSION['paciente_acceso_id'],
                ':paciente_id' => $_SESSION['paciente_id'],
            ]);
            $paciente = $stmt->fetch();

            if (!$paciente || ($paciente['cuenta_estado'] ?? '') !== 'activo' || ($paciente['paciente_estado'] ?? '') !== 'activo') {
                clearPatientSession();
                $_SESSION['alerta_portal_paciente'] = 'Tu cuenta no esta activa. Contacta al consultorio.';
                header('Location: /DentiSoft1.0/portal-login.php');
                exit;
            }

            $_SESSION['paciente_acceso_id'] = (int) $paciente['acceso_id'];
            $_SESSION['paciente_id'] = (int) $paciente['paciente_id'];
            $_SESSION['paciente_nombre'] = (string) $paciente['nombre'];
            $_SESSION['paciente_apellido'] = (string) $paciente['apellido'];
            $_SESSION['paciente_documento'] = (string) $paciente['usuario_documento'];
            $_SESSION['paciente_debe_cambiar_password'] = (int) $paciente['debe_cambiar_password'] === 1;
        } catch (Throwable $e) {
            error_log('Portal paciente validacion sesion error: ' . $e->getMessage());
            clearPatientSession();
            $_SESSION['alerta_portal_paciente'] = 'No fue posible validar la sesion. Ingresa nuevamente.';
            header('Location: /DentiSoft1.0/portal-login.php');
            exit;
        }
    }
}

function currentPatient(): array {
    return [
        'acceso_id' => $_SESSION['paciente_acceso_id'] ?? null,
        'id' => $_SESSION['paciente_id'] ?? null,
        'nombre' => $_SESSION['paciente_nombre'] ?? '',
        'apellido' => $_SESSION['paciente_apellido'] ?? '',
        'documento' => $_SESSION['paciente_documento'] ?? '',
        'debe_cambiar_password' => (bool) ($_SESSION['paciente_debe_cambiar_password'] ?? false),
    ];
}

function requirePatientPasswordReady(): void {
    requirePatientLogin();
    if (!empty($_SESSION['paciente_debe_cambiar_password'])) {
        header('Location: /DentiSoft1.0/portal-cambiar-password.php');
        exit;
    }
}

/**
 * Retorna los datos del usuario actual en sesión.
 * @return array
 */
function currentUser(): array {
    return [
        'id'       => $_SESSION['usuario_id'] ?? null,
        'nombre'   => $_SESSION['nombre'] ?? '',
        'apellido' => $_SESSION['apellido'] ?? '',
        'rol'      => $_SESSION['rol'] ?? '',
        'rol_id'   => $_SESSION['rol_id'] ?? null,
        'email'    => $_SESSION['email'] ?? '',
    ];
}

/**
 * Valida el token CSRF enviado en un formulario POST.
 * Si no coincide, aborta con error 403.
 */
function validarCSRF(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        // Si es AJAX
        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            header('Content-Type: application/json; charset=utf-8');
            die(json_encode(['success' => false, 'error' => 'Token CSRF inválido. Recarga la página.']));
        }
        die('Error 403: Token de seguridad inválido. <a href="/DentiSoft1.0/">Volver al inicio</a>');
    }
}

/**
 * Establece un mensaje flash que se mostrará una sola vez.
 *
 * @param string $mensaje Texto del mensaje
 * @param string $tipo    Tipo Bootstrap: success, danger, warning, info
 */
function setAlerta(string $mensaje, string $tipo = 'success'): void {
    $_SESSION['alerta'] = [
        'tipo'    => $tipo,
        'mensaje' => $mensaje,
    ];
}

/**
 * Obtiene y elimina el mensaje flash (se muestra una sola vez).
 * @return array|null
 */
function getAlerta(): ?array {
    if (isset($_SESSION['alerta'])) {
        $alerta = $_SESSION['alerta'];
        unset($_SESSION['alerta']);
        return $alerta;
    }
    return null;
}

// ─── RBAC Helpers (can, requirePermission) ─────────────────────────────────
// Carga la matriz de permisos centralizada
function loadPermissionsMatrix(): array {
    static $perms = null;
    if ($perms !== null) {
        return $perms;
    }
    $file = __DIR__ . '/permissions.php';
    if (file_exists($file)) {
        $perms = include $file;
        if (!is_array($perms)) {
            $perms = [];
        }
        return $perms;
    }
    $perms = [];
    return $perms;
}

function getPermissionsForRole(string $role): array {
    $matrix = loadPermissionsMatrix();
    return $matrix[$role] ?? [];
}

function getUserPermissions(): array {
    $role = $_SESSION['rol'] ?? '';
    return getPermissionsForRole((string) $role);
}

function userHasPermission(string $perm): bool {
    // Exact match or wildcard style could be extended here
    $perms = getUserPermissions();
    return in_array($perm, $perms, true);
}

function can(string $perm): bool {
    return userHasPermission($perm);
}

/**
 * Middleware: exige un permiso, si no lo tiene responde 403 y registra auditoría.
 * @param string $perm
 */
function requirePermission(string $perm): void {
    if (!isLoggedIn()) {
        requireLogin();
    }
    if (!can($perm)) {
        // Auditoría (si existe la función)
        if (function_exists('registrarAuditoria')) {
            $usuarioId = $_SESSION['usuario_id'] ?? null;
            try {
                registrarAuditoria('acceso_denegado', 'sistema', $usuarioId, null, [
                    'permiso' => $perm,
                    'uri' => $_SERVER['REQUEST_URI'] ?? '',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ]);
            } catch (Throwable $e) {
                error_log('registrarAuditoria fallo: ' . $e->getMessage());
            }
        }
        http_response_code(403);
        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => '403 - Acceso denegado']);
            exit;
        }
        // Mostrar una página 403 simple
        echo '<h1>403 - Acceso denegado</h1><p>No tienes permisos para ver esta página.</p>';
        exit;
    }
}

function logAccessDenied(string $perm): void {
    if (function_exists('registrarAuditoria')) {
        $usuarioId = $_SESSION['usuario_id'] ?? null;
        try {
            registrarAuditoria('acceso_denegado', 'sistema', $usuarioId, null, [
                'permiso' => $perm,
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            ]);
        } catch (Throwable $e) {
            error_log('registrarAuditoria fallo: ' . $e->getMessage());
        }
    }
}

/**
 * Middleware: exige permiso o acceso al recurso propio.
 * Permite que un usuario edite su propio perfil aunque no tenga permiso global.
 *
 * @param string $perm
 * @param int|null $ownerId
 */
function requirePermissionOrOwn(string $perm, ?int $ownerId): void {
    if (!isLoggedIn()) {
        requireLogin();
    }
    $currentUserId = $_SESSION['usuario_id'] ?? null;
    if (!can($perm) && ($ownerId === null || $currentUserId !== $ownerId)) {
        logAccessDenied($perm);
        http_response_code(403);
        echo '<h1>403 - Acceso denegado</h1><p>No tienes permisos para ver esta página.</p>';
        exit;
    }
}
