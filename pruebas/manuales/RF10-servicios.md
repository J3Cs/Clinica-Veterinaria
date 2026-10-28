# Matriz y Guía de Pruebas Manuales: RF10 — Catálogo de Servicios (US-10, T-069)

Este documento detalla los casos de prueba para verificar el requisito funcional **RF10 (Gestión del catálogo de servicios veterinarios, US-10, T-065 a T-069)** en el sistema de Clínica Veterinaria, asegurando el cumplimiento de las reglas del negocio: **TEC-01**, **ARQ-01**, **ACC-01**, **SEC-01**, **BAJA-01** y **UI-01**.

---

## 1. Datos de Prueba Sugeridos

| Campo | Servicio A (Completo) | Servicio B (Campos Mínimos) | Servicio C (Duración Inválida) |
|---|---|---|---|
| **Nombre del Servicio** | Limpieza Dental Ultrasónica | Corte de Uñas Clínico | Ecografía Abdominal |
| **Descripción** | Profilaxis dental completa con ultrasonido y pulido. | *Vacío* | Estudio ecográfico de órganos internos |
| **Duración Estimada (min)** | `60` | `15` | `0` o `-15` (Inválida) |

---

## 2. Matriz de Casos de Prueba

| ID | Caso de Prueba | Precondición | Pasos de Ejecución | Resultado Esperado | Estado |
|---|---|---|---|---|---|
| **CP-01** | Alta exitosa de servicio completo | Sesión activa como `administrador`. | 1. Navegar a `/servicios/crear`<br>2. Ingresar Nombre, Descripción y Duración (`60`) del Servicio A<br>3. Clic en "Guardar Servicio" | Redirección a `/servicios`. Mensaje flash de éxito: *"Servicio «Limpieza Dental Ultrasónica» registrado exitosamente."* Visible en la tabla con estado `Activo`. | [ ] Aprobado |
| **CP-02** | Alta exitosa con descripción opcional vacía | Sesión activa como `administrador`. | 1. Navegar a `/servicios/crear`<br>2. Ingresar Nombre y Duración (`15`) del Servicio B<br>3. Dejar Descripción en blanco<br>4. Clic en "Guardar Servicio" | Registro exitoso. En la tabla se muestra `—` en la columna Descripción y estado `Activo`. | [ ] Aprobado |
| **CP-03** | Rechazo de nombre de servicio duplicado | Sesión activa como `administrador`. Existe "Consulta General". | 1. Navegar a `/servicios/crear`<br>2. Ingresar nombre `Consulta General` (o `consulta general`) y duración `30`<br>3. Clic en "Guardar Servicio" | Se rechaza el registro con mensaje de error en el campo: *"Ya existe un servicio con ese nombre."* Se preservan los datos en el formulario. | [ ] Aprobado |
| **CP-04** | Rechazo de duración menor o igual a cero | Sesión activa como `administrador`. | 1. Navegar a `/servicios/crear`<br>2. Ingresar Nombre válido y Duración `0` o `-10`<br>3. Intentar enviar el formulario | Bloqueo en cliente (JS) y validación en servidor: *"La duración debe ser mayor o igual a 1 minuto(s)"* / *"número entero positivo"*. No se guarda en BD. | [ ] Aprobado |
| **CP-05** | Rechazo de nombre vacío o menor a 3 caracteres | Sesión activa como `administrador`. | 1. Dejar el campo nombre vacío o ingresar `Rx`<br>2. Intentar guardar | Alerta visual `.is-invalid` indicando que el nombre es obligatorio y debe tener al menos 3 caracteres. | [ ] Aprobado |
| **CP-06** | Edición exitosa de un servicio existente | Sesión activa como `administrador`. Existe al menos un servicio. | 1. En `/servicios`, hacer clic en "Editar" sobre un servicio<br>2. Modificar la duración estimada (ej. de `30` a `40`) y descripción<br>3. Clic en "Guardar Cambios" | Redirección a `/servicios` con mensaje de éxito. La tabla refleja la nueva duración (`40 min`) y descripción actualizada. | [ ] Aprobado |
| **CP-07** | Desactivación lógica de un servicio sin citas (`BAJA-01`) | Sesión activa como `administrador`. Servicio activo sin citas. | 1. En `/servicios`, hacer clic en "Desactivar" junto al servicio<br>2. Confirmar el diálogo | El servicio cambia su estado a `Inactivo` sin eliminarse de la tabla. El botón cambia a "Activar". | [ ] Aprobado |
| **CP-08** | **Desactivación lógica de un servicio con citas asociadas (`BAJA-01`)** | Sesión activa como `administrador`. Servicio asociado a citas existentes. | 1. Desactivar un servicio que tenga citas registradas<br>2. Navegar a `/citas` y revisar el listado histórico | El servicio se marca como `Inactivo` en `/servicios`. En `/citas`, las citas históricas siguen mostrando el nombre del servicio sin errores ni pérdida de integridad referencial. | [ ] Aprobado |
| **CP-09** | Servicio inactivo no se ofrece al agendar nuevas citas | Existe un servicio con estado `Inactivo`. | 1. Navegar a `/citas` (sección Agendar cita)<br>2. Desplegar el selector "Servicio" | El servicio inactivo **no aparece** entre las opciones seleccionables para nuevas citas; solo se listan servicios activos. | [ ] Aprobado |
| **CP-10** | Control de acceso: **Veterinario intenta gestionar servicios (`ACC-01`)** | Sesión activa como `veterinario`. | 1. Intentar acceder por URL a `/servicios` o `/servicios/crear` | Respuesta HTTP 403 (Acceso denegado). El catálogo es exclusivo del rol `administrador`. | [ ] Aprobado |
| **CP-11** | Acceso anónimo sin sesión iniciada (`ACC-01`) | Sin sesión activa. | 1. Navegar a `/servicios` | Redirección automática a `/login`. | [ ] Aprobado |
| **CP-12** | Protección CSRF en operaciones POST (`SEC-01`) | Sesión activa como `administrador`. | 1. Alterar o eliminar el campo `csrf_token` en `/servicios/crear` o en el botón "Desactivar"<br>2. Enviar el formulario | Respuesta HTTP 419 / bloqueo de seguridad sin ejecutar cambios en la base de datos. | [ ] Aprobado |

---

## 3. Criterios de Aceptación Verificados
- **T-065 y T-066:** Modelo y repositorio soportan creación, listado completo/activos, actualización, desactivación/reactivación y consulta de integridad con sentencias preparadas (`SEC-01`).
- **T-067:** Validación estricta de nombre único, descripción y duración entera positiva (`> 0`).
- **T-068:** Operaciones de creación, edición y cambio de estado restringidas exclusivamente al rol `administrador` (`ACC-01`).
- **T-069 y BAJA-01:** Baja lógica sin borrado físico; los servicios inactivos no aparecen en el formulario de agendamiento de citas pero preservan la trazabilidad de citas históricas.
