# ADR-002 — Sin Frameworks ni Bibliotecas Externas

**Fecha:** 2026-10-21
**Estado:** Aceptada
**Incremento:** I0 (US-00)
**Regla de origen:** TEC-01

---

## Contexto

El documento del proyecto (p. 40) restringe explícitamente el uso de Bootstrap, Composer packages, npm, CDN y cualquier biblioteca externa. La aplicación debe construirse únicamente con PHP nativo, HTML, CSS propio y MySQL.

## Decisión

- **No** se usa Bootstrap, Tailwind, ni ningún otro framework CSS.
- **No** se registran dependencias en `composer.json` (solo se mantiene para el autoloader PSR-4 manual).
- **No** se usa jQuery ni ningún paquete npm.
- Todo el CSS es propio (`public/css/style.css`) con variables CSS nativas.
- El autoloader es un `spl_autoload_register` manual en `index.php`.

## Consecuencias

- **Positivas:** Cumplimiento total de TEC-01; sin riesgo de deprecación por versiones externas; auditoría simple.
- **Negativas:** Más CSS manual en cada incremento; no hay componentes pre-construidos.
- **Future stories:** Al añadir nuevas funcionalidades, expandir `style.css` con nuevas clases. Nunca linkear CDN externo. Las validaciones de formulario deben ser en PHP nativo del lado del servidor.
