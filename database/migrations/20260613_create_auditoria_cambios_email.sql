-- ============================================================
-- MIGRACIÓN: auditoria_cambios_email
-- Sistema de auditoría para cambios de correo electrónico
-- DentiSoft 1.0
-- ============================================================

USE odonto_db;

-- ------------------------------------------------------------
-- TABLA: auditoria_cambios_email
-- Registro de todos los cambios de correo electrónico de usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria_cambios_email (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL COMMENT 'ID del usuario cuyo correo fue cambiado',
    email_anterior VARCHAR(150) NOT NULL COMMENT 'Correo electrónico anterior',
    email_nuevo VARCHAR(150) NOT NULL COMMENT 'Nuevo correo electrónico',
    cambiado_por INT NOT NULL COMMENT 'ID del administrador que realizó el cambio',
    fecha_cambio DATETIME NOT NULL COMMENT 'Fecha y hora del cambio',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (cambiado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_cambiado_por (cambiado_por),
    INDEX idx_fecha_cambio (fecha_cambio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auditoría de cambios de correo electrónico de usuarios';
