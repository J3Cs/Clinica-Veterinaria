/**
 * Validación Interactiva, Selección de Filas y Confirmaciones — Módulo de Propietarios
 * US-03 (T-020), US-12 (T-080) — Cumple TEC-01, UNI-01, BAJA-01, UI-01
 */
document.addEventListener('DOMContentLoaded', () => {
    // ── 1. Selección de Fila y Barra Contextual en Directorio de Propietarios ──
    const ownerRows        = document.querySelectorAll('.owner-row');
    const actionBar        = document.getElementById('selection-action-bar');
    const summarySpan      = document.getElementById('selection-summary');
    const policyHintSpan   = document.getElementById('selection-policy-hint');
    const btnVerMascotas   = document.getElementById('btn-ver-mascotas-selected');
    const btnEdit          = document.getElementById('btn-edit-selected');
    const btnAddPet        = document.getElementById('btn-add-pet-selected');
    const btnBajaLogica    = document.getElementById('btn-baja-logica-selected');
    const btnDelete        = document.getElementById('btn-delete-selected');
    const btnReactivate    = document.getElementById('btn-reactivate-selected');
    const btnCancel        = document.getElementById('btn-cancel-selected');
    const formBaja         = document.getElementById('form-baja-seleccion');
    const inputBajaId      = document.getElementById('input-baja-id');
    const inputBajaAccion  = document.getElementById('input-baja-accion');

    let selectedOwnerRow = null;

    function deselectOwnerRows() {
        ownerRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });
        selectedOwnerRow = null;
        if (actionBar) actionBar.style.display = 'none';
        if (inputBajaId) inputBajaId.value = '';
    }

    function selectOwnerRow(row) {
        if (selectedOwnerRow === row) {
            deselectOwnerRows();
            return;
        }

        ownerRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });

        row.classList.add('is-selected');
        row.setAttribute('aria-pressed', 'true');
        selectedOwnerRow = row;

        const id           = row.getAttribute('data-id') || '';
        const resumen      = row.getAttribute('data-resumen') || 'Propietario seleccionado';
        const activo       = row.getAttribute('data-activo') === '1';
        const tieneDep     = row.getAttribute('data-dependencias') === '1';
        const numMascotas  = parseInt(row.getAttribute('data-mascotas') || '0', 10);
        const numCitas     = parseInt(row.getAttribute('data-citas') || '0', 10);

        if (inputBajaId) inputBajaId.value = id;
        if (summarySpan) summarySpan.textContent = resumen;

        if (policyHintSpan) {
            if (!activo) {
                policyHintSpan.textContent = 'Inactivo (historial conservado)';
            } else if (tieneDep) {
                policyHintSpan.textContent = `${numMascotas} mascota(s), ${numCitas} cita(s)`;
            } else {
                policyHintSpan.textContent = 'Sin mascotas ni citas registradas';
            }
        }

        if (btnAddPet) {
            btnAddPet.style.display = activo ? 'inline-block' : 'none';
        }
        if (btnBajaLogica) {
            btnBajaLogica.style.display = (activo && tieneDep) ? 'inline-block' : 'none';
        }
        if (btnDelete) {
            btnDelete.style.display = (activo && !tieneDep) ? 'inline-block' : 'none';
        }
        if (btnReactivate) {
            btnReactivate.style.display = (!activo) ? 'inline-block' : 'none';
        }

        if (actionBar) {
            actionBar.style.display = 'flex';
        }
    }

    ownerRows.forEach((row) => {
        row.addEventListener('click', function () {
            selectOwnerRow(this);
        });

        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectOwnerRow(this);
            }
        });
    });

    if (btnCancel) {
        btnCancel.addEventListener('click', () => deselectOwnerRows());
    }

    if (btnVerMascotas) {
        btnVerMascotas.addEventListener('click', () => {
            if (!selectedOwnerRow) return;
            const id = selectedOwnerRow.getAttribute('data-id');
            if (id) window.location.href = '/mascotas?id_propietario=' + encodeURIComponent(id);
        });
    }

    if (btnEdit) {
        btnEdit.addEventListener('click', () => {
            if (!selectedOwnerRow) return;
            const id = selectedOwnerRow.getAttribute('data-id');
            if (id) window.location.href = '/propietarios/editar?id=' + encodeURIComponent(id);
        });
    }

    if (btnAddPet) {
        btnAddPet.addEventListener('click', () => {
            if (!selectedOwnerRow) return;
            const id = selectedOwnerRow.getAttribute('data-id');
            if (id) window.location.href = '/mascotas/crear?propietario_id=' + encodeURIComponent(id);
        });
    }

    if (btnBajaLogica) {
        btnBajaLogica.addEventListener('click', () => {
            if (!selectedOwnerRow || !formBaja) return;
            const nombre   = selectedOwnerRow.getAttribute('data-nombre') || 'este propietario';
            const dui      = selectedOwnerRow.getAttribute('data-dui') || '';
            const mascotas = selectedOwnerRow.getAttribute('data-mascotas') || '0';
            const citas    = selectedOwnerRow.getAttribute('data-citas') || '0';
            const msg = `¿Deseas desactivar al propietario "${nombre}" (DUI: ${dui})?\n\n` +
                        `Tiene ${mascotas} mascota(s) y ${citas} cita(s) registradas.\n` +
                        `El propietario y sus mascotas quedarán inactivos, sus citas programadas pendientes se cancelarán automáticamente y su historial médico se conservará.`;
            if (window.confirm(msg)) {
                if (inputBajaAccion) inputBajaAccion.value = 'baja';
                formBaja.submit();
            }
        });
    }

    if (btnDelete) {
        btnDelete.addEventListener('click', () => {
            if (!selectedOwnerRow || !formBaja) return;
            const nombre = selectedOwnerRow.getAttribute('data-nombre') || 'este propietario';
            const dui    = selectedOwnerRow.getAttribute('data-dui') || '';
            const msg = `¿Deseas eliminar el registro del propietario "${nombre}" (DUI: ${dui})?\n\n` +
                        `Esta acción no se puede deshacer.`;
            if (window.confirm(msg)) {
                if (inputBajaAccion) inputBajaAccion.value = 'baja';
                formBaja.submit();
            }
        });
    }

    if (btnReactivate) {
        btnReactivate.addEventListener('click', () => {
            if (!selectedOwnerRow || !formBaja) return;
            const nombre = selectedOwnerRow.getAttribute('data-nombre') || 'este propietario';
            if (window.confirm(`¿Deseas reactivar al propietario "${nombre}"?`)) {
                if (inputBajaAccion) inputBajaAccion.value = 'reactivar';
                formBaja.submit();
            }
        });
    }

    // ── 2. Selección de Mascotas Asociadas dentro de Editar Propietario ──
    const assocPetRows      = document.querySelectorAll('.associated-pet-row');
    const barMascotas       = document.getElementById('selection-action-bar-mascotas');
    const summaryMascota    = document.getElementById('selection-summary-mascota');
    const btnEditAssocPet   = document.getElementById('btn-edit-associated-pet');
    const btnCancelAssocPet = document.getElementById('btn-cancel-associated-pet');
    let selectedAssocPetRow = null;

    function deselectAssocPetRows() {
        assocPetRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });
        selectedAssocPetRow = null;
        if (barMascotas) barMascotas.style.display = 'none';
    }

    assocPetRows.forEach((row) => {
        row.addEventListener('click', function () {
            if (selectedAssocPetRow === this) {
                deselectAssocPetRows();
                return;
            }
            assocPetRows.forEach((r) => {
                r.classList.remove('is-selected');
                r.setAttribute('aria-pressed', 'false');
            });
            this.classList.add('is-selected');
            this.setAttribute('aria-pressed', 'true');
            selectedAssocPetRow = this;
            if (summaryMascota) {
                summaryMascota.textContent = this.getAttribute('data-resumen') || 'Mascota seleccionada';
            }
            if (barMascotas) barMascotas.style.display = 'flex';
        });
    });

    if (btnCancelAssocPet) {
        btnCancelAssocPet.addEventListener('click', () => deselectAssocPetRows());
    }

    if (btnEditAssocPet) {
        btnEditAssocPet.addEventListener('click', () => {
            if (!selectedAssocPetRow) return;
            const id = selectedAssocPetRow.getAttribute('data-id');
            if (id) window.location.href = '/mascotas/editar?id=' + encodeURIComponent(id);
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (selectedOwnerRow) deselectOwnerRows();
            if (selectedAssocPetRow) deselectAssocPetRows();
        }
    });

    // ── 3. Confirmaciones nativas en vista de Edición ──
    const formsBaja = document.querySelectorAll('.form-baja-propietario');
    formsBaja.forEach((formEl) => {
        formEl.addEventListener('submit', (e) => {
            const nombre        = formEl.getAttribute('data-nombre') || 'este propietario';
            const dui           = formEl.getAttribute('data-dui') || '';
            const tieneDep      = formEl.getAttribute('data-dependencias') === '1';
            const numMascotas   = formEl.getAttribute('data-mascotas') || '0';
            const numCitas      = formEl.getAttribute('data-citas') || '0';
            const esReactivar   = formEl.getAttribute('data-accion') === 'reactivar';

            let mensaje = '';
            if (esReactivar) {
                mensaje = `¿Deseas reactivar al propietario "${nombre}"?`;
            } else if (tieneDep) {
                mensaje = `¿Deseas desactivar al propietario "${nombre}" (DUI: ${dui})?\n\n` +
                          `Tiene ${numMascotas} mascota(s) y ${numCitas} cita(s) registradas.\n` +
                          `El propietario y sus mascotas quedarán inactivos, sus citas programadas pendientes se cancelarán automáticamente y su historial médico se conservará.`;
            } else {
                mensaje = `¿Deseas eliminar el registro del propietario "${nombre}" (DUI: ${dui})?\n\n` +
                          `Esta acción no se puede deshacer.`;
            }

            if (!window.confirm(mensaje)) {
                e.preventDefault();
            }
        });
    });

    // ── 4. Filtro instantáneo opcional en tabla ──
    const searchInput = document.getElementById('owner-search-input');
    const tableRows   = document.querySelectorAll('#owners-table tbody tr.owner-row');
    if (searchInput && tableRows.length > 0) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            const qDigits = q.replace(/\D/g, '');
            tableRows.forEach((row) => {
                const nombre = (row.getAttribute('data-nombre') || '').toLowerCase();
                const dui    = (row.getAttribute('data-dui') || '').toLowerCase();
                const duiDig = dui.replace(/\D/g, '');
                const tel    = (row.getAttribute('data-telefono') || '').toLowerCase();
                const correo = (row.getAttribute('data-correo') || '').toLowerCase();

                const coincide = !q ||
                    nombre.includes(q) ||
                    dui.includes(q) ||
                    (qDigits.length >= 4 && duiDig.includes(qDigits)) ||
                    tel.includes(q) ||
                    correo.includes(q);

                row.style.display = coincide ? '' : 'none';
                if (!coincide && selectedOwnerRow === row) {
                    deselectOwnerRows();
                }
            });
        });
    }

    // ── 5. Validación de Formulario de Alta / Edición de Propietario ──
    const form            = document.getElementById('form-propietario');
    if (!form) return;

    const nombreInput     = document.getElementById('nombre_completo');
    const duiInput        = document.getElementById('dui');
    const telefonoInput   = document.getElementById('telefono');
    const correoInput     = document.getElementById('correo');
    const submitBtn       = document.getElementById('btn-submit');
    const jsErrorBox      = document.getElementById('js-error-container');
    const jsErrorText     = document.getElementById('js-error-text');
    const serverErrorBox  = document.getElementById('server-error-msg');

    const DUI_REGEX       = /^\d{8}-\d$/;
    const TEL_REGEX       = /^[0-9+\-\s()]{7,20}$/;
    const EMAIL_REGEX     = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

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

    if (duiInput) {
        duiInput.addEventListener('input', (e) => {
            hideJsError();
            duiInput.classList.remove('is-invalid');

            const digits = e.target.value.replace(/\D/g, '').slice(0, 9);
            if (digits.length > 8) {
                e.target.value = `${digits.slice(0, 8)}-${digits.slice(8, 9)}`;
            } else {
                e.target.value = digits;
            }
        });
    }

    [nombreInput, telefonoInput, correoInput].forEach((input) => {
        if (input) {
            input.addEventListener('input', () => {
                hideJsError();
                input.classList.remove('is-invalid');
            });
        }
    });

    form.addEventListener('submit', (e) => {
        hideJsError();

        const nombre   = nombreInput ? nombreInput.value.trim() : '';
        const dui      = duiInput ? duiInput.value.trim() : '';
        const telefono = telefonoInput ? telefonoInput.value.trim() : '';
        const correo   = correoInput ? correoInput.value.trim() : '';

        if (!nombre || nombre.length < 3) {
            e.preventDefault();
            showJsError('Por favor ingresa el nombre completo del propietario (mínimo 3 caracteres).');
            if (nombreInput) {
                nombreInput.classList.add('is-invalid');
                nombreInput.focus();
            }
            return;
        }

        if (!DUI_REGEX.test(dui)) {
            e.preventDefault();
            showJsError('El DUI debe tener el formato 00000000-0 (8 dígitos, guion y 1 dígito verificador).');
            if (duiInput) {
                duiInput.classList.add('is-invalid');
                duiInput.focus();
            }
            return;
        }

        if (telefono !== '' && !TEL_REGEX.test(telefono)) {
            e.preventDefault();
            showJsError('El teléfono ingresado no tiene un formato válido (ej. 7000-0000).');
            if (telefonoInput) {
                telefonoInput.classList.add('is-invalid');
                telefonoInput.focus();
            }
            return;
        }

        if (correo !== '' && !EMAIL_REGEX.test(correo)) {
            e.preventDefault();
            showJsError('Por favor ingresa un correo electrónico válido (ej. cliente@correo.com).');
            if (correoInput) {
                correoInput.classList.add('is-invalid');
                correoInput.focus();
            }
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Guardando...';
        }
    });
});
