-- ============================================================
-- MIGRACIÓN: notificaciones_email
-- Sistema de notificaciones por correo electrónico
-- DentiSoft 1.0
-- ============================================================

USE odonto_db;

-- ------------------------------------------------------------
-- TABLA: notificaciones_email
-- Registro de todas las notificaciones enviadas por correo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notificaciones_email (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'ID del usuario que generó la notificación',
    tipo VARCHAR(50) NOT NULL COMMENT 'Tipo de notificación (cita_asignada, recordatorio, etc.)',
    destinatario VARCHAR(150) NOT NULL COMMENT 'Correo electrónico del destinatario',
    asunto VARCHAR(255) NOT NULL COMMENT 'Asunto del correo',
    contenido TEXT COMMENT 'Contenido del correo (opcional, para auditoría)',
    estado ENUM('enviado', 'pendiente', 'fallido') DEFAULT 'pendiente' COMMENT 'Estado del envío',
    fecha_envio DATETIME COMMENT 'Fecha y hora de envío exitoso',
    error TEXT COMMENT 'Mensaje de error si falló el envío',
    datos_adicionales JSON COMMENT 'Datos adicionales en formato JSON (IDs, etc.)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_tipo (tipo),
    INDEX idx_estado (estado),
    INDEX idx_destinatario (destinatario),
    INDEX idx_fecha_envio (fecha_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de notificaciones por correo electrónico';
