/**
 * Validación Interactiva, Selección de Filas y Confirmaciones — Módulo de Mascotas
 * US-03 (T-020), US-12 (T-080) — Cumple TEC-01, DAT-01, BAJA-01, UI-01
 */
document.addEventListener('DOMContentLoaded', () => {
    // ── 1. Selección de Fila y Barra Contextual en Directorio de Mascotas ──
    const petRows              = document.querySelectorAll('.pet-row');
    const actionBar            = document.getElementById('selection-action-bar');
    const summarySpan          = document.getElementById('selection-summary');
    const policyHintSpan       = document.getElementById('selection-policy-hint');
    const btnEdit              = document.getElementById('btn-edit-selected');
    const btnVerPropietario    = document.getElementById('btn-ver-propietario-selected');
    const btnBajaLogica        = document.getElementById('btn-baja-logica-selected');
    const btnDelete            = document.getElementById('btn-delete-selected');
    const btnReactivate        = document.getElementById('btn-reactivate-selected');
    const btnFiltrarProp       = document.getElementById('btn-filtrar-prop-selected');
    const btnIrAgenda          = document.getElementById('btn-ir-agenda-selected');
    const btnCancel            = document.getElementById('btn-cancel-selected');
    const formBaja             = document.getElementById('form-baja-seleccion');
    const inputBajaId          = document.getElementById('input-baja-id');
    const inputBajaAccion      = document.getElementById('input-baja-accion');

    let selectedPetRow = null;

    function deselectPetRows() {
        petRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });
        selectedPetRow = null;
        if (actionBar) actionBar.style.display = 'none';
        if (inputBajaId) inputBajaId.value = '';
    }

    function selectPetRow(row) {
        if (selectedPetRow === row) {
            deselectPetRows();
            return;
        }

        petRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });

        row.classList.add('is-selected');
        row.setAttribute('aria-pressed', 'true');
        selectedPetRow = row;

        const id            = row.getAttribute('data-id') || '';
        const resumen       = row.getAttribute('data-resumen') || 'Mascota seleccionada';
        const activa        = row.getAttribute('data-activo') === '1';
        const tieneDep      = row.getAttribute('data-dependencias') === '1';
        const numCitas      = parseInt(row.getAttribute('data-citas') || '0', 10);
        const numConsultas  = parseInt(row.getAttribute('data-consultas') || '0', 10);
        const numHistorial  = parseInt(row.getAttribute('data-historial') || '0', 10);

        if (inputBajaId) inputBajaId.value = id;
        if (summarySpan) summarySpan.textContent = resumen;

        if (policyHintSpan) {
            if (!activa) {
                policyHintSpan.textContent = 'Inactiva (expediente conservado)';
            } else if (tieneDep) {
                policyHintSpan.textContent = `${numCitas} cita(s), ${numConsultas} consulta(s), ${numHistorial} antecedente(s)`;
            } else {
                policyHintSpan.textContent = 'Sin citas ni consultas registradas';
            }
        }

        if (btnBajaLogica) {
            btnBajaLogica.style.display = (activa && tieneDep) ? 'inline-block' : 'none';
        }
        if (btnDelete) {
            btnDelete.style.display = (activa && !tieneDep) ? 'inline-block' : 'none';
        }
        if (btnReactivate) {
            btnReactivate.style.display = (!activa) ? 'inline-block' : 'none';
        }

        if (actionBar) {
            actionBar.style.display = 'flex';
        }
    }

    petRows.forEach((row) => {
        row.addEventListener('click', function () {
            selectPetRow(this);
        });

        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectPetRow(this);
            }
        });
    });

    if (btnCancel) {
        btnCancel.addEventListener('click', () => deselectPetRows());
    }

    if (btnEdit) {
        btnEdit.addEventListener('click', () => {
            if (!selectedPetRow) return;
            const id = selectedPetRow.getAttribute('data-id');
            if (id) window.location.href = '/mascotas/editar?id=' + encodeURIComponent(id);
        });
    }

    if (btnVerPropietario) {
        btnVerPropietario.addEventListener('click', () => {
            if (!selectedPetRow) return;
            const idProp = selectedPetRow.getAttribute('data-id-propietario');
            if (idProp) window.location.href = '/propietarios/editar?id=' + encodeURIComponent(idProp);
        });
    }

    if (btnFiltrarProp) {
        btnFiltrarProp.addEventListener('click', () => {
            if (!selectedPetRow) return;
            const idProp = selectedPetRow.getAttribute('data-id-propietario');
            if (idProp) window.location.href = '/mascotas?id_propietario=' + encodeURIComponent(idProp);
        });
    }

    if (btnIrAgenda) {
        btnIrAgenda.addEventListener('click', () => {
            window.location.href = '/veterinario/agenda';
        });
    }

    if (btnBajaLogica) {
        btnBajaLogica.addEventListener('click', () => {
            if (!selectedPetRow || !formBaja) return;
            const nombre    = selectedPetRow.getAttribute('data-nombre') || 'esta mascota';
            const citas     = selectedPetRow.getAttribute('data-citas') || '0';
            const consultas = selectedPetRow.getAttribute('data-consultas') || '0';
            const historial = selectedPetRow.getAttribute('data-historial') || '0';
            const msg = `¿Deseas desactivar a la mascota "${nombre}"?\n\n` +
                        `Tiene ${citas} cita(s), ${consultas} consulta(s) y ${historial} antecedente(s) registrados.\n` +
                        `La mascota quedará inactiva, pero su historial médico se conservará.`;
            if (window.confirm(msg)) {
                if (inputBajaAccion) inputBajaAccion.value = 'baja';
                formBaja.submit();
            }
        });
    }

    if (btnDelete) {
        btnDelete.addEventListener('click', () => {
            if (!selectedPetRow || !formBaja) return;
            const nombre = selectedPetRow.getAttribute('data-nombre') || 'esta mascota';
            const msg = `¿Deseas eliminar el registro de la mascota "${nombre}"?\n\n` +
                        `Esta acción no se puede deshacer.`;
            if (window.confirm(msg)) {
                if (inputBajaAccion) inputBajaAccion.value = 'baja';
                formBaja.submit();
            }
        });
    }

    if (btnReactivate) {
        btnReactivate.addEventListener('click', () => {
            if (!selectedPetRow || !formBaja) return;
            const nombre = selectedPetRow.getAttribute('data-nombre') || 'esta mascota';
            if (window.confirm(`¿Deseas reactivar a la mascota "${nombre}"?`)) {
                if (inputBajaAccion) inputBajaAccion.value = 'reactivar';
                formBaja.submit();
            }
        });
    }

    // ── 2. Selección de Citas Asociadas dentro de Expediente de Mascota ──
    const petCitaRows       = document.querySelectorAll('.pet-cita-row');
    const barCitas          = document.getElementById('selection-action-bar-citas');
    const summaryCita       = document.getElementById('selection-summary-cita');
    const btnIrModuloCitas  = document.getElementById('btn-ir-modulo-citas');
    const btnCancelarCita   = document.getElementById('btn-cancelar-cita-selected');
    const btnDeselectCita   = document.getElementById('btn-deselect-cita');
    let selectedCitaRow     = null;

    function deselectCitaRows() {
        petCitaRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });
        selectedCitaRow = null;
        if (barCitas) barCitas.style.display = 'none';
    }

    petCitaRows.forEach((row) => {
        row.addEventListener('click', function () {
            if (selectedCitaRow === this) {
                deselectCitaRows();
                return;
            }
            petCitaRows.forEach((r) => {
                r.classList.remove('is-selected');
                r.setAttribute('aria-pressed', 'false');
            });
            this.classList.add('is-selected');
            this.setAttribute('aria-pressed', 'true');
            selectedCitaRow = this;
            if (summaryCita) {
                summaryCita.textContent = this.getAttribute('data-resumen') || 'Cita seleccionada';
            }
            if (btnCancelarCita) {
                const esProgramada = this.getAttribute('data-estado') === 'Programada';
                btnCancelarCita.style.display = esProgramada ? 'inline-block' : 'none';
            }
            if (barCitas) barCitas.style.display = 'flex';
        });
    });

    if (btnDeselectCita) {
        btnDeselectCita.addEventListener('click', () => deselectCitaRows());
    }

    if (btnIrModuloCitas) {
        btnIrModuloCitas.addEventListener('click', () => {
            window.location.href = '/citas';
        });
    }

    if (btnCancelarCita) {
        btnCancelarCita.addEventListener('click', () => {
            if (!selectedCitaRow) return;
            const idCita = selectedCitaRow.getAttribute('data-id');
            if (idCita) window.location.href = '/citas/cancelar?id=' + encodeURIComponent(idCita);
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (selectedPetRow) deselectPetRows();
            if (selectedCitaRow) deselectCitaRows();
        }
    });

    // ── 3. Confirmaciones nativas en vista Edición ──
    const formsBaja = document.querySelectorAll('.form-baja-mascota');
    formsBaja.forEach((formEl) => {
        formEl.addEventListener('submit', (e) => {
            const nombre      = formEl.getAttribute('data-nombre') || 'esta mascota';
            const tieneDep    = formEl.getAttribute('data-dependencias') === '1';
            const citas       = formEl.getAttribute('data-citas') || '0';
            const consultas   = formEl.getAttribute('data-consultas') || '0';
            const historial   = formEl.getAttribute('data-historial') || '0';
            const esReactivar = formEl.getAttribute('data-accion') === 'reactivar';

            let mensaje = '';
            if (esReactivar) {
                mensaje = `¿Deseas reactivar a la mascota "${nombre}"?`;
            } else if (tieneDep) {
                mensaje = `¿Deseas desactivar a la mascota "${nombre}"?\n\n` +
                          `Tiene ${citas} cita(s), ${consultas} consulta(s) y ${historial} antecedente(s) registrados.\n` +
                          `La mascota quedará inactiva, pero su historial médico se conservará.`;
            } else {
                mensaje = `¿Deseas eliminar el registro de la mascota "${nombre}"?\n\n` +
                          `Esta acción no se puede deshacer.`;
            }

            if (!window.confirm(mensaje)) {
                e.preventDefault();
            }
        });
    });

    // ── 4. Filtro instantáneo en Directorio de Mascotas ────────────
    const searchInput  = document.getElementById('pet-search-input');
    const countLabel   = document.getElementById('pet-search-count');
    const noMatchesRow = document.getElementById('pets-no-matches-row');

    if (searchInput && petRows.length > 0) {
        const total = petRows.length;

        function filterPets() {
            const query       = searchInput.value.trim().toLowerCase();
            const queryDigits = query.replace(/\D/g, '');
            let visibleCount  = 0;

            petRows.forEach((row) => {
                const nombre      = (row.dataset.nombre || '').toLowerCase();
                const propietario = (row.dataset.propietario || '').toLowerCase();
                const dui         = (row.dataset.dui || '').toLowerCase();
                const duiDigits   = dui.replace(/\D/g, '');
                const especie     = (row.dataset.especie || '').toLowerCase();
                const raza        = (row.dataset.raza || '').toLowerCase();

                const matchesText =
                    !query ||
                    nombre.includes(query) ||
                    propietario.includes(query) ||
                    dui.includes(query) ||
                    especie.includes(query) ||
                    raza.includes(query) ||
                    (queryDigits.length >= 4 && duiDigits.includes(queryDigits));

                if (matchesText) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    if (selectedPetRow === row) {
                        deselectPetRows();
                    }
                }
            });

            if (countLabel) {
                countLabel.innerHTML = query
                    ? `Mostrando <strong>${visibleCount}</strong> de <strong>${total}</strong> mascota(s)`
                    : `Mostrando <strong>${total}</strong> mascota(s)`;
            }

            if (noMatchesRow) {
                noMatchesRow.style.display = visibleCount === 0 ? '' : 'none';
            }
        }

        searchInput.addEventListener('input', filterPets);
    }

    // ── 5. Validación de Formulario de Creación / Edición de Mascota ──
    const form = document.getElementById('form-mascota');
    if (!form) return;

    const propietarioSelect  = document.getElementById('id_propietario');
    const nombreInput        = document.getElementById('nombre');
    const especieInput       = document.getElementById('especie');
    const razaInput          = document.getElementById('raza');
    const sexoSelect         = document.getElementById('sexo');
    const fechaNacInput      = document.getElementById('fecha_nacimiento');
    const submitBtn          = document.getElementById('btn-submit-mascota');
    const jsErrorBox         = document.getElementById('js-error-container');
    const jsErrorText        = document.getElementById('js-error-text');
    const serverErrorBox     = document.getElementById('server-error-msg');

    function showJsError(message) {
        if (serverErrorBox) {
            serverErrorBox.style.display = 'none';
        }
        if (jsErrorText && jsErrorBox) {
            jsErrorText.textContent = message;
            jsErrorBox.style.display = 'flex';
        }
    }

    function hideJsError() {
        if (jsErrorBox && jsErrorBox.style.display !== 'none') {
            jsErrorBox.style.display = 'none';
            if (jsErrorText) jsErrorText.textContent = '';
        }
    }

    [propietarioSelect, nombreInput, especieInput, razaInput, sexoSelect, fechaNacInput].forEach((el) => {
        if (el) {
            el.addEventListener('input', () => {
                hideJsError();
                el.classList.remove('is-invalid');
            });
            el.addEventListener('change', () => {
                hideJsError();
                el.classList.remove('is-invalid');
            });
        }
    });

    form.addEventListener('submit', (e) => {
        hideJsError();

        const idPropietario = propietarioSelect ? parseInt(propietarioSelect.value, 10) : 0;
        const nombre        = nombreInput ? nombreInput.value.trim() : '';
        const especie       = especieInput ? especieInput.value.trim() : '';
        const raza          = razaInput ? razaInput.value.trim() : '';
        const sexo          = sexoSelect ? sexoSelect.value : '';
        const fechaNac      = fechaNacInput ? fechaNacInput.value : '';

        if (!idPropietario || idPropietario <= 0) {
            e.preventDefault();
            showJsError('Debes seleccionar un propietario responsable existente para la mascota.');
            if (propietarioSelect) {
                propietarioSelect.classList.add('is-invalid');
                propietarioSelect.focus();
            }
            return;
        }

        if (!nombre || nombre.length < 2) {
            e.preventDefault();
            showJsError('Por favor ingresa el nombre de la mascota (mínimo 2 caracteres).');
            if (nombreInput) {
                nombreInput.classList.add('is-invalid');
                nombreInput.focus();
            }
            return;
        }

        if (!especie) {
            e.preventDefault();
            showJsError('La especie de la mascota es obligatoria (ej. Canino, Felino).');
            if (especieInput) {
                especieInput.classList.add('is-invalid');
                especieInput.focus();
            }
            return;
        }

        if (!raza) {
            e.preventDefault();
            showJsError('La raza de la mascota es obligatoria (ej. Mestizo, Labrador).');
            if (razaInput) {
                razaInput.classList.add('is-invalid');
                razaInput.focus();
            }
            return;
        }

        if (sexo !== 'Macho' && sexo !== 'Hembra') {
            e.preventDefault();
            showJsError('Debes seleccionar el sexo de la mascota ("Macho" o "Hembra").');
            if (sexoSelect) {
                sexoSelect.classList.add('is-invalid');
                sexoSelect.focus();
            }
            return;
        }

        if (fechaNac !== '') {
            const today = new Date().toISOString().split('T')[0];
            if (fechaNac > today) {
                e.preventDefault();
                showJsError('La fecha de nacimiento no puede ser una fecha futura.');
                if (fechaNacInput) {
                    fechaNacInput.classList.add('is-invalid');
                    fechaNacInput.focus();
                }
                return;
            }
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Guardando...';
        }
    });
});
