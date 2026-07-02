<?php
use DentiSoft\App\Helpers\ApiResponse;
use DentiSoft\App\Services\HistoriaClinicaService;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requirePermission('historias.ver');

if (($_GET['action'] ?? '') !== 'buscar') {
    ApiResponse::error('Accion invalida.', ['code' => 'INVALID_ACTION'], 400);
}

try {
    $search = trim((string) ($_GET['query'] ?? ''));
    $page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1);
    $limit = min(50, max(5, filter_var($_GET['limit'] ?? REGISTROS_POR_PAGINA, FILTER_VALIDATE_INT) ?: REGISTROS_POR_PAGINA));

    $result = (new HistoriaClinicaService())->search($search, $page, $limit);

    ApiResponse::success([
        'historias' => $result['items'],
        'meta' => $result['meta'],
    ]);
} catch (PDOException $e) {
    $traceId = bin2hex(random_bytes(4));
    error_log("historias_api error [$traceId]: " . $e->getMessage());
    ApiResponse::error('Error interno al buscar historias clinicas.', [
        'code' => 'SERVER_ERROR',
        'trace_id' => $traceId,
    ], 500);
}
