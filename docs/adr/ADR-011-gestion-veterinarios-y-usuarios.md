# ADR-011: Gestión de Veterinarios y Cuentas de Usuario en Transacción (US-09)

- **Estado:** Aceptado
- **Fecha:** 2026-10-28
- **Incremento:** Incremento 3 — US-09 (`T-059` a `T-064`)
- **Requisitos y Reglas Relacionadas:** `RF09`, `ARQ-01`, `TEC-01`, `UNI-01`, `DAT-01`, `BAJA-01`, `ACC-01`, `SEC-01`, `UI-01`

---

## 1. Contexto

Cada médico veterinario del sistema requiere tanto una ficha profesional en la tabla `veterinarios` (`especialidad`, `telefono`, `licencia_medica`) como una cuenta de acceso en la tabla `usuarios` (`rol = 'veterinario'`, `activo = 1`) vinculada mediante una relación uno a uno (`veterinarios.id_usuario UNIQUE`).

Si la creación de la cuenta y de la ficha profesional no se realiza de forma atómica, un fallo intermedio dejaría cuentas de usuario huérfanas sin ficha médica. Asimismo, cuando un veterinario ya registra citas (`citas`) o atenciones (`consultas_veterinarias`), su eliminación física destruiría la trazabilidad clínica exigida por `BAJA-01`.

---

## 2. Decisión

1. **Relación 1:1 y Alta Transaccional (`T-059`, `T-060`):**
   - `VeterinarioModel` expone `transaccion(callable $fn)` apoyado en `PDO::beginTransaction()`, `commit()` y `rollBack()`.
   - `VeterinarioRepository::createConUsuario()` ejecuta dentro de una única transacción la inserción en `usuarios` (`rol = 'veterinario'`, contraseña cifrada con `PASSWORD_BCRYPT`) seguida de la inserción en `veterinarios` con el `id_usuario` generado.

2. **Política de Credenciales en Alta vs. Edición (`T-061`, `T-063`):**
   - Al **crear** un veterinario (`/veterinarios/crear`), la contraseña inicial es obligatoria (mínimo 8 caracteres y confirmación).
   - Al **editar** un veterinario (`/veterinarios/editar`), los campos de nueva contraseña son opcionales: si se dejan vacíos, `UsuarioModel::update()` conserva intacto el hash actual; solo si el administrador escribe una nueva contraseña se valida y actualiza el hash.

3. **Política de Baja Controlada cuando existen Citas o Consultas (`T-061`, `BAJA-01`):**
   - `VeterinariosService::darDeBajaVeterinario()` verifica mediante `VeterinarioRepository::obtenerRelaciones()` si existen citas o consultas asociadas al veterinario:
     - **Con historial clínico o citas (`tiene_dependencias = true`):** Se bloquea la eliminación física y se aplica **baja lógica** sobre la cuenta (`usuarios.activo = 0`), cancelando automáticamente sus citas en estado `Programada` y preservando todas sus citas pasadas y consultas veterinarias. Un veterinario con `activo = 0` no puede iniciar sesión ni aparece en los selectores operativos de agendamiento.
     - **Sin citas ni consultas (`tiene_dependencias = false`):** Se ejecuta `deleteFisico()` en transacción (eliminando primero la ficha en `veterinarios` y luego la cuenta en `usuarios`). Si una restricción de clave foránea (`ON DELETE RESTRICT`) impide el borrado por concurrencia, el servicio captura la excepción sin exponer SQL y aplica baja lógica como respaldo seguro (`SEC-01`).

4. **Interfaz, Confirmación en Cliente y Navegación (`T-062`, `T-063`, `T-064`):**
   - Se incorpora la pestaña **Veterinarios** (`/veterinarios`) en `App\Config\Navegacion` exclusivamente para el rol `administrador`.
   - `public/assets/js/veterinarios.js` solicita confirmación explícita (`window.confirm`) detallando el número de citas y consultas vinculadas antes de enviar cualquier solicitud de desactivación, eliminación o reactivación.

---

## 3. Consecuencias

- **Positivas:**
  - Garantía de integridad 1:1 entre `usuarios` y `veterinarios` sin cuentas huérfanas.
  - Preservación total del historial clínico (`BAJA-01`) y manejo seguro de errores de unicidad y claves foráneas sin exponer detalles de SQL (`SEC-01`).
- **Verificación:**
  - Cubierto por `pruebas/test_veterinarios.php` (10 pruebas automatizadas incluyendo integración real con MySQL y rollback) y `pruebas/manuales/RF09-veterinarios.md` (12 casos manuales).
