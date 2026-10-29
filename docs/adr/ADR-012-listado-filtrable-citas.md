# ADR-012 — Listado Filtrable de Citas

**Fecha:** 2026-10-28  
**Estado:** Aceptada  
**Incremento:** I3 (US-08 / RF08, T-054 a T-058)  
**Reglas de origen:** ARQ-01, ACC-01, SEC-01, DAT-01, UI-01, TEC-01  

---

## Contexto

En el incremento previo de gestión de citas (US-05 / ADR-007) se implementó la cancelación atómica y una pestaña única con listado básico que únicamente filtraba por estado fijo. Sin embargo, para la operación diaria de la clínica, los administradores requerían consultar la agenda filtrando dinámicamente por estado, médico veterinario, rangos de fecha específicos y términos de búsqueda relacionados (mascota, propietario o servicio), manteniendo la visualización organizada y responsiva en cualquier dispositivo.

## Decisiones

1. **Consulta base proyectada intacta con filtros opcionales dinámicos (T-054, SEC-01, DAT-01):**  
   La consulta base `SELECT_DETALLE` en `CitaModel` mantiene los `INNER JOIN` relacionales completos hacia `mascotas`, `propietarios`, `servicios`, `veterinarios` y `usuarios`. Los filtros opcionales (`estado`, `fecha_desde`, `fecha_hasta`, `id_veterinario`, `termino`) se concatenan condicionalmente en una cláusula `WHERE` usando exclusivamente parámetros vinculados PDO (`:estado`, `:fecha_desde`, `:fecha_hasta`, `:id_veterinario`, `:termino`). Ningún dato de entrada del usuario se interpola directamente en SQL.

2. **Validación previa en la capa de negocio (T-055, ARQ-01):**  
   En cumplimiento con la separación de responsabilidades, `CitaService::validarFiltros()` procesa los criterios antes de interactuar con el repositorio:
   - Se admiten exclusivamente los estados `Programada`, `Atendida`, `Cancelada` o `Todas`. Estados desconocidos o mal formados producen mensajes de error claros y no se ejecutan consultas arbitrarias.
   - Las fechas se validan en formato ISO `AAAA-MM-DD` y existencia en el calendario gregoriano.
   - Se asegura la coherencia cronológica: `fecha_desde <= fecha_hasta`. Si se provee un rango invertido, se informa un error específico.
   - Los errores de filtro se devuelven de forma estructurada para que la interfaz muestre feedback inmediato al usuario sin interrumpir la navegación.

3. **Control de acceso y preservación de estado en navegación (T-056, ACC-01):**  
   - La acción `CitaController::index` exige rol `administrador` vía `AuthMiddleware::requireRole('administrador')`. Cualquier rol no autorizado recibe HTTP 403 o redirección al login.
   - El controlador preserva todos los filtros aplicados (`$filtrosListado`) tanto en los inputs del formulario como en los enlaces de acción ("Limpiar", "Restablecer filtros", etc.).

4. **Diseño responsivo y tabla homologada (T-057, UI-01):**  
   - Se implementa la clase `.tabla-escritorio` de `modulos.css` junto con `citas.css`. En pantallas mayores a 768px se presenta una tabla de datos completa y en dispositivos móviles las filas se adaptan automáticamente a tarjetas seleccionables accesibles con atributos `data-label`.
   - Se definen columnas claras para: Fecha, Hora, Mascota, Propietario, Médico Veterinario, Servicio Clínico, Estado y Acciones permitidas.
   - La acción **Cancelar** se ofrece de forma visible únicamente para citas con estado `Programada`. Las citas `Atendida` o `Cancelada` muestran un indicador pasivo `—`.

5. **Pruebas y verificación sin dependencias externas (T-058, TEC-01):**  
   - Se definieron 10 nuevas pruebas en `pruebas/test_citas.php` empleando `assert()` nativo en PHP 8.2 (39 pruebas en total).
   - Se documentó la matriz completa de casos de prueba manuales en `pruebas/manuales/RF08-listado-citas.md` cubriendo filtros individuales, combinados, errores de usuario y control de acceso.

## Consecuencias

- Los administradores pueden localizar rápidamente citas por fechas pasadas o futuras, por médico veterinario asignado o por estado específico sin recargar citas innecesarias.
- Las consultas son eficientes y seguras frente a inyecciones SQL (SEC-01).
- La interfaz conserva total coherencia estética con los módulos de Horarios, Propietarios, Mascotas y Veterinarios.
