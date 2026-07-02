<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requirePermission('historias.editar');

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $input['action'] ?? '';

if ($method === 'POST') {
    validarCSRF();
}

try {
    $db = getDB();

    if ($action === 'guardar' && $method === 'POST') {
        $historiaId = filter_var($input['historia_id'] ?? null, FILTER_VALIDATE_INT);
        $pieza = trim($input['pieza_dental'] ?? '');
        $estado = trim($input['estado'] ?? 'sano');
        $caras = $input['caras_afectadas'] ?? [];
        $notas = trim($input['notas'] ?? '');

        if (!$historiaId || $pieza === '') {
            throw new RuntimeException('Datos incompletos para guardar la pieza.');
        }

        $validPiezas = [
            '18','17','16','15','14','13','12','11',
            '21','22','23','24','25','26','27','28',
            '38','37','36','35','34','33','32','31',
            '41','42','43','44','45','46','47','48'
        ];
        if (!in_array($pieza, $validPiezas, true)) {
            throw new RuntimeException('La pieza dental enviada no es valida.');
        }

        $validEstados = ['sano','caries','obturado','extraccion_indicada','ausente','corona','protesis','implante','fractura','tratamiento_conductos','otro'];
        if (!in_array($estado, $validEstados, true)) {
            $estado = 'sano';
        }

        $caras = array_values(array_filter((array) $caras, fn($c) => in_array($c, ['mesial','distal','oclusal','vestibular','palatino'], true)));
        $colorMapping = [
            'sano' => '#10b981',
            'caries' => '#ef4444',
            'obturado' => '#3b82f6',
            'extraccion_indicada' => '#f59e0b',
            'ausente' => '#6b7280',
            'corona' => '#facc15',
            'protesis' => '#8b5cf6',
            'implante' => '#14b8a6',
            'fractura' => '#f97316',
            'tratamiento_conductos' => '#0ea5e9',
            'otro' => '#94a3b8',
        ];

        $color = $colorMapping[$estado] ?? '#10b981';
        $carasJson = json_encode($caras, JSON_UNESCAPED_UNICODE);
        if ($carasJson === false) {
            throw new RuntimeException('No fue posible procesar las caras afectadas.');
        }

        $historiaStmt = $db->prepare("SELECT id FROM historias_clinicas WHERE id = :historia_id LIMIT 1");
        $historiaStmt->execute([':historia_id' => $historiaId]);
        if (!$historiaStmt->fetchColumn()) {
            throw new RuntimeException('La historia clinica enviada no existe.');
        }

        $db->beginTransaction();

        $existsStmt = $db->prepare("SELECT COUNT(*) FROM odontograma WHERE historia_id = :historia_id AND pieza_dental = :pieza_dental");
        $existsStmt->execute([
            ':historia_id' => $historiaId,
            ':pieza_dental' => $pieza,
        ]);
        $exists = (int) $existsStmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $db->prepare("UPDATE odontograma
                SET estado = :estado,
                    caras_afectadas = :caras_afectadas,
                    color_estado = :color_estado,
                    notas = :notas,
                    usuario_id = :usuario_id,
                    updated_at = CURRENT_TIMESTAMP
                WHERE historia_id = :historia_id
                  AND pieza_dental = :pieza_dental");
        } else {
            $stmt = $db->prepare("INSERT INTO odontograma
                (historia_id, pieza_dental, estado, caras_afectadas, color_estado, notas, usuario_id)
                VALUES (:historia_id, :pieza_dental, :estado, :caras_afectadas, :color_estado, :notas, :usuario_id)");
        }

        try {
            $stmt->execute([
                ':historia_id' => $historiaId,
                ':pieza_dental' => $pieza,
                ':estado' => $estado,
                ':caras_afectadas' => $carasJson,
                ':color_estado' => $color,
                ':notas' => $notas,
                ':usuario_id' => $_SESSION['usuario_id'] ?? null,
            ]);
        } catch (PDOException $e) {
            if (!$exists && $e->getCode() === '23000') {
                $stmt = $db->prepare("UPDATE odontograma
                    SET estado = :estado,
                        caras_afectadas = :caras_afectadas,
                        color_estado = :color_estado,
                        notas = :notas,
                        usuario_id = :usuario_id,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE historia_id = :historia_id
                      AND pieza_dental = :pieza_dental");
                $stmt->execute([
                    ':historia_id' => $historiaId,
                    ':pieza_dental' => $pieza,
                    ':estado' => $estado,
                    ':caras_afectadas' => $carasJson,
                    ':color_estado' => $color,
                    ':notas' => $notas,
                    ':usuario_id' => $_SESSION['usuario_id'] ?? null,
                ]);
            } else {
                throw $e;
            }
        }

        $updatedStmt = $db->prepare("SELECT MAX(updated_at) FROM odontograma WHERE historia_id = :historia_id AND pieza_dental = :pieza_dental");
        $updatedStmt->execute([
            ':historia_id' => $historiaId,
            ':pieza_dental' => $pieza,
        ]);
        $updatedAt = $updatedStmt->fetchColumn() ?: date('Y-m-d H:i:s');

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Pieza dental actualizada correctamente',
            'historia_id' => $historiaId,
            'pieza' => $pieza,
            'estado' => $estado,
            'color_estado' => $color,
            'caras_afectadas' => $caras,
            'notas' => $notas,
            'updated_at' => $updatedAt,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Accion invalida.']);
    exit;
} catch (RuntimeException $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
} catch (PDOException $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('odontograma_api error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno al guardar el odontograma.']);
    exit;
}
