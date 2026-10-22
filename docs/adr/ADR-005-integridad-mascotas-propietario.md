# ADR-005 — Integridad Referencial de Mascotas e Integridad Histórica

**Fecha:** 2026-10-21  
**Estado:** Aceptada  
**Incremento:** I2 (US-03 / RF03, T-023 a T-029)  
**Reglas de origen:** DAT-01, BAJA-01, ARQ-01, ACC-01, SEC-01, UI-01  

---

## Contexto

La regla **DAT-01** establece que: *"Una mascota siempre debe pertenecer a un propietario existente. Validar en servicio y mantener la FK en MySQL."*  
Asimismo, la regla **BAJA-01** (*Integridad histórica*) exige que los registros de pacientes y propietarios con citas, consultas o historial médico relacionado no se eliminen físicamente sino que se desactiven y conserven mediante bajas lógicas.

Para la historia **US-03** (*Registro de Mascotas*), el administrador debe poder dar de alta mascotas asociándolas de forma obligatoria a un propietario válido, con validaciones estrictas de campos, disponibilidad inmediata para citas médicas y protección contra peticiones maliciosas o inconsistentes.

---

## Decisiones Tomadas

1. **Integridad Referencial en Dos Niveles (`DAT-01`):**
   * **Nivel Base de Datos:** Clave foránea `fk_mascotas_propietarios` (`FOREIGN KEY (id_propietario) REFERENCES propietarios(id_propietario)`).
   * **Nivel Servicio (`PropietariosMascotasService`):** Se verifica activamente la existencia del propietario en la base de datos (`$this->propietarioRepo->findById($idPropietario)`) y que este se encuentre en estado activo (`activo = 1`) antes de proceder con el registro. Se rechaza con mensaje de negocio si no existe o si está inactivo.

2. **Soporte de Bajas Lógicas en la Entidad Mascota (`BAJA-01`):**
   * Se añade la columna `activo TINYINT(1) NOT NULL DEFAULT 1` a la tabla `mascotas` mediante la migración versionada `003_mascotas_activo.sql` y en el script inicial `init.sql`.
   * Los listados de mascotas en `MascotaModel` filtran por defecto solo registros activos (`activo = 1`), preservando la información para consultas e historiales médicos posteriores.

3. **Criterios de Validación de Datos:**
   * **Nombre:** Obligatorio, longitud entre 2 y 100 caracteres.
   * **Especie:** Obligatoria, longitud entre 2 y 50 caracteres (asistido con `<datalist>` HTML5 de sugerencias comunes sin bloquear especies exóticas).
   * **Raza:** Obligatoria según especificación US-03, permitiendo texto libre como *"Mestizo / Criollo"* si se desconoce.
   * **Sexo:** Obligatorio, restringido estrictamente a los valores del ENUM MySQL: `'Macho'` o `'Hembra'`.
   * **Fecha de nacimiento:** Opcional. Si se proporciona, se valida el formato ISO `AAAA-MM-DD`, la validez de calendario con `checkdate()` y que no sea una fecha futura (`<= CURRENT_DATE`).
   * **Características relevantes:** Opcional, campo de texto para señas particulares, cicatrices o temperamento.

4. **Coherencia Visual y Accesibilidad (`UI-01`):**
   * Las vistas (`crear.php` e `index.php`) adoptan el mismo lenguaje visual de la pantalla de *login*: estructura de tarjetas, feedback accesible con `#alert-container`, clases `.is-invalid` y `.invalid-feedback`, iconografía temática (`🐾`) y estilo adaptable sin librerías externas.
   * Si no existen propietarios en el sistema, la pantalla de alta muestra un *Empty State* que impide el error y guía al usuario mediante un botón directo para registrar un propietario primero.

5. **Seguridad y Control de Acceso (`ACC-01`, `SEC-01`):**
   * El registro de mascotas es una acción restringida exclusivamente al rol `administrador` (`AuthMiddleware::requireRole('administrador')`).
   * Toda petición POST requiere validación previa de token CSRF (`CsrfMiddleware::validateToken`).
   * Todos los datos presentados en vistas se escapan con `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

---

## Consecuencias

- **Positivas:**
  * No es posible huérfanos de datos en la entidad `mascotas`.
  * La mascota queda inmediatamente disponible con su `id_mascota` para la futura agenda de citas médicas (US-03 / CIT-01).
  * Se cumple al 100% con la política de no utilizar dependencias externas ni Composer/npm (`TEC-01`).
- **Próximos Pasos:**
  * Módulo de Citas Médicas (`citas`): requerirá relacionar `id_mascota`, `id_veterinario` y `id_servicio`.
