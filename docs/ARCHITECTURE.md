# Arquitectura DentiSoft

## Estilo actual

La aplicacion conserva rutas PHP directas para no romper compatibilidad:

- Raiz: login, dashboard, portal y seleccion de acceso.
- `api/`: endpoints AJAX.
- `modules/`: pantallas administrativas.
- `portal-paciente/`: vistas del portal del paciente.

## Capa empresarial introducida

- `app/Repositories`: unica ubicacion objetivo para consultas SQL nuevas.
- `app/Services`: coordinacion de casos de uso, validacion y reglas de aplicacion.
- `app/Validators`: validaciones reutilizables.
- `app/Helpers`: utilidades transversales, como respuestas JSON.
- `app/Controllers`: destino para controladores nuevos o wrappers futuros.
- `app/Middleware`: destino para autenticacion/autorizacion futura.

## Flujo recomendado

1. Ruta PHP recibe request y valida sesion/permisos.
2. Ruta delega a un Service.
3. Service valida y coordina repositories.
4. Repository ejecuta PDO y retorna arrays.
5. Ruta/API renderiza vista o responde JSON.

## Reglas de evolucion

- No agregar SQL nuevo en vistas, includes o rutas.
- No agregar validaciones complejas en HTML/PHP de vista.
- No romper URLs existentes; usar wrappers finos cuando se mueva logica.
- Toda respuesta JSON nueva debe usar `DentiSoft\App\Helpers\ApiResponse`.
- Todo dato sensible debe venir de entorno o archivos en `config/`.
