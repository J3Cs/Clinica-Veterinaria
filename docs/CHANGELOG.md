# Changelog — Clínica Veterinaria

Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.0.0/).
Versiones alineadas con los Incrementos del proyecto.

---

## [ACC-01] — 2026-10-26 — Usuarios de los veterinarios y menú según el rol

### Added
- **Usuarios de los dos veterinarios con acceso:** `dev_citas_seed.sql` crea a `dra.demo1` y `dr.demo2` con contraseñas iniciales documentadas (solo desarrollo). Al reejecutarlo en una base con el seed anterior se les da acceso solo si siguen sin él; nunca pisa una contraseña cambiada.
- **`App\Config\Navegacion`** — única tabla de opciones de menú, pantalla de inicio y nombre/insignia por rol.
- **`Views/partials/navbar.php`** — menú compartido que se arma según el rol de la sesión y resalta la sección actual.
- **`pruebas/test_navegacion.php`** (14 pruebas) y **`pruebas/manuales/ACC-roles-y-menu.md`** (13 casos).

### Changed
- Las 10 pantallas privadas usan el menú compartido en lugar de copias propias.
- **Administrador:** Dashboard · Propietarios · Mascotas · Citas. **Veterinario:** Mi agenda · Mascotas · Propietarios.
- `AuthController::redirectByRole` y `DashboardController::index` obtienen el inicio de `Navegacion`.
- `style.css`: resaltado de la sección actual del menú. `init_veterinario.php`, README y guías RF06: ya no dicen que los veterinarios del seed no pueden iniciar sesión.

### Fixed
- El veterinario veía en Mascotas y Propietarios un enlace «Dashboard» a `/admin/dashboard` que le daba 403, y sus propias pantallas no enlazaban a Mascotas.

### Decisions
- Ver `docs/adr/ADR-009-roles-y-menu-por-usuario.md`

---

## [US-06] — 2026-10-25 — Consultas veterinarias (T-042 a T-048, RF06)

### Added
- **`ConsultaVeterinariaModel`** — inserción con sentencia preparada; exige diagnóstico y tratamiento; consultas por cita y por mascota (T-042). **`HistorialMascotaModel`** — lectura de antecedentes.
- **`ConsultaVeterinariaRepository`** y **`HistorialMascotaRepository`** — insertar y comprobar si una cita ya tiene consulta (T-043).
- **`ConsultaService`** (+ `ConsultaNoGuardadaException`) — guarda la consulta y pasa la cita a Atendida en una transacción con la cita bloqueada; solo el veterinario asignado y solo citas Programadas; historial consolidado sin duplicados; agenda del veterinario (T-044).
- **`CitaModel` / `CitaRepository`** — `bloquearPorId()` (`FOR UPDATE`) y `marcarAtendidaSiProgramada()` (UPDATE condicional); el listado admite filtrar por veterinario. **`VeterinarioModel` / `VeterinarioRepository`** — `findActivoByUsuarioId()`.
- **`ConsultaController`** y rutas `GET /consultas/crear?id_cita=N` y `POST /consultas` (solo veterinario, CSRF; ignora cualquier campo que no sea la cita y los cuatro textos) (T-045).
- **`Views/consultas/crear.php`** — datos de la cita y de la mascota en solo lectura, formulario y historial clínico (T-046). **`public/assets/js/consultas.js`** y **`consultas.css`** — contador de caracteres y aviso de cambios sin guardar, sin bloquear el envío (T-047).
- **`init_veterinario.php`** — script de consola para crear un veterinario con acceso o asignar contraseña a uno existente (hasta que existan US-09/US-11).
- **`pruebas/test_consultas.php`** (19), **`pruebas/test_consultas_integracion.php`** (11, con rollback real y concurrencia) y **`pruebas/manuales/RF06-consultas.md`** (21 casos) (T-048).

### Changed
- **`/veterinario/agenda`:** de un mensaje de bienvenida a la agenda del veterinario con sus citas Programadas y el botón «Registrar consulta».
- **`CitaModel::findDetalleById()`** devuelve más datos de la mascota y del propietario (raza, sexo, nacimiento, teléfono).
- **Docs de US-05:** ya existe un camino real hacia el estado Atendida (registrar la consulta).

### Decisions
- Ver `docs/adr/ADR-008-consultas-veterinarias-atomicidad.md`

---

## [US-05] — 2026-10-24 — Cancelación de Citas y pestaña «Citas» (T-037 a T-041, RF05)

### Added
- **`CitaModel::cancelarSiProgramada()`** — `UPDATE` condicional (`estado = 'Programada'`); el resultado se decide por filas afectadas. También `findDetalleById()` y `findAllDetalle()` con las uniones a mascota, propietario, servicio y veterinario (T-037).
- **`CitaRepository`** — `cancelarSiProgramada()`, `findDetalleById()` y `listDetalle()`.
- **`CitaService`** — `cancelarCita()` (estado, motivo opcional de hasta 500 caracteres, resultado), `obtenerParaCancelar()` y `listarCitas()` con filtro por estado y tope de 200 (T-038).
- **`CitaController`** — `index` (pestaña), `confirmarCancelacion` y `cancelar` (solo administrador, CSRF, redirige a `/citas` con mensaje); rutas `GET /citas`, `GET|POST /citas/cancelar` (T-039).
- **`Views/citas/listar.php`** y **`confirmar_cancelacion.php`** — listado con estados, filtros y botón Cancelar solo para Programadas; confirmación con motivo opcional (T-040).
- **`pruebas/manuales/RF05-cancelacion.md`** (20 casos) y nuevas pruebas en `test_citas.php` (29 en total) y `test_citas_integracion.php` (14, incluye 6 cancelaciones simultáneas) (T-041).

### Changed
- **Pestaña única `/citas`:** formulario de agendar arriba y listado debajo. `/citas/crear` redirige a `/citas`; `crear.php` es ahora un fragmento. El botón «Agendar Cita» del dashboard y la pestaña «Citas» apuntan a `/citas`.
- **Menú:** la pestaña «Citas» aparece en Propietarios y Mascotas solo para administradores.
- **`CitaService`:** los identificadores se validan de forma estricta (solo dígitos).
- **`citas.css` / `citas.js`:** estilos de estado y botón de peligro; evita el doble envío al cancelar.

### Decisions
- Ver `docs/adr/ADR-007-cancelacion-citas-y-pestana.md`

---

## [US-04] — 2026-10-23 — Agendamiento de Citas (T-030 a T-036, RF04)

### Added
- **`CitaModel`** (estados Programada/Atendida/Cancelada, citas por veterinario/fecha/estado, transacción), **`ServicioModel`**, **`HorarioAtencionModel`**, **`VeterinarioModel`** (T-030).
- **`CitaRepository`**, **`HorarioAtencionRepository`**, **`ServicioRepository`**, **`VeterinarioRepository`** — ocupación (excluye canceladas) y disponibilidad con parámetros preparados (T-031).
- **`CitaService`** — cálculo de franjas, rechazo de horas fuera de horario, solapadas o pasadas, y revalidación en transacción con bloqueo del veterinario (T-032).
- **`CitaController`** — `GET /citas/crear` y `POST /citas`, solo administrador, solo campos esperados, CSRF (T-033).
- **`Views/citas/crear.php`** — formulario con horarios disponibles, mensajes sin cupo y estados vacíos (T-034).
- **`public/assets/js/citas.js`** y **`citas.css`** — validación previa y resumen de confirmación con JS nativo; el flujo funciona sin JS (T-035).
- **`pruebas/test_citas.php`** (18), **`pruebas/test_citas_integracion.php`** (8, contra MySQL, con 8 procesos concurrentes) y **`pruebas/manuales/RF04-agendamiento.md`** (21 casos) (T-036).
- **`database/migrations/005_citas_indice_disponibilidad.sql`** y **`database/seeds/dev_citas_seed.sql`** (solo desarrollo: US-09/10/11 aún no existen).

### Changed
- **`index.php`** — rutas de citas y zona horaria `America/El_Salvador` (`APP_TIMEZONE`).
- **`MascotaModel::findById`** expone `propietario_activo`; **`admin.php`** enlaza a Citas; **`init.sql`** incluye el índice de disponibilidad.

### Decisions
- Ver `docs/adr/ADR-006-agendamiento-citas-concurrencia.md`

---

## [Seguridad] — 2026-10-22 — Endurecimiento tras revisión (SEC-01, BAJA-01)

### Fixed
- **`ErrorHandlerMiddleware`** — El modo debug estaba siempre activo (`|| true`). Ahora depende de `APP_DEBUG` y el detalle se registra con `error_log`.
- **`CsrfMiddleware`** — Se rechazan tokens vacíos (`hash_equals('', '')` era `true`).
- **`init.sql`** — Ya no contiene `DROP TABLE` (usa `CREATE TABLE IF NOT EXISTS`). El reinicio destructivo pasa a `database/dev_reset.sql`.
- **FK clínicas** — `ON DELETE CASCADE` cambiado a `RESTRICT` (mascotas, citas, consultas, historial, veterinarios) para respetar BAJA-01. Migración `004_fk_restrict.sql` para bases existentes.

### Added
- **`pruebas/test_csrf.php`** — Pruebas del middleware CSRF.

---

## [US-03] — 2026-10-21 — Gestión y Registro de Mascotas (T-023 a T-029, RF03, DAT-01, BAJA-01, UI-01)

### Added
- **`database/migrations/003_mascotas_activo.sql`** — Migración para asegurar la columna `activo TINYINT(1) NOT NULL DEFAULT 1` en la tabla `mascotas` para soporte de bajas lógicas e integridad histórica (BAJA-01).
- **`src/app/Models/MascotaModel.php`** y **`src/app/Models/Mascota.php`** — Modelo de datos con sentencias preparadas PDO para inserción segura, búsqueda por ID, listado general con `JOIN` a propietarios, conteo y alias `Mascota` (T-023, ARQ-01, SEC-01, DAT-01).
- **`src/app/Repositories/MascotaRepository.php`** — Repositorio desacoplado para la entidad Mascota con filtrado de estados activos y validación de clave foránea (T-024, ARQ-01, BAJA-01, DAT-01).
- **`src/app/Services/PropietariosMascotasService.php`** — Lógica de negocio de mascotas: verificación estricta de propietario existente y activo (`DAT-01`), validación de campos obligatorios (nombre, especie, raza, sexo), validación de fecha de nacimiento no futura y soporte de campos opcionales preservando caracteres especiales (T-025).
- **`src/app/Controllers/MascotaController.php`** — Controlador con autorización exclusiva para administrador (`AuthMiddleware::requireRole('administrador')`), validación de token CSRF, recarga de propietarios activos y redirección tras alta con mensaje de éxito (T-026, ACC-01, SEC-01).
- **`src/app/Views/mascotas/crear.php`** — Formulario semántico accesible de alta de mascotas concordante con el login, selector de propietarios reales, aviso cuando no hay propietarios y persistencia segura de datos ante fallos (T-027, UI-01, DAT-01).
- **`src/app/Views/mascotas/index.php`** — Tabla de expedientes de pacientes con datos de la mascota y del propietario, badges de sexo (`Macho` / `Hembra`), estado activo y empty state amigable (T-027, UI-01).
- **`src/public/js/mascotas.js`**, **`src/public/assets/js/mascotas.js`** y **`src/public/assets/css/mascotas.css`** — Scripts cliente en JavaScript nativo con validación visual interactiva en tiempo real y estilos responsivos adaptables (T-028, TEC-01, UI-01).
- **`pruebas/manuales/RF03-mascotas.md`** — Matriz de 12 casos de prueba manuales documentados que verifican mascota válida, falta de propietario, FK inválida y campos requeridos (T-029).
- **`pruebas/test_mascotas.php`** — Suite automatizada con 12 aserciones nativas en PHP sin dependencias (100% de cobertura funcional) (T-029, TEC-01).

### Changed
- **`init.sql`** — Agregada columna `activo TINYINT(1) NOT NULL DEFAULT 1` a la tabla `mascotas` para nuevas instalaciones (BAJA-01).
- **`src/public/index.php`** — Registro de rutas `/mascotas`, `/mascotas/crear` (GET) y `/mascotas` (POST) (T-026).
- **`src/app/Views/dashboard/admin.php`**, **`src/app/Views/propietarios/index.php`**, **`src/app/Views/propietarios/crear.php`** — Barra de navegación actualizada con enlace a Mascotas.

### Decisions
- Ver `docs/adr/ADR-005-integridad-mascotas-propietario.md` (Integridad referencial y bajas lógicas)

---

## [US-02] — 2026-10-21 — Gestión de Propietarios (T-016 a T-022, T-087)

### Added
- **`database/migrations/002_propietarios_dui.sql`** — Migración SQL versionada que asegura el campo `dui CHAR(10) NOT NULL UNIQUE` (T-087, UNI-01).
- **`src/app/Models/PropietarioModel.php`** — Modelo con consultas preparadas PDO para inserción, búsqueda por DUI único, ID y listado (T-016).
- **`src/app/Repositories/PropietarioRepository.php`** — Repositorio desacoplado con filtros de unicidad de DUI y estado activo (T-017).
- **`src/app/Services/PropietariosMascotasService.php`** — Lógica de negocio: normalización automática a formato salvadoreño `########-#`, validación regex, rechazo de DUI duplicado, validación de correo opcional y soporte de teléfonos compartidos (T-018, UNI-01).
- **`src/app/Controllers/PropietarioController.php`** — Controlador con autorización exclusiva para administrador (`AuthMiddleware::requireRole('administrador')`), validación CSRF y persistencia de datos previos ante errores (T-019, ACC-01, SEC-01).
- **`src/app/Views/propietarios/crear.php`** — Formulario semántico HTML5 accesible por teclado con repoblación segura de campos y mensajes de error específicos (T-020, UI-01).
- **`src/app/Views/propietarios/index.php`** — Tabla de listado de propietarios registrados con acciones directas de alta (T-019).
- **`src/public/js/propietarios.js`** — Formateo/máscara automática de DUI en cliente y validación visual interactiva en JavaScript nativo (T-021, TEC-01).
- **`src/public/css/style.css`** — Estilos para formulario en cuadrícula (`.form-grid`), tablas de datos responsivas y feedback de validación (T-021).
- **`pruebas/manuales/RF02-propietarios.md`** — Matriz de 11 casos de prueba manuales documentados (T-022).
- **`pruebas/test_propietarios.php`** — Suite automatizada con 9 aserciones nativas en PHP sin dependencias (T-022, TEC-01).

---

## [US-01] — 2026-10-21 — Autenticación y Control de Acceso (T-009 a T-015)

### Added
- **`src/app/Models/UsuarioModel.php`** — Búsqueda dual parametrizada por nombre de usuario o correo (`findByIdentifier`) y por ID (`findById`) (T-009).
- **`src/app/Repositories/UsuarioRepository.php`** — Filtrado de cuentas activas (`findActiveByIdentifier`) y consulta de estado (`isActive`) (T-010).
- **`src/app/Services/AuthService.php`** — Lógica de negocio con respuestas estructuradas, validación de cuentas activas, prevención de session fixation (`session_regenerate_id`) y logout seguro (T-011).
- **`src/app/Controllers/AuthController.php`** — Coordinación web, alias en español (`mostrarFormulario`, `iniciarSesion`, `cerrarSesion`), flash messages y **redirección según rol** (`administrador` $\rightarrow$ `/admin/dashboard`, `veterinario` $\rightarrow$ `/veterinario/agenda`) (T-012).
- **`src/app/Controllers/DashboardController.php`** — Vistas protegidas para administrador y veterinario con validación de roles (`AuthMiddleware::requireRole`) (T-012, ACC-01).
- **`src/app/Views/auth/login.php`** — Formulario de acceso semántico con campo unificado usuario/correo, mensajes accesibles y navegación por teclado (T-013).
- **`src/app/Views/dashboard/admin.php`** y **`src/app/Views/dashboard/veterinario.php`** — Paneles específicos por rol.
- **`src/public/js/main.js`** — Validación visual interactiva en cliente sin sustituir la validación del servidor (T-014).
- **`src/public/css/style.css`** — Estilos para `.is-invalid`, `:focus-visible`, alertas y adaptabilidad responsive móvil/desktop (T-014, UI-01).
- **`pruebas/manuales/RF01-autenticacion.md`** — Matriz de 12 casos de prueba manuales documentados (T-015).
- **`pruebas/test_autenticacion.php`** — Suite automatizada con 12 aserciones nativas en PHP sin dependencias (T-015, TEC-01).

---

## [I0] — 2026-10-21 — Base Técnica MVC (US-00)

### Added
- **`src/app/Router.php`** — Router centralizado. Mapea `[METHOD, URI]` a `[Controlador, acción]`. Respuesta 404 sanitizada.
- **`src/app/Middlewares/AuthMiddleware.php`** — Valida sesión y rol en cada acción protegida (`requireLogin`, `requireRole`). Llamado dentro del controlador, no en el router.
- **`src/app/Middlewares/CsrfMiddleware.php`** — Genera y valida tokens CSRF por sesión. Token rota tras cada POST válido. Usa `hash_equals` para evitar timing attacks.
- **`src/app/Models/UsuarioModel.php`** — Modelo puro de la tabla `usuarios`. Solo SQL con prepared statements via PDO.
- **`src/app/Repositories/UsuarioRepository.php`** — Agrega filtro `activo = 1` sobre el modelo.
- **`src/app/Services/AuthService.php`** — Lógica de negocio de autenticación: `login()` (password_verify + session_regenerate_id), `logout()` (destrucción completa de sesión y cookie).
- **`src/app/Controllers/AuthController.php`** — `showLogin`, `handleLogin`, `logout`. Valida CSRF antes de procesar POST.
- **`src/app/Controllers/DashboardController.php`** — Stub de panel post-login. Llama `AuthMiddleware::requireLogin()`.
- **`src/app/Views/auth/login.php`** — Formulario de inicio de sesión. Español. CSRF token oculto. Flash de error genérico.
- **`src/app/Views/dashboard/index.php`** — Vista de bienvenida autenticada. Muestra nombre y rol.
- **`src/public/css/style.css`** — Sistema de diseño completo: variables CSS, reset, navbar, botones, formularios, cards, alertas, página de login. Sin Bootstrap ni CDN externo.
- **`init_admin.php`** — Script CLI de inicialización del primer administrador. Bloqueado si ya existe uno. Nunca expuesto en `public/`.
- **`docs/adr/`** — Registros de decisiones de arquitectura (ver archivos ADR-*.md).

### Changed
- **`src/app/Config/Database.php`** — Corrección: `DB_NAME` por defecto cambió de `app_db` a `clinica_veterinaria`.
- **`src/public/index.php`** — Reescrito: registra autoloader, `ErrorHandlerMiddleware`, `session_start()`, y delega todo el enrutamiento al `Router`.
- **`init.sql`** — Tabla `propietarios`: se añadió columna `dui CHAR(10) NOT NULL UNIQUE` (regla UNI-01) y columna `activo` (BAJA-01). Se eliminó el INSERT del administrador hardcodeado (regla ADM-01).

### Decisions
- Ver `docs/adr/ADR-001-arquitectura-mvc-capas.md`
- Ver `docs/adr/ADR-002-sin-frameworks-externos.md`
- Ver `docs/adr/ADR-003-autenticacion-sesion-php.md`
- Ver `docs/adr/ADR-004-dui-propietario.md`

---

## Próximos Incrementos

| Incremento | Historia | Módulo principal |
|------------|----------|-----------------|
| I1         | US-01    | Gestión de propietarios y mascotas |
| I2         | US-02    | Gestión de veterinarios y horarios |
| I3         | US-03    | Agenda de citas (CIT-01, CIT-02) |
| I4         | US-04    | Consultas y historial médico (HIST-01) |
| I5         | US-05    | Bajas lógicas (BAJA-01) |
