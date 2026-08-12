<?php
/**
 * Envio de correos simple para el portal del paciente.
 * Independiente de MailService (que esta acoplado a notificaciones de citas).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envia un correo HTML por SMTP. Devuelve true si se envio correctamente.
 * Si el correo esta deshabilitado o falla, registra en el log y devuelve false.
 */
function enviarCorreoHtml(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('enviarCorreoHtml: email invalido: ' . $toEmail);
        return false;
    }

    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        // Fallback util en desarrollo: deja rastro para depurar sin SMTP.
        error_log('enviarCorreoHtml: correo deshabilitado (MAIL_ENABLED=false). Para=' . $toEmail . ' Asunto="' . $subject . '"');
        return false;
    }

    if (!class_exists(PHPMailer::class)) {
        error_log('enviarCorreoHtml: PHPMailer no disponible. Ejecuta composer install.');
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;

        if (MAIL_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (MAIL_ENCRYPTION === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = MAIL_PORT;

        if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = static function ($str, $level) {
                error_log("PHPMailer[$level]: $str");
            };
        }

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = trim(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $htmlBody)));

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('enviarCorreoHtml error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Plantilla HTML del correo de recuperacion de contrasena.
 * $area define el encabezado (ej. "Portal del Paciente" o "Equipo Clinico").
 */
function plantillaCorreoReset(string $nombre, string $enlace, int $minutos, string $area = 'Portal del Paciente'): string {
    $nombreSafe = htmlspecialchars($nombre !== '' ? $nombre : 'usuario', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $enlaceSafe = htmlspecialchars($enlace, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $areaSafe = htmlspecialchars($area, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:24px;background:#f4f6fb;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#2d3748;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 8px 30px rgba(20,30,50,.08);">
        <div style="background:linear-gradient(135deg,#8B7EFF,#2FE0B0);padding:28px 30px;color:#ffffff;">
            <h1 style="margin:0;font-size:20px;font-weight:700;">🦷 DentiSoft — {$areaSafe}</h1>
        </div>
        <div style="padding:30px;">
            <p style="font-size:16px;margin:0 0 16px;">Hola <strong>{$nombreSafe}</strong>,</p>
            <p style="margin:0 0 20px;line-height:1.6;">
                Recibimos una solicitud para restablecer la contrase&ntilde;a de tu cuenta del portal.
                Haz clic en el bot&oacute;n para definir una nueva contrase&ntilde;a:
            </p>
            <p style="text-align:center;margin:28px 0;">
                <a href="{$enlaceSafe}" style="display:inline-block;background:linear-gradient(135deg,#8B7EFF,#2FE0B0);color:#ffffff;text-decoration:none;padding:14px 28px;border-radius:10px;font-weight:700;font-size:15px;">
                    Restablecer mi contrase&ntilde;a
                </a>
            </p>
            <p style="margin:0 0 8px;line-height:1.6;color:#4a5568;font-size:14px;">
                Si el bot&oacute;n no funciona, copia y pega este enlace en tu navegador:
            </p>
            <p style="word-break:break-all;font-size:13px;color:#8B7EFF;margin:0 0 20px;">{$enlaceSafe}</p>
            <p style="margin:0 0 8px;line-height:1.6;color:#4a5568;font-size:14px;">
                Este enlace vence en <strong>{$minutos} minutos</strong> y solo puede usarse una vez.
            </p>
            <p style="margin:0;line-height:1.6;color:#4a5568;font-size:14px;">
                Si t&uacute; no solicitaste este cambio, ignora este correo; tu contrase&ntilde;a actual seguir&aacute; funcionando.
            </p>
        </div>
        <div style="background:#2d3748;color:#cbd5e0;padding:18px 30px;text-align:center;font-size:12px;">
            Mensaje autom&aacute;tico de DentiSoft — Sistema de Gesti&oacute;n Odontol&oacute;gica.
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Plantilla del correo de recuperacion para el Portal del Paciente.
 */
function plantillaCorreoResetPortal(string $nombre, string $enlace, int $minutos): string {
    return plantillaCorreoReset($nombre, $enlace, $minutos, 'Portal del Paciente');
}
