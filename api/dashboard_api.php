<?php
use DentiSoft\App\Helpers\ApiResponse;
use DentiSoft\App\Services\DashboardService;

/**
 * API Dashboard - DentiSoft 1.0
 * Retorna KPIs en JSON para actualizacion dinamica.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

if (!isLoggedIn()) {
    ApiResponse::error('No autenticado', [], 401);
}

try {
    ApiResponse::success((new DashboardService())->kpis());
} catch (PDOException $e) {
    ApiResponse::error('Error al obtener KPIs', [], 500);
}
