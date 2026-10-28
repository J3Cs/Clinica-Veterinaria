/**
 * JavaScript nativo — Catálogo de Servicios (US-10, T-069, TEC-01, UI-01)
 *
 * REGLA: Esta validación en cliente funciona como mejora progresiva.
 * La validación definitiva ocurre siempre en CatalogoServiciosService (PHP).
 */

document.addEventListener('DOMContentLoaded', () => {
    // ── 1. Validación en formulario de Alta / Edición ──────────────── //
    const form = document.getElementById('form-servicio');
    if (form) {
        const inputNombre    = document.getElementById('nombre_servicio');
        const inputDuracion  = document.getElementById('duracion_estimada_min');
        const inputDesc      = document.getElementById('descripcion');
        const errorContainer = document.getElementById('js-error-container');
        const errorText      = document.getElementById('js-error-text');
        const serverErrorMsg = document.getElementById('server-error-msg');
        const btnSubmit      = document.getElementById('btn-submit-servicio');

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

        [inputNombre, inputDuracion, inputDesc].forEach(el => {
            if (el) {
                el.addEventListener('input', () => {
                    clearError(el);
                    if (serverErrorMsg) serverErrorMsg.style.display = 'none';
                });
            }
        });

        form.addEventListener('submit', (e) => {
            const nombreVal = inputNombre ? inputNombre.value.trim() : '';
            if (nombreVal === '') {
                e.preventDefault();
                showError('El nombre del servicio es obligatorio.', inputNombre);
                return;
            }
            if (nombreVal.length < 3) {
                e.preventDefault();
                showError('El nombre debe tener al menos 3 caracteres.', inputNombre);
                return;
            }
            if (nombreVal.length > 100) {
                e.preventDefault();
                showError('El nombre no puede superar los 100 caracteres.', inputNombre);
                return;
            }

            const durVal = inputDuracion ? inputDuracion.value.trim() : '';
            if (durVal === '' || !/^\d+$/.test(durVal)) {
                e.preventDefault();
                showError('La duración estimada es obligatoria y debe ser un número entero positivo.', inputDuracion);
                return;
            }
            const durNum = parseInt(durVal, 10);
            if (durNum < 1) {
                e.preventDefault();
                showError('La duración debe ser mayor o igual a 1 minuto(s).', inputDuracion);
                return;
            }

            const descVal = inputDesc ? inputDesc.value.trim() : '';
            if (descVal.length > 500) {
                e.preventDefault();
                showError('La descripción no puede superar los 500 caracteres.', inputDesc);
                return;
            }

            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.textContent = 'Guardando…';
            }
        });
    }

    // ── 2. Confirmación al desactivar/activar desde el listado ──────── //
    const formsEstado = document.querySelectorAll('.form-estado-servicio');
    formsEstado.forEach(formEstado => {
        formEstado.addEventListener('submit', (e) => {
            const nombre = formEstado.dataset.nombre || 'este servicio';
            const accion = formEstado.dataset.accion || 'cambiar estado';
            const msg = accion === 'desactivar'
                ? `¿Deseas desactivar el servicio «${nombre}»?\n\nDejará de ofrecerse en nuevos agendamientos, pero sus citas históricas se conservarán.`
                : `¿Deseas reactivar el servicio «${nombre}»?`;

            if (!window.confirm(msg)) {
                e.preventDefault();
                return;
            }

            const btn = formEstado.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
            }
        });
    });
});
