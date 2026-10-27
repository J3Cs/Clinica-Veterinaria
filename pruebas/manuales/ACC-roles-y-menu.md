# Matriz y Guía de Pruebas Manuales: ACC-01 — Usuarios de los veterinarios, roles y menú

Verifica que los dos veterinarios tienen usuario propio con acceso, y que lo que cada persona **ve** (menú y botones) coincide con lo que **puede hacer**. Cumple con: **ACC-01**, **SEC-01**, **UI-01**.

Pruebas automatizadas relacionadas:

- `php -d zend.assertions=1 pruebas/test_navegacion.php` — tabla de opciones por rol, menú compartido, escape del nombre y que ninguna pantalla traiga su propio menú copiado (14 pruebas).
- La coincidencia entre menú y permisos reales se comprobó de punta a punta por HTTP (ver esta matriz).

---

## 1. Preparación

1. Haber cargado `database/seeds/dev_citas_seed.sql` (también sirve volver a ejecutarlo: si las cuentas de los veterinarios aún no tenían acceso, se les asigna la contraseña inicial; **nunca** se pisa una contraseña que ya hayas cambiado).
2. Tener el usuario administrador creado con `init_admin.php`.

| Usuario | Rol | Contraseña inicial | Menú esperado |
|---|---|---|---|
| (el que creaste con `init_admin.php`) | Administrador | la que elegiste | Dashboard · Propietarios · Mascotas · Citas |
| `dra.demo1` (Dra. Laura Mendoza) | Veterinario | `Mendoza#2026` | Mi agenda · Mascotas · Propietarios |
| `dr.demo2` (Dr. Carlos Rivas) | Veterinario | `Rivas#2026` | Mi agenda · Mascotas · Propietarios |

Son contraseñas conocidas, **solo para desarrollo**. Para cambiarlas: `php init_veterinario.php`.

---

## 2. Matriz de Casos de Prueba

| ID | Caso de Prueba | Pasos de Ejecución | Resultado Esperado | Estado |
|---|---|---|---|---|
| **CP-01** | Los dos veterinarios entran | 1. Iniciar sesión con `dra.demo1` / `Mendoza#2026`<br>2. Cerrar sesión<br>3. Iniciar sesión con `dr.demo2` / `Rivas#2026` | Cada uno entra y llega directo a **Mi agenda** (`/veterinario/agenda`). También funciona con el correo (`laura.demo@clinica.local`, `carlos.demo@clinica.local`). | [ ] Aprobado |
| **CP-02** | Contraseña equivocada | 1. Probar `dra.demo1` con la contraseña del otro veterinario | No entra; mensaje de credenciales inválidas. | [ ] Aprobado |
| **CP-03** | Cada veterinario ve su propia agenda | 1. Como administrador, agendar citas con cada veterinario<br>2. Entrar como cada uno | Cada uno ve únicamente sus citas Programadas. | [ ] Aprobado |
| **CP-04** | Menú del administrador | 1. Entrar como administrador y recorrer Dashboard, Propietarios, Mascotas y Citas (y sus pantallas de crear) | En **todas** las pantallas el menú es el mismo: Dashboard, Propietarios, Mascotas, Citas. La sección actual aparece resaltada. | [ ] Aprobado |
| **CP-05** | Menú del veterinario | 1. Entrar como veterinario y recorrer Mi agenda, Mascotas, Propietarios y el formulario de una consulta | En **todas** las pantallas el menú es: Mi agenda, Mascotas, Propietarios. No aparece Dashboard ni Citas. | [ ] Aprobado |
| **CP-06** | Todo enlace del menú funciona | 1. Pulsar cada opción del menú, como administrador y como cada veterinario | Ninguna da error 403 ni pantalla en blanco. | [ ] Aprobado |
| **CP-07** | El veterinario no ve botones de administrador | 1. Entrar como veterinario a Mascotas y a Propietarios | Ve los listados **sin** botones de "Registrar". | [ ] Aprobado |
| **CP-08** | Lo oculto también está prohibido | 1. Como veterinario, escribir en la barra de direcciones `/admin/dashboard`, `/citas`, `/propietarios/crear` y `/mascotas/crear` | HTTP 403 en las cuatro. | [ ] Aprobado |
| **CP-09** | El administrador y lo clínico | 1. Como administrador, escribir `/veterinario/agenda` y `/consultas/crear?id_cita=1` | HTTP 403: registrar consultas es un acto clínico exclusivo del veterinario (US-06). | [ ] Aprobado |
| **CP-10** | Sin sesión | 1. Cerrar sesión<br>2. Abrir `/citas`, `/mascotas`, `/veterinario/agenda` | Redirige a `/login`. | [ ] Aprobado |
| **CP-11** | Pantalla de inicio por rol | 1. Con sesión iniciada abrir `/` y `/dashboard` | Administrador → `/admin/dashboard`; veterinario → `/veterinario/agenda`. | [ ] Aprobado |
| **CP-12** | Nombre con caracteres especiales | 1. En BD, poner a un usuario el nombre `<b>Ana</b> & "Co"`<br>2. Iniciar sesión con él | El menú muestra el texto literal, sin interpretarlo como HTML. | [ ] Aprobado |
| **CP-13** | El seed no pisa contraseñas | 1. Cambiar la contraseña de `dr.demo2` con `php init_veterinario.php`<br>2. Volver a ejecutar `dev_citas_seed.sql`<br>3. Iniciar sesión | Entra con la contraseña nueva; la inicial ya no funciona. | [ ] Aprobado |

---

## 3. Criterios Verificados

- [ ] Los dos veterinarios tienen usuario propio con acceso (CP-01, CP-02, CP-13).
- [ ] Cada rol ve solo las opciones que le corresponden, en todas las pantallas (CP-04, CP-05, CP-07).
- [ ] Todo lo que se muestra funciona, y lo que no se muestra está prohibido por el servidor (CP-06, CP-08, CP-09, CP-10).
