/**
 * JavaScript nativo — Registro y Validación de Mascotas (T-028, US-03, TEC-01, DAT-01, UI-01)
 *
 * Asistencia interactiva visual al usuario en cliente.
 * El servidor en PHP vuelve a verificar estrictamente todas las reglas de negocio (SEC-01).
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-mascota');
    if (!form) return;

    const selectPropietario    = document.getElementById('id_propietario');
    const inputNombre          = document.getElementById('nombre');
    const inputEspecie         = document.getElementById('especie');
    const inputRaza            = document.getElementById('raza');
    const selectSexo           = document.getElementById('sexo');
    const inputFechaNacimiento = document.getElementById('fecha_nacimiento');
    const errorContainer       = document.getElementById('js-error-container');
    const errorText            = document.getElementById('js-error-text');
    const serverErrorMsg       = document.getElementById('server-error-msg');

    function showError(message, element) {
        if (errorContainer && errorText) {
            errorText.textContent = message;
            errorContainer.style.display = 'flex';
        }
        if (element) {
            element.classList.add('is-invalid');
            element.setAttribute('aria-invalid', 'true');
            element.focus();
        }
    }

    function clearError(element) {
        if (element) {
            element.classList.remove('is-invalid');
            element.removeAttribute('aria-invalid');
        }
        if (errorContainer) {
            errorContainer.style.display = 'none';
        }
    }

    // Limpiar errores visuales al interactuar con los campos
    [selectPropietario, inputNombre, inputEspecie, inputRaza, selectSexo, inputFechaNacimiento].forEach(el => {
        if (!el) return;
        el.addEventListener('input', () => {
            clearError(el);
            if (serverErrorMsg) serverErrorMsg.style.display = 'none';
        });
        el.addEventListener('change', () => {
            clearError(el);
            if (serverErrorMsg) serverErrorMsg.style.display = 'none';
        });
    });

    // Validación interactiva antes de enviar
    form.addEventListener('submit', (e) => {
        // 1. Propietario Responsable (DAT-01)
        const idProp = selectPropietario ? selectPropietario.value.trim() : '';
        if (!idProp || parseInt(idProp, 10) <= 0) {
            e.preventDefault();
            showError('Debes seleccionar un propietario registrado para asociar la mascota (DAT-01).', selectPropietario);
            return;
        }

        // 2. Nombre de la Mascota
        const nombreVal = inputNombre ? inputNombre.value.trim() : '';
        if (nombreVal === '') {
            e.preventDefault();
            showError('El nombre de la mascota es obligatorio.', inputNombre);
            return;
        }
        if (nombreVal.length < 2) {
            e.preventDefault();
            showError('El nombre debe tener al menos 2 caracteres.', inputNombre);
            return;
        }

        // 3. Especie
        const especieVal = inputEspecie ? inputEspecie.value.trim() : '';
        if (especieVal === '') {
            e.preventDefault();
            showError('La especie de la mascota es obligatoria.', inputEspecie);
            return;
        }

        // 4. Raza
        const razaVal = inputRaza ? inputRaza.value.trim() : '';
        if (razaVal === '') {
            e.preventDefault();
            showError('La raza es obligatoria (indica "Mestizo / Criollo" si se desconoce).', inputRaza);
            return;
        }

        // 5. Sexo
        const sexoVal = selectSexo ? selectSexo.value.trim() : '';
        if (sexoVal !== 'Macho' && sexoVal !== 'Hembra') {
            e.preventDefault();
            showError('Debes seleccionar el sexo de la mascota ("Macho" o "Hembra").', selectSexo);
            return;
        }

        // 6. Fecha de Nacimiento (Opcional, no futura)
        if (inputFechaNacimiento && inputFechaNacimiento.value) {
            const fechaVal = inputFechaNacimiento.value;
            const hoy = new Date().toISOString().split('T')[0];
            if (fechaVal > hoy) {
                e.preventDefault();
                showError('La fecha de nacimiento no puede ser una fecha futura.', inputFechaNacimiento);
                return;
            }
        }

        // Todo en orden: permitir submit
    });
});
