# Changelog — Clínica Veterinaria

Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.0.0/).
Versiones alineadas con los Incrementos del proyecto.

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
