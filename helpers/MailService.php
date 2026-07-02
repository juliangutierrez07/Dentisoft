<?php
/**
 * DentiSoft 1.0 - Servicio de Correo Electrónico
 * Servicio profesional para envío de notificaciones por correo
 * 
 * Características:
 * - Envío de correos con PHPMailer
 * - Registro de notificaciones en base de datos
 * - Manejo de errores y logging
 * - Soporte para múltiples tipos de notificaciones
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private $db;
    private $mailer;
    
    public function __construct()
    {
        $this->db = getDB();
        $this->initializeMailer();
    }
    
    /**
     * Inicializar PHPMailer con configuración
     */
    private function initializeMailer()
    {
        if (!MAIL_ENABLED) {
            return;
        }
        
        $this->mailer = new PHPMailer(true);
        
        // Configuración SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = MAIL_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = MAIL_USERNAME;
        $this->mailer->Password = MAIL_PASSWORD;
        $this->mailer->SMTPSecure = MAIL_ENCRYPTION;
        $this->mailer->Port = MAIL_PORT;
        
        // Remitente
        $this->mailer->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        
        // Debug - Activar temporalmente para diagnóstico
        $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
        $this->mailer->Debugoutput = function($str, $level) {
            error_log("PHPMailer [$level]: $str");
        };
    }
    
    /**
     * Enviar notificación de cita asignada a odontólogo
     * 
     * @param int $citaId ID de la cita
     * @param int $odontologoId ID del odontólogo
     * @param int $usuarioId ID del usuario que registró la cita
     * @return array Resultado del envío
     */
    public function enviarNotificacionCitaAsignada($citaId, $odontologoId, $usuarioId)
    {
        // Registrar diagnostico sin exponer errores al navegador.
        ini_set('display_errors', 0);
        error_reporting(E_ALL);
        ini_set('error_log', dirname(__DIR__) . '/storage/logs/mail_service_debug.log');
        
        error_log('=== MAIL SERVICE - INICIANDO NOTIFICACIÓN ===');
        error_log('CITA ID: ' . $citaId);
        error_log('ODONTOLOGO ID: ' . $odontologoId);
        error_log('USUARIO ID: ' . $usuarioId);
        error_log('MAIL_ENABLED: ' . (MAIL_ENABLED ? 'TRUE' : 'FALSE'));
        error_log('MAIL_HOST: ' . (defined('MAIL_HOST') ? MAIL_HOST : 'NOT DEFINED'));
        error_log('MAIL_PORT: ' . (defined('MAIL_PORT') ? MAIL_PORT : 'NOT DEFINED'));
        error_log('MAIL_USERNAME: ' . (defined('MAIL_USERNAME') && MAIL_USERNAME !== '' ? '[configured]' : 'NOT DEFINED'));
        error_log('MAIL_FROM: ' . (defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'NOT DEFINED'));
        error_log('ERROR LOG PATH: ' . ini_get('error_log'));
        
        if (!MAIL_ENABLED) {
            error_log('MailService: MAIL_ENABLED is false, skipping email notification');
            return [
                'success' => false,
                'message' => 'El servicio de correo no está habilitado',
                'notification_id' => null
            ];
        }
        
        try {
            error_log('PASO 1 INICIO - Consultando datos de cita y odontólogo');
            
            $sql = "
                SELECT
                    c.id,
                    c.fecha,
                    c.hora_inicio,
                    c.hora_fin,
                    c.motivo,
                    c.estado,
                    p.nombre AS paciente_nombre,
                    p.apellido AS paciente_apellido,
                    p.numero_documento AS paciente_documento,
                    u.nombre AS odontologo_nombre,
                    u.apellido AS odontologo_apellido,
                    u.email AS odontologo_email,
                    u_reg.nombre AS usuario_registro_nombre,
                    u_reg.apellido AS usuario_registro_apellido
                FROM citas c
                INNER JOIN pacientes p ON p.id = c.paciente_id
                INNER JOIN usuarios u ON u.id = c.odontologo_id
                INNER JOIN usuarios u_reg ON u_reg.id = :usuario_id
                WHERE c.id = :cita_id AND c.odontologo_id = :odontologo_id
                LIMIT 1
            ";
            
            error_log('SQL PASO 1: ' . $sql);
            error_log('PARÁMETROS PASO 1: cita_id=' . $citaId . ', odontologo_id=' . $odontologoId . ', usuario_id=' . $usuarioId);
            
            $stmt = $this->db->prepare($sql);
            
            $params = [
                ':cita_id' => $citaId,
                ':odontologo_id' => $odontologoId,
                ':usuario_id' => $usuarioId
            ];
            
            $stmt->execute($params);
            
            $cita = $stmt->fetch();
            
            error_log('CITA ENCONTRADA: ' . ($cita ? 'SI' : 'NO'));
            
            if (!$cita) {
                error_log('MailService: No se encontró la cita ID ' . $citaId . ' con odontólogo ID ' . $odontologoId);
                return [
                    'success' => false,
                    'message' => 'No se encontró la cita',
                    'notification_id' => null
                ];
            }
            
            error_log('ODONTÓLOGO ENCONTRADO: SI');
            error_log('EMAIL ODONTÓLOGO: ' . ($cita['odontologo_email'] ?? 'NULL'));
            error_log('USUARIO QUE REGISTRA: ' . ($cita['usuario_registro_nombre'] ?? 'NULL') . ' ' . ($cita['usuario_registro_apellido'] ?? 'NULL'));
            error_log('PASO 1 OK');
            
            // Verificar que el odontólogo tenga email
            if (empty($cita['odontologo_email'])) {
                error_log('MailService: El odontólogo ID ' . $odontologoId . ' no tiene email configurado');
                return [
                    'success' => false,
                    'message' => 'El odontólogo no tiene email configurado',
                    'notification_id' => null
                ];
            }
            
            error_log('PASO 2 INICIO - Registrando notificación en base de datos');
            // Registrar notificación como pendiente
            $notificationId = $this->registrarNotificacion(
                $usuarioId,
                'cita_asignada',
                $cita['odontologo_email'],
                'Nueva cita asignada - DentiSoft',
                $cita
            );
            
            error_log('NOTIFICATION ID RETORNADO: ' . ($notificationId ?? 'NULL'));
            
            if ($notificationId === null) {
                error_log('PASO 2 FALLÓ - registrarNotificacion() retornó null');
                return [
                    'success' => false,
                    'message' => 'Error de base de datos al enviar notificación',
                    'notification_id' => null
                ];
            }
            
            error_log('PASO 2 OK - Notificación registrada con ID: ' . $notificationId);
            
            error_log('PASO 3 INICIO - Preparando contenido del correo');
            // Preparar contenido del correo
            $asunto = 'Nueva cita asignada - DentiSoft';
            $contenido = $this->generarContenidoCitaAsignada($cita);
            
            error_log('PASO 3 OK - Contenido generado');
            
            error_log('PASO 4 INICIO - Configurando PHPMailer');
            // Configurar y enviar correo
            $this->mailer->addAddress($cita['odontologo_email'], $cita['odontologo_nombre'] . ' ' . $cita['odontologo_apellido']);
            $this->mailer->Subject = $asunto;
            $this->mailer->Body = $contenido;
            $this->mailer->isHTML(true);
            
            error_log('PASO 4 OK - PHPMailer configurado');
            
            error_log('=== CONFIGURACIÓN PHPMailer ANTES DE ENVIAR ===');
            error_log('Host: ' . $this->mailer->Host);
            error_log('Port: ' . $this->mailer->Port);
            error_log('SMTPSecure: ' . $this->mailer->SMTPSecure);
            error_log('SMTPAuth: ' . ($this->mailer->SMTPAuth ? 'TRUE' : 'FALSE'));
            error_log('Username: ' . $this->mailer->Username);
            error_log('From: ' . $this->mailer->From);
            error_log('From Name: ' . $this->mailer->FromName);
            error_log('To: ' . $cita['odontologo_email']);
            error_log('Subject: ' . $this->mailer->Subject);
            error_log('==============================================');
            
            error_log('PASO 5 INICIO - Enviando correo');
            // Enviar
            $enviado = $this->mailer->send();
            
            error_log('RESULTADO ENVÍO: ' . ($enviado ? 'EXITOSO' : 'FALLIDO'));
            error_log('PASO 5 OK');
            
            if ($enviado) {
                error_log('PASO 6 INICIO - Actualizando notificación como enviada');
                // Actualizar notificación como enviada
                $this->actualizarNotificacionEnviada($notificationId);
                
                error_log('PASO 6 OK - Notificación enviada exitosamente a ' . $cita['odontologo_email'] . ' para cita ID ' . $citaId);
                
                return [
                    'success' => true,
                    'message' => 'Notificación enviada exitosamente',
                    'notification_id' => $notificationId
                ];
            } else {
                error_log('PASO 6 INICIO - Actualizando notificación como fallida');
                // Actualizar notificación como fallida
                $this->actualizarNotificacionFallida($notificationId, 'Error desconocido al enviar correo');
                
                error_log('PASO 6 OK - Notificación marcada como fallida');
                
                return [
                    'success' => false,
                    'message' => 'No fue posible enviar la notificación',
                    'notification_id' => $notificationId
                ];
            }
            
        } catch (Throwable $e) {
            error_log('=== MAIL SERVICE - EXCEPTION CAPTURADA ===');
            error_log('TIPO: ' . get_class($e));
            error_log('MENSAJE: ' . $e->getMessage());
            error_log('ARCHIVO: ' . $e->getFile());
            error_log('LÍNEA: ' . $e->getLine());
            error_log('STACK TRACE: ' . $e->getTraceAsString());
            error_log('========================================');
            
            // Mostrar error en pantalla temporalmente
            echo '<pre style="background: #f00; color: #fff; padding: 20px;">';
            print_r([
                'TIPO' => get_class($e),
                'MENSAJE' => $e->getMessage(),
                'ARCHIVO' => $e->getFile(),
                'LÍNEA' => $e->getLine(),
                'TRACE' => $e->getTraceAsString()
            ]);
            echo '</pre>';
            
            // Actualizar notificación como fallida si se registró
            if (isset($notificationId) && $notificationId) {
                $this->actualizarNotificacionFallida($notificationId, $e->getMessage());
            }
            
            return [
                'success' => false,
                'message' => 'Error al enviar la notificación: ' . $e->getMessage(),
                'notification_id' => $notificationId ?? null
            ];
        }
    }
    
    /**
     * Generar contenido HTML para notificación de cita asignada
     */
    private function generarContenidoCitaAsignada($cita)
    {
        $nombreOdontologo = htmlspecialchars($cita['odontologo_nombre'] . ' ' . $cita['odontologo_apellido'], ENT_QUOTES, 'UTF-8');
        $nombrePaciente = htmlspecialchars($cita['paciente_nombre'] . ' ' . $cita['paciente_apellido'], ENT_QUOTES, 'UTF-8');
        $documentoPaciente = htmlspecialchars($cita['paciente_documento'], ENT_QUOTES, 'UTF-8');
        $fechaCita = date('d/m/Y', strtotime($cita['fecha']));
        $horaInicio = date('H:i', strtotime($cita['hora_inicio']));
        $horaFin = date('H:i', strtotime($cita['hora_fin']));
        $motivo = htmlspecialchars($cita['motivo'] ?? 'No especificado', ENT_QUOTES, 'UTF-8');
        $usuarioRegistro = htmlspecialchars($cita['usuario_registro_nombre'] . ' ' . $cita['usuario_registro_apellido'], ENT_QUOTES, 'UTF-8');
        
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Cita Asignada - DentiSoft</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #7c3aed, #2563eb);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #1a202c;
        }
        .details {
            background-color: #f7fafc;
            border-left: 4px solid #7c3aed;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .detail-item {
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
        }
        .detail-label {
            font-weight: 600;
            color: #4a5568;
            min-width: 120px;
            margin-right: 10px;
        }
        .detail-value {
            color: #2d3748;
            flex: 1;
        }
        .footer {
            background-color: #2d3748;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        .footer a {
            color: #7df9ff;
            text-decoration: none;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #7c3aed, #2563eb);
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 20px;
        }
        .button:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🦷 Nueva Cita Asignada</h1>
        </div>
        <div class="content">
            <p class="greeting">Hola Dr(a). {$nombreOdontologo}</p>
            
            <p>Se ha registrado una nueva cita en su agenda. A continuación encontrará los detalles:</p>
            
            <div class="details">
                <div class="detail-item">
                    <span class="detail-label">Paciente:</span>
                    <span class="detail-value">{$nombrePaciente}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Documento:</span>
                    <span class="detail-value">{$documentoPaciente}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Fecha:</span>
                    <span class="detail-value">{$fechaCita}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Hora:</span>
                    <span class="detail-value">{$horaInicio} - {$horaFin}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Motivo:</span>
                    <span class="detail-value">{$motivo}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Estado:</span>
                    <span class="detail-value" style="text-transform: capitalize;">{$cita['estado']}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Registrado por:</span>
                    <span class="detail-value">{$usuarioRegistro}</span>
                </div>
            </div>
            
            <p style="text-align: center;">
                <a href="{$this->getAppUrl()}/dashboard.php" class="button">Ver en DentiSoft</a>
            </p>
            
            <p>Por favor revise su agenda en DentiSoft para confirmar la cita.</p>
        </div>
        <div class="footer">
            <p>Este es un mensaje automático del sistema DentiSoft.</p>
            <p>© 2024 DentiSoft - Sistema de Gestión Odontológica</p>
        </div>
    </div>
</body>
</html>
HTML;
        
        return $html;
    }
    
    /**
     * Registrar notificación en base de datos
     */
    private function registrarNotificacion($usuarioId, $tipo, $destinatario, $asunto, $datosAdicionales)
    {
        try {
            error_log('=== MAIL SERVICE - REGISTRAR NOTIFICACIÓN ===');
            error_log('USUARIO ID: ' . $usuarioId);
            error_log('TIPO: ' . $tipo);
            error_log('DESTINATARIO: ' . $destinatario);
            error_log('ASUNTO: ' . $asunto);
            
            // Verificar que usuario_id existe en tabla usuarios
            error_log('VERIFICANDO SI usuario_id ' . $usuarioId . ' EXISTE EN TABLA usuarios...');
            $stmtCheck = $this->db->prepare("SELECT id, nombre, email FROM usuarios WHERE id = :id");
            $stmtCheck->execute([':id' => $usuarioId]);
            $usuarioExiste = $stmtCheck->fetch();
            
            if ($usuarioExiste) {
                error_log('✓ USUARIO ENCONTRADO: ID=' . $usuarioExiste['id'] . ', NOMBRE=' . $usuarioExiste['nombre'] . ', EMAIL=' . $usuarioExiste['email']);
            } else {
                error_log('✗ USUARIO ID ' . $usuarioId . ' NO EXISTE EN TABLA usuarios');
                
                // Mostrar IDs existentes
                $stmtAll = $this->db->query("SELECT id, nombre, email FROM usuarios ORDER BY id");
                $todosUsuarios = $stmtAll->fetchAll(PDO::FETCH_ASSOC);
                error_log('USUARIOS EXISTENTES EN TABLA usuarios:');
                foreach ($todosUsuarios as $u) {
                    error_log('  ID=' . $u['id'] . ', NOMBRE=' . $u['nombre'] . ', EMAIL=' . $u['email']);
                }
                
                return null;
            }
            
            $datosJson = json_encode($datosAdicionales);
            error_log('DATOS ADICIONALES JSON: ' . $datosJson);
            error_log('JSON VALID: ' . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO'));
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('JSON ERROR: ' . json_last_error_msg());
            }
            
            $sql = "
                INSERT INTO notificaciones_email 
                (usuario_id, tipo, destinatario, asunto, estado, datos_adicionales)
                VALUES (:usuario_id, :tipo, :destinatario, :asunto, 'pendiente', :datos_adicionales)
            ";
            error_log('SQL A EJECUTAR: ' . $sql);
            
            $stmt = $this->db->prepare($sql);
            
            $params = [
                ':usuario_id' => $usuarioId,
                ':tipo' => $tipo,
                ':destinatario' => $destinatario,
                ':asunto' => $asunto,
                ':datos_adicionales' => $datosJson
            ];
            error_log('PARÁMETROS: ' . print_r($params, true));
            
            $stmt->execute($params);
            
            $notificationId = $this->db->lastInsertId();
            error_log('NOTIFICACIÓN REGISTRADA CON ID: ' . $notificationId);
            error_log('=============================================');
            
            return $notificationId;
            
        } catch (PDOException $e) {
            error_log('=== MAIL SERVICE - ERROR SQL AL REGISTRAR NOTIFICACIÓN ===');
            error_log('ERROR MESSAGE: ' . $e->getMessage());
            error_log('ERROR CODE: ' . $e->getCode());
            error_log('SQL STATE: ' . $e->errorInfo[0] ?? 'N/A');
            error_log('DRIVER ERROR CODE: ' . ($e->errorInfo[1] ?? 'N/A'));
            error_log('DRIVER ERROR MESSAGE: ' . ($e->errorInfo[2] ?? 'N/A'));
            error_log('ERROR INFO COMPLETO: ' . print_r($e->errorInfo(), true));
            error_log('SQL FALLIDO: ' . $sql ?? 'N/A');
            error_log('PARÁMETROS FALLIDOS: ' . print_r($params ?? 'N/A', true));
            error_log('=====================================================');
            return null;
        }
    }
    
    /**
     * Actualizar notificación como enviada
     */
    private function actualizarNotificacionEnviada($notificationId)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE notificaciones_email 
                SET estado = 'enviado', fecha_envio = NOW()
                WHERE id = :id
            ");
            
            $stmt->execute([':id' => $notificationId]);
            
        } catch (PDOException $e) {
            error_log('MailService: Error al actualizar notificación como enviada - ' . $e->getMessage());
        }
    }
    
    /**
     * Actualizar notificación como fallida
     */
    private function actualizarNotificacionFallida($notificationId, $error)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE notificaciones_email 
                SET estado = 'fallido', error = :error
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':id' => $notificationId,
                ':error' => substr($error, 0, 500) // Limitar a 500 caracteres
            ]);
            
        } catch (PDOException $e) {
            error_log('MailService: Error al actualizar notificación como fallida - ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener URL pública de la aplicación
     */
    private function getAppUrl()
    {
        return defined('APP_PUBLIC_URL') && APP_PUBLIC_URL !== '' 
            ? APP_PUBLIC_URL 
            : BASE_URL;
    }
    
    /**
     * Verificar si el servicio de correo está habilitado
     */
    public static function isEnabled()
    {
        return MAIL_ENABLED;
    }
}
