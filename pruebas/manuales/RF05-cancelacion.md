# Matriz y Guía de Pruebas Manuales: RF05 — Cancelación de Citas (US-05, T-041)

Este documento detalla los casos de prueba para verificar el requisito funcional **RF05 (Cancelación de citas)**, asegurando el cumplimiento de: **DAT-01**, **ARQ-01**, **ACC-01**, **SEC-01**, **BAJA-01** y **UI-01**, y la restricción de estados (solo una cita *Programada* puede cancelarse).

Pruebas automatizadas relacionadas:

- `php -d zend.assertions=1 pruebas/test_citas.php` — reglas de cancelación y listado con dobles en memoria (incluye casos *US-05*).
- `php -d zend.assertions=1 pruebas/test_citas_integracion.php` — contra MySQL real: `UPDATE` condicional, listado con uniones y **6 procesos cancelando la misma cita a la vez** (solo uno lo logra).

---

## 1. Preparación

1. Haber aplicado `database/migrations/004_fk_restrict.sql` y `005_citas_indice_disponibilidad.sql`, y cargado `database/seeds/dev_citas_seed.sql` (ver `RF04-agendamiento.md`).
2. Sesión de **administrador**, una mascota activa y, para CP-17, una sesión de **veterinario**.
3. Crear al menos tres citas Programadas desde **Citas** (`/citas`) con la Dra. Mendoza en un día laborable futuro.
4. Todavía no existe una pantalla para marcar una cita como *Atendida*. Para CP-03 se hace por SQL:

```sql
UPDATE citas SET estado = 'Atendida' WHERE id_cita = <id>;
```

---

## 2. Matriz de Casos de Prueba

| ID | Caso de Prueba | Precondición | Pasos de Ejecución | Resultado Esperado | Estado |
|---|---|---|---|---|---|
| **CP-01** | Cancelar una cita Programada con motivo | Cita Programada en el listado. | 1. Ir a **Citas** (`/citas`)<br>2. En la cita, clic en **Cancelar**<br>3. Escribir un motivo<br>4. Clic en **Sí, cancelar cita** | Se muestra *"Cita cancelada: … El horario quedó disponible."* La cita sale de "Programadas". En BD: `estado = 'Cancelada'` y `motivo_cancelacion` con el texto. | [ ] Aprobado |
| **CP-02** | Cancelar sin motivo | Cita Programada. | 1. Repetir CP-01 dejando el motivo vacío (o solo espacios) | Se cancela con éxito. `motivo_cancelacion` queda en `NULL`. El motivo **no** es obligatorio. | [ ] Aprobado |
| **CP-03** | Una cita Atendida no se puede cancelar | Cita con `estado = 'Atendida'` (por SQL). | 1. Filtrar el listado por **Atendidas**<br>2. Forzar por URL `/citas/cancelar?id=<id>` | En el listado **no** hay botón Cancelar (se ve "—"). Por URL redirige a `/citas` con *"Solo se pueden cancelar citas Programadas (esta cita está Atendida)."* El estado no cambia. | [ ] Aprobado |
| **CP-04** | Una cita Cancelada no vuelve a cancelarse | Cita ya cancelada con motivo "A". | 1. Filtrar por **Canceladas**<br>2. Forzar `/citas/cancelar?id=<id>`<br>3. Forzar un POST a `/citas/cancelar` con motivo "B" | Sin botón Cancelar. Mensaje *"…esta cita está Cancelada."* En BD el motivo sigue siendo "A". | [ ] Aprobado |
| **CP-05** | El horario se libera | Cita Programada de las 09:00 con Dra. Mendoza. | 1. En **Agendar cita** consultar ese día: `09:00` no aparece<br>2. Cancelar la cita<br>3. Volver a consultar | `09:00` vuelve a ofrecerse y se puede agendar otra cita en ese horario. | [ ] Aprobado |
| **CP-06** | Dos administradores cancelan la misma cita | Dos sesiones de administrador. | 1. Ambas abren la pantalla de confirmación de la misma cita<br>2. La primera confirma<br>3. La segunda confirma | La primera cancela. La segunda recibe un aviso (*"Solo se pueden cancelar citas Programadas…"* o *"La cita ya no está Programada; no se realizaron cambios."*). Se conserva el motivo de la primera. | [ ] Aprobado |
| **CP-07** | Concurrencia real (automatizada) | MySQL accesible. | 1. Ejecutar `php -d zend.assertions=1 pruebas/test_citas_integracion.php` | Pasa *"6 procesos cancelan la misma cita a la vez -> exactamente 1 lo logra"*. | [ ] Aprobado |
| **CP-08** | Protección CSRF | Sesión de administrador. | 1. Enviar POST a `/citas/cancelar` con `csrf_token` incorrecto, vacío o ausente | HTTP 419 en los tres casos. La cita no cambia (SEC-01). | [ ] Aprobado |
| **CP-09** | Identificador inválido o inexistente | Sesión de administrador. | 1. Abrir `/citas/cancelar?id=abc`, `?id=999` y `?id[]=1` | Redirige a `/citas` con *"La cita indicada no es válida."* o *"La cita no existe."* Sin errores 500. | [ ] Aprobado |
| **CP-10** | Motivo demasiado largo | Cita Programada. | 1. En la confirmación, pegar más de 500 caracteres (el navegador limita a 500)<br>2. Forzar un POST con 501 | El servidor rechaza con *"El motivo no puede superar los 500 caracteres."* y la cita sigue Programada. | [ ] Aprobado |
| **CP-11** | Motivo con HTML | Cita Programada. | 1. Cancelar con el motivo `<script>alert(1)</script>`<br>2. Filtrar por **Canceladas** | El texto se muestra literal, sin ejecutarse ni interpretarse como HTML. | [ ] Aprobado |
| **CP-12** | Volver sin cancelar | Cita Programada. | 1. Abrir la confirmación<br>2. Clic en **No, volver al listado** | Regresa a `/citas`. La cita sigue Programada. | [ ] Aprobado |
| **CP-13** | Doble clic en confirmar | Cita Programada. | 1. En la confirmación, hacer doble clic en **Sí, cancelar cita** | El botón se deshabilita ("Cancelando…") y la cita se cancela una sola vez. | [ ] Aprobado |
| **CP-14** | Control de acceso: veterinario | Sesión de `veterinario`. | 1. Abrir `/citas`<br>2. Abrir `/citas/cancelar?id=<id>`<br>3. Enviar un POST a `/citas/cancelar` | HTTP 403 en los tres casos (ACC-01). La cita no cambia. | [ ] Aprobado |
| **CP-15** | Acceso anónimo | Sin sesión. | 1. Abrir `/citas` | Redirección a `/login`. | [ ] Aprobado |
| **CP-16** | Filtros del listado | Citas en los tres estados. | 1. Abrir `/citas` (por defecto **Programadas**)<br>2. Cambiar el filtro a **Atendidas**, **Canceladas** y **Todas** | Cada filtro muestra solo su estado; "Todas" muestra todas, ordenadas por fecha y hora. Un valor inventado en la URL se trata como "Programadas". | [ ] Aprobado |
| **CP-17** | Pestaña **Citas** y menú | Sesiones de administrador y veterinario. | 1. Como administrador, ver el menú en Dashboard, Propietarios y Mascotas<br>2. Como veterinario, abrir `/mascotas` | El administrador ve la pestaña **Citas** en todas. El veterinario **no** la ve. | [ ] Aprobado |
| **CP-18** | El botón del dashboard abre la pestaña con el formulario | Sesión de administrador. | 1. En el Dashboard, clic en **Agendar Cita** | Abre `/citas` con el formulario de agendar ya visible arriba y el listado debajo. | [ ] Aprobado |
| **CP-19** | Agendar desde la pestaña | Datos del seed. | 1. En `/citas`, elegir mascota, servicio, veterinario y fecha<br>2. **Ver horarios disponibles** → elegir hora → **Confirmar cita** | Vuelve a `/citas` con el mensaje de éxito y la cita nueva aparece en el listado de Programadas. | [ ] Aprobado |
| **CP-20** | Compatibilidad con la ruta anterior | Sesión de administrador. | 1. Abrir `/citas/crear` | Redirige a `/citas`. | [ ] Aprobado |

---

## 3. Criterios de Aceptación Verificados

- [ ] Solo se cancela una cita existente en estado Programada; pasa a Cancelada y se muestra confirmación (CP-01, CP-03, CP-04, CP-09).
- [ ] El motivo es opcional y se conserva si se proporciona (CP-01, CP-02, CP-10, CP-11).
- [ ] Las citas Atendidas o Canceladas no pueden volver a cancelarse (CP-03, CP-04, CP-06).
- [ ] El horario vuelve a estar disponible (CP-05).
- [ ] Solo el administrador cancela, con token CSRF (CP-08, CP-14, CP-15).
- [ ] La pantalla muestra el estado y pide confirmación (CP-01, CP-12, CP-13).
