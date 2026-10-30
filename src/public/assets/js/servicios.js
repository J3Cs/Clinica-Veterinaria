/**
 * JavaScript nativo — Catálogo de Servicios (US-10, T-069, TEC-01, UI-01)
 * Homologado con la barra contextual e interacción por filas de Horarios, Propietarios y Mascotas.
 */

document.addEventListener('DOMContentLoaded', () => {
    // ── 1. Selección interactiva de filas en el listado de Servicios ── //
    const serviceRows       = document.querySelectorAll('.service-row');
    const actionBar         = document.getElementById('selection-action-bar');
    const summarySpan       = document.getElementById('selection-summary');
    const policyHintSpan    = document.getElementById('selection-policy-hint');
    const btnEdit           = document.getElementById('btn-edit-selected');
    const btnAgendar        = document.getElementById('btn-agendar-servicio-selected');
    const btnDeactivate     = document.getElementById('btn-deactivate-selected');
    const btnActivate       = document.getElementById('btn-activate-selected');
    const btnCancel         = document.getElementById('btn-cancel-selected');
    const formEstado        = document.getElementById('form-estado-seleccion');
    const inputEstadoId     = document.getElementById('input-estado-id');
    const inputEstadoAccion = document.getElementById('input-estado-accion');

    let selectedServiceRow = null;

    function deselectServiceRows() {
        serviceRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });
        selectedServiceRow = null;
        if (actionBar) actionBar.style.display = 'none';
        if (inputEstadoId) inputEstadoId.value = '';
        if (inputEstadoAccion) inputEstadoAccion.value = '';
    }

    function selectServiceRow(row) {
        if (selectedServiceRow === row) {
            deselectServiceRows();
            return;
        }

        serviceRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });

        row.classList.add('is-selected');
        row.setAttribute('aria-pressed', 'true');
        selectedServiceRow = row;

        const id      = row.getAttribute('data-id') || '';
        const resumen = row.getAttribute('data-resumen') || 'Servicio seleccionado';
        const activo  = row.getAttribute('data-activo') === '1';

        if (inputEstadoId) inputEstadoId.value = id;
        if (summarySpan) summarySpan.textContent = resumen;

        if (policyHintSpan) {
            policyHintSpan.textContent = activo
                ? 'Habilitado para nuevas citas'
                : 'Inactivo (no se ofrece en agendamiento)';
        }

        if (btnAgendar) {
            btnAgendar.style.display = activo ? 'inline-block' : 'none';
        }
        if (btnDeactivate) {
            btnDeactivate.style.display = activo ? 'inline-block' : 'none';
        }
        if (btnActivate) {
            btnActivate.style.display = !activo ? 'inline-block' : 'none';
        }

        if (actionBar) {
            actionBar.style.display = 'flex';
        }
    }

    serviceRows.forEach((row) => {
        row.addEventListener('click', function () {
            selectServiceRow(this);
        });

        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectServiceRow(this);
            }
        });
    });

    if (btnCancel) {
        btnCancel.addEventListener('click', () => deselectServiceRows());
    }

    if (btnEdit) {
        btnEdit.addEventListener('click', () => {
            if (!selectedServiceRow) return;
            const id = selectedServiceRow.getAttribute('data-id');
            if (id) window.location.href = '/servicios/editar?id=' + encodeURIComponent(id);
        });
    }

    if (btnAgendar) {
        btnAgendar.addEventListener('click', () => {
            if (!selectedServiceRow) return;
            const id = selectedServiceRow.getAttribute('data-id');
            if (id) window.location.href = '/citas?id_servicio=' + encodeURIComponent(id) + '#agendar';
        });
    }

    if (btnDeactivate) {
        btnDeactivate.addEventListener('click', () => {
            if (!selectedServiceRow || !formEstado) return;
            const nombre = selectedServiceRow.getAttribute('data-nombre') || 'este servicio';
            const msg = `¿Deseas desactivar el servicio «${nombre}»?\n\nDejará de ofrecerse en nuevos agendamientos, pero sus citas históricas se conservarán.`;
            if (window.confirm(msg)) {
                if (inputEstadoAccion) inputEstadoAccion.value = 'desactivar';
                formEstado.submit();
            }
        });
    }

    if (btnActivate) {
        btnActivate.addEventListener('click', () => {
            if (!selectedServiceRow || !formEstado) return;
            const nombre = selectedServiceRow.getAttribute('data-nombre') || 'este servicio';
            if (window.confirm(`¿Deseas reactivar el servicio «${nombre}» para que vuelva a estar disponible al agendar citas?`)) {
                if (inputEstadoAccion) inputEstadoAccion.value = 'activar';
                formEstado.submit();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && selectedServiceRow) {
            deselectServiceRows();
        }
    });

    // ── 2. Filtro instantáneo en tabla de Servicios ────────────────── //
    const searchInput   = document.getElementById('service-search-input');
    const noMatchesRow  = document.getElementById('services-no-matches-row');
    const searchCountEl = document.getElementById('service-search-count');

    if (searchInput && serviceRows.length > 0) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            let visibles = 0;

            serviceRows.forEach((row) => {
                const nombre = (row.getAttribute('data-nombre') || '').toLowerCase();
                const desc   = (row.getAttribute('data-descripcion') || '').toLowerCase();
                const dur    = (row.getAttribute('data-duracion') || '').toLowerCase();

                const coincide = !q || nombre.includes(q) || desc.includes(q) || dur.includes(q);
                row.style.display = coincide ? '' : 'none';

                if (coincide) {
                    visibles++;
                } else if (selectedServiceRow === row) {
                    deselectServiceRows();
                }
            });

            if (noMatchesRow) {
                noMatchesRow.style.display = visibles === 0 ? '' : 'none';
            }
            if (searchCountEl) {
                searchCountEl.innerHTML = `Mostrando <strong>${visibles}</strong> servicio(s)`;
            }
        });
    }

    // ── 3. Validación en formulario de Alta / Edición ──────────────── //
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

    // ── 4. Confirmación al desactivar/activar desde vista de edición ── //
    const formsEstado = document.querySelectorAll('.form-estado-servicio');
    formsEstado.forEach(formEstadoEl => {
        formEstadoEl.addEventListener('submit', (e) => {
            const nombre = formEstadoEl.dataset.nombre || 'este servicio';
            const accion = formEstadoEl.dataset.accion || 'cambiar estado';
            const msg = accion === 'desactivar'
                ? `¿Deseas desactivar el servicio «${nombre}»?\n\nDejará de ofrecerse en nuevos agendamientos, pero sus citas históricas se conservarán.`
                : `¿Deseas reactivar el servicio «${nombre}»?`;

            if (!window.confirm(msg)) {
                e.preventDefault();
                return;
            }

            const btn = formEstadoEl.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
            }
        });
    });
});
