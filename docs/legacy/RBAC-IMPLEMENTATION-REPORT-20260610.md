**Resumen**
- Fecha: 2026-06-10
- Objetivo: Implementación inicial de RBAC (roles y permisos) para DentiSoft.

**Cambios principales**
- Añadida matriz centralizada de permisos: [config/permissions.php](config/permissions.php)
- Helpers de autorización y middleware: funciones `can()`, `userHasPermission()`, `getUserPermissions()` y `requirePermission()` añadidas en [config/session.php](config/session.php)
- Menú lateral protegido con `can()` en [includes/sidebar.php](includes/sidebar.php)
- Cabecera de usuario actualizada para mostrar rol, permisos y último acceso: [includes/header.php](includes/header.php)
- Rutas/protecciones aplicadas en módulos clave (ver lista abajo).
- Auditoría: `requirePermission()` registra intento de acceso denegado usando `registrarAuditoria()` si existe.

**Permisos implementados (ejemplos)**
- usuarios.ver, usuarios.crear, usuarios.editar, usuarios.eliminar
- pacientes.ver, pacientes.crear, pacientes.editar, pacientes.eliminar
- historias.ver, historias.crear, historias.editar, historias.eliminar, historias.adjuntar
- citas.ver, citas.crear, citas.editar, citas.cancelar, citas.confirmar
- tratamientos.ver, tratamientos.crear, tratamientos.editar, tratamientos.eliminar
- facturacion.ver, facturacion.crear, facturacion.pagar, facturacion.imprimir
- reportes.ver
- sistema.config, sistema.audit

**Rutas y archivos protegidos (modificados)**
- modules/usuarios/index.php (requirePermission('usuarios.ver'))
- modules/usuarios/crear.php (requirePermission('usuarios.crear'))
- modules/usuarios/editar.php (requirePermission('usuarios.editar'))
- modules/pacientes/index.php (requirePermission('pacientes.ver'))
- modules/pacientes/crear.php (requirePermission('pacientes.crear'))
- modules/pacientes/editar.php (requirePermission('pacientes.editar'))
- modules/pacientes/eliminar.php (requirePermission('pacientes.eliminar'))
- modules/pacientes/ver.php (requirePermission('pacientes.ver'))
- modules/historia_clinica/* (index, crear, editar, ver, adjuntos) -> requirePermission('historias.*')
- modules/citas/* (index, crear, editar, calendario, cambiar_estado) -> requirePermission('citas.*')
- modules/tratamientos/* (index, crear_plan, sesiones, avance) -> requirePermission('tratamientos.*')
- modules/facturacion/* (index, crear, ver, pagos, imprimir) -> requirePermission('facturacion.*')
- modules/reportes/* (index, cuentas_cobrar, exportar, procedimientos, ingresos) -> requirePermission('reportes.ver')

**Vistas / acciones ocultadas**
- Botones "Nuevo usuario", "Editar usuario" en [modules/usuarios/index.php] se muestran solo si `can('usuarios.crear')` / `can('usuarios.editar')`.
- Botones "Nuevo paciente", editar/inactivar/eliminar en [modules/pacientes/index.php] envueltos en `can('pacientes.*')`.
- Botones de citas (Nueva cita, editar, confirmar, cancelar, marcar completada) controlados por `can('citas.crear')` / `can('citas.editar')` en [modules/citas/index.php].

**Mejoras de seguridad realizadas**
- Centralización de permisos para evitar duplicación y facilitar auditoría.
- Protección backend (403) en rutas sensibles usando `requirePermission()` para impedir accesos manuales por URL.
- Registro de intentos de acceso denegado a auditoría (si `registrarAuditoria()` existe).
- Menú y botones UI ocultos según permisos, evitando mostrar superficies de ataque innecesarias.
- Compatibilidad con PHP 8+ (tipos de retorno y funciones existentes conservadas).

**Siguientes pasos recomendados**
1. Revisar y completar la protección en todos los archivos de módulos menores (actualmente protegidos los principales).
2. Actualizar consultas para que `odontologo` solo vea sus pacientes/citas (en consultas SQL agregar `WHERE odontologo_id = :id` si aplica).
3. Añadir pruebas manuales / automatizadas para permisos críticos.
4. Ejecutar migración/validación para `ultimo_acceso` (si aplica) y desplegar en staging.
5. Revisar `registrarAuditoria()` para asegurar almacenamiento de metadata (IP, URI, usuario, permiso).

**Archivos modificados**
- config/permissions.php (nuevo)
- config/session.php (modificado)
- includes/sidebar.php (modificado)
- includes/header.php (modificado)
- modules/usuarios/index.php (modificado)
- modules/usuarios/crear.php (modificado)
- modules/usuarios/editar.php (modified)
- modules/pacientes/index.php (modified)
- modules/pacientes/crear.php (modified)
- modules/pacientes/editar.php (modified)
- modules/pacientes/eliminar.php (modified)
- modules/pacientes/ver.php (modified)
- modules/historia_clinica/index.php (modified)
- modules/historia_clinica/crear.php (modified)
- modules/historia_clinica/editar.php (modified)
- modules/historia_clinica/ver.php (modified)
- modules/historia_clinica/adjuntos.php (modified)
- modules/citas/index.php (modified)
- modules/citas/crear.php (modified)
- modules/citas/editar.php (modified)
- modules/citas/calendario.php (modified)
- modules/citas/cambiar_estado.php (modified)
- modules/tratamientos/index.php (modified)
- modules/tratamientos/crear_plan.php (modified)
- modules/tratamientos/sesiones.php (modified)
- modules/tratamientos/avance.php (modified)
- modules/facturacion/index.php (modified)
- modules/facturacion/crear.php (modified)
- modules/facturacion/ver.php (modified)
- modules/facturacion/pagos.php (modified)
- modules/facturacion/imprimir.php (modified)
- modules/reportes/index.php (modified)
- modules/reportes/cuentas_cobrar.php (modified)
- modules/reportes/exportar.php (modified)
- modules/reportes/procedimientos.php (modified)
- modules/reportes/ingresos.php (modified)

**Notas**
- No se han tocado las funciones de negocio (consultas complejas) salvo añadir `requirePermission()` en las cabeceras de archivos.
- Algunas vistas adicionales pueden requerir ajustes de botones/acciones; se recomienda una pasada de QA funcional para cada rol.

---
Generado automáticamente por la acción de refactor RBAC. Para proceder puedo:
- Completar protección de archivos restantes (marcar en TODO y continuar).
- Implementar filtros SQL que limiten vistas para `odontologo` (ver solo sus pacientes/citas).
- Añadir página de administración de roles/permisos si desea interfaz para editar la matriz.

Si quiere que continúe, indíqueme la prioridad entre: terminar protección completa, políticas por consulta (odontólogo), o interfaz de administración de permisos.
