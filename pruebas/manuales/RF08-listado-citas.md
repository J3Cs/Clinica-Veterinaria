# Matriz y Guía de Pruebas Manuales: RF08 — Listado Filtrable de Citas (US-08, T-058)

Este documento detalla los casos de prueba para verificar el requisito funcional **RF08 (Listado y filtrado de citas)**, asegurando el cumplimiento de las reglas de arquitectura y negocio del proyecto: **DAT-01**, **ARQ-01**, **ACC-01**, **SEC-01**, **UI-01** y **TEC-01**.

Pruebas automatizadas relacionadas:
- `php -d zend.assertions=1 pruebas/test_citas.php` — 39 pruebas automatizadas (incluye validación de filtros de US-08, filtrado por estado, fechas, veterinario, combinados, empty state y acciones permitidas).

---

## 1. Preparación y Datos Previos

1. Asegurarse de tener la base de datos con las migraciones aplicadas (`init.sql`, `dev_citas_seed.sql`).
2. Contar con al menos dos médicos veterinarios activos con citas registradas en diferentes estados (`Programada`, `Atendida`, `Cancelada`) y distintas fechas.
3. Iniciar sesión como usuario con rol `administrador` (ej. `admin` / `admin123`).
4. Para las pruebas de control de acceso (CP-10 y CP-11), disponer de una cuenta con rol `veterinario` (ej. `dra.demo1` / `demo123`) o una ventana en modo incógnito.

---

## 2. Matriz de Casos de Prueba

| ID | Caso de Prueba | Precondición | Pasos de Ejecución | Resultado Esperado | Estado |
|---|---|---|---|---|---|
| **CP-01** | Carga inicial del listado por defecto | Sesión de administrador activa. | 1. Ingresar a `/citas`<br>2. Bajar a la sección del listado | Por defecto se visualizan las citas en estado **Programada**, ordenadas cronológicamente por fecha y hora ascendente. | [ ] Aprobado |
| **CP-02** | Filtrado por estado: Atendidas | Existen citas con estado Atendida. | 1. En el selector de estado, elegir **Atendidas**<br>2. Clic en **Filtrar** | La tabla se actualiza mostrando únicamente citas con badge verde `ATENDIDA`. La columna de acciones muestra `—` (no cancelable). | [ ] Aprobado |
| **CP-03** | Filtrado por estado: Canceladas | Existen citas con estado Cancelada. | 1. En el selector de estado, elegir **Canceladas**<br>2. Clic en **Filtrar** | La tabla muestra únicamente citas con badge rojo `CANCELADA` y se visualiza el motivo de cancelación si fue registrado. Acciones muestra `—`. | [ ] Aprobado |
| **CP-04** | Filtrado por estado: Todas las citas | Existen citas en diversos estados. | 1. Elegir **Todas las citas** en el filtro<br>2. Clic en **Filtrar** | La tabla incluye citas `Programada`, `Atendida` y `Cancelada`, ordenadas cronológicamente. | [ ] Aprobado |
| **CP-05** | Filtrado por médico veterinario | Existen citas asignadas a distintos veterinarios. | 1. Seleccionar un médico en el selector **Médico Veterinario**<br>2. Clic en **Filtrar** | Se muestran únicamente las citas asignadas al veterinario elegido. El selector conserva la selección activa. | [ ] Aprobado |
| **CP-06** | Filtrado por rango de fechas (Desde / Hasta) | Existen citas en varias fechas. | 1. Ingresar una fecha en **Desde** y otra en **Hasta**<br>2. Clic en **Filtrar** | Solo se muestran las citas comprendidas dentro del rango inclusivo. Los campos de fecha conservan los valores ingresados. | [ ] Aprobado |
| **CP-07** | Combinación de filtros simultáneos | Citas variadas en BD. | 1. Seleccionar estado **Programadas**<br>2. Seleccionar un veterinario específico<br>3. Especificar rango de fechas<br>4. Clic en **Filtrar** | La consulta aplica todos los filtros concurrentemente con sentencias preparadas PDO. La tabla refleja la intersección exacta de criterios. | [ ] Aprobado |
| **CP-08** | Búsqueda por término relacionado (texto rápido) | Citas con diversos nombres de mascota, propietario y servicio. | 1. Escribir texto en el campo de búsqueda rápida `q`<br>2. Clic en **Filtrar** (o búsqueda interactiva en vivo) | Se filtran las filas que coincidan en nombre de mascota, propietario, veterinario o servicio. | [ ] Aprobado |
| **CP-09** | Estado vacío (Empty State) ante filtros sin resultados | No existen citas para un rango o criterio seleccionado. | 1. Seleccionar un rango de fechas en el que no existan citas (ej. año 2020)<br>2. Clic en **Filtrar** | Se muestra el Empty State amigable con icono 📅 indicando *"No se encontraron citas médicas con los filtros actuales."* y un botón de acción rápida **Restablecer filtros**. | [ ] Aprobado |
| **CP-10** | Validación de fechas inválidas o rango invertido | Sesión de administrador. | 1. Ingresar en URL `/citas?fecha_desde=2026-10-30&fecha_hasta=2026-10-10`<br>o fecha mal formada `/citas?fecha_desde=2026-02-31` | El sistema no falla (sin error 500) y presenta una alerta clara: *"La fecha inicial no puede ser posterior a la fecha final."* o *"La fecha inicial no tiene un formato válido (AAAA-MM-DD)."* | [ ] Aprobado |
| **CP-11** | Validación de estado inválido por URL | Sesión de administrador. | 1. Ingresar por URL `/citas?estado=Inexistente` | Se muestra mensaje claro de validación: *"Estado de cita no válido: solo se admiten Programada, Atendida o Cancelada."* | [ ] Aprobado |
| **CP-12** | Acción Cancelar solo permitida en estado Programada | Citas en estados Programada, Atendida y Cancelada en la misma tabla. | 1. Filtrar por **Todas las citas**<br>2. Observar la columna **Acciones** de cada fila | Únicamente las filas con estado `Programada` exhiben el botón rojo **Cancelar**. En filas `Atendida` y `Cancelada` aparece `—`. | [ ] Aprobado |
| **CP-13** | Botón Limpiar / Restablecer filtros | Filtros activos en el formulario. | 1. Con filtros aplicados, hacer clic en **Limpiar** o **Restablecer filtros** | Se reinicia el formulario a sus valores predeterminados (Programadas, sin fechas ni veterinario específico) y recarga la lista. | [ ] Aprobado |
| **CP-14** | Control de acceso: rol Veterinario (ACC-01 / RF08) | Sesión activa de veterinario (ej. Dra. Demo). | 1. Intentar acceder a `/citas` | Responde con código HTTP 403 Prohibido. El módulo de citas es exclusivo de administradores. | [ ] Aprobado |
| **CP-15** | Control de acceso: usuario sin sesión | Navegador sin cookies ni sesión. | 1. Abrir `/citas` | Redirección inmediata a `/login` (ACC-01). | [ ] Aprobado |
| **CP-16** | Responsividad móvil | Dispositivo móvil o viewport <= 768px. | 1. Abrir `/citas` con resolución reducida<br>2. Inspeccionar formulario de filtros y tabla | Los filtros se apilan fluidamente y la tabla adopta el diseño de tarjetas seleccionables responsivas con etiquetas `data-label` sin desbordamiento horizontal. | [ ] Aprobado |

---

## 3. Criterio de Aceptación Global

La historia **US-08** se considera **Aprobada** cuando:
- Todos los casos de prueba manuales de la matriz están verificados y marcados como aprobados.
- La suite automatizada `test_citas.php` se ejecuta al 100% de éxito.
- Los filtros no alteran la integridad de las relaciones (`mascotas`, `propietarios`, `veterinarios`, `servicios`).
- Se cumple estrictamente con las reglas **ARQ-01**, **ACC-01**, **SEC-01**, **DAT-01**, **UI-01** y **TEC-01**.
