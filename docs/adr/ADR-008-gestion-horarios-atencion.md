# ADR-008 — Gestión de Horarios de Atención Médica y Protección de Citas

**Fecha:** 2026-10-27  
**Estado:** Aceptada  
**Incremento:** US-11 (T-070 a T-075, RF11)  
**Reglas de origen:** `ARQ-01`, `CIT-01`, `BAJA-01`, `ACC-01`, `SEC-01`, `TEC-01`

---

## Contexto

El sistema de agendamiento de citas clínicas veterinarias (US-04 y US-05) requiere franjas horarias configuradas por veterinario y día de la semana para determinar la disponibilidad de turnos. Sin embargo, no existía un módulo administrativo para que el administrador pudiera crear, modificar, consultar o eliminar dichas franjas.

Existía el riesgo crítico (`CIT-01`, `BAJA-01`) de que un administrador pudiera alterar o borrar una franja horaria que ya contiene citas agendadas (`Programadas`) para fechas futuras, lo que dejaría pacientes en estado inconsistente o huérfanos de horario de atención.

## Decisión

1. **Separación estricta de 4 capas (`ARQ-01`):**
   - **`HorarioAtencionModel`**: Métodos SQL mediante sentencias preparadas PDO para consulta de disponibilidad, solapamientos, inserción, actualización y eliminación física.
   - **`HorarioAtencionRepository`**: Capa desacoplada para consultas y operaciones sobre las franjas.
   - **`HorariosServicio`**: Toda la lógica de negocio; validación de día (1 a 7), rangos horarios (`hora_inicio < hora_fin`), duración mínima, detección de solapamientos con franjas existentes y verificación de impacto en citas futuras.
   - **`HorarioAtencionControlador`**: Control de acceso exclusivo para rol `administrador` (`ACC-01`), protección CSRF en mutaciones (`SEC-01`), y patrón PRG.

2. **Detección de Solapamientos:**
   - Se adopta la condición matemática estricta de solapamiento para dos intervalos $[A, B)$ y $[C, D)$:
     $$\text{hora\_inicio} < \text{nueva\_hora\_fin} \quad\land\quad \text{hora\_fin} > \text{nueva\_hora\_inicio}$$
   - Las franjas contiguas (donde el fin de una coincide con el inicio de otra, ej. 08:00–12:00 y 12:00–16:00) **están permitidas**, facilitando la división de turnos matutinos y vespertinos.

3. **Protección de Citas Futuras (`CIT-01`, `BAJA-01`):**
   - Ante cualquier solicitud de **modificación** o **eliminación** de una franja, el servicio consulta citas en estado `Programada` para fechas futuras ($\ge \text{CURDATE()}$).
   - Si una cita quedaría fuera de cobertura horaria, la operación se **bloquea de inmediato** y se retorna un mensaje descriptivo con el ID de cita, fecha, hora y paciente para que el administrador resuelva o reprograme la cita antes de cambiar el horario.

## Consecuencias

- **Positivas:**
  - Integridad absoluta de la agenda: ninguna cita programada queda desamparada por cambios de horario médico.
  - Interfaz accesible y clara con validación reactiva en JavaScript nativo y protección en servidor.
  - Cobertura completa de pruebas automatizadas y manuales sin frameworks externos.
- **Negativas:**
  - El administrador no puede recortar o borrar una franja con citas activas de forma automática; debe reubicar o cancelar dichas citas primero (garantizando trazabilidad y comunicación al cliente).
