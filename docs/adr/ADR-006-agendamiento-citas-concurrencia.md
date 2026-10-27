# ADR-006 — Agendamiento de Citas: Disponibilidad y Concurrencia

**Fecha:** 2026-10-23  
**Estado:** Aceptada  
**Incremento:** I3 (US-04 / RF04, T-030 a T-036)  
**Reglas de origen:** DAT-01, BAJA-01, ARQ-01, ACC-01, SEC-01, TEC-01, UI-01  

---

> **Nota (ADR-007):** el formulario descrito aquí ya no es una página aparte: vive en la pestaña `/citas`; `/citas/crear` redirige allí.

## Contexto

US-04 permite al administrador agendar una cita eligiendo mascota, servicio, veterinario, fecha y horario. Los horarios salen de `horarios_atencion` y de la duración del servicio, no puede haber dos citas solapadas para el mismo veterinario, y la validación final se repite en el servidor dentro de una transacción.

## Decisiones

1. **Estructura y nombres.** La tabla de tareas cita rutas como `app/Modelos/Cita.php` o `app/Servicios/CitasServicio.php`. Se mantuvo la convención vigente del proyecto: `Models/CitaModel`, `Repositories/*Repository`, `Services/CitaService`, `Controllers/CitaController`, `Views/citas/crear.php`. Los recursos estáticos sí siguen la ruta de la tarea (`public/assets/js/citas.js`, `public/assets/css/citas.css`).
2. **Modelos adicionales.** T-031 lista solo repositorios para horarios y servicios. Para respetar ARQ-01 (solo los modelos contienen SQL) se añadieron `HorarioAtencionModel`, `ServicioModel`, `VeterinarioModel` y `VeterinarioRepository`.
3. **Franjas.** Dentro de cada bloque de `horarios_atencion` se ofrecen inicios cada **15 minutos** (`CitaService::PASO_MINUTOS`) mientras `inicio + duración <= fin del bloque`. Se admiten varios bloques por día. Si la fecha es hoy, solo horas posteriores a la actual.
4. **Qué ocupa agenda.** Toda cita que no esté `Cancelada` (Programada o Atendida). Dos intervalos se solapan si `inicioA < finB` y `finA > inicioB`; las contiguas no se solapan. El fin de una cita existente se calcula con la duración actual de su servicio, porque `citas` no guarda hora de fin.
5. **Concurrencia.** `agendarCita` abre una transacción y: (1) bloquea la fila del veterinario con `SELECT … FOR UPDATE`, lo que serializa sus reservas aunque aún no haya citas que bloquear; (2) relee las citas con `FOR UPDATE`; (3) recalcula las franjas y exige que la hora pedida esté entre ellas; (4) inserta y confirma. Verificado con 8 procesos PHP simultáneos contra MariaDB: queda una cita. Sin el bloqueo se guardaban las 8.
6. **Solo se aceptan horas ofrecidas.** El servidor acepta únicamente una hora que él mismo habría ofrecido; una sola regla cubre fuera de horario, solape, hora no alineada y fecha pasada.
7. **Veterinario activo.** `veterinarios` no tiene columna `activo`; se considera activo si su cuenta en `usuarios` lo está.
8. **Flujo sin JavaScript.** Consulta por `GET /citas/crear?consultar=1&…` y confirmación por `POST /citas`. El JavaScript solo añade validación previa y resumen de confirmación, sin peticiones asíncronas. Si el POST falla, se redirige a la consulta ya actualizada conservando la selección.
9. **Zona horaria.** `index.php` fija `America/El_Salvador` (configurable con `APP_TIMEZONE`); sin ello "no agendar en el pasado" dependería de la zona del servidor (UTC en Docker).
10. **Datos maestros.** US-04 depende de US-09, US-10 y US-11, que aún no tienen pantallas. `database/seeds/dev_citas_seed.sql` carga datos de ejemplo solo para desarrollo, con cuentas de veterinario sin acceso.

## Consecuencias

- No pueden quedar dos citas no canceladas solapadas para un veterinario, ni con solicitudes simultáneas.
- La validación depende solo del servidor; manipular el HTML no sirve.
- Índice `idx_citas_vet_fecha_estado` (migración 005) para la consulta de agenda.

**Limitaciones conocidas:**
- No hay listado, cancelación ni reprogramación de citas (fuera de US-04).
- El bloqueo es por veterinario: reservas simultáneas del mismo veterinario se serializan aunque sean de horas distintas (impacto despreciable a este volumen).
- No se impide que una misma mascota tenga dos citas a la misma hora con veterinarios distintos; los criterios no lo piden y conviene decidirlo con el negocio.
- Si cambia la duración de un servicio, las citas ya creadas se interpretan con la duración nueva.
