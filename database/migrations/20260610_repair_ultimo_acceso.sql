-- Migration: Verify and Repair ultimo_acceso field
-- Fecha: 2026-06-10
-- Descripción: Verifica que el campo ultimo_acceso exista y esté correctamente configurado
--              Sincroniza los valores desde audit_log si es necesario

-- 1. Verificar y crear el campo si no existe
ALTER TABLE usuarios
ADD COLUMN IF NOT EXISTS ultimo_acceso DATETIME NULL AFTER estado;

-- 2. Crear índice para mejor rendimiento en queries
ALTER TABLE usuarios
ADD INDEX IF NOT EXISTS idx_ultimo_acceso (ultimo_acceso);

-- 3. Sincronizar ultimo_acceso desde audit_log para usuarios que tienen logins registrados
-- Tomar el último login registrado en audit_log para cada usuario
UPDATE usuarios u
SET u.ultimo_acceso = (
    SELECT MAX(al.created_at)
    FROM audit_log al
    WHERE al.usuario_id = u.id 
    AND al.accion = 'login'
)
WHERE u.id IN (
    SELECT DISTINCT usuario_id 
    FROM audit_log 
    WHERE accion = 'login'
)
AND u.ultimo_acceso IS NULL;

-- 4. Registrar que esta migración se ejecutó
INSERT INTO audit_log (usuario_id, accion, tabla_afectada, datos_nuevos, ip_address)
VALUES (
    NULL,
    'migration',
    'usuarios',
    JSON_OBJECT('event', 'ultimo_acceso_repaired', 'timestamp', NOW()),
    '127.0.0.1'
);
