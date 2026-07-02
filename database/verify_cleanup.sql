-- Script para verificar el estado de la limpieza
SELECT 'RESULTADO DE LIMPIEZA - odonto_db' AS Reporte;
SELECT '========================================' AS '';

SELECT 
    'TABLAS VACIADAS (12 tablas)' AS Categoria;

SELECT CONCAT('pacientes: ', COUNT(*), ' registros') AS Estado FROM pacientes;
SELECT CONCAT('historias_clinicas: ', COUNT(*), ' registros') AS Estado FROM historias_clinicas;
SELECT CONCAT('odontograma: ', COUNT(*), ' registros') AS Estado FROM odontograma;
SELECT CONCAT('imagenes_clinicas: ', COUNT(*), ' registros') AS Estado FROM imagenes_clinicas;
SELECT CONCAT('planes_tratamiento: ', COUNT(*), ' registros') AS Estado FROM planes_tratamiento;
SELECT CONCAT('sesiones_tratamiento: ', COUNT(*), ' registros') AS Estado FROM sesiones_tratamiento;
SELECT CONCAT('citas: ', COUNT(*), ' registros') AS Estado FROM citas;
SELECT CONCAT('facturas: ', COUNT(*), ' registros') AS Estado FROM facturas;
SELECT CONCAT('detalle_facturas: ', COUNT(*), ' registros') AS Estado FROM detalle_facturas;
SELECT CONCAT('pagos: ', COUNT(*), ' registros') AS Estado FROM pagos;
SELECT CONCAT('notificaciones: ', COUNT(*), ' registros') AS Estado FROM notificaciones;
SELECT CONCAT('audit_log: ', COUNT(*), ' registros') AS Estado FROM audit_log;

SELECT '========================================' AS '';
SELECT 'TABLAS CONSERVADAS (3 tablas)' AS Categoria;

SELECT CONCAT('roles: ', COUNT(*), ' registros') AS Estado FROM roles;
SELECT CONCAT('usuarios: ', COUNT(*), ' registros') AS Estado FROM usuarios;
SELECT CONCAT('procedimientos_catalogo: ', COUNT(*), ' registros') AS Estado FROM procedimientos_catalogo;

SELECT '========================================' AS '';
SELECT 'USUARIOS DISPONIBLES PARA LOGIN:' AS Categoria;

SELECT 
    id,
    CONCAT(nombre, ' ', apellido) AS Nombre,
    email,
    (SELECT nombre FROM roles WHERE id = usuarios.rol_id) AS Rol,
    estado
FROM usuarios
ORDER BY id;
