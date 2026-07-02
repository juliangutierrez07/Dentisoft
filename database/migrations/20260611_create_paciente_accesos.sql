-- Migration: Create paciente_accesos
-- Descripcion: Credenciales separadas para el Portal del Paciente.

CREATE TABLE IF NOT EXISTS paciente_accesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    usuario_documento VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 1,
    estado ENUM('activo','inactivo','suspendido') NOT NULL DEFAULT 'activo',
    ultimo_acceso DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_paciente_accesos_paciente (paciente_id),
    UNIQUE KEY uq_paciente_accesos_documento (usuario_documento),
    KEY idx_paciente_accesos_estado (estado),
    KEY idx_paciente_accesos_ultimo_acceso (ultimo_acceso),
    CONSTRAINT fk_paciente_accesos_paciente
        FOREIGN KEY (paciente_id) REFERENCES pacientes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
