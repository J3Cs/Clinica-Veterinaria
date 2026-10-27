# ADR-008 — Consultas Veterinarias: Atomicidad y Acceso

**Fecha:** 2026-10-25  
**Estado:** Aceptada  
**Incremento:** I4 (US-06 / RF06, T-042 a T-048)  
**Reglas de origen:** DAT-01, ARQ-01, ACC-01, SEC-01, UI-01  

---

> **Nota (ADR-009):** los veterinarios del seed ya tienen usuario con acceso; `init_veterinario.php` queda para cambiar contraseñas o crear otro veterinario.

## Contexto

US-06 permite al veterinario registrar diagnóstico, observaciones y tratamiento de una cita Programada. La consulta y el cambio de la cita a Atendida deben guardarse de forma atómica, no puede haber más de una consulta por cita, y la consulta debe verse una sola vez en el historial consolidado.

## Decisiones

1. **Transacción con la cita bloqueada.** `registrarConsulta` abre una transacción y: (1) lee la cita con `SELECT ... FOR UPDATE`; (2) revalida estado Programada, que sea del veterinario y que no tenga consulta; (3) inserta la consulta con la mascota y el veterinario **de la cita**; (4) cambia la cita con `UPDATE ... WHERE estado = 'Programada'` y exige 1 fila afectada; si no, lanza una excepción que revierte la consulta. Cualquier fallo de base de datos también revierte todo y el formulario conserva lo escrito.

2. **Varias capas de protección que se solapan, a propósito.** El bloqueo de la fila, el `UPDATE` condicional y el `UNIQUE` de `consultas_veterinarias.id_cita` impiden por separado el estado inconsistente. Se verificó con procesos simultáneos contra MySQL que, al quitar las tres a la vez, la prueba de carrera detecta el fallo; con cualquiera en pie, la cita no queda inconsistente. El bloqueo, además, da mensajes claros en vez de errores de clave duplicada.

3. **Cada veterinario atiende solo sus citas.** La consulta solo se acepta si `citas.id_veterinario` coincide con el perfil del usuario autenticado (`veterinarios.id_usuario`). Es la lectura más estricta de «el veterinario correcto». Si el negocio quisiera permitir que un colega cubra una cita, basta relajar esa comprobación en `ConsultaService`.

4. **El cliente solo aporta el id de la cita y cuatro textos.** Mascota, veterinario, estado y fecha de atención salen de la base de datos o del servidor (T-045); cualquier otro campo enviado se ignora. La fecha de atención se fija desde PHP (zona `America/El_Salvador`) en vez de depender del reloj del servidor MySQL.

5. **Validación.** Diagnóstico y tratamiento obligatorios (la columna `tratamiento` admite NULL en el esquema; la obligatoriedad se exige en el modelo y el servicio). Observaciones y servicios adicionales opcionales (vacío = NULL). Máximo **2000** caracteres por campo, texto UTF-8 válido; se unifican los saltos de línea y se eliminan caracteres de control salvo salto de línea y tabulación.

6. **Sin duplicar en `historial_mascota`.** Esa tabla guarda antecedentes (condiciones y alergias previas), no eventos. La consulta no se copia allí: el historial consolidado (`ConsultaService::historialClinico`) une `consultas_veterinarias` y `historial_mascota` en una línea de tiempo, así cada consulta aparece una sola vez. Se muestra, solo lectura, bajo el formulario (10 eventos).

7. **Agenda del veterinario como punto de entrada.** El panel `/veterinario/agenda` era un mensaje de bienvenida. Ahora lista las citas Programadas del veterinario con un botón **Registrar consulta**. No estaba en la lista de tareas, pero sin ella la función no tendría cómo alcanzarse desde la interfaz.

8. **Acceso.** `GET /consultas/crear?id_cita=N` y `POST /consultas` exigen el rol veterinario; el administrador recibe 403. El `POST` exige token CSRF. Una cita no atendible redirige a la agenda con el motivo.

9. **`init_veterinario.php`.** Como todavía no hay pantallas para gestionar veterinarios (US-09/US-11), este script de consola crea un veterinario con acceso o asigna contraseña a uno existente (por ejemplo los del seed, que no pueden iniciar sesión). Es solo CLI, como `init_admin.php`.

## Consecuencias

- Una cita no puede quedar Atendida sin consulta ni con consulta estando Cancelada, ni con solicitudes simultáneas.
- Cancelar (US-05) y atender (US-06) se serializan correctamente; ya existe un camino real hacia el estado Atendida.

**Limitaciones conocidas:**
- Las consultas no se pueden editar ni anular una vez guardadas.
- El historial consolidado se ve solo dentro del formulario de consulta; aún no hay una pantalla propia de historial por mascota.
- El administrador no ve el contenido clínico de las consultas.
- La agenda del veterinario solo muestra citas Programadas (no las atendidas) y no pagina (tope de 200).
- Si el veterinario de una cita cambia de usuario o se desactiva, nadie más puede atender esa cita hasta que se reasigne (aún no existe la reasignación).
