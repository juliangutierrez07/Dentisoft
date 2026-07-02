# Modulos

## Pacientes

Responsable de datos administrativos del paciente, busqueda y acceso al portal. Se creo `PacienteRepository`, `PacienteService` y `PacienteValidator` como primera extraccion.

## Historias Clinicas

Responsable de historias, odontograma y adjuntos. Se creo `HistoriaClinicaRepository` y `HistoriaClinicaService` para busqueda API.

## Agenda

Implementada actualmente como `modules/citas` y `api/citas_api.php`. Pendiente mover consultas y normalizadores de calendario a `CitaRepository` y `CitaService`.

## Tratamientos

Implementada en `modules/tratamientos`. Pendiente separar planes, sesiones y avances en servicios distintos.

## Facturacion

Implementada en `modules/facturacion`. Se creo `FacturaRepository` para consulta de cartera usada por dashboard.

## Reportes

Implementada en `modules/reportes`. Tiene alta concentracion de consultas agregadas; debe migrarse a `ReporteRepository`.

## Usuarios

Implementada en `modules/usuarios`. Se creo `UsuarioRepository` para centralizar autenticacion y ultimo acceso en siguientes pasos.

## Configuracion

Implementada en `config/`. Mantiene constantes globales por compatibilidad, pero la configuracion sensible debe venir de `.env`.
