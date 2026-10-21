# Matriz y Guía de Pruebas Manuales: RF01 — Autenticación de Usuarios (US-01)

Este documento detalla los casos de prueba manuales para la verificación del requisito funcional **RF01 (Autenticación de Usuarios)** en el sistema de Clínica Veterinaria, asegurando el cumplimiento de las reglas **TEC-01**, **ARQ-01**, **ACC-01**, **SEC-01**, **ADM-01** y **UI-01**.

---

## 1. Datos de Prueba Sugeridos

Antes de iniciar la ejecución de pruebas, verificar que la base de datos contenga los siguientes registros de prueba (o crearlos según corresponda):

| Rol | Nombre | Usuario | Correo Electrónico | Contraseña | Activo |
|---|---|---|---|---|---|
| Administrador | Dr. Administrador | `admin` | `admin@clinica.com` | `Admin1234*` | `1` (Activo) |
| Veterinario | Dra. Valeria Vet | `vvaleria` | `valeria@clinica.com` | `Vet1234*` | `1` (Activo) |
| Inactivo | Usuario Inactivo | `inactivo` | `inactivo@clinica.com` | `Inactivo1234*` | `0` (Inactivo) |

> [!NOTE]
> La primera cuenta de administrador se crea mediante el script CLI:  
> `php init_admin.php` o dentro de Docker: `docker exec -it php_mvc_dual php /var/www/html/init_admin.php` (ADM-01).

---

## 2. Matriz de Casos de Prueba

| ID | Caso de Prueba | Precondiciones | Pasos de Ejecución | Resultado Esperado | Estado |
|---|---|---|---|---|---|
| **CP-01** | Inicio de sesión exitoso con **nombre de usuario** (Administrador) | Usuario `admin` activo en la BD. | 1. Navegar a `/login`<br>2. Ingresar `admin` en el campo "Usuario o Correo"<br>3. Ingresar la contraseña correcta<br>4. Clic en "Iniciar Sesión" | Redirección automática a `/admin/dashboard`. Mensaje de bienvenida con rol de Administrador. | [ ] Aprobado |
| **CP-02** | Inicio de sesión exitoso con **correo electrónico** (Administrador) | Cuenta `admin@clinica.com` activa. | 1. Navegar a `/login`<br>2. Ingresar `admin@clinica.com`<br>3. Ingresar la contraseña correcta<br>4. Clic en "Iniciar Sesión" | Redirección exitosa a `/admin/dashboard`. | [ ] Aprobado |
| **CP-03** | Inicio de sesión exitoso con **Veterinario** (Redirección por Rol) | Cuenta `vvaleria` con rol `veterinario` activa. | 1. Navegar a `/login`<br>2. Ingresar `vvaleria`<br>3. Ingresar contraseña correcta<br>4. Clic en "Iniciar Sesión" | Redirección automática a `/veterinario/agenda` (T-012, ACC-01). | [ ] Aprobado |
| **CP-04** | Intento con **contraseña incorrecta** | Usuario `admin` existe. | 1. Navegar a `/login`<br>2. Ingresar `admin`<br>3. Ingresar contraseña incorrecta (`Error9999`)<br>4. Clic en "Iniciar Sesión" | Permanece en `/login`. Muestra mensaje de error genérico: *"Usuario o contraseña incorrectos."* No revela si el fallo fue de password. | [ ] Aprobado |
| **CP-05** | Intento con **usuario/correo inexistente** | Usuario `noexiste` no está registrado. | 1. Navegar a `/login`<br>2. Ingresar `noexiste`<br>3. Ingresar cualquier contraseña<br>4. Clic en "Iniciar Sesión" | Permanece en `/login`. Muestra mensaje idéntico al CP-04: *"Usuario o contraseña incorrectos."* (SEC-01: no enumeración). | [ ] Aprobado |
| **CP-06** | Intento con **cuenta inactiva** (`activo = 0`) | Usuario `inactivo` con columna `activo = 0`. | 1. Navegar a `/login`<br>2. Ingresar `inactivo`<br>3. Ingresar contraseña correcta<br>4. Clic en "Iniciar Sesión" | Acceso denegado. Muestra mensaje claro: *"La cuenta se encuentra inactiva. Contacte al administrador."* | [ ] Aprobado |
| **CP-07** | Intento de acceso a **ruta protegida sin sesión** | Sin sesión activa en el navegador. | 1. Ingresar directamente en la barra de URL `http://localhost:8080/admin/dashboard` | Redirección inmediata a `/login`. La vista protegida no carga (ACC-01). | [ ] Aprobado |
| **CP-08** | Control de acceso por rol: **Veterinario intenta acceder a `/admin/dashboard`** | Sesión activa con rol `veterinario`. | 1. Iniciar sesión como `vvaleria`<br>2. Escribir manualmente en URL `/admin/dashboard` | Respuesta HTTP 403 (Acceso denegado). El panel de administración no se muestra (ACC-01, SEC-01). | [ ] Aprobado |
| **CP-09** | Protección contra **CSRF en POST /login** | Formulario de login. | 1. Simular envío POST a `/login` sin token `csrf_token` o con valor arbitrario (vía DevTools o curl). | Respuesta HTTP 419 (Solicitud inválida / Token no válido). La solicitud es rechazada (SEC-01). | [ ] Aprobado |
| **CP-10** | **Cierre de sesión seguro** (Logout) | Sesión activa. | 1. Clic en botón "Cerrar sesión"<br>2. Verificar redirección a `/login?logout=1`<br>3. Presionar botón "Atrás" del navegador | Muestra aviso de sesión cerrada. Al pulsar atrás o refrescar, no permite navegar dentro de rutas privadas sin volver a autenticar. | [ ] Aprobado |
| **CP-11** | **Accesibilidad y navegación por teclado** | Formulario en `/login`. | 1. Cargar `/login`<br>2. Verificar que el foco inicia en el campo usuario (`autofocus`)<br>3. Presionar `Tab` $\rightarrow$ enfoca campo contraseña<br>4. Presionar `Tab` $\rightarrow$ enfoca botón submit<br>5. Presionar `Enter` | La navegación fluye sin necesidad de mouse y el formulario envía con Enter (UI-01). | [ ] Aprobado |
| **CP-12** | **Validación visual JS previa** (Campos vacíos) | Formulario en `/login`. | 1. Dejar campos vacíos y pulsar "Iniciar Sesión"<br>2. Ingresar solo espacios en blanco | JavaScript previene el envío, resalta el campo con borde de error (`.is-invalid`), enfoca el input y muestra aviso sin recargar la página (T-014). | [ ] Aprobado |

---

## 3. Criterios de Aceptación Globales
- Todas las rutas privadas verifican `AuthMiddleware` en el servidor (ACC-01).
- Ningún mensaje de error expone trazas de SQL ni información de la estructura interna (SEC-01).
- Toda acción POST está protegida con token CSRF validado mediante `hash_equals` (SEC-01).
- El formulario es responsivo y se adapta sin deformarse en resoluciones de pantalla reducidas (UI-01).

