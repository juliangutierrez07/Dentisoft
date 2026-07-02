# Auditoria Arquitectonica DentiSoft

Fecha: 2026-06-15

## Resumen ejecutivo

DentiSoft es una aplicacion PHP procedural con rutas directas en raiz, `api/`, `modules/` y `portal-paciente/`. La prioridad fue limpiar superficie publica, introducir capas empresariales y mover codigo de bajo riesgo sin cambiar rutas activas ni reglas de negocio.

## Hallazgos por severidad

### Alta

- `.env` existe en raiz publica. Contiene configuracion SMTP sensible. Se mantiene por compatibilidad local, pero debe protegerse en Apache y nunca versionarse.
- Scripts de diagnostico, prueba y migracion estaban expuestos en raiz: `debug_*`, `test_*`, `setup-*`, `verify_*`, `run-migration-*`, `monitoreo-logins.php`.
- Archivos de sesion `sess_*` estaban en raiz publica.
- Logs operativos estaban en raiz publica: `error.log` y `logs/mail_service_debug.log`.
- SQL distribuido por vistas/rutas/controladores. Se detectaron 225 accesos PDO en runtime.

### Media

- APIs con formatos JSON inconsistentes: algunas usaban `error`, otras `code`, otras listas al nivel superior.
- Archivos grandes pendientes: `login.php` 830 lineas, `modules/pacientes/editar.php` 755, `helpers/MailService.php` 583, `index.php` 508.
- `helpers/MailService.php` activaba `display_errors` durante diagnostico y registraba usuario SMTP en log.
- `config/database.php` tenia valores de conexion hardcodeados.
- Documentacion operativa historica estaba mezclada con rutas publicas.

### Baja

- Carpetas `logs/`, `runtime_sessions/` y `database/backups/` quedaron vacias por compatibilidad historica.
- `composer.phar` sigue en raiz para no romper flujos locales de Composer, aunque conviene moverlo fuera del docroot en una segunda ventana controlada.

## Archivos movidos

### A `storage/cache/`

- 16 archivos `*.tmp`.

### A `storage/sessions/`

- 18 archivos `sess_*` desde raiz.
- 5 sesiones historicas desde `runtime_sessions/`.

### A `tests/manual/`

- `debug-ultimo-acceso.php`
- `debug_phpinfo.php`
- `monitoreo-logins.php`
- `run-migration-ultimo-acceso.php`
- `setup-mail.php`
- `test-mail.php`
- `test_conflict_validation.php`
- `test_db.php`
- `test_notificacion.php`
- `test_smtp.php`
- `test_smtp_email.php`
- `verify_cleanup.php`
- `login_tmp.html`

### A `docs/legacy/`

- `DIAGNOSTICO-ULTIMO-ACCESO.md`
- `FIX-EDICION-USUARIOS.md`
- `GUIA-RAPIDA-ULTIMO-ACCESO.md`
- `IMPLEMENTATION-MAIL.md`
- `QUICKSTART-MAIL.md`
- `RBAC-IMPLEMENTATION-REPORT-20260610.md`
- `README-MAIL.md`
- `REPORTE_LIMPIEZA_20260611.md`

### A `storage/logs/`

- `error.log`
- `mail_service_debug.log`

### A `storage/backups/`

- `odonto_db_backup_20260611_095026.sql`

### A `storage/tools/`

- `composer-setup.php`

## Cambios aplicados

- Creada capa `app/` con `Repositories`, `Services`, `Helpers`, `Validators`, `Controllers`, `Models`, `Middleware`, `Traits`.
- Agregado `app/Bootstrap.php` para autoload PSR-4 local de `DentiSoft\App`.
- Creado `ApiResponse` con formato uniforme `success/message/data` y compatibilidad temporal con claves antiguas.
- Migradas consultas de `api/pacientes_api.php`, `api/historias_api.php` y `api/dashboard_api.php` hacia Services/Repositories.
- `config/database.php` ahora lee `DB_*` desde entorno con fallbacks iguales a los valores anteriores.
- `.env.example` documenta variables de base de datos.
- `.gitignore` cubre `storage`, sesiones, temporales y pruebas manuales.
- `helpers/MailService.php` ya no muestra errores al navegador y oculta el usuario SMTP en logs.
- Agregados `storage/.htaccess` y `tests/.htaccess` para bloquear acceso web directo.

## Pendientes controlados

- Extraer progresivamente SQL de `modules/*`, `portal-paciente/*`, `includes/header.php`, `config/session.php` y `helpers/MailService.php`.
- Dividir `login.php`, `modules/pacientes/editar.php`, `helpers/MailService.php` e `index.php`.
- Homologar todas las APIs restantes al helper `ApiResponse`.
- Mover `assets/uploads` a `storage/uploads` con wrappers de descarga seguros. No se hizo para no romper rutas actuales de imagenes/documentos.
- Inicializar Git antes de una refactorizacion masiva adicional.

## Metricas

- Archivos retirados de raiz publica: 57.
- Runtime PHP auditado: 80 archivos.
- Archivos runtime de mas de 500 lineas: 4.
- Accesos SQL/PDO pendientes en runtime: 225.
- APIs migradas parcialmente a formato estandar y repositories: 3.
- Verificacion sintactica: `C:\xampp\php\php.exe -l` OK en 80 archivos runtime.
