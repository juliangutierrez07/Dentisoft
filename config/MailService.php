<?php
/**
 * Servicio de correo electronico - DentiSoft 1.0
 */

use PHPMailer\PHPMailer\PHPMailer;

class MailService {
    private ?PHPMailer $mailer = null;
    private bool $isConfigured = false;
    private ?string $lastError = null;

    public function __construct() {
        try {
            $autoload = ROOT_PATH . '/vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }

            if (!MAIL_ENABLED) {
                $this->lastError = 'El servicio de correo esta deshabilitado o no tiene credenciales SMTP completas.';
                return;
            }

            if (!class_exists(PHPMailer::class)) {
                $this->lastError = 'PHPMailer no esta instalado. Ejecuta composer install.';
                error_log('MailService init error: ' . $this->lastError);
                return;
            }

            $this->mailer = new PHPMailer(true);
            $this->configure();
            $this->isConfigured = true;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('MailService init error: ' . $this->lastError);
        }
    }

    private function configure(): void {
        if (!$this->mailer) {
            return;
        }

        $this->mailer->isSMTP();
        $this->mailer->Host = MAIL_HOST;
        $this->mailer->Port = MAIL_PORT;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = MAIL_USERNAME;
        $this->mailer->Password = MAIL_PASSWORD;

        if (MAIL_ENCRYPTION !== '') {
            $this->mailer->SMTPSecure = MAIL_ENCRYPTION;
        }

        $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $this->mailer->Encoding = PHPMailer::ENCODING_BASE64;
        $this->mailer->Timeout = 10;
        $this->mailer->SMTPKeepAlive = false;

        if (MAIL_DEBUG) {
            $this->mailer->SMTPDebug = 2;
            $this->mailer->Debugoutput = 'error_log';
        }

        $this->mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    }

    public function enviarBienvenidaPaciente(array $paciente): bool {
        if (!$this->isConfigured || !$this->mailer) {
            error_log('MailService no configurado: ' . ($this->lastError ?? 'sin detalle'));
            return false;
        }

        $email = trim((string) ($paciente['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Email de paciente invalido.';
            error_log('MailService validation error: ' . $this->lastError);
            return false;
        }

        try {
            $destinatario = MAIL_TEST_EMAIL !== '' ? MAIL_TEST_EMAIL : $email;
            $nombreCompleto = trim((string) ($paciente['nombre'] ?? '') . ' ' . (string) ($paciente['apellido'] ?? ''));

            $this->mailer->clearAllRecipients();
            $this->mailer->clearReplyTos();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders();

            $this->mailer->addAddress($destinatario, $nombreCompleto);
            $this->mailer->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $this->mailer->Subject = 'Bienvenido a ' . CLINICA_NOMBRE;
            $this->mailer->isHTML(true);
            $this->mailer->Body = $this->obtenerPlantillaWelcome($paciente);
            $this->mailer->AltBody = $this->obtenerTextoPlanoWelcome($paciente);

            $enviado = $this->mailer->send();
            if ($enviado) {
                error_log('Email de bienvenida enviado a ' . $destinatario);
                $this->lastError = null;
            }

            return $enviado;
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('MailService send error: ' . $this->lastError);
            return false;
        }
    }

    private function obtenerPlantillaWelcome(array $paciente): string {
        $nombre = htmlspecialchars((string) ($paciente['nombre'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $apellido = htmlspecialchars((string) ($paciente['apellido'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $numeroDocumento = htmlspecialchars((string) ($paciente['numero_documento'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $tipoDocumento = htmlspecialchars((string) ($paciente['tipo_documento'] ?? 'CC'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $fechaRegistro = date('d/m/Y', strtotime((string) ($paciente['created_at'] ?? 'now')));
        $clinicaNombre = htmlspecialchars(CLINICA_NOMBRE, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $clinicaDireccion = htmlspecialchars(CLINICA_DIRECCION, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $clinicaCiudad = htmlspecialchars(CLINICA_CIUDAD, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $clinicaTelefono = htmlspecialchars(CLINICA_TELEFONO, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $clinicaEmail = htmlspecialchars(CLINICA_EMAIL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $portalUrl = APP_PUBLIC_URL !== '' ? APP_PUBLIC_URL . BASE_URL : '';
        $cta = $portalUrl !== ''
            ? '<tr><td align="center" style="padding: 8px 0 32px;"><a href="' . htmlspecialchars($portalUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="display:inline-block;background:#35d0ff;color:#061426;text-decoration:none;font-weight:800;border-radius:8px;padding:14px 22px;">Acceder al portal</a></td></tr>'
            : '';

        return <<<HTML
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a DentiSoft</title>
</head>
<body style="margin:0;padding:0;background:#06111f;color:#edf6ff;font-family:Inter,Segoe UI,Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#06111f;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#0b1728;border:1px solid #20364f;border-radius:18px;overflow:hidden;box-shadow:0 24px 70px rgba(0,0,0,.38);">
                    <tr>
                        <td style="padding:34px 30px;background:#10223a;border-bottom:1px solid #24445f;">
                            <div style="font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:#35d0ff;font-weight:800;">DentiSoft</div>
                            <h1 style="margin:12px 0 0;font-size:30px;line-height:1.15;color:#ffffff;font-weight:800;">Bienvenido, $nombre</h1>
                            <p style="margin:14px 0 0;color:#a9bdd3;font-size:15px;line-height:1.6;">Tu perfil fue registrado correctamente en $clinicaNombre.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 30px 18px;">
                            <p style="margin:0 0 18px;color:#dcecff;font-size:16px;line-height:1.7;">Desde ahora el equipo clinico podra consultar tu informacion de forma segura para acompanarte en tus citas, tratamientos y seguimiento odontologico.</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#081321;border:1px solid #20364f;border-radius:14px;margin:24px 0;">
                                <tr>
                                    <td style="padding:20px 22px;border-bottom:1px solid #1d3148;color:#35d0ff;font-size:13px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;">Datos de registro</td>
                                </tr>
                                <tr>
                                    <td style="padding:0 22px 18px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr><td style="padding:14px 0;border-bottom:1px solid #17283b;color:#91a8c0;">Nombre completo</td><td align="right" style="padding:14px 0;border-bottom:1px solid #17283b;color:#ffffff;font-weight:700;">$nombre $apellido</td></tr>
                                            <tr><td style="padding:14px 0;border-bottom:1px solid #17283b;color:#91a8c0;">Documento</td><td align="right" style="padding:14px 0;border-bottom:1px solid #17283b;color:#ffffff;font-weight:700;">$tipoDocumento - $numeroDocumento</td></tr>
                                            <tr><td style="padding:14px 0;color:#91a8c0;">Fecha de registro</td><td align="right" style="padding:14px 0;color:#ffffff;font-weight:700;">$fechaRegistro</td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <div style="background:#0d2638;border-left:4px solid #35d0ff;border-radius:10px;padding:16px 18px;color:#c9dbee;font-size:14px;line-height:1.6;">Tu informacion se gestiona bajo controles de privacidad y solo sera usada para fines clinicos y administrativos de la atencion odontologica.</div>
                        </td>
                    </tr>
                    $cta
                    <tr>
                        <td style="padding:24px 30px 30px;color:#a9bdd3;font-size:14px;line-height:1.7;">
                            Si necesitas actualizar tus datos o resolver alguna duda, contactanos por los canales oficiales de la clinica.
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 30px;background:#081321;border-top:1px solid #20364f;color:#8da4bc;font-size:12px;line-height:1.7;text-align:center;">
                            <strong style="color:#edf6ff;">$clinicaNombre</strong><br>
                            $clinicaDireccion<br>
                            $clinicaCiudad<br>
                            $clinicaTelefono | $clinicaEmail
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function obtenerTextoPlanoWelcome(array $paciente): string {
        $nombre = trim((string) ($paciente['nombre'] ?? '') . ' ' . (string) ($paciente['apellido'] ?? ''));
        $numeroDocumento = (string) ($paciente['numero_documento'] ?? '');
        $tipoDocumento = (string) ($paciente['tipo_documento'] ?? 'CC');
        $fechaRegistro = date('d/m/Y', strtotime((string) ($paciente['created_at'] ?? 'now')));

        $clinicaNombre = CLINICA_NOMBRE;
        $clinicaTelefono = CLINICA_TELEFONO;
        $clinicaEmail = CLINICA_EMAIL;

        return <<<TEXT
Bienvenido a DentiSoft

Hola $nombre,

Tu perfil fue registrado correctamente en $clinicaNombre.

Datos de registro:
- Nombre: $nombre
- Documento: $tipoDocumento - $numeroDocumento
- Fecha de registro: $fechaRegistro

Si necesitas actualizar tus datos o resolver alguna duda, contactanos:
$clinicaTelefono
$clinicaEmail
TEXT;
    }

    public function obtenerError(): ?string {
        return $this->lastError;
    }

    public function estaConfigurado(): bool {
        return $this->isConfigured;
    }
}
