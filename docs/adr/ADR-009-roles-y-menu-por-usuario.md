# ADR-009 — Usuarios de los veterinarios y menú según el rol

**Fecha:** 2026-10-26  
**Estado:** Aceptada  
**Reglas de origen:** ACC-01, SEC-01, UI-01, ARQ-01  

---

## Contexto

Hasta ahora solo el administrador podía iniciar sesión: los veterinarios del seed tenían una contraseña inválida a propósito y solo se les podía dar acceso con `init_veterinario.php`. Además, el menú estaba copiado en 10 pantallas, con diferencias entre ellas; en las pantallas que también usa el veterinario (`/mascotas`, `/propietarios`) el enlace «Dashboard» apuntaba a `/admin/dashboard`, que le daba 403, y sus propias pantallas no tenían enlace a Mascotas.

## Decisiones

1. **Los dos veterinarios tienen usuario propio.** `dev_citas_seed.sql` crea a la Dra. Laura Mendoza (`dra.demo1`) y al Dr. Carlos Rivas (`dr.demo2`) con contraseñas iniciales conocidas y documentadas, solo para desarrollo. Al volver a ejecutarlo en una base que ya tenía el seed anterior, solo se asigna la contraseña si la cuenta sigue con el valor inválido (`contrasena = '!sin-acceso-seed'`), de modo que **nunca se pisa** una contraseña que alguien ya cambió. Se mantuvieron los nombres de usuario existentes para no romper los accesos ya usados. `init_veterinario.php` sigue sirviendo para cambiar contraseñas o crear otro veterinario. *Reemplaza la observación del ADR-008 (punto 9) de que los veterinarios del seed no podían iniciar sesión.*

2. **Una sola fuente de verdad para lo que ve cada rol.** `App\Config\Navegacion` define, por rol, las opciones del menú, la pantalla de inicio y el nombre/insignia. `AuthController::redirectByRole` y `DashboardController::index` usan esa misma tabla.

3. **Un solo menú compartido.** `Views/partials/navbar.php` reemplaza las 10 copias. Lee el rol y el nombre de la **sesión** (no de variables que cada controlador prepare con nombres distintos), escapa el texto una sola vez y resalta la sección actual (`aria-current="page"`).

4. **El menú es presentación; la seguridad sigue en el servidor.** Cada acción mantiene su `requireRole()`. Para que ambas cosas no se separen, `test_navegacion.php` verifica que cada opción del menú sea una ruta GET registrada y que el veterinario no reciba opciones exclusivas del administrador, y la matriz `ACC-roles-y-menu.md` comprueba de punta a punta que todo lo que se muestra funciona y lo que no se muestra da 403. Otra prueba falla si una pantalla vuelve a traer su propio menú.

5. **Qué ve cada rol hoy.**

| | Administrador | Veterinario |
|---|---|---|
| Menú | Dashboard · Propietarios · Mascotas · Citas | Mi agenda · Mascotas · Propietarios |
| Inicio tras el login | `/admin/dashboard` | `/veterinario/agenda` |
| Ver listados de propietarios y mascotas | Sí | Sí (solo lectura) |
| Registrar propietarios y mascotas | Sí | No |
| Agendar, listar y cancelar citas | Sí | No |
| Ver su agenda y registrar consultas | No | Sí |

   El administrador administra todo, pero **no registra consultas ni tiene agenda clínica**: US-06 reserva ese acto al veterinario autenticado. Si se quisiera que el administrador también pueda hacerlo, habría que cambiar esa historia, no solo el menú.

## Para agregar la recepcionista más adelante

1. Ampliar el ENUM `usuarios.rol` (hoy solo `administrador` y `veterinario`) con una migración.
2. Añadir el rol en `Navegacion` (`MENU`, `INICIO`, `ETIQUETA`, `INSIGNIA`).
3. Permitirlo en los `requireRole()` de las acciones que le correspondan (por ejemplo, agendar y cancelar citas, registrar propietarios y mascotas).
4. Ampliar las pruebas: `test_navegacion.php` ya falla si un rol conocido queda sin menú o sin inicio, y hay que añadirlo a la matriz manual.
5. Crear su usuario (hoy `init_admin.php` y `init_veterinario.php` solo crean esos dos roles).

## Consecuencias

- Cada persona ve solo lo que puede usar, y un cambio de menú se hace en un solo lugar.
- Desaparece el enlace roto del veterinario.

**Limitaciones conocidas:**
- Las contraseñas iniciales de desarrollo son conocidas (están en el seed); no deben usarse fuera de desarrollo.
- Todavía no hay pantallas para crear o editar usuarios ni para cambiar la contraseña desde la aplicación.
- El veterinario ve el listado de propietarios (incluye DUI y teléfono), como ya ocurría por la regla de ACC-01; si no debiera, habría que quitarlo del menú y de `requireRole`.
