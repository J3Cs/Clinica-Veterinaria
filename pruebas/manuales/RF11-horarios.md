# Matriz de Pruebas Manuales: RF11 — Horarios de Atención (US-11)

**Historia de Usuario:** US-11 (T-070 a T-075)  
**Módulo:** Gestión de Horarios de Atención Médica  
**Roles involucrados:** Administrador (gestión), Veterinario (consulta/agenda)  
**Reglas asociadas:** `TEC-01`, `ARQ-01`, `ACC-01`, `SEC-01`, `CIT-01`, `BAJA-01`, `UI-01`, `RNF-02`

---

## Matriz de Casos de Prueba

| ID | Caso de Prueba | Precondición | Pasos de Ejecución | Resultado Esperado | Estado |
|:---|:---|:---|:---|:---|:---:|
| **CP-HOR-01** | Acceso exclusivo a administradores (`ACC-01`) | Sesión iniciada con rol `veterinario` | Navegar a `/horarios` o `/horarios/crear` | Redirección inmediata o error 403 Forbidden. Solo rol `administrador` puede gestionar horarios. | Pasó |
| **CP-HOR-02** | Visualización de listado y estado vacío (`UI-01`) | Base de datos sin horarios asignados | Iniciar sesión como administrador e ingresar a `/horarios` | Se muestra pantalla con empty state amigable y botón destacado para asignar el primer horario. | Pasó |
| **CP-HOR-03** | Filtrado por médico veterinario | Existen múltiples veterinarios con horarios | Seleccionar un veterinario en el desplegable de filtro | La tabla recarga mostrando únicamente las franjas asociadas al veterinario elegido. | Pasó |
| **CP-HOR-04** | Creación exitosa de franja horaria | Veterinario activo registrado | Ingresar a `/horarios/crear`, elegir veterinario, día Lunes (1), inicio `08:00`, fin `12:00`. Clic en "Guardar Franja" | Se registra la franja con éxito, mensaje flash verde y redirección al listado. | Pasó |
| **CP-HOR-05** | Validación cliente y servidor: `hora_inicio >= hora_fin` (`T-070`, `T-075`) | Formulario `/horarios/crear` abierto | Ingresar inicio `14:00` y fin `10:00`. Intentar enviar | JavaScript bloquea el envío mostrando alerta roja; si se desactiva JS, el servidor rechaza con mensaje de error específico. | Pasó |
| **CP-HOR-06** | Validación de día fuera de rango (`T-070`, `T-072`) | Manipulación de petición POST | Enviar `dia_semana=0` o `dia_semana=8` | El servidor rechaza la petición indicando que el día debe estar entre 1 (Lunes) y 7 (Domingo). | Pasó |
| **CP-HOR-07** | Rechazo de solapamiento idéntico o parcial (`CIT-01`, `T-072`) | Existe franja Lunes de `08:00` a `12:00` | Intentar registrar para el mismo veterinario el Lunes de `10:00` a `14:00` | El sistema rechaza la operación informando que la franja se solapa con una existente. | Pasó |
| **CP-HOR-08** | Aceptación de franjas contiguas | Existe franja Lunes de `08:00` a `12:00` | Registrar para el mismo veterinario el Lunes de `12:00` a `16:00` | El sistema permite el guardado exitosamente ya que no hay superposición de minutos. | Pasó |
| **CP-HOR-09** | Bloqueo de modificación por citas programadas (`CIT-01`, `T-072`) | Existe cita futura programada a las `11:00` (duración 30 min) en la franja `08:00–12:00` | Editar la franja para recortarla a `08:00–10:00` | El sistema **bloquea la modificación**, informando detalladamente la cita comprometida (fecha, hora y paciente). | Pasó |
| **CP-HOR-10** | Bloqueo de eliminación por citas programadas (`CIT-01`, `BAJA-01`, `T-072`) | Existe cita futura programada dentro de la franja | Clic en "Eliminar" en dicha franja | El sistema rechaza la eliminación y conserva la franja para proteger la cita agendada. | Pasó |
| **CP-HOR-11** | Eliminación física permitida sin dependencias (`BAJA-01`) | Franja sin citas programadas asociadas | Clic en "Eliminar" y confirmar diálogo | La franja se elimina de la base de datos y desaparece del listado con confirmación flash. | Pasó |
| **CP-HOR-12** | Protección contra falsificación de petición (CSRF) (`SEC-01`) | Petición POST a `/horarios` | Enviar formulario con token CSRF inválido o vacío | La acción es rechazada y el usuario es redirigido con mensaje de seguridad. | Pasó |
