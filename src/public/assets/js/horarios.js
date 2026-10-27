/**
 * Horarios de Atención — Validación visual e interactiva nativa (T-075, TEC-01, UI-01)
 */
document.addEventListener('DOMContentLoaded', function () {
    const formHorario = document.getElementById('form-horario');
    const inputInicio = document.getElementById('hora_inicio');
    const inputFin    = document.getElementById('hora_fin');
    const btnSubmit   = document.getElementById('btn-submit');
    const clientError = document.getElementById('client-error');

    function showError(msg) {
        if (clientError) {
            clientError.textContent = msg;
            clientError.style.display = 'block';
            clientError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function clearError() {
        if (clientError) {
            clientError.textContent = '';
            clientError.style.display = 'none';
        }
        if (inputInicio) inputInicio.classList.remove('is-invalid');
        if (inputFin) inputFin.classList.remove('is-invalid');
    }

    function validarHoras() {
        if (!inputInicio || !inputFin) return true;
        clearError();

        const vInicio = inputInicio.value.trim();
        const vFin    = inputFin.value.trim();

        if (!vInicio || !vFin) {
            return true; // Validado por atributo required
        }

        if (vInicio >= vFin) {
            showError('La hora de inicio debe ser anterior a la hora de fin.');
            inputInicio.classList.add('is-invalid');
            inputFin.classList.add('is-invalid');
            return false;
        }

        // Convertir a minutos para validar mínimo 15 min
        const pIni = vInicio.split(':');
        const pFin = vFin.split(':');
        const mIni = parseInt(pIni[0], 10) * 60 + parseInt(pIni[1], 10);
        const mFin = parseInt(pFin[0], 10) * 60 + parseInt(pFin[1], 10);

        if (mFin - mIni < 15) {
            showError('La franja de atención debe ser de al menos 15 minutos.');
            inputFin.classList.add('is-invalid');
            return false;
        }

        return true;
    }

    if (inputInicio && inputFin) {
        inputInicio.addEventListener('change', validarHoras);
        inputFin.addEventListener('change', validarHoras);
    }

    if (formHorario) {
        formHorario.addEventListener('submit', function (e) {
            if (!validarHoras()) {
                e.preventDefault();
                return false;
            }

            // Evitar envíos múltiples
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.textContent = 'Guardando...';
            }
        });
    }
});
