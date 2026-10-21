# ADR-004 — DUI como Identificador Único del Propietario

**Fecha:** 2026-10-21
**Estado:** Aceptada
**Incremento:** I0 (US-00)
**Regla de origen:** UNI-01

---

## Contexto

La regla UNI-01 establece que el DUI salvadoreño sea el identificador único del propietario. El DUI tiene el formato `#########-#` (9 dígitos, guion, 1 dígito = 10 caracteres totales).

## Decisión tomadas

1. **Columna en BD:** `dui CHAR(10) NOT NULL` con `UNIQUE KEY` en la tabla `propietarios`.
2. **Tipo `CHAR(10)`:** longitud fija porque el formato normalizado siempre tiene 10 caracteres.
3. **Validación en Service:** el formato `########-#` se verifica con expresión regular en PHP antes de insertar, no como CHECK constraint de MySQL (para compatibilidad).
4. **Normalización:** eliminar espacios y guiones extra antes de validar (Service layer).
5. **No reemplaza `id_propietario`:** el INT autoincrement sigue siendo la PK interna; el DUI es un identificador de negocio con unique constraint.
6. **El correo del propietario sigue siendo opcional** (a diferencia del correo del usuario del sistema, que es `UNIQUE NOT NULL`).

## Consecuencias

- **Future stories (US-01 — Propietarios):** El formulario de alta de propietario debe incluir campo DUI con validación del formato antes de enviar al servicio. Si el DUI ya existe → mostrar error de negocio amigable, nunca exponer el mensaje de MySQL.
- El campo `dui` debe sanitizarse con `htmlspecialchars` al mostrarse en vistas.
- Las búsquedas de propietarios pueden hacerse por DUI sin ambigüedad.
