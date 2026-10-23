# Matriz y Guía de Pruebas Manuales: RF04 — Agendamiento de Citas (US-04, T-036)

Este documento detalla los casos de prueba para verificar el requisito funcional **RF04 (Agendamiento de citas)**, asegurando el cumplimiento de: **DAT-01**, **ARQ-01**, **ACC-01**, **SEC-01**, **BAJA-01**, **TEC-01** y **UI-01**.

Pruebas automatizadas relacionadas:

- `php -d zend.assertions=1 pruebas/test_citas.php` — reglas de negocio con dobles en memoria (sin base de datos).
- `php -d zend.assertions=1 pruebas/test_citas_integracion.php` — contra MySQL real, incluida la concurrencia con 8 procesos simultáneos (requiere `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`; se omite si no hay conexión).

---

## 1. Preparación

Las pantallas para gestionar servicios, veterinarios y horarios (US-09, US-10, US-11) todavía no existen. Para poder probar, cargar los datos de ejemplo **solo en desarrollo**:

```bash
docker exec -i mysql_dual mysql -uroot -proot_password < database/seeds/dev_citas_seed.sql
```

Si la base ya existía antes de US-04, aplicar también `database/migrations/005_citas_indice_disponibilidad.sql`.

| Elemento | Valor |
|---|---|
| Servicios activos | Consulta general (30 min), Vacunación (20), Desparasitación (15), Cirugía menor (60) |
| Servicio inactivo | Servicio descontinuado |
| Dra. Laura Mendoza | Lunes a viernes 08:00–12:00 y 14:00–17:00 |
| Dr. Carlos Rivas | Lunes a sábado 09:00–13:00 |
| Domingo | Sin horario para ninguno |

Además se necesita una sesión de **administrador**, al menos una mascota activa (con propietario activo) y, para CP-12, una sesión de **veterinario** (los del seed no tienen contraseña válida: crear uno por SQL con `password_hash`).

Los horarios se ofrecen cada **15 minutos**. Un servicio de 30 min en un bloque 08:00–12:00 ofrece de 08:00 a 11:30.

---

## 2. Matriz de Casos de Prueba

| ID | Caso de Prueba | Precondición | Pasos de Ejecución | Resultado Esperado | Estado |
|---|---|---|---|---|---|
| **CP-01** | Horario libre: agendar con éxito | Sesión de `administrador`. Seed cargado. Mascota activa. | 1. Ir a `/citas/crear`<br>2. Elegir mascota, *Consulta general*, Dra. Mendoza y un lunes futuro<br>3. Clic en "Ver horarios disponibles"<br>4. Elegir `09:00 – 09:30`<br>5. Clic en "Confirmar cita" | Con JS aparece un resumen para confirmar. Mensaje *"Cita programada para …"*. En BD la cita queda con `estado = 'Programada'`, `hora_cita = 09:00:00`. | [ ] Aprobado |
| **CP-02** | Disponibilidad visible y calculada | Igual a CP-01. | 1. Consultar un lunes futuro con *Consulta general* y Dra. Mendoza | Franjas de `08:00` a `11:30` y de `14:00` a `16:30`; ninguna entre 12:00 y 14:00. | [ ] Aprobado |
| **CP-03** | La duración condiciona las franjas | Igual a CP-01. | 1. Consultar el mismo lunes con *Cirugía menor* (60 min) | La última franja de la mañana es `11:00 – 12:00`. No se ofrece `11:15` ni `11:30`. | [ ] Aprobado |
| **CP-04** | Día sin horario definido | Seed cargado. | 1. Consultar un **domingo** | *"No hay horarios disponibles para esa combinación. Prueba con otra fecha u otro veterinario."* Sin botón de confirmar. | [ ] Aprobado |
| **CP-05** | Fuera de horario por POST manipulado | Sesión de administrador. | 1. En las herramientas del navegador cambiar el valor del radio a `12:00` (o `07:45`, `11:45`)<br>2. Enviar | Vuelve a la pantalla de horarios con *"Ese horario ya no está disponible."* No se crea cita. | [ ] Aprobado |
| **CP-06** | Servicio inactivo | Seed cargado. | 1. Abrir el selector de servicios<br>2. Forzar por URL `?consultar=1&id_servicio=<id del inactivo>&…` | El selector no lo lista. Por URL: *"El servicio seleccionado no existe o no está activo."* y sin horarios. | [ ] Aprobado |
| **CP-07** | Cita solapada | Existe una cita Programada con Dra. Mendoza el lunes a las 09:00 (30 min). | 1. Consultar ese lunes con 30 min y la misma veterinaria | No se ofrecen `08:45`, `09:00` ni `09:15`. Sí `08:30` y `09:30` (contiguas). | [ ] Aprobado |
| **CP-08** | Misma hora con otro veterinario | Igual a CP-07. | 1. Consultar el mismo lunes con el Dr. Rivas | `09:00` disponible; se puede agendar. | [ ] Aprobado |
| **CP-09** | Una cita cancelada libera el horario | Existe una cita Programada. | 1. Cambiar su estado a `Cancelada` en BD<br>2. Consultar de nuevo | El horario vuelve a ofrecerse. | [ ] Aprobado |
| **CP-10** | **Dos solicitudes concurrentes** | Dos sesiones de administrador (dos navegadores o ventanas privadas). | 1. En ambas, consultar el mismo veterinario y fecha; ambas ven libre `10:00`<br>2. En la primera confirmar `10:00`<br>3. En la segunda confirmar `10:00` | La primera se guarda. La segunda vuelve a los horarios, actualizados, con *"El horario seleccionado ya no está disponible. Elige otro de la lista actualizada."* En BD hay **una sola** cita Programada a las 10:00. | [ ] Aprobado |
| **CP-11** | Concurrencia real (automatizada) | MySQL accesible. | 1. Ejecutar `php -d zend.assertions=1 pruebas/test_citas_integracion.php` | Pasa *"8 procesos simultáneos … exactamente 1 reserva"*. | [ ] Aprobado |
| **CP-12** | Control de acceso: veterinario | Sesión de `veterinario`. | 1. Ir a `/citas/crear`<br>2. Enviar un POST a `/citas` | HTTP 403 en ambos casos (ACC-01). | [ ] Aprobado |
| **CP-13** | Acceso anónimo | Sin sesión. | 1. Ir a `/citas/crear` | Redirección a `/login`. | [ ] Aprobado |
| **CP-14** | Protección CSRF | Sesión de administrador. | 1. Enviar POST a `/citas` con `csrf_token` incorrecto, vacío o ausente | HTTP 419 en los tres casos (SEC-01). | [ ] Aprobado |
| **CP-15** | Fecha pasada | Sesión de administrador. | 1. Consultar con una fecha anterior a hoy (forzando la URL) | *"No se pueden agendar citas en fechas pasadas."* | [ ] Aprobado |
| **CP-16** | Hoy: solo horas futuras | Hoy es día laborable del veterinario. | 1. Consultar la fecha de hoy | No se ofrecen horas anteriores o iguales a la actual; si ya cerró, sin cupo. | [ ] Aprobado |
| **CP-17** | Mascota no válida | Existe una mascota inactiva o con propietario inactivo. | 1. Abrir el selector de mascotas<br>2. Forzar por URL su `id_mascota` | El selector no la lista. Por URL: *"La mascota seleccionada no está activa."* | [ ] Aprobado |
| **CP-18** | Selección incompleta | Sesión de administrador. | 1. Pulsar "Ver horarios disponibles" sin elegir mascota | Con JS: *"Selecciona una mascota."* sin recargar. Sin JS: *"Debes seleccionar una mascota."* | [ ] Aprobado |
| **CP-19** | Funciona sin JavaScript | JavaScript desactivado. | 1. Repetir CP-01 | El flujo completo funciona con solicitudes normales (sin confirmación emergente). | [ ] Aprobado |
| **CP-20** | Datos con HTML | Mascota llamada `<b>Rex</b>`. | 1. Agendar una cita para ella | El nombre se muestra literal en selector, resumen y mensaje de éxito. | [ ] Aprobado |
| **CP-21** | Sin datos maestros | Base sin servicios activos o sin veterinarios. | 1. Ir a `/citas/crear` | Estado vacío que indica qué falta; sin formulario. | [ ] Aprobado |

---

## 3. Criterios de Aceptación Verificados

- [ ] Solo se ofrecen mascotas, servicios y veterinarios activos (CP-06, CP-17, CP-21).
- [ ] Los horarios salen de `horarios_atencion` y de la duración del servicio (CP-02, CP-03, CP-04).
- [ ] No se admiten citas solapadas con las del mismo veterinario (CP-07, CP-08, CP-09).
- [ ] La validación final se repite en el servidor y se guarda en transacción (CP-05, CP-10, CP-11).
- [ ] Solo el administrador agenda (CP-12, CP-13) y todo POST exige CSRF (CP-14).
- [ ] El flujo funciona sin JavaScript (CP-19).
