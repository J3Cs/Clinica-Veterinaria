# Matriz y Guía de Pruebas Manuales: RF06 — Consultas Veterinarias (US-06, T-048)

Este documento detalla los casos de prueba para verificar el requisito funcional **RF06 (Registro de la consulta)**, asegurando el cumplimiento de: **DAT-01**, **ARQ-01**, **ACC-01**, **SEC-01**, **BAJA-01**, **TEC-01** y **UI-01**, y la regla de estados (solo una cita *Programada* puede atenderse).

Pruebas automatizadas relacionadas:

- `php -d zend.assertions=1 pruebas/test_consultas.php` — reglas con dobles en memoria que simulan el rollback (19 pruebas).
- `php -d zend.assertions=1 pruebas/test_consultas_integracion.php` — contra MySQL real: rollback verdadero, `UNIQUE` de `id_cita`, que no se escriba en `historial_mascota`, **6 procesos registrando la misma consulta** y **cancelar contra registrar a la vez** (8 rondas) sin dejar estados inconsistentes.

---

## 1. Preparación

1. Haber aplicado las migraciones previas y cargado `database/seeds/dev_citas_seed.sql` (ver `RF04-agendamiento.md`). **US-06 no trae migraciones nuevas.**
2. Los dos veterinarios del seed ya tienen usuario con acceso (ver `ACC-roles-y-menu.md`):

   | Usuario | Contraseña inicial |
   |---|---|
   | `dra.demo1` (Dra. Laura Mendoza) | `Mendoza#2026` |
   | `dr.demo2` (Dr. Carlos Rivas) | `Rivas#2026` |

   Si tu base ya tenía el seed anterior, vuelve a ejecutar `database/seeds/dev_citas_seed.sql` (es idempotente). Para cambiar una contraseña o crear otro veterinario: `php init_veterinario.php`.
3. Como **administrador**, agendar desde **Citas** (`/citas`) varias citas con la **Dra. Mendoza** (`dra.demo1`) y una con el segundo veterinario. Para CP-04 cancelar una de las de la Dra. Mendoza.
4. Opcional, para ver el historial consolidado (CP-12): en `historial_mascota` insertar un antecedente de la mascota, por ejemplo:

```sql
INSERT INTO historial_mascota (id_mascota, condicion_medica, alergias, fecha_registro_antecedente)
VALUES (<id_mascota>, 'Epilepsia', 'Pollo', '2029-06-01');
```

---

## 2. Matriz de Casos de Prueba

| ID | Caso de Prueba | Precondición | Pasos de Ejecución | Resultado Esperado | Estado |
|---|---|---|---|---|---|
| **CP-01** | Registrar una consulta válida | Sesión de `dra.demo1` con una cita Programada suya. | 1. Entrar: abre `/veterinario/agenda`<br>2. En la cita, clic en **Registrar consulta**<br>3. Escribir diagnóstico y tratamiento (y, si se quiere, observaciones y servicios adicionales)<br>4. **Guardar consulta** | Vuelve a la agenda con *"Consulta registrada. La cita quedó como Atendida."* La cita ya no está en la lista. En BD: una fila en `consultas_veterinarias` y la cita en `Atendida`. | [ ] Aprobado |
| **CP-02** | La agenda muestra solo mis citas | Dos veterinarios con citas. | 1. Entrar como cada uno | Cada veterinario ve únicamente sus citas **Programadas**, de la más próxima a la más lejana. No aparecen Canceladas, Atendidas ni las del otro. | [ ] Aprobado |
| **CP-03** | Diagnóstico y tratamiento obligatorios | Formulario abierto. | 1. Dejar vacío el diagnóstico (o el tratamiento, o poner solo espacios)<br>2. Guardar | El navegador lo señala; si se fuerza, el servidor responde *"El diagnóstico es obligatorio."* / *"El tratamiento es obligatorio."* y **conserva lo escrito**. No se guarda nada y la cita sigue Programada. | [ ] Aprobado |
| **CP-04** | Cita Cancelada o Atendida | Una cita Cancelada y otra Atendida de la misma veterinaria. | 1. Forzar por URL `/consultas/crear?id_cita=<id>` para cada una | Redirige a la agenda con *"La cita fue cancelada…"* / *"La cita ya fue atendida…"*. No hay formulario. | [ ] Aprobado |
| **CP-05** | Una sola consulta por cita | Cita ya atendida con consulta. | 1. Con otra pestaña abierta en el formulario de **otra** cita, enviar el POST de la ya atendida (o repetir el envío) | Se rechaza con *"La cita ya fue atendida; no se puede registrar otra consulta."* En BD sigue **una** sola consulta para esa cita. | [ ] Aprobado |
| **CP-06** | Cita de otro veterinario | Sesión de la Dra. Mendoza y una cita del segundo veterinario. | 1. Abrir `/consultas/crear?id_cita=<id de la otra>` | Redirige a la agenda con *"Esta cita no está asignada a tu usuario."* No se crea consulta. | [ ] Aprobado |
| **CP-07** | El cliente no decide mascota, veterinario ni estado | Formulario abierto. | 1. Con las herramientas del navegador, añadir al formulario `id_mascota`, `id_veterinario`, `estado=Cancelada` y `fecha_atencion=2000-01-01`<br>2. Enviar | Se ignoran: la consulta queda con la mascota y el veterinario **de la cita**, y la fecha de atención es la actual. | [ ] Aprobado |
| **CP-08** | Fallo transaccional (automatizada) | MySQL accesible. | 1. Ejecutar `php -d zend.assertions=1 pruebas/test_consultas_integracion.php` | Pasan *"ROLLBACK real: si falla el cambio de estado…"* y *"…si el UPDATE no afecta filas…"*: no queda consulta parcial ni estado inconsistente, y el reintento funciona. | [ ] Aprobado |
| **CP-09** | Dos registros a la vez | Dos pestañas con el formulario de la misma cita. | 1. Guardar en la primera<br>2. Guardar en la segunda | La primera guarda. La segunda recibe aviso y no se duplica nada. (Automatizada: *"6 procesos registran la consulta de la misma cita -> exactamente 1"*.) | [ ] Aprobado |
| **CP-10** | Cancelar y atender a la vez | Administrador con la cita abierta en la confirmación de cancelar y veterinario con su formulario. | 1. El veterinario guarda la consulta<br>2. El administrador confirma la cancelación | Gana una sola operación: la cancelación se rechaza (*"…esta cita está Atendida"*) y la cita queda Atendida con su consulta. Al revés (cancelar primero), la consulta se rechaza. Nunca queda *Cancelada con consulta* ni *Atendida sin consulta*. (Automatizada: 8 rondas.) | [ ] Aprobado |
| **CP-11** | Control de acceso | Sesiones de administrador y anónima. | 1. Como administrador, abrir `/consultas/crear?id_cita=<id>` y enviar un POST a `/consultas`<br>2. Sin sesión, abrir la misma URL | Administrador: HTTP 403 en ambos. Anónimo: redirige a `/login`. El veterinario tampoco puede abrir `/citas` (403). | [ ] Aprobado |
| **CP-12** | Historial consolidado sin duplicados | Mascota con un antecedente y una consulta previa. | 1. Abrir el formulario de otra cita de esa mascota | Bajo el formulario, el historial muestra la **consulta una sola vez** y el antecedente, de lo más reciente a lo más antiguo. En BD, `historial_mascota` no ganó filas por la consulta. | [ ] Aprobado |
| **CP-13** | Protección CSRF | Sesión de veterinario. | 1. Enviar POST a `/consultas` con `csrf_token` incorrecto, vacío o ausente | HTTP 419 en los tres casos. No se guarda nada (SEC-01). | [ ] Aprobado |
| **CP-14** | Identificador inválido | Sesión de veterinario. | 1. Abrir `/consultas/crear?id_cita=abc`, `?id_cita[]=1`, `?id_cita=999` y sin parámetro | Redirige a la agenda con un mensaje. Sin errores 500. | [ ] Aprobado |
| **CP-15** | Texto con HTML | Formulario abierto. | 1. Escribir en el diagnóstico `<script>alert(1)</script>`<br>2. Guardar y abrir el formulario de otra cita de la misma mascota | El texto se muestra literal en el historial, sin ejecutarse. | [ ] Aprobado |
| **CP-16** | Límite de longitud | Formulario abierto. | 1. Pegar más de 2000 caracteres (el navegador limita a 2000)<br>2. Forzar un POST con 2001 | El servidor rechaza con *"…no puede superar los 2000 caracteres."* y conserva lo escrito. | [ ] Aprobado |
| **CP-17** | Contador y aviso de cambios sin guardar | Formulario abierto, JavaScript activado. | 1. Escribir en un campo: el contador muestra `n / 2000`<br>2. Sin guardar, intentar cerrar la pestaña o pulsar **Volver a mi agenda** | El navegador pide confirmar que se abandona la página. Al **Guardar** no aparece ninguna advertencia. | [ ] Aprobado |
| **CP-18** | Funciona sin JavaScript | JavaScript desactivado. | 1. Repetir CP-01 | Todo funciona igual (sin contador ni advertencia); el servidor valida. | [ ] Aprobado |
| **CP-19** | Veterinario sin perfil | Usuario con rol veterinario y sin fila en `veterinarios`. | 1. Entrar y abrir `/veterinario/agenda` | Aviso *"Tu cuenta no tiene un perfil de veterinario activo…"* y sin citas. | [ ] Aprobado |
| **CP-20** | Lo ve el administrador | Consulta ya registrada. | 1. Como administrador, abrir **Citas** y filtrar por **Atendidas** | La cita aparece como Atendida y **sin** botón Cancelar. | [ ] Aprobado |
| **CP-21** | `init_veterinario.php` | Terminal en la carpeta del proyecto. | 1. Ejecutar con un usuario que sea administrador, con contraseña de menos de 8 caracteres y con un correo inválido | Cada caso termina con un mensaje de error y sin cambios. Con datos válidos el veterinario puede iniciar sesión con la contraseña indicada. | [ ] Aprobado |

---

## 3. Criterios de Aceptación Verificados

- [ ] Solo el veterinario autenticado registra la consulta, y solo de una cita Programada suya (CP-01, CP-04, CP-06, CP-11).
- [ ] Diagnóstico y tratamiento son obligatorios; observaciones y servicios adicionales son opcionales (CP-01, CP-03, CP-16).
- [ ] La consulta y el cambio a Atendida se guardan de forma atómica (CP-08, CP-10).
- [ ] No se crea más de una consulta por cita (CP-05, CP-09).
- [ ] La consulta no se duplica en `historial_mascota` y se ve una sola vez en el historial consolidado (CP-12).
- [ ] La acción no se fía de campos enviados por el cliente (CP-07) y exige CSRF (CP-13).
