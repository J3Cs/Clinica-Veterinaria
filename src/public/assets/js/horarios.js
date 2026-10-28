/**
 * Horarios de Atención — Validación visual e interactiva nativa (T-075, TEC-01, UI-01)
 * Incluye mecanismo de selección de fila por clic para acciones contextuales (Eliminar/Editar).
 */
document.addEventListener('DOMContentLoaded', function () {
    // ────────────────────────────────────────────────────────────── //
    // 1. Manejo de Selección de Filas en la Tabla de Horarios
    // ────────────────────────────────────────────────────────────── //
    const rows = document.querySelectorAll('.horario-row');
    const actionBar = document.getElementById('selection-action-bar');
    const countSpan = document.getElementById('selection-count');
    const textSpan = document.getElementById('selection-text');
    const btnDelete = document.getElementById('btn-delete-selected');
    const btnEdit = document.getElementById('btn-edit-selected');
    const btnCancel = document.getElementById('btn-cancel-selected');
    const formDelete = document.getElementById('form-eliminar-seleccion');
    const inputDeleteId = document.getElementById('input-delete-id');

    let selectedRow = null;

    function deselectAll() {
        rows.forEach(function (r) {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });
        selectedRow = null;
        if (actionBar) actionBar.style.display = 'none';
        if (inputDeleteId) inputDeleteId.value = '';
    }

    function selectRow(row) {
        if (selectedRow === row) {
            // Si ya estaba seleccionada, deseleccionar
            deselectAll();
            return;
        }

        // Selección única
        rows.forEach(function (r) {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });

        row.classList.add('is-selected');
        row.setAttribute('aria-pressed', 'true');
        selectedRow = row;

        const id = row.getAttribute('data-id');
        if (inputDeleteId) inputDeleteId.value = id;

        if (actionBar) {
            if (countSpan) countSpan.textContent = '1';
            if (textSpan) textSpan.textContent = 'franja seleccionada';
            actionBar.style.display = 'flex';
            if (btnEdit) btnEdit.style.display = 'inline-flex';
        }
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function () {
            selectRow(this);
        });

        // Soporte de accesibilidad con teclado (Enter o Espacio)
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectRow(this);
            }
        });
    });

    if (btnCancel) {
        btnCancel.addEventListener('click', function () {
            deselectAll();
        });
    }

    if (btnEdit) {
        btnEdit.addEventListener('click', function () {
            if (!selectedRow) return;
            const id = selectedRow.getAttribute('data-id');
            if (id) {
                window.location.href = '/horarios/editar?id=' + encodeURIComponent(id);
            }
        });
    }

    if (btnDelete) {
        btnDelete.addEventListener('click', function () {
            if (!selectedRow || !formDelete) return;
            const confirmMsg = '¿Está seguro de eliminar esta franja horaria seleccionada?\nEl sistema verificará automáticamente que no afecte citas médicas ya programadas.';
            if (window.confirm(confirmMsg)) {
                formDelete.submit();
            }
        });
    }

    // Cerrar selección si se presiona la tecla Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && selectedRow) {
            deselectAll();
        }
    });

    // ────────────────────────────────────────────────────────────── //
    // 2. Validación de Formularios (Crear / Editar Horarios)
    // ────────────────────────────────────────────────────────────── //
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
