# Clínica Veterinaria — Sistema de Gestión

Sistema web para la gestión clínica veterinaria. Desarrollado en **PHP nativo, HTML, CSS propio y MySQL** sin frameworks externos.

## Tecnología

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.x nativo (sin Composer packages) |
| Base de datos | MySQL 8 via PDO |
| Frontend | HTML5 + CSS3 propio (sin Bootstrap) |

## Estructura del Proyecto

```
Clinica-Veterinaria/
├── database/
│   └── migrations/               ← Migraciones SQL versionadas
├── docs/
│   ├── CHANGELOG.md              ← Historial de cambios por incremento
│   └── adr/                      ← Architecture Decision Records (ADRs)
├── pruebas/
│   ├── manuales/                 ← Matrices y casos de prueba manuales
│   ├── test_autenticacion.php   ← Suite automatizada US-01
│   ├── test_propietarios.php    ← Suite automatizada US-02
│   ├── test_mascotas.php        ← Suite automatizada US-03
│   ├── test_csrf.php            ← Pruebas del middleware CSRF
│   ├── test_citas.php           ← Suite automatizada US-04 (sin BD)
│   ├── test_citas_integracion.php ← US-04/US-05 contra MySQL real, con concurrencia
│   ├── test_consultas.php       ← Suite automatizada US-06 (sin BD)
│   ├── test_navegacion.php      ← Menú y funciones según el rol (ACC-01)
│   └── test_consultas_integracion.php ← US-06 contra MySQL real (rollback y concurrencia)
├── init.sql                      ← Esquema completo de la BD (9 tablas)
├── init_admin.php                ← Script CLI para crear el primer admin
├── init_veterinario.php          ← Script CLI para dar acceso a un veterinario (hasta US-09US-11)
└── src/
    ├── app/
    │   ├── Config/
    │   │   └── Database.php      (singleton PDO)
    │   ├── Controllers/          (coordinan flujo, sin lógica de negocio)
    │   ├── Middlewares/          (AuthMiddleware, CsrfMiddleware, ErrorHandlerMiddleware)
    │   ├── Models/               (SQL puro con PDO)
    │   ├── Repositories/         (filtros de negocio sobre modelos)
    │   ├── Services/             (lógica de negocio)
    │   ├── Views/                (HTML + htmlspecialchars, sin SQL)
    │   └── Router.php
    └── public/
        ├── index.php             (único punto de entrada)
        ├── .htaccess
        ├── css/style.css
        └── js/                   (JavaScript nativo de soporte visual)
```


## Inicio con XAMPP

1. Copiar `src/` como carpeta virtual en `htdocs/clinica/`.
2. Importar `init.sql` en phpMyAdmin.
3. Ejecutar `php init_admin.php` desde la terminal apuntando al directorio raíz.
4. Acceder en `http://localhost/clinica/public/`.

## Reglas de arquitectura (ver docs/adr/)

- **TEC-01** — Sin frameworks externos, sin Bootstrap, sin Composer packages.
- **ARQ-01** — Capas estrictas: Model → Repository → Service → Controller → View.
- **ACC-01** — Solo administradores y veterinarios pueden iniciar sesión.
- **SEC-01** — Passwords con `password_hash`, PDO prepared statements, CSRF en todo POST, sin exponer errores internos.
- **UNI-01** — DUI salvadoreño como identificador único del propietario (`########-#`).
- **DAT-01** — Una mascota siempre debe pertenecer a un propietario existente y activo (integridad referencial).
- **ADM-01** — El primer administrador se crea con `init_admin.php` desde CLI, nunca por interfaz web pública.
- **BAJA-01** — Bajas lógicas con columna `activo`. No se elimina físicamente si existen relaciones.
- **UI-01** — Interfaz adaptable y en español con CSS propio.

## Incrementos

| # | Historia | Módulo | Estado |
|---|----------|--------|--------|
| I0 | US-00 | Base técnica MVC | ✅ Completado |
| I1 | US-01 | Autenticación y Control de Acceso | ✅ Completado |
| I2 | US-02 | Gestión de Propietarios (DUI) | ✅ Completado |
| I2 | US-03 | Gestión y Registro de Mascotas (T-023 a T-029) | ✅ Completado |
| I3 | US-04 | Agenda de citas (T-030 a T-036) | ✅ Completado |
| I3 | US-05 | Cancelación de citas (T-037 a T-041) | ✅ Completado |
| I4 | US-06 | Consultas veterinarias (T-042 a T-048) | ✅ Completado |
| I5 | US-07 | Bajas lógicas y reportes | ⏳ Pendiente |

### Tareas del Módulo Mascotas (US-03):
- **T-023 (2.1 - Modelo):** Atributos y reglas de Mascota con relación obligatoria a Propietario (`MascotaModel.php` / `Mascota.php`).
- **T-024 (2.2 - Persistencia):** Creación, consultas parametrizadas y validación de clave foránea (`MascotaRepository.php`).
- **T-025 (2.3 - Reglas):** Validación de campos obligatorios, sexo, fecha no futura e integridad (`PropietariosMascotasService.php`).
- **T-026 (2.4 - Flujo web):** Controlador con sesión de administrador y rutas web (`MascotaController.php`, `src/public/index.php`).
- **T-027 (2.5 - Pantalla):** Formulario semántico accesible y aviso de propietarios previos (`vistas/mascotas/crear.php`, `index.php`).
- **T-028 (2.6 - Interacción):** Validación visual interactiva y diseño adaptable móvil/desktop (`js/mascotas.js`, `style.css`).
- **T-029 (2.7 - Verificación):** Pruebas de alta, falta de propietario, FK inválida y campos requeridos (`RF03-mascotas.md`, `test_mascotas.php`).

### Citas (US-04 y US-05)

- Pestaña **Citas** (`/citas`, solo administrador): formulario para agendar, listado por estado y cancelación con motivo opcional. Los horarios se ofrecen cada 15 min según `horarios_atencion` y la duración del servicio.
- Las pantallas de servicios, veterinarios y horarios (dependencias US-09, US-10 y US-11) aún no existen. Para probar en desarrollo, cargar `database/seeds/dev_citas_seed.sql`.
- Bases creadas antes de US-04: aplicar `database/migrations/005_citas_indice_disponibilidad.sql`.
- Decisiones y límites: `docs/adr/ADR-006-agendamiento-citas-concurrencia.md` y `docs/adr/ADR-007-cancelacion-citas-y-pestana.md`.

### Consultas veterinarias (US-06)

- El veterinario entra a **Mi agenda** (`/veterinario/agenda`), elige una de sus citas Programadas y registra diagnóstico, observaciones y tratamiento. Al guardar, la cita pasa a Atendida en la misma transacción.
- Los veterinarios del seed ya tienen usuario con acceso (ver «Usuarios y roles»). Para cambiar una contraseña o crear otro veterinario, en el equipo con acceso a la base de datos: `php init_veterinario.php`.
- Sin migraciones nuevas. Decisiones y límites: `docs/adr/ADR-008-consultas-veterinarias-atomicidad.md`.

### Usuarios y roles

| Usuario | Rol | Contraseña inicial | Menú |
|---|---|---|---|
| el que creaste con `init_admin.php` | Administrador | la que elegiste | Dashboard · Propietarios · Mascotas · Citas |
| `dra.demo1` (Dra. Laura Mendoza) | Veterinario | `Mendoza#2026` | Mi agenda · Mascotas · Propietarios |
| `dr.demo2` (Dr. Carlos Rivas) | Veterinario | `Rivas#2026` | Mi agenda · Mascotas · Propietarios |

Las cuentas de los veterinarios las crea `database/seeds/dev_citas_seed.sql` (también sirve volver a ejecutarlo en una base que ya lo tenía: da acceso sin pisar contraseñas ya cambiadas). **Son contraseñas conocidas, solo para desarrollo.**

| Función | Administrador | Veterinario |
|---|---|---|
| Ver propietarios y mascotas | Sí | Sí (solo lectura) |
| Registrar propietarios y mascotas | Sí | No |
| Agendar, listar y cancelar citas | Sí | No |
| Ver su agenda y registrar consultas | No | Sí |

El menú se arma según el rol (`src/app/Config/Navegacion.php`); el acceso real lo exige cada controlador. El rol de recepción queda pendiente: los pasos para agregarlo están en `docs/adr/ADR-009-roles-y-menu-por-usuario.md`.

Ver `docs/CHANGELOG.md` para el detalle de cambios por incremento.
