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
├── docs/
│   ├── CHANGELOG.md          ← Historial de cambios por incremento
│   └── adr/                  ← Architecture Decision Records
├── init.sql                  ← Esquema completo de la BD (9 tablas)
├── init_admin.php            ← Script CLI para crear el primer admin
├── docker-compose.yml
├── Dockerfile
└── src/
    ├── app/
    │   ├── Config/
    │   │   └── Database.php          (singleton PDO)
    │   ├── Controllers/              (coordinan flujo, sin lógica de negocio)
    │   ├── Middlewares/              (AuthMiddleware, CsrfMiddleware, ErrorHandlerMiddleware)
    │   ├── Models/                   (SQL puro con PDO)
    │   ├── Repositories/             (filtros de negocio sobre modelos)
    │   ├── Services/                 (lógica de negocio)
    │   ├── Views/                    (HTML + htmlspecialchars, sin SQL)
    │   └── Router.php
    └── public/
        ├── index.php                 (único punto de entrada)
        ├── .htaccess
        └── css/style.css
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
- **UNI-01** — DUI salvadoreño como identificador único del propietario.
- **ADM-01** — El primer administrador se crea con `init_admin.php` desde CLI, nunca por interfaz web pública.
- **BAJA-01** — Bajas lógicas con columna `activo`. No se elimina físicamente si existen relaciones.

## Incrementos

| # | Historia | Módulo |
|---|----------|--------|
| I0 | US-00 | Base técnica MVC ✅ |
| I1 | US-01 | Propietarios y mascotas |
| I2 | US-02 | Veterinarios y horarios |
| I3 | US-03 | Agenda de citas |
| I4 | US-04 | Consultas e historial médico |
| I5 | US-05 | Bajas lógicas |

Ver `docs/CHANGELOG.md` para el detalle de cambios por incremento.
