<?php
/**
 * Configuración Global — DentiSoft 1.0
 */

// ✅ Forzar UTF-8 en toda la salida PHP
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Zona horaria Colombia
date_default_timezone_set('America/Bogota');

// Registrar errores en desarrollo, pero no mostrarlos al navegador.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/storage/logs/dentisoft-error.log');

// Ruta base del sistema (configurable por entorno).
// Local (XAMPP): usa el valor por defecto "/DentiSoft1.0".
// Despliegue en raíz de dominio (Dokploy/producción): define BASE_URL=/ en el entorno.
require_once __DIR__ . '/env.php';
define('BASE_URL',        rtrim((string) env('BASE_URL', '/DentiSoft1.0'), '/'));
define('APP_NAME',        'DentiSoft');
define('APP_VERSION',     '1.0');
define('APP_DESCRIPTION', 'Sistema de Gestión Odontológica');

// Rutas de archivos
define('ROOT_PATH',       dirname(__DIR__));
define('STORAGE_DIR',     ROOT_PATH . '/storage');
define('LOGS_DIR',        STORAGE_DIR . '/logs');
define('SESSIONS_DIR',    STORAGE_DIR . '/sessions');
define('CACHE_DIR',       STORAGE_DIR . '/cache');
define('UPLOADS_DIR',     ROOT_PATH . '/assets/uploads');
define('RADIOGRAFIAS_DIR', UPLOADS_DIR . '/radiografias');
define('FOTOS_DIR',       UPLOADS_DIR . '/fotos');

foreach ([STORAGE_DIR, LOGS_DIR, SESSIONS_DIR, CACHE_DIR] as $runtimeDir) {
    if (!is_dir($runtimeDir)) {
        @mkdir($runtimeDir, 0775, true);
    }
}

require_once ROOT_PATH . '/app/Bootstrap.php';

// Configuración de uploads
define('MAX_FILE_SIZE',        5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// Paginación
define('REGISTROS_POR_PAGINA', 20);

// Información de la clínica
define('CLINICA_NOMBRE',    'DentiSoft - Clínica Odontológica');
define('CLINICA_DIRECCION', 'Calle 10 #5-30, Centro');
define('CLINICA_CIUDAD',    'Neiva, Huila - Colombia');
define('CLINICA_TELEFONO',  '(608) 871-0000');
define('CLINICA_EMAIL',     'contacto@dentisoft.com');
define('CLINICA_NIT',       '900.123.456-7');
