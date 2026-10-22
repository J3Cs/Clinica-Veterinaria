# Clínica Veterinaria — Sistema de Gestión

Sistema web para la gestión clínica veterinaria. Desarrollado en **PHP nativo, HTML, CSS propio y MySQL** sin frameworks externos.

## Tecnología

| Capa | Tecnología |
|------|-----------|
| Backend | PHP 8.x nativo (sin Composer packages) |
| Base de datos | MySQL 8 via PDO |
| Frontend | HTML5 + CSS3 propio (sin Bootstrap) |
| Infraestructura | Docker + Docker Compose |

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
│   └── test_mascotas.php        ← Suite automatizada US-03
├── init.sql                      ← Esquema completo de la BD (9 tablas)
├── init_admin.php                ← Script CLI para crear el primer admin
├── docker-compose.yml
├── Dockerfile
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

## Inicio rápido con Docker

```bash
# 1. Levantar contenedores (PHP + MySQL)
docker-compose up -d

# 2. Crear el primer administrador (solo una vez)
docker exec -it <nombre_contenedor_app> php /var/www/html/init_admin.php

# 3. Abrir en el navegador
#    http://localhost
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
| I2 | US-04 | Gestión de Veterinarios y Horarios | ⏳ Pendiente |
| I3 | US-05 | Agenda de citas | ⏳ Pendiente |
| I4 | US-06 | Consultas e historial médico | ⏳ Pendiente |
| I5 | US-07 | Bajas lógicas y reportes | ⏳ Pendiente |

### Tareas del Módulo Mascotas (US-03):
- **T-023 (2.1 - Modelo):** Atributos y reglas de Mascota con relación obligatoria a Propietario (`MascotaModel.php` / `Mascota.php`).
- **T-024 (2.2 - Persistencia):** Creación, consultas parametrizadas y validación de clave foránea (`MascotaRepository.php`).
- **T-025 (2.3 - Reglas):** Validación de campos obligatorios, sexo, fecha no futura e integridad (`PropietariosMascotasService.php`).
- **T-026 (2.4 - Flujo web):** Controlador con sesión de administrador y rutas web (`MascotaController.php`, `src/public/index.php`).
- **T-027 (2.5 - Pantalla):** Formulario semántico accesible y aviso de propietarios previos (`vistas/mascotas/crear.php`, `index.php`).
- **T-028 (2.6 - Interacción):** Validación visual interactiva y diseño adaptable móvil/desktop (`js/mascotas.js`, `style.css`).
- **T-029 (2.7 - Verificación):** Pruebas de alta, falta de propietario, FK inválida y campos requeridos (`RF03-mascotas.md`, `test_mascotas.php`).

Ver `docs/CHANGELOG.md` para el detalle de cambios por incremento.
