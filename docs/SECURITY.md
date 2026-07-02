# Seguridad

## Credenciales

- `.env` contiene configuracion sensible y no debe versionarse.
- `.env.example` solo debe contener placeholders.
- `config/database.php` lee `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` y `DB_CHARSET` desde entorno.
- `config/mail.php` lee credenciales SMTP desde entorno.

## Superficie publica

- Scripts `test/debug/setup/verify/run-migration` fueron movidos a `tests/manual/`.
- Sesiones y temporales fueron movidos a `storage/sessions` y `storage/cache`.
- Logs y backups fueron movidos a `storage/logs` y `storage/backups`.
- `storage/.htaccess` y `tests/.htaccess` deniegan acceso directo en Apache.

## Logging

- Los errores PHP se escriben en `storage/logs/dentisoft-error.log`.
- El log de mail se escribe en `storage/logs/mail_service_debug.log`.
- `helpers/MailService.php` ya no habilita `display_errors` y no imprime el usuario SMTP completo.

## Recomendaciones siguientes

- Mover `.env` fuera del docroot o bloquearlo explicitamente por Apache.
- Eliminar `composer.phar` del docroot despues de confirmar el flujo de despliegue.
- Usar descargas controladas para archivos clinicos en vez de rutas directas bajo `assets/uploads`.
- Agregar rate limiting al login y portal paciente.
- Centralizar CSRF y permisos en `app/Middleware`.
