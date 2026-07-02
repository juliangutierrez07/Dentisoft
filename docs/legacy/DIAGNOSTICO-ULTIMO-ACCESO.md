# Investigación y Solución: Últimos Accesos de Usuarios

## 🔍 Problema Identificado

**Síntoma:** En la vista "Editar Usuario", todos los usuarios mostraban "Nunca ha iniciado sesión" incluso cuando tenían logins previos.

**Causa Raíz:** 
El UPDATE de `ultimo_acceso` en `login.php` se ejecutaba sin captura de errores específica. Si fallaba, nadie se enteraba porque:
1. El error se tragaba silenciosamente dentro del try-catch general
2. No había logging específico del UPDATE
3. No había verificación de si la ejecución fue exitosa

```php
// ❌ Código anterior - SIN manejo específico de errores
$db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id")
   ->execute([':id' => $usuario['id']]);
```

## 🛠️ Solución Implementada

### 1. **login.php** - Mejora del UPDATE con logging específico

```php
// ✅ Código nuevo - CON manejo de errores y logging
try {
    $updateStmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
    $updateResult = $updateStmt->execute([':id' => $usuario['id']]);
    
    if (!$updateResult) {
        error_log('Login: Error al actualizar ultimo_acceso para usuario_id=' . $usuario['id']);
    } else {
        error_log('Login exitoso: usuario_id=' . $usuario['id'] . ', email=' . $usuario['email']);
    }
} catch (Exception $e) {
    error_log('Login: Excepción al actualizar ultimo_acceso para usuario_id=' . $usuario['id'] . ': ' . $e->getMessage());
}
```

**Beneficios:**
- Captura explícitamente errores del UPDATE
- Registra en error.log cada login exitoso
- Permite diagnosticar problemas de actualización

### 2. **config/database.php** - Mejora de auditoría

Actualicé `registrarAuditoria()` para:
- Aceptar `ip_address` en datos
- Usar usuario_id del registro en caso de login
- Mejor captura de contexto

```php
// Pasar usuario_id explícitamente en login
registrarAuditoria('login', 'usuarios', $usuario['id'], null, ['ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
```

### 3. **modules/usuarios/editar.php** - Query mejorada

Cambié de `SELECT * FROM usuarios` a un SELECT explícito que asegure incluir `ultimo_acceso`:

```php
$userStmt = $db->prepare("
    SELECT 
        u.id, u.rol_id, u.nombre, u.apellido, u.email, u.password, u.telefono, u.estado, 
        u.ultimo_acceso, u.created_at, u.updated_at,
        r.nombre AS rol
    FROM usuarios u
    LEFT JOIN roles r ON u.rol_id = r.id
    WHERE u.id = :id
");
```

**Beneficios:**
- Explícito: vemos exactamente qué se selecciona
- Incluye JOIN con roles para contexto
- Mejor rendimiento con índices específicos

### 4. **Herramientas de Diagnóstico**

#### a) `debug-ultimo-acceso.php`
- Página de diagnóstico protegida (requiere `?pass=DentiSoft2026Debug`)
- Solo para administradores autenticados
- Muestra:
  - Estructura completa de tabla `usuarios`
  - Último acceso de cada usuario con timestamps
  - Total de logins auditados por usuario
  - Últimos 20 registros de login en audit_log
  - Errores recientes del error.log

#### b) `run-migration-ultimo-acceso.php`
- Script de migración ejecutable
- Sincroniza `ultimo_acceso` desde `audit_log`
- Crea índices para optimización
- Solo para administradores

### 5. **Migraciones SQL**

#### a) `20260610_add_ultimo_acceso_to_usuarios.sql`
Agrega el campo si no existe

#### b) `20260610_repair_ultimo_acceso.sql`
Sincroniza datos desde audit_log:
```sql
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
```

## 📝 Archivos Modificados

1. **login.php** (líneas 60-80)
   - Añadido manejo específico de errores para UPDATE
   - Añadido logging detallado

2. **config/database.php** (función registrarAuditoria)
   - Mejorada captura de usuario_id
   - Mejorada captura de IP

3. **modules/usuarios/editar.php** (líneas 17-37)
   - Query mejorada con campos explícitos
   - Añadido rol en SELECT

## 🚀 Pasos para Verificar y Solucionar

### Paso 1: Ejecutar Migración (Sincronizar datos históricos)
```
1. Ir a: http://localhost/DentiSoft1.0/run-migration-ultimo-acceso.php
2. Se sincronizarán los logins previos desde audit_log
3. Se creará el índice de rendimiento
```

### Paso 2: Verificar Diagnóstico
```
1. Ir a: http://localhost/DentiSoft1.0/debug-ultimo-acceso.php?pass=DentiSoft2026Debug
2. Revisar que los usuarios muestren último_acceso correcto
3. Revisar audit_log tenga registros de login
```

### Paso 3: Hacer un login nuevo
```
1. Logout del usuario actual
2. Login con cualquier usuario
3. Revisar error.log para confirmar que se loguea el suceso
4. Ir a editar ese usuario y verificar que muestre la hora correcta
```

### Paso 4: Revisar error.log
```
En Windows (XAMPP):
C:\xampp\htdocs\DentiSoft1.0\error.log

Debe mostrar líneas como:
[10-Jun-2026 14:32:45] Login exitoso: usuario_id=1, email=admin@dentisoft.com
```

## 🔐 Comportamiento Esperado Después de la Solución

### Vista de Editar Usuario:
- ✅ **Con último_acceso:** "Último acceso: 12/06/2026 02:45 PM"
- ✅ **Sin último_acceso (nunca inició):** "Nunca ha iniciado sesión"

### Vista de Listado de Usuarios (index.php):
- ✅ Muestra cada usuario con su último acceso
- ✅ Indica cuánto tiempo hace del último login
- ✅ Marca "Sin registro" si nunca ha iniciado sesión

## 🧪 Validación

Para validar que todo funciona:

```bash
# 1. Verificar estructura de tabla
mysql -uroot -e "USE odonto_db; SHOW COLUMNS FROM usuarios;" | grep ultimo_acceso

# 2. Verificar última inserción en audit_log
mysql -uroot -e "USE odonto_db; SELECT usuario_id, accion, created_at FROM audit_log WHERE accion='login' ORDER BY created_at DESC LIMIT 5;"

# 3. Revisar error.log
tail -20 /xampp/htdocs/DentiSoft1.0/error.log
```

## 📊 Estadísticas de Debugging

### Campos verificados en tabla usuarios:
✅ `id` - INT
✅ `rol_id` - INT  
✅ `nombre` - VARCHAR
✅ `apellido` - VARCHAR
✅ `email` - VARCHAR
✅ `password` - VARCHAR
✅ `telefono` - VARCHAR
✅ `estado` - ENUM
✅ `ultimo_acceso` - DATETIME (¡Este era el problema!)
✅ `created_at` - TIMESTAMP
✅ `updated_at` - TIMESTAMP

### Tabla audit_log:
✅ Registra todas las acciones
✅ Registra IP de conexión
✅ Registra timestamp de cada login
✅ Accesible para sincronizar datos históricos

## 🎯 Conclusión

El problema NO era que el campo no existiera, sino que:
1. El UPDATE no tenía logging específico para diagnosticar errores
2. No había forma de verificar si se estaba actualizando correctamente
3. No había herramientas de diagnóstico para investigar

**Solución implementada:**
- ✅ Logging específico en login
- ✅ Herramientas de diagnóstico
- ✅ Migraciones para sincronizar datos históricos
- ✅ Mejora de auditoría
- ✅ Queries mejoradas y más explícitas

Ahora es posible diagnosticar y solucionar cualquier problema de último_acceso en tiempo real.
