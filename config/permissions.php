<?php
/**
 * Matriz centralizada de permisos por rol
 * Cada permiso es una cadena única usada en `can()` y `requirePermission()`
 */
return [
    'administrador' => [
        // Usuarios
        'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar',
        // Pacientes
        'pacientes.ver', 'pacientes.crear', 'pacientes.editar', 'pacientes.eliminar',
        // Historias clínicas
        'historias.ver', 'historias.crear', 'historias.editar', 'historias.eliminar',
        // Citas
        'citas.ver', 'citas.crear', 'citas.editar', 'citas.cancelar',
        // Tratamientos
        'tratamientos.ver', 'tratamientos.crear', 'tratamientos.editar', 'tratamientos.eliminar',
        // Facturación
        'facturacion.ver', 'facturacion.crear', 'facturacion.pagar', 'facturacion.imprimir',
        // Reportes
        'reportes.ver',
        // Configuración y auditoría
        'sistema.config', 'sistema.audit',
    ],

    'asistente' => [
        'dashboard.limited',
        // Pacientes
        'pacientes.ver', 'pacientes.crear', 'pacientes.editar',
        // Citas
        'citas.ver', 'citas.crear', 'citas.editar', 'citas.cancelar', 'citas.confirmar',
        // Facturación
        'facturacion.ver', 'facturacion.crear', 'facturacion.pagar', 'facturacion.imprimir',
        // Consultas
        'tratamientos.ver',
    ],

    'odontologo' => [
        'dashboard.professional',
        // Pacientes (solo ver sus pacientes handled at query level)
        'pacientes.ver',
        // Citas (ver only assigned)
        'citas.ver',
        // Historias
        'historias.ver', 'historias.crear', 'historias.editar',
        // Tratamientos
        'tratamientos.ver', 'tratamientos.crear', 'tratamientos.editar',
        // Archivos clinicos
        'historias.adjuntar',
    ],
];
