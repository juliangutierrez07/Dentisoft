-- ============================================================
-- SCRIPT DE LIMPIEZA - DentiSoft 1.0
-- Elimina datos operativos conservando usuarios y configuración
-- Fecha: 2026-06-11
-- ============================================================

-- Desabilitar restricciones de claves foráneas temporalmente
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- PASO 1: VACIAR TABLAS DEPENDIENTES (Orden de dependencias)
-- ============================================================

-- Tabla: pagos (sin dependientes)
TRUNCATE TABLE pagos;
ALTER TABLE pagos AUTO_INCREMENT = 1;

-- Tabla: detalle_facturas (sin dependientes)
TRUNCATE TABLE detalle_facturas;
ALTER TABLE detalle_facturas AUTO_INCREMENT = 1;

-- Tabla: facturas (depende de pacientes, usuarios, planes_tratamiento)
TRUNCATE TABLE facturas;
ALTER TABLE facturas AUTO_INCREMENT = 1;

-- Tabla: citas (depende de pacientes, usuarios, planes_tratamiento)
TRUNCATE TABLE citas;
ALTER TABLE citas AUTO_INCREMENT = 1;

-- Tabla: sesiones_tratamiento (depende de planes_tratamiento, usuarios)
TRUNCATE TABLE sesiones_tratamiento;
ALTER TABLE sesiones_tratamiento AUTO_INCREMENT = 1;

-- Tabla: planes_tratamiento (depende de historias_clinicas, pacientes, usuarios)
TRUNCATE TABLE planes_tratamiento;
ALTER TABLE planes_tratamiento AUTO_INCREMENT = 1;

-- Tabla: odontograma (depende de historias_clinicas, usuarios)
TRUNCATE TABLE odontograma;
ALTER TABLE odontograma AUTO_INCREMENT = 1;

-- Tabla: imagenes_clinicas (depende de historias_clinicas, usuarios)
TRUNCATE TABLE imagenes_clinicas;
ALTER TABLE imagenes_clinicas AUTO_INCREMENT = 1;

-- Tabla: historias_clinicas (depende de pacientes, usuarios)
TRUNCATE TABLE historias_clinicas;
ALTER TABLE historias_clinicas AUTO_INCREMENT = 1;

-- Tabla: paciente_accesos (depende de pacientes)
TRUNCATE TABLE paciente_accesos;
ALTER TABLE paciente_accesos AUTO_INCREMENT = 1;

-- Tabla: pacientes (sin dependientes después de limpiar las anteriores)
TRUNCATE TABLE pacientes;
ALTER TABLE pacientes AUTO_INCREMENT = 1;

-- Tabla: notificaciones (limpiar notificaciones de prueba, usuarios se conservan)
TRUNCATE TABLE notificaciones;
ALTER TABLE notificaciones AUTO_INCREMENT = 1;

-- Tabla: audit_log (limpiar logs de prueba)
TRUNCATE TABLE audit_log;
ALTER TABLE audit_log AUTO_INCREMENT = 1;

-- ============================================================
-- PASO 2: TABLAS QUE SE CONSERVAN
-- ============================================================
-- roles          ✓ CONSERVADO
-- usuarios       ✓ CONSERVADO
-- procedimientos_catalogo ✓ CONSERVADO

-- ============================================================
-- PASO 3: REHABILITAR RESTRICCIONES DE CLAVES FORÁNEAS
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- RESUMEN DE LIMPIEZA
-- ============================================================
-- TABLAS VACIADAS (12):
-- 1. pacientes
-- 2. historias_clinicas
-- 3. odontograma
-- 4. imagenes_clinicas
-- 5. planes_tratamiento
-- 6. sesiones_tratamiento
-- 7. citas
-- 8. facturas
-- 9. detalle_facturas
-- 10. pagos
-- 11. notificaciones
-- 12. audit_log
--
-- TABLAS CONSERVADAS (3):
-- 1. roles
-- 2. usuarios
-- 3. procedimientos_catalogo
--
-- RESULTADO: Base de datos lista para nuevos datos de prueba
-- ============================================================
