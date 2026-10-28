# ADR-010: Búsqueda, Mantenimiento y Política de Baja de Propietarios, Mascotas y Veterinarios (US-12)

- **Estado:** Aceptado
- **Fecha:** 2026-10-28
- **Incremento:** Incremento 3 — US-12 (`T-076` a `T-081`, `T-090`)
- **Requisitos y Reglas Relacionadas:** `RF12`, `ARQ-01`, `TEC-01`, `UNI-01`, `DAT-01`, `BAJA-01`, `ACC-01`, `SEC-01`, `UI-01`

---

## 1. Contexto

A medida que la clínica veterinaria opera diariamente, los datos de contacto o identificación de los propietarios pueden requerir correcciones (cambio de teléfono, dirección, correo o corrección de DUI), los datos de las mascotas pueden actualizarse, y ciertos registros de propietarios, mascotas o médicos veterinarios pueden dejar de operar o haberse creado por error.

Sin embargo, la eliminación indiscriminada (`DELETE` o `ON DELETE CASCADE`) de un propietario, mascota o veterinario que ya posee historial clínico (`consultas_veterinarias`, `historial_mascota`) o registro de citas (`citas`) destruiría la trazabilidad médica y administrativa exigida por `BAJA-01`.

---

## 2. Decisión

1. **Campos de Baja Lógica (`T-090`):**
   - Se garantiza la presencia de la columna `activo TINYINT(1) NOT NULL DEFAULT 1` tanto en `propietarios` como en `mascotas` (`database/migrations/004_baja_logica.sql`), complementando `usuarios.activo` para los veterinarios.
   - Todas las consultas operativas por defecto (`findAll(true)`, selectores de propietarios al registrar mascotas y selectores de mascotas al agendar citas) excluyen los registros con `activo = 0`.
   - Los directorios incluyen un filtro explícito (`incluir_inactivos=1`) para auditoría y reactivación administrativa.

2. **Búsqueda Parametrizada y Expediente con Relaciones (`T-076`):**
   - `PropietarioModel` y `MascotaModel` incorporan consultas parametrizadas con PDO (`search()`, `contarRelaciones()`, `obtenerRelacionesClinicas()`) que agregan el conteo de mascotas, citas, consultas veterinarias y antecedentes médicos.
   - Al cargar una mascota (`obtenerMascotaConRelaciones()`), se conoce siempre su propietario y la totalidad de sus relaciones clínicas asociadas (`citas`, `consultas_veterinarias`, `historial_mascota`).
   - La búsqueda de propietarios normaliza automáticamente entradas de DUI de 9 dígitos sin guion al formato oficial `########-#` (`UNI-01`).

3. **Política Definitiva de Edición y Baja (`T-077`, `BAJA-01`):**
   - **Propietarios (`PropietariosMascotasService::darDeBajaPropietario`):**
     - Si el propietario posee mascotas registradas, citas o consultas asociadas, se aplica **baja lógica** (`propietarios.activo = 0`) y se desactivan lógicamente sus mascotas (`mascotas.activo = 0`), preservando intacto el historial clínico y de citas.
     - Solo si el propietario no tiene ninguna mascota ni cita asociada se ejecuta la **eliminación física** (`DELETE`).
   - **Mascotas (`PropietariosMascotasService::darDeBajaMascota`):**
     - Si la mascota posee citas (`citas`), atenciones (`consultas_veterinarias`) o antecedentes (`historial_mascota`), se aplica **baja lógica** (`mascotas.activo = 0`).
     - Solo si la mascota carece por completo de relaciones clínicas se ejecuta la **eliminación física** (`DELETE`).
   - **Veterinarios (`VeterinariosService::darDeBajaVeterinario`):**
     - Si el veterinario posee citas o consultas asociadas, se desactiva lógicamente su cuenta (`usuarios.activo = 0`). Conforme a `AuthService`, un veterinario con `activo = 0` **no puede iniciar sesión** en el sistema.
     - Solo si el veterinario no registra citas ni consultas se permite su eliminación física.

4. **Control de Acceso, Seguridad e Interfaz (`T-078`, `T-079`, `T-080`):**
   - Las acciones de edición, baja y reactivación en `PropietarioController`, `MascotaController` y `VeterinarioController` exigen rol `administrador` (`AuthMiddleware::requireRole('administrador')`) y validación de token CSRF en peticiones `POST` (`SEC-01`, `ACC-01`).
   - Las vistas de directorio y edición (`Views/propietarios/listar.php`, `Views/propietarios/editar.php`, `Views/mascotas/listar.php`, `Views/mascotas/editar.php`) muestran insignias de relaciones, estados vacíos claros cuando no hay coincidencias (`UI-01`) y confirmación nativa `window.confirm` antes de ejecutar una baja (`T-080`).

---

## 3. Consecuencias

- **Positivas:**
  - Cumplimiento estricto de `BAJA-01`, `UNI-01` y `DAT-01`: cero pérdida de historial médico o citas por bajas operativas, y limpieza física únicamente para registros creados por error sin actividad.
  - Consistencia arquitectónica en 4 capas (`Model` → `Repository` → `Service` → `Controller` → `View`) con nomenclatura unificada en inglés (`*Model`, `*Repository`, `*Service`, `*Controller`) sin duplicación de archivos.
- **Verificación:**
  - Cubierto por `pruebas/test_mantenimiento.php` (12 pruebas automatizadas incluyendo integración PDO con rollback) y `pruebas/manuales/RF12-mantenimiento.md` (12 casos manuales).
