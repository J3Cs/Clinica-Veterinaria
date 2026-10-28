/**
 * Validación Interactiva, Selección de Filas y Confirmaciones de Desactivación — Módulo de Veterinarios
 * US-09 (T-063, T-064), US-12 — Cumple TEC-01, BAJA-01, UI-01
 *
 * La validación en cliente complementa la experiencia del administrador, pero nunca reemplaza
 * la validación estricta en el servidor (VeterinariosService / VeterinarioController).
 */
document.addEventListener('DOMContentLoaded', () => {
    // ── 1. Selección de Fila y Barra Contextual en Directorio de Veterinarios ──
    const vetRows          = document.querySelectorAll('.vet-row');
    const actionBar        = document.getElementById('selection-action-bar');
    const summarySpan      = document.getElementById('selection-summary');
    const policyHintSpan   = document.getElementById('selection-policy-hint');
    const btnEdit          = document.getElementById('btn-edit-selected');
    const btnHorarios      = document.getElementById('btn-horarios-selected');
    const btnAddHorario    = document.getElementById('btn-add-horario-selected');
    const btnBajaLogica    = document.getElementById('btn-baja-logica-selected');
    const btnDelete        = document.getElementById('btn-delete-selected');
    const btnReactivate    = document.getElementById('btn-reactivate-selected');
    const btnCancel        = document.getElementById('btn-cancel-selected');
    const formBaja         = document.getElementById('form-baja-seleccion');
    const inputBajaId      = document.getElementById('input-baja-id');
    const inputBajaAccion  = document.getElementById('input-baja-accion');

    let selectedVetRow = null;

    function deselectVetRows() {
        vetRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });
        selectedVetRow = null;
        if (actionBar) actionBar.style.display = 'none';
        if (inputBajaId) inputBajaId.value = '';
    }

    function selectVetRow(row) {
        if (selectedVetRow === row) {
            deselectVetRows();
            return;
        }

        vetRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });

        row.classList.add('is-selected');
        row.setAttribute('aria-pressed', 'true');
        selectedVetRow = row;

        const id            = row.getAttribute('data-id') || '';
        const resumen       = row.getAttribute('data-resumen') || 'Veterinario seleccionado';
        const activo        = row.getAttribute('data-activo') === '1';
        const tieneDep      = row.getAttribute('data-dependencias') === '1';
        const numCitas      = parseInt(row.getAttribute('data-citas') || '0', 10);
        const numConsultas  = parseInt(row.getAttribute('data-consultas') || '0', 10);

        if (inputBajaId) inputBajaId.value = id;
        if (summarySpan) summarySpan.textContent = resumen;

        if (policyHintSpan) {
            if (!activo) {
                policyHintSpan.textContent = 'Cuenta inactiva (historial clínico conservado)';
            } else if (tieneDep) {
                policyHintSpan.textContent = `${numCitas} cita(s), ${numConsultas} consulta(s) — aplica baja lógica`;
            } else {
                policyHintSpan.textContent = 'Sin citas ni consultas registradas';
            }
        }

        if (btnAddHorario) {
            btnAddHorario.style.display = activo ? 'inline-block' : 'none';
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

    vetRows.forEach((row) => {
        row.addEventListener('click', function () {
            selectVetRow(this);
        });

        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectVetRow(this);
            }
        });
    });

    if (btnCancel) {
        btnCancel.addEventListener('click', () => deselectVetRows());
    }

    if (btnEdit) {
        btnEdit.addEventListener('click', () => {
            if (!selectedVetRow) return;
            const id = selectedVetRow.getAttribute('data-id');
            if (id) window.location.href = '/veterinarios/editar?id=' + encodeURIComponent(id);
        });
    }

    if (btnHorarios) {
        btnHorarios.addEventListener('click', () => {
            if (!selectedVetRow) return;
            const id = selectedVetRow.getAttribute('data-id');
            if (id) window.location.href = '/horarios?id_veterinario=' + encodeURIComponent(id);
        });
    }

    if (btnAddHorario) {
        btnAddHorario.addEventListener('click', () => {
            if (!selectedVetRow) return;
            const id = selectedVetRow.getAttribute('data-id');
            if (id) window.location.href = '/horarios/crear?id_veterinario=' + encodeURIComponent(id);
        });
    }

    // Confirmación de desactivación lógica desde el directorio (T-064)
    if (btnBajaLogica) {
        btnBajaLogica.addEventListener('click', () => {
            if (!selectedVetRow || !formBaja) return;
            const nombre      = selectedVetRow.getAttribute('data-nombre') || 'este veterinario';
            const usuario     = selectedVetRow.getAttribute('data-usuario') || '';
            const citas       = selectedVetRow.getAttribute('data-citas') || '0';
            const programadas = selectedVetRow.getAttribute('data-citas-programadas') || '0';
            const consultas   = selectedVetRow.getAttribute('data-consultas') || '0';

            const msg = `¿Deseas desactivar la cuenta del veterinario "${nombre}" (@${usuario})?\n\n` +
                        `Tiene ${citas} cita(s) (${programadas} programada(s)) y ${consultas} consulta(s) vinculadas.\n` +
                        `Su cuenta pasará a estado INACTIVO (no podrá iniciar sesión), sus citas programadas pendientes se cancelarán automáticamente y todo su historial clínico se conservará.`;
            if (window.confirm(msg)) {
                if (inputBajaAccion) inputBajaAccion.value = 'baja';
                formBaja.submit();
            }
        });
    }

    // Confirmación de eliminación física cuando no tiene historial (T-064)
    if (btnDelete) {
        btnDelete.addEventListener('click', () => {
            if (!selectedVetRow || !formBaja) return;
            const nombre  = selectedVetRow.getAttribute('data-nombre') || 'este veterinario';
            const usuario = selectedVetRow.getAttribute('data-usuario') || '';
            const msg = `¿Deseas eliminar el registro del veterinario "${nombre}" (@${usuario})?\n\n` +
                        `No registra citas ni consultas asociadas. Esta acción eliminará su ficha y su cuenta de usuario.`;
            if (window.confirm(msg)) {
                if (inputBajaAccion) inputBajaAccion.value = 'baja';
                formBaja.submit();
            }
        });
    }

    if (btnReactivate) {
        btnReactivate.addEventListener('click', () => {
            if (!selectedVetRow || !formBaja) return;
            const nombre = selectedVetRow.getAttribute('data-nombre') || 'este veterinario';
            if (window.confirm(`¿Deseas reactivar la cuenta del veterinario "${nombre}"?`)) {
                if (inputBajaAccion) inputBajaAccion.value = 'reactivar';
                formBaja.submit();
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && selectedVetRow) {
            deselectVetRows();
        }
    });

    // ── 2. Confirmaciones nativas en la Vista de Edición (T-064) ──
    const formsBaja = document.querySelectorAll('.form-baja-veterinario');
    formsBaja.forEach((formEl) => {
        formEl.addEventListener('submit', (e) => {
            const nombre      = formEl.getAttribute('data-nombre') || 'este veterinario';
            const usuario     = formEl.getAttribute('data-usuario') || '';
            const tieneDep    = formEl.getAttribute('data-dependencias') === '1';
            const numCitas    = formEl.getAttribute('data-citas') || '0';
            const programadas = formEl.getAttribute('data-citas-programadas') || '0';
            const consultas   = formEl.getAttribute('data-consultas') || '0';
            const esReactivar = formEl.getAttribute('data-accion') === 'reactivar';

            let mensaje = '';
            if (esReactivar) {
                mensaje = `¿Deseas reactivar la cuenta del veterinario "${nombre}" (@${usuario})?`;
            } else if (tieneDep) {
                mensaje = `¿Deseas desactivar la cuenta del veterinario "${nombre}" (@${usuario})?\n\n` +
                          `Tiene ${numCitas} cita(s) (${programadas} programada(s)) y ${consultas} consulta(s) clínicas registradas.\n` +
                          `No se eliminará físicamente: su cuenta quedará inactiva, sus citas programadas se cancelarán automáticamente y se conservará su historial.`;
            } else {
                mensaje = `¿Deseas eliminar el registro del veterinario "${nombre}" (@${usuario})?\n\n` +
                          `Esta acción no se puede deshacer.`;
            }

            if (!window.confirm(mensaje)) {
                e.preventDefault();
            }
        });
    });

    // ── 3. Filtro instantáneo opcional en tabla de veterinarios ──
    const searchInput = document.getElementById('vet-search-input');
    const tableRows   = document.querySelectorAll('#vets-table tbody tr.vet-row');
    if (searchInput && tableRows.length > 0) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            tableRows.forEach((row) => {
                const nombre   = (row.getAttribute('data-nombre') || '').toLowerCase();
                const usuario  = (row.getAttribute('data-usuario') || '').toLowerCase();
                const correo   = (row.getAttribute('data-correo') || '').toLowerCase();
                const espec    = (row.getAttribute('data-especialidad') || '').toLowerCase();
                const licencia = (row.getAttribute('data-licencia') || '').toLowerCase();
                const tel      = (row.getAttribute('data-telefono') || '').toLowerCase();

                const coincide = !q ||
                    nombre.includes(q) ||
                    usuario.includes(q) ||
                    correo.includes(q) ||
                    espec.includes(q) ||
                    licencia.includes(q) ||
                    tel.includes(q);

                row.style.display = coincide ? '' : 'none';
                if (!coincide && selectedVetRow === row) {
                    deselectVetRows();
                }
            });
        });
    }

    // ── 4. Validación en Cliente del Formulario de Alta / Edición (T-063, T-064) ──
    const form = document.getElementById('form-veterinario');
    if (!form) return;

    const modo                = form.getAttribute('data-modo') || 'crear';
    const nombreInput         = document.getElementById('nombre');
    const usuarioInput        = document.getElementById('usuario');
    const correoInput         = document.getElementById('correo');
    const telefonoInput       = document.getElementById('telefono');
    const contrasenaInput     = document.getElementById('contrasena');
    const confirmarInput      = document.getElementById('confirmar_contrasena');
    const submitBtn           = document.getElementById('btn-submit');
    const jsErrorBox          = document.getElementById('js-error-container');
    const jsErrorText         = document.getElementById('js-error-text');
    const serverErrorBox      = document.getElementById('server-error-msg');

    const USER_REGEX          = /^[a-zA-Z0-9._-]{3,50}$/;
    const EMAIL_REGEX         = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const TEL_REGEX           = /^[0-9+\-\s()]{7,20}$/;

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

    [nombreInput, usuarioInput, correoInput, telefonoInput, contrasenaInput, confirmarInput].forEach((input) => {
        if (input) {
            input.addEventListener('input', () => {
                hideJsError();
                input.classList.remove('is-invalid');
            });
        }
    });

    form.addEventListener('submit', (e) => {
        hideJsError();

        const nombre     = nombreInput ? nombreInput.value.trim() : '';
        const usuario    = usuarioInput ? usuarioInput.value.trim() : '';
        const correo     = correoInput ? correoInput.value.trim() : '';
        const telefono   = telefonoInput ? telefonoInput.value.trim() : '';
        const contrasena = contrasenaInput ? contrasenaInput.value : '';
        const confirmar  = confirmarInput ? confirmarInput.value : '';

        if (!nombre || nombre.length < 3) {
            e.preventDefault();
            showJsError('Por favor ingresa el nombre completo del veterinario (mínimo 3 caracteres).');
            if (nombreInput) {
                nombreInput.classList.add('is-invalid');
                nombreInput.focus();
            }
            return;
        }

        if (!USER_REGEX.test(usuario)) {
            e.preventDefault();
            showJsError('El nombre de usuario debe tener entre 3 y 50 caracteres (letras, números, puntos o guiones).');
            if (usuarioInput) {
                usuarioInput.classList.add('is-invalid');
                usuarioInput.focus();
            }
            return;
        }

        if (!EMAIL_REGEX.test(correo)) {
            e.preventDefault();
            showJsError('Por favor ingresa un correo electrónico válido.');
            if (correoInput) {
                correoInput.classList.add('is-invalid');
                correoInput.focus();
            }
            return;
        }

        if (telefono !== '' && !TEL_REGEX.test(telefono)) {
            e.preventDefault();
            showJsError('El teléfono ingresado debe contener entre 7 y 20 caracteres válidos.');
            if (telefonoInput) {
                telefonoInput.classList.add('is-invalid');
                telefonoInput.focus();
            }
            return;
        }

        // La contraseña es obligatoria al crear, y opcional en edición (solo si se ingresa una nueva — T-063)
        if (modo === 'crear' || contrasena !== '' || confirmar !== '') {
            if (contrasena.length < 8) {
                e.preventDefault();
                showJsError('La contraseña debe tener al menos 8 caracteres.');
                if (contrasenaInput) {
                    contrasenaInput.classList.add('is-invalid');
                    contrasenaInput.focus();
                }
                return;
            }

            if (confirmarInput && contrasena !== confirmar) {
                e.preventDefault();
                showJsError('La confirmación de contraseña no coincide.');
                confirmarInput.classList.add('is-invalid');
                confirmarInput.focus();
                return;
            }
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Guardando...';
        }
    });
});
