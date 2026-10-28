# ADR-007 — Cancelación de Citas y Pestaña «Citas»

**Fecha:** 2026-10-24  
**Estado:** Aceptada  
**Incremento:** I3 (US-05 / RF05, T-037 a T-041)  
**Reglas de origen:** DAT-01, BAJA-01, ARQ-01, ACC-01, SEC-01, UI-01  

---

## Contexto

US-05 permite al administrador cancelar una cita Programada, con un motivo opcional, para liberar su horario. Además se pidió una pestaña que muestre las citas, que permita agendar desde ella y que el botón del dashboard lleve directamente al formulario.

## Decisiones

1. **Cancelación atómica.** La cancelación es un único `UPDATE ... WHERE id_cita = :id AND estado = 'Programada'` (T-037). El resultado se interpreta por el número de filas afectadas: 1 = cancelada; 0 = no existe o ya no estaba Programada. Así dos administradores que cancelan a la vez no se pisan ni sobrescriben el motivo, y una cita Atendida no puede cancelarse aunque el estado cambie entre la lectura y la escritura. No hace falta bloquear al veterinario: cancelar solo libera agenda.

2. **El horario se libera por consecuencia, no por código extra.** La ocupación (ADR-006) ya excluye las citas Canceladas, así que cambiar el estado basta para que la franja vuelva a ofrecerse.

3. **Reglas (T-038).** El servicio valida: id entero estricto (solo dígitos), que la cita exista, que esté Programada y que el motivo sea válido. El motivo es opcional: se recorta, se eliminan caracteres de control, vacío o solo espacios = `NULL`, máximo **500** caracteres, solo texto UTF-8 válido.

4. **Pestaña única `/citas`.** Contiene el formulario de agendar (arriba) y el listado (abajo). `/citas/crear` queda como redirección a `/citas` para no romper marcadores. `crear.php` pasó a ser un fragmento incluido por `listar.php`. El botón «Agendar Cita» del dashboard apunta a `/citas`, donde el formulario ya está visible.

5. **Listado.** Filtra por estado (Programadas por defecto, Atendidas, Canceladas, Todas; un valor desconocido equivale a Programadas), ordena de la más próxima a la más lejana y muestra como máximo **200** citas, avisando si hay más.

6. **Confirmación en dos pasos.** Cancelar abre una pantalla (`confirmar_cancelacion.php`) con los datos de la cita y el motivo opcional; la cancelación es un `POST /citas/cancelar` con token CSRF. Un token inválido responde 419 sin cambiar datos; el resto de resultados redirige a `/citas` con un mensaje. El botón de confirmar se deshabilita tras el primer clic.

7. **Acceso y menú.** Todas las rutas de citas exigen rol administrador. La pestaña «Citas» solo aparece en el menú de los administradores; en las pantallas compartidas con veterinarios (`/mascotas`, `/propietarios`) se muestra únicamente si el rol es administrador.

## Consecuencias

- Cancelar y reservar son seguras ante concurrencia (verificado con 6 y 8 procesos simultáneos contra MySQL).
- La interfaz solo ofrece la acción donde procede, y el servidor la vuelve a validar.

**Limitaciones conocidas:**
- Se puede cancelar una cita Programada cuya fecha ya pasó (útil para registrar inasistencias); no hay un estado intermedio.
- Una cita pasa a Atendida al registrar su consulta (US-06, ADR-008); antes de eso no existía ese camino y se probaba por SQL.
- El listado no pagina ni filtra por fecha o veterinario; con muchas citas pasadas sin atender, las más antiguas ocupan los primeros lugares.
- La cancelación no registra quién la hizo ni cuándo (la tabla `citas` no tiene esas columnas).
- Los veterinarios no ven su agenda; se decidió mantener las citas como una función exclusiva del administrador, tal como piden las historias.
