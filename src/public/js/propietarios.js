/**
 * JavaScript nativo — Gestión de Propietarios (T-021, TEC-01, UNI-01)
 *
 * REGLA: Esta validación y máscara en el cliente funciona como asistencia visual para el usuario.
 * El servidor en PHP vuelve a normalizar y validar estrictamente todos los datos (UNI-01, SEC-01).
 */

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-propietario');
    if (!form) return;

    const inputNombre    = document.getElementById('nombre_completo');
    const inputDui       = document.getElementById('dui');
    const inputTelefono  = document.getElementById('telefono');
    const inputCorreo    = document.getElementById('correo');
    const errorContainer = document.getElementById('js-error-container');
    const errorText      = document.getElementById('js-error-text');
    const serverErrorMsg = document.getElementById('server-error-msg');

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

    // ── 1. Máscara automática de DUI (UNI-01) ──────────────────────── //
    if (inputDui) {
        inputDui.addEventListener('input', (e) => {
            let val = e.target.value.replace(/\D/g, ''); // Solo dígitos
            if (val.length > 9) {
                val = val.slice(0, 9);
            }

            if (val.length > 8) {
                e.target.value = val.slice(0, 8) + '-' + val.slice(8);
            } else {
                e.target.value = val;
            }

            clearError(inputDui);
            if (serverErrorMsg) serverErrorMsg.style.display = 'none';
        });
    }

    // Limpiar errores visuales al tipear en otros campos
    [inputNombre, inputTelefono, inputCorreo].forEach(input => {
        if (input) {
            input.addEventListener('input', () => {
                clearError(input);
                if (serverErrorMsg) serverErrorMsg.style.display = 'none';
            });
        }
    });

    // ── 2. Validación en Submit ────────────────────────────────────── //
    form.addEventListener('submit', (e) => {
        // Validación Nombre
        const nombreVal = inputNombre ? inputNombre.value.trim() : '';
        if (nombreVal === '') {
            e.preventDefault();
            showError('El nombre completo del propietario es obligatorio.', inputNombre);
            return;
        }
        if (nombreVal.length < 3) {
            e.preventDefault();
            showError('El nombre completo debe tener al menos 3 caracteres.', inputNombre);
            return;
        }

        // Validación DUI (formato salvadoreño ########-#)
        const duiVal = inputDui ? inputDui.value.trim() : '';
        const duiRegex = /^\d{8}-\d{1}$/;
        if (duiVal === '') {
            e.preventDefault();
            showError('El número de DUI es obligatorio.', inputDui);
            return;
        }
        if (!duiRegex.test(duiVal)) {
            e.preventDefault();
            showError('El DUI debe tener 8 dígitos, un guion y 1 dígito de control (ej. 12345678-9).', inputDui);
            return;
        }

        // Validación Correo (opcional)
        const correoVal = inputCorreo ? inputCorreo.value.trim() : '';
        if (correoVal !== '') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(correoVal)) {
                e.preventDefault();
                showError('El correo electrónico no tiene un formato válido.', inputCorreo);
                return;
            }
        }

        // Todo válido en cliente, permite envío hacia PHP
    });
});

