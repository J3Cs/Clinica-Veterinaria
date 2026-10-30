# Guía de Pruebas Manuales — RF09: Gestión de Veterinarios y Usuarios (`US-09`, `T-059` a `T-064`)

## Objetivo
Verificar el flujo integral de administración de médicos veterinarios y sus cuentas de acceso al sistema:
1. Creación atómica en transacción (`usuarios` + `veterinarios`) exclusiva para el rol `administrador`.
2. Validación de duplicados (`usuario`, `correo`, `licencia_medica`) y requisitos de contraseña.
3. Directorio con diferenciación de estado (`ACTIVO` / `INACTIVO`), buscador y contadores de relaciones clínicas (`citas`, `consultas`, `horarios`).
4. Edición de perfil donde **la contraseña solo se solicita al crear o cambiar credenciales**.
5. Confirmación en cliente (`window.confirm`) y política de baja controlada (`BAJA-01`) cuando existen citas o consultas asociadas.

---

## Matriz de Casos de Prueba (`CP-RF09-01` a `CP-RF09-12`)

| ID Caso | Escenario | Pasos de Ejecución | Resultado Esperado | Tarea / Regla |
| :--- | :--- | :--- | :--- | :--- |
| **CP-RF09-01** | Acceso exclusivo del Administrador a `/veterinarios` | 1. Iniciar sesión como `veterinario` (`dra.demo1`).<br>2. Intentar navegar a `GET /veterinarios` o `GET /veterinarios/crear`. | El sistema responde `403 Acceso Denegado` y el enlace «Veterinarios» no aparece en la barra de navegación del veterinario. | `T-062`, `ACC-01` |
| **CP-RF09-02** | Alta exitosa de veterinario y su cuenta inicial en transacción | 1. Iniciar sesión como `administrador`.<br>2. Ir a **Veterinarios → + Registrar Veterinario** (`/veterinarios/crear`).<br>3. Completar: Nombre `Dr. Carlos Eduardo Mena`, Especialidad `Cardiología`, Licencia `JVPMV-801`, Teléfono `7890-4567`, Usuario `dr.cmena`, Correo `cmena@clinica.sv`, Contraseña `Mena#2026!` y confirmación.<br>4. Enviar el formulario. | Se crea el registro en `usuarios` (`rol = 'veterinario'`, `activo = 1`) y su ficha 1:1 en `veterinarios` dentro de una transacción. Redirige a `/veterinarios` mostrando la alerta de éxito y el estado `ACTIVO`. | `T-059`, `T-060`, `T-062`, `T-063` |
| **CP-RF09-03** | Inicio de sesión con la nueva cuenta del veterinario | 1. Cerrar sesión del administrador.<br>2. Iniciar sesión con `dr.cmena` y contraseña `Mena#2026!`. | Autenticación exitosa y redirección automática a `/veterinario/agenda`. | `T-060`, `T-061`, `ACC-01` |
| **CP-RF09-04** | Rechazo de nombre de usuario o correo electrónico duplicado | 1. Como `administrador`, abrir `/veterinarios/crear`.<br>2. Intentar registrar otro veterinario con el usuario `dr.cmena` o correo `cmena@clinica.sv`. | El servidor rechaza el alta sin exponer errores SQL, señala los campos con error y conserva los datos ingresados (excepto la contraseña). | `T-061`, `T-062`, `SEC-01` |
| **CP-RF09-05** | Rechazo de licencia médica duplicada | 1. En `/veterinarios/crear`, ingresar una licencia ya asignada (`JVPMV-801`). | Se muestra mensaje de validación en el campo `licencia_medica` indicando que ya pertenece a otro veterinario. | `T-059`, `T-061` |
| **CP-RF09-06** | Edición de datos profesionales sin cambiar contraseña | 1. En `/veterinarios`, seleccionar al `Dr. Carlos Eduardo Mena` y pulsar **Editar / Ver Detalle**.<br>2. Cambiar la especialidad a `Cardiología y Ecografía` y dejar los campos de **Nueva Contraseña** en blanco.<br>3. Guardar cambios. | Los datos profesionales se actualizan correctamente y la contraseña original (`Mena#2026!`) sigue funcionando sin haberse requerido en el formulario. | `T-062`, `T-063` |
| **CP-RF09-07** | Cambio explícito de credenciales en edición | 1. En `/veterinarios/editar?id=N`, escribir en **Nueva Contraseña** `NuevaMena#2027` y su confirmación.<br>2. Guardar cambios. | Se actualiza el hash `PASSWORD_BCRYPT`; la contraseña anterior deja de funcionar y la nueva permite iniciar sesión. | `T-061`, `T-063` |
| **CP-RF09-08** | Confirmación previa antes de desactivar o eliminar | 1. En `/veterinarios`, seleccionar una fila y hacer clic en **Desactivar Cuenta** o **Eliminar**.<br>2. En el diálogo nativo `window.confirm`, pulsar **Cancelar**. | No se envía la petición `POST` y el estado del veterinario no cambia. | `T-064`, `UI-01` |
| **CP-RF09-09** | Baja lógica de veterinario con citas o consultas vinculadas | 1. Seleccionar un veterinario con citas registradas (ej. `Dra. Laura Demo`) y confirmar **Desactivar Cuenta**. | Se bloquea la eliminación física: la cuenta pasa a `INACTIVO` (`usuarios.activo = 0`), las citas `Programada` pendientes se cancelan automáticamente y las citas/consultas históricas se conservan. | `T-060`, `T-061`, `T-064`, `BAJA-01` |
| **CP-RF09-10** | Bloqueo de acceso a veterinario desactivado | 1. Intentar iniciar sesión en `/login` con las credenciales de un veterinario cuya cuenta fue desactivada. | El sistema impide el acceso informando que la cuenta se encuentra inactiva. | `T-061`, `BAJA-01` |
| **CP-RF09-11** | Reactivación de veterinario inactivo | 1. En `/veterinarios`, filtrar por estado **Inactivos**.<br>2. Seleccionar el veterinario inactivo y pulsar **Reactivar Cuenta** confirmando el diálogo. | La cuenta vuelve a estado `ACTIVO` (`usuarios.activo = 1`) y puede volver a iniciar sesión. | `T-061`, `T-063` |
| **CP-RF09-12** | Eliminación física de veterinario sin historial clínico | 1. Seleccionar un veterinario recién creado que no posea ninguna cita ni consulta asociada.<br>2. Pulsar **Eliminar** y confirmar el diálogo. | Se eliminan en transacción tanto la fila de `veterinarios` como la cuenta de `usuarios`. | `T-060`, `T-061`, `T-064` |
