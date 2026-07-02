<?php
/**
 * Configuracion de correo electronico - DentiSoft 1.0
 */

require_once __DIR__ . '/env.php';
cargarVariablesEntorno();

$mailAutoload = ROOT_PATH . '/vendor/autoload.php';
if (is_file($mailAutoload)) {
    require_once $mailAutoload;
}

$mailHost = trim((string) env('MAIL_HOST', 'smtp.gmail.com'));
$mailPort = (int) env('MAIL_PORT', 465);
$mailEncryption = strtolower(trim((string) env('MAIL_ENCRYPTION', 'ssl')));
$mailUsername = trim((string) env('MAIL_USERNAME', ''));
$mailPassword = (string) env('MAIL_PASSWORD', '');
$mailFromEmail = trim((string) env('MAIL_FROM_EMAIL', ''));
$mailFromName = trim((string) env('MAIL_FROM_NAME', CLINICA_NOMBRE));
$mailTestEmail = trim((string) env('MAIL_TEST_EMAIL', ''));

if (!in_array($mailEncryption, ['tls', 'ssl', ''], true)) {
    $mailEncryption = 'ssl';
}

if ($mailPort <= 0) {
    $mailPort = $mailEncryption === 'ssl' ? 465 : 587;
}

if (!filter_var($mailFromEmail, FILTER_VALIDATE_EMAIL)) {
    $mailFromEmail = filter_var($mailUsername, FILTER_VALIDATE_EMAIL) ? $mailUsername : CLINICA_EMAIL;
}

if ($mailTestEmail !== '' && !filter_var($mailTestEmail, FILTER_VALIDATE_EMAIL)) {
    $mailTestEmail = '';
}

define('MAIL_HOST', $mailHost);
define('MAIL_PORT', $mailPort);
define('MAIL_ENCRYPTION', $mailEncryption);
define('MAIL_USERNAME', $mailUsername);
define('MAIL_PASSWORD', $mailPassword);
define('MAIL_FROM_EMAIL', $mailFromEmail);
define('MAIL_FROM_NAME', $mailFromName !== '' ? $mailFromName : CLINICA_NOMBRE);
define('MAIL_DEBUG', env_bool('MAIL_DEBUG', false));
define('MAIL_TEST_EMAIL', $mailTestEmail);
define('MAIL_ENABLED', env_bool('MAIL_ENABLED', false) && MAIL_USERNAME !== '' && MAIL_PASSWORD !== '');
define('APP_PUBLIC_URL', rtrim((string) env('APP_PUBLIC_URL', ''), '/'));

if (MAIL_ENABLED && !is_file($mailAutoload)) {
    error_log('PHPMailer no esta instalado. Ejecuta: composer install');
}

if (MAIL_DEBUG && MAIL_ENABLED) {
    error_log('Mail Config: SMTP=' . MAIL_HOST . ':' . MAIL_PORT . ' | From=' . MAIL_FROM_EMAIL);
}
