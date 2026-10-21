# ADR-003 — Autenticación con Sesiones PHP Nativas

**Fecha:** 2026-10-21
**Estado:** Aceptada
**Incremento:** I0 (US-00)
**Reglas de origen:** SEC-01, ACC-01

---

## Contexto

Solo administradores y veterinarios pueden iniciar sesión (ACC-01). No existe portal público para propietarios. Se necesita un mecanismo de autenticación seguro sin bibliotecas externas (TEC-01).

## Decisión

Se usa **sesión PHP nativa** (`session_start`, `$_SESSION`) con las siguientes medidas de seguridad:

| Medida | Implementación |
|--------|---------------|
| Hash de contraseñas | `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])` |
| Verificación | `password_verify($input, $hash)` |
| Session fixation | `session_regenerate_id(true)` al autenticar |
| CSRF | `CsrfMiddleware`: token en sesión, validado con `hash_equals` en cada POST |
| Logout | Vaciar `$_SESSION`, destruir cookie de sesión, `session_destroy()` |
| Mensajes de error | Siempre genéricos: "Usuario o contraseña incorrectos" (no revela si el usuario existe) |
| Verificación de rol | `AuthMiddleware::requireRole()` al inicio de cada acción protegida |

## Consecuencias

- **Positivas:** Sin dependencias externas; control total del ciclo de vida de la sesión; fácil auditoría.
- **Negativas:** Escalabilidad limitada en entornos multi-servidor (sin Redis); aceptable para el alcance del proyecto.
- **Future stories:** Toda acción que modifique datos (POST/PUT/DELETE) debe llamar `CsrfMiddleware::validateToken()` antes de procesar. Todos los parámetros de usuario deben pasarse a través de prepared statements.
