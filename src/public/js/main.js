/**
 * JavaScript nativo — Clínica Veterinaria (TEC-01)
 * Validación visual interactiva del lado del cliente (T-014).
 *
 * REGLA: La validación visual en JS mejora la experiencia de usuario
 * pero NUNCA sustituye la validación estricta del lado del servidor en PHP.
 */

document.addEventListener('DOMContentLoaded', () => {
    // ── 1. Validación interactiva del Formulario de Inicio de Sesión ── //
    const formLogin = document.getElementById('form-login');

    if (formLogin) {
        const inputIdentifier = document.getElementById('identifier');
        const inputPassword   = document.getElementById('contrasena');
        const errorContainer  = document.getElementById('js-error-container');
        const errorText       = document.getElementById('js-error-text');
        const serverErrorMsg  = document.getElementById('server-error-msg');

        function showError(message, focusElement) {
            if (errorContainer && errorText) {
                errorText.textContent = message;
                errorContainer.style.display = 'flex';
            }
            if (focusElement) {
                focusElement.classList.add('is-invalid');
                focusElement.setAttribute('aria-invalid', 'true');
                focusElement.focus();
            }
        }

        function clearError(inputElement) {
            if (inputElement) {
                inputElement.classList.remove('is-invalid');
                inputElement.removeAttribute('aria-invalid');
            }
            // Ocultar mensaje JS si ambos campos tienen texto
            if (inputIdentifier && inputPassword) {
                if (inputIdentifier.value.trim() !== '' && inputPassword.value !== '') {
                    if (errorContainer) {
                        errorContainer.style.display = 'none';
                    }
                }
            }
        }

        // Limpiar errores visuales mientras el usuario escribe
        [inputIdentifier, inputPassword].forEach(input => {
            if (input) {
                input.addEventListener('input', () => {
                    clearError(input);
                    // Ocultar el mensaje del servidor cuando se empieza a corregir
                    if (serverErrorMsg) {
                        serverErrorMsg.style.display = 'none';
                    }
                });
            }
        });

        // Interceptar submit para comprobación previa
        formLogin.addEventListener('submit', (e) => {
            const identifierVal = inputIdentifier ? inputIdentifier.value.trim() : '';
            const passwordVal   = inputPassword ? inputPassword.value : '';

            if (identifierVal === '' && passwordVal === '') {
                e.preventDefault();
                showError('Por favor ingresa tu usuario/correo y contraseña.', inputIdentifier);
                if (inputPassword) {
                    inputPassword.classList.add('is-invalid');
                    inputPassword.setAttribute('aria-invalid', 'true');
                }
                return;
            }

            if (identifierVal === '') {
                e.preventDefault();
                showError('Por favor ingresa tu usuario o correo electrónico.', inputIdentifier);
                return;
            }

            if (passwordVal === '') {
                e.preventDefault();
                showError('Por favor ingresa tu contraseña.', inputPassword);
                return;
            }

            // Ambos campos tienen datos: permitir envío a PHP
        });
    }

    // ── 2. Manejo de botón de prueba en página home (si existe) ──────── //
    const btnTest = document.getElementById('btnTest');
    if (btnTest) {
        btnTest.addEventListener('click', () => {
            alert('¡JavaScript operativo al 100%!');
        });
    }
});
