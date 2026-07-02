<?php
/**
 * API Notificaciones — DentiSoft 1.0
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');

// ✅ CORRECCIÓN: Las APIs NUNCA redirigen — solo responden JSON
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$action = $_GET['action'] ?? '';
$db     = getDB();
$userId = $_SESSION['usuario_id'];

try {
    switch ($action) {
        case 'listar':
            $stmt = $db->prepare("
                SELECT id, tipo, titulo, mensaje, leida, url_accion, created_at
                FROM notificaciones
                WHERE usuario_id = :uid OR usuario_id IS NULL
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $stmt->execute([':uid' => $userId]);
            $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'notificaciones' => $notificaciones]);
            break;

        case 'marcar_leida':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                break;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int) ($input['id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = :id AND (usuario_id = :uid OR usuario_id IS NULL)");
                $stmt->execute([':id' => $id, ':uid' => $userId]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'marcar_todas':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Método no permitido']);
                break;
            }
            $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE (usuario_id = :uid OR usuario_id IS NULL) AND leida = 0");
            $stmt->execute([':uid' => $userId]);
            echo json_encode(['success' => true, 'marcadas' => $stmt->rowCount()]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
    error_log('Notificaciones API: ' . $e->getMessage());
}