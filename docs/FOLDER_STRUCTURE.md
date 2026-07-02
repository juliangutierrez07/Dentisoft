# Estructura de Carpetas

## Estructura activa

```text
app/
  Controllers/
  Helpers/
  Middleware/
  Models/
  Repositories/
  Services/
  Traits/
  Validators/
api/
assets/
config/
database/
docs/
  legacy/
helpers/
includes/
modules/
  citas/
  facturacion/
  historia_clinica/
  pacientes/
  reportes/
  tratamientos/
  usuarios/
portal-paciente/
storage/
  backups/
  cache/
  logs/
  sessions/
  tools/
  uploads/
tests/
  manual/
vendor/
```

## Nota de compatibilidad

La estructura objetivo proponia `modules/Pacientes`, `modules/Agenda`, etc. En esta intervencion no se renombraron carpetas activas porque las URLs actuales dependen de nombres en minuscula y Windows puede ocultar problemas de mayusculas/minusculas que aparecerian en Linux.

## Politica

- `storage/` no debe exponer archivos al navegador.
- `tests/manual/` contiene scripts antiguos de prueba y diagnostico, bloqueados por `.htaccess`.
- `docs/legacy/` conserva documentacion operativa anterior.
- `assets/uploads/` se conserva por compatibilidad; el destino recomendado es `storage/uploads/`.
