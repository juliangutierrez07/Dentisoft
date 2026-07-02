<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('citas.editar');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

try {
    validarCSRF();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nuevoEstado = trim($_POST['estado'] ?? '');
    $estadosValidos = ['pendiente','confirmada','atendida','cancelada','no_asistio'];

    if (!$id || !in_array($nuevoEstado, $estadosValidos, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Datos inválidos para cambiar el estado.']);
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("UPDATE citas SET estado = :estado, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->execute([':estado' => $nuevoEstado, ':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Estado actualizado.']);
    exit;
} catch (PDOException $e) {
    error_log('Citas cambiar_estado error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno al actualizar el estado.']);
    exit;
} catch (RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}