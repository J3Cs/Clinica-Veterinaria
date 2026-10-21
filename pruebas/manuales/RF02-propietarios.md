# Matriz y Guía de Pruebas Manuales: RF02 — Gestión de Propietarios (US-02)

Este documento detalla los casos de prueba para verificar el requisito funcional **RF02 (Acceso y datos básicos de Propietarios)** en el sistema de Clínica Veterinaria, asegurando el cumplimiento de las reglas **UNI-01**, **TEC-01**, **ARQ-01**, **ACC-01**, **SEC-01**, **BAJA-01** y **UI-01**.

---

## 1. Datos de Prueba Sugeridos

| Campo | Propietario A | Propietario B (Mismo Teléfono) | Propietario C (DUI sin guion) |
|---|---|---|---|
| **Nombre Completo** | Carlos Eduardo Méndez Santos | Ana Patricia Méndez Santos | Roberto Carlos Martínez |
| **DUI** | `01234567-8` | `08765432-1` | `098765432` (debe normalizar a `09876543-2`) |
| **Teléfono** | `2233-4455` | `2233-4455` (Compartido) | *Vacío* |
| **Correo** | `carlos.mendez@ejemplo.com` | *Vacío* | `roberto@ejemplo.com` |
| **Dirección** | San Salvador, Col. Escalón | San Salvador, Col. Escalón | Santa Ana |

---

## 2. Matriz de Casos de Prueba

| ID | Caso de Prueba | Precondición | Pasos de Ejecución | Resultado Esperado | Estado |
|---|---|---|---|---|---|
| **CP-01** | Registro exitoso de propietario completo | Sesión activa como `administrador`. | 1. Navegar a `/propietarios/crear`<br>2. Completar todos los campos con datos del Propietario A<br>3. Clic en "Guardar Propietario" | Redirección a `/propietarios`. Mensaje de éxito *"Propietario registrado exitosamente."* Registro visible en la tabla con `activo = 1`. | [ ] Aprobado |
| **CP-02** | Registro con campos opcionales vacíos | Sesión activa como `administrador`. | 1. Navegar a `/propietarios/crear`<br>2. Ingresar solo Nombre y DUI<br>3. Dejar teléfono, correo y dirección vacíos<br>4. Clic en "Guardar Propietario" | Registro exitoso. En la tabla se muestra `—` para los campos no suministrados. | [ ] Aprobado |
| **CP-03** | Intento sin ingresar DUI | Sesión activa como `administrador`. | 1. Ingresar nombre completo<br>2. Dejar el campo DUI vacío<br>3. Intentar guardar | Bloqueo en cliente por JS y validación en servidor: error *"El número de DUI es obligatorio."* Los datos ingresados no se pierden. | [ ] Aprobado |
| **CP-04** | Intento con formato de DUI inválido | Sesión activa como `administrador`. | 1. Ingresar DUI con menos dígitos (ej. `12345-6`) o letras<br>2. Intentar guardar | Mensaje de error claro: *"El formato del DUI no es válido. Debe ser ########-# (ej. 12345678-9)."* Campo resaltado con `.is-invalid`. | [ ] Aprobado |
| **CP-05** | Intento con **DUI duplicado** (`UNI-01`) | Propietario A con DUI `01234567-8` ya registrado. | 1. Intentar registrar un nuevo propietario con el mismo DUI `01234567-8`<br>2. Enviar formulario | Rechazo en la capa de servicio: *"El número de DUI ya se encuentra registrado en el sistema."* No se lanza error SQL crudo y se conservan los datos en pantalla. | [ ] Aprobado |
| **CP-06** | Formato de correo electrónico inválido | Sesión activa como `administrador`. | 1. Ingresar correo sin arroba o dominio (ej. `correoinvalido`)<br>2. Intentar guardar | Error: *"El correo electrónico no tiene un formato válido."* | [ ] Aprobado |
| **CP-07** | **Teléfono compartido** entre propietarios (`UNI-01`) | Propietario A tiene teléfono `2233-4455`. | 1. Registrar Propietario B con DUI diferente y el mismo teléfono `2233-4455` | Registro exitoso sin errores (el teléfono no es identificador único). | [ ] Aprobado |
| **CP-08** | Control de acceso: **Veterinario intenta acceder al alta** | Sesión activa con rol `veterinario`. | 1. Intentar ingresar directamente a `/propietarios/crear` en la URL | Respuesta HTTP 403 (Acceso denegado). El veterinario no puede dar de alta propietarios (ACC-01). | [ ] Aprobado |
| **CP-09** | Acceso a `/propietarios/crear` sin sesión | Sin sesión iniciada. | 1. Navegar a `/propietarios/crear` | Redirección inmediata a `/login` (ACC-01). | [ ] Aprobado |
| **CP-10** | Protección CSRF en POST `/propietarios` | Sesión activa. | 1. Manipular o remover el campo `csrf_token` en la petición POST | Respuesta HTTP 419 (Token inválido). La solicitud no se procesa (SEC-01). | [ ] Aprobado |
| **CP-11** | Máscara y navegación por teclado (UI-01) | Pantalla de `/propietarios/crear`. | 1. Navegar con tecla `Tab`<br>2. Al teclear en el campo DUI 9 dígitos seguidos, comprobar que el guion se inserta automáticamente<br>3. Comprobar usabilidad en resolución móvil | Enfoque ordenado y formateo dinámico correcto sin librerías externas. | [ ] Aprobado |

---

## 3. Criterios de Aceptación Globales
- `UNI-01`: Unicidad absoluta por DUI normalizado.
- `ACC-01`: Exclusivo para administradores.
- `SEC-01`: Consultas preparadas PDO, tokens CSRF y escape HTML en re-populación.
- `UI-01`: Español, responsive y sin dependencias externas.

