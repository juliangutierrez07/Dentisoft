<?php
/**
 * Conexión a Base de Datos — DentiSoft 1.0
 */

require_once __DIR__ . '/env.php';
cargarVariablesEntorno();

define('DB_HOST',    (string) env('DB_HOST', 'localhost'));
define('DB_NAME',    (string) env('DB_NAME', 'odonto_db'));
define('DB_USER',    (string) env('DB_USER', 'root'));
define('DB_PASS',    (string) env('DB_PASS', ''));
define('DB_CHARSET', (string) env('DB_CHARSET', 'utf8mb4'));
define('DB_PORT',    (int) env('DB_PORT', 3306));

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // ✅ charset en el DSN + SET NAMES en opciones + SET NAMES explícito
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
            // ✅ Doble seguro: ejecutar SET NAMES explícitamente
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("SET CHARACTER SET utf8mb4");
            $pdo->exec("SET time_zone = '-05:00'");

        } catch (PDOException $e) {
            http_response_code(500);
            if (
                isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            ) {
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos.']));
            }
            die('
            <!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
            <title>Error — DentiSoft</title>
            <style>
                body{font-family:sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;}
                .box{background:#1e293b;border-radius:12px;padding:40px;max-width:480px;text-align:center;}
                h2{color:#f87171;margin-bottom:12px;}p{color:#94a3b8;margin-bottom:8px;}
                .detail{background:rgba(248,113,113,0.1);border-radius:8px;padding:12px;margin-top:12px;font-size:.85rem;color:#fca5a5;}
            </style></head><body>
            <div class="box">
                <div style="font-size:3rem">🦷</div>
                <h2>Error de Conexión</h2>
                <p>No se pudo conectar a MySQL. Verifica que XAMPP esté corriendo.</p>
                <div class="detail">' . htmlspecialchars($e->getMessage()) . '</div>
            </div></body></html>');
        }
    }
    return $pdo;
}

function registrarAuditoria(
    string $accion,
    string $tabla_afectada,
    ?int $registro_id = null,
    ?array $datos_anteriores = null,
    ?array $datos_nuevos = null
): void {
    try {
        $db   = getDB();
        $usuario_id = $_SESSION['usuario_id'] ?? $registro_id; // Si es login, registro_id es el usuario_id
        $ip_address = ($datos_nuevos['ip'] ?? null) ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        
        $stmt = $db->prepare("
            INSERT INTO audit_log (usuario_id, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address)
            VALUES (:uid, :accion, :tabla, :rid, :ant, :nue, :ip)
        ");
        $stmt->execute([
            ':uid'    => $usuario_id,
            ':accion' => $accion,
            ':tabla'  => $tabla_afectada,
            ':rid'    => $registro_id,
            ':ant'    => $datos_anteriores ? json_encode($datos_anteriores, JSON_UNESCAPED_UNICODE) : null,
            ':nue'    => $datos_nuevos     ? json_encode($datos_nuevos,     JSON_UNESCAPED_UNICODE) : null,
            ':ip'     => $ip_address,
        ]);
    } catch (PDOException $e) {
        error_log('audit_log error: ' . $e->getMessage());
    }
}
