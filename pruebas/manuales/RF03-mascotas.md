# Matriz y Guía de Pruebas Manuales: RF03 — Gestión de Mascotas (US-03, T-029)

Este documento detalla los casos de prueba para verificar el requisito funcional **RF03 (Registro y control de pacientes veterinarios / Mascotas, T-029)** en el sistema de Clínica Veterinaria, asegurando el cumplimiento de las reglas del negocio: **DAT-01**, **TEC-01**, **ARQ-01**, **ACC-01**, **SEC-01**, **BAJA-01** y **UI-01**.

---

## 1. Datos de Prueba Sugeridos

| Campo | Mascota A (Completa) | Mascota B (Campos Mínimos) | Mascota C (Fecha Futura - Error) |
|---|---|---|---|
| **Propietario** | Propietario activo registrado (ej. Ana Martínez) | Propietario activo registrado | Propietario activo registrado |
| **Nombre** | Rocky | Pelusa | Toby |
| **Especie** | Canino (Perro) | Felino (Gato) | Canino |
| **Raza** | Bulldog Francés | Mestizo / Criollo | Labrador |
| **Sexo** | Macho | Hembra | Macho |
| **Fecha de Nacimiento** | `2021-08-10` | *Vacío* | `2028-01-01` (Inválida por futura) |
| **Características Relevantes**| Color atigrado, dócil, esterilizado | *Vacío* | Ninguna |

---

## 2. Matriz de Casos de Prueba

| ID | Caso de Prueba | Precondición | Pasos de Ejecución | Resultado Esperado | Estado |
|---|---|---|---|---|---|
| **CP-01** | Registro exitoso de mascota completa | Sesión activa como `administrador`. Existe al menos un propietario activo. | 1. Navegar a `/mascotas/crear`<br>2. Seleccionar propietario de la lista desplegable<br>3. Ingresar Nombre, Especie, Raza, Sexo, Fecha de Nacimiento y Características (Mascota A)<br>4. Clic en "Guardar Mascota" | Redirección a `/mascotas`. Mensaje flash de éxito: *"Mascota registrada exitosamente y disponible para citas."* Registro visible en la tabla con todos sus datos. | [ ] Aprobado |
| **CP-02** | Registro con campos opcionales omitidos | Sesión activa como `administrador`. | 1. Navegar a `/mascotas/crear`<br>2. Seleccionar propietario<br>3. Ingresar únicamente los campos obligatorios: Nombre, Especie, Raza y Sexo (Mascota B)<br>4. Dejar fecha y características vacías<br>5. Clic en "Guardar Mascota" | Registro exitoso. En la tabla se visualiza "No registrada" en fecha de nacimiento y se guarda con `activo = 1`. | [ ] Aprobado |
| **CP-03** | **Integridad Referencial sin Propietario (`DAT-01`)** | Sesión activa como `administrador`. | 1. Navegar a `/mascotas/crear`<br>2. Dejar el selector de propietario sin seleccionar (`-- Selecciona el propietario...`)<br>3. Completar los demás campos<br>4. Intentar enviar el formulario | Bloqueo en cliente por JavaScript y validación de servicio: *"Debes seleccionar un propietario válido."* No se crea registro en BD. | [ ] Aprobado |
| **CP-04** | Intentar registrar con fecha de nacimiento futura | Sesión activa como `administrador`. | 1. Completar formulario asignando una fecha posterior al día de hoy (ej. `2028-12-31`)<br>2. Intentar guardar | Bloqueo con alerta: *"La fecha de nacimiento no puede ser posterior a la fecha actual."* Se preservan los datos ya ingresados en el formulario. | [ ] Aprobado |
| **CP-05** | Nombre de mascota vacío o menor a 2 caracteres | Sesión activa como `administrador`. | 1. Dejar nombre vacío o escribir una sola letra<br>2. Intentar guardar | Alerta visual con clase `.is-invalid` y mensaje: *"El nombre de la mascota es obligatorio"* / *"debe tener al menos 2 caracteres."* | [ ] Aprobado |
| **CP-06** | Especie o Raza vacías | Sesión activa como `administrador`. | 1. Dejar especie o raza en blanco<br>2. Intentar guardar | Validación estricta que exige ambos campos obligatorios según US-03. | [ ] Aprobado |
| **CP-07** | Sexo no seleccionado | Sesión activa como `administrador`. | 1. Dejar el selector de sexo en blanco<br>2. Intentar guardar | Alerta: *"El sexo de la mascota es obligatorio"* (solo permite 'Macho' o 'Hembra'). | [ ] Aprobado |
| **CP-08** | Estado sin propietarios registrados | No existen propietarios en la base de datos. | 1. Ingresar a `/mascotas/crear` | Se visualiza un panel amigable (Empty State) advirtiendo que se requiere registrar un propietario antes de asociar mascotas, con enlace directo a `/propietarios/crear`. | [ ] Aprobado |
| **CP-09** | Control de acceso: **Veterinario intenta registrar mascota** | Sesión activa como `veterinario`. | 1. Intentar acceder directamente por URL a `/mascotas/crear` | Respuesta HTTP 403 (Acceso denegado). El rol de veterinario no tiene permisos de alta de mascotas (ACC-01). | [ ] Aprobado |
| **CP-10** | Consulta de mascotas por Veterinario | Sesión activa como `veterinario`. | 1. Navegar a `/mascotas` | Puede ver la lista de pacientes registrados (para citas y consultas), pero sin el botón "+ Registrar Mascota". | [ ] Aprobado |
| **CP-11** | Acceso anónimo sin sesión iniciada | Sin sesión activa. | 1. Navegar a `/mascotas` o `/mascotas/crear` | Redirección automática a `/login` (ACC-01). | [ ] Aprobado |
| **CP-12** | Protección CSRF en POST `/mascotas` | Sesión activa. | 1. Modificar o vaciar el campo oculto `csrf_token`<br>2. Enviar petición POST a `/mascotas` | Respuesta HTTP 419 (Token inválido) garantizando la protección contra ataques de falsificación entre sitios (SEC-01). | [ ] Aprobado |

---

## 3. Criterios de Aceptación Verificados
- **DAT-01:** Toda mascota queda vinculada a un propietario activo existente mediante clave foránea en MySQL y validación en servicio.
- **ACC-01:** Rutas protegidas y autorización de creación exclusiva para Administrador.
- **SEC-01:** Sentencias preparadas PDO, tokens CSRF por formulario y escape HTML.
- **BAJA-01:** La tabla y entidades contemplan la columna `activo` para trazabilidad e integridad histórica.
- **UI-01:** Interfaz semántica, consistente con la pantalla de login, responsiva y en español sin dependencias externas.
