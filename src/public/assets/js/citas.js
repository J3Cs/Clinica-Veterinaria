/**
 * Agendamiento y Gestión de Citas — US-04 y US-05 (T-035, TEC-01, UI-01)
 * Homologado con la selección interactiva por filas y barra contextual de Horarios, Propietarios y Mascotas.
 */
document.addEventListener('DOMContentLoaded', () => {
    const errorBox  = document.getElementById('js-error-container');
    const errorText = document.getElementById('js-error-text');

    function mostrarError(mensaje) {
        if (!errorBox || !errorText) return;
        errorText.textContent = mensaje;
        errorBox.style.display = 'flex';
        errorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function ocultarError() {
        if (errorBox) errorBox.style.display = 'none';
    }

    // ── 1. Selección interactiva de filas en el Listado de Citas ──────────── //
    const citaRows         = document.querySelectorAll('.cita-row');
    const actionBar        = document.getElementById('selection-action-bar');
    const summarySpan      = document.getElementById('selection-summary');
    const policyHintSpan   = document.getElementById('selection-policy-hint');
    const btnCancelarCita  = document.getElementById('btn-cancelar-cita-selected');
    const btnVerMascota    = document.getElementById('btn-ver-mascota-selected');
    const btnDeselect      = document.getElementById('btn-deselect-cita');

    let selectedCitaRow = null;

    function deselectCitaRows() {
        citaRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });
        selectedCitaRow = null;
        if (actionBar) actionBar.style.display = 'none';
    }

    function selectCitaRow(row) {
        if (selectedCitaRow === row) {
            deselectCitaRows();
            return;
        }

        citaRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });

        row.classList.add('is-selected');
        row.setAttribute('aria-pressed', 'true');
        selectedCitaRow = row;

        const resumen = row.getAttribute('data-resumen') || 'Cita seleccionada';
        const estado  = row.getAttribute('data-estado') || '';
        const vet     = row.getAttribute('data-veterinario') || '';
        const srv     = row.getAttribute('data-servicio') || '';

        if (summarySpan) summarySpan.textContent = resumen;

        if (policyHintSpan) {
            if (estado === 'Programada') {
                policyHintSpan.textContent = `${srv} · ${vet}`;
            } else if (estado === 'Atendida') {
                policyHintSpan.textContent = 'Cita ya atendida (no cancelable)';
            } else {
                policyHintSpan.textContent = 'Cita cancelada (horario liberado)';
            }
        }

        if (btnCancelarCita) {
            btnCancelarCita.style.display = (estado === 'Programada') ? 'inline-block' : 'none';
        }

        if (actionBar) {
            actionBar.style.display = 'flex';
        }
    }

    citaRows.forEach((row) => {
        row.addEventListener('click', function () {
            selectCitaRow(this);
        });

        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectCitaRow(this);
            }
        });
    });

    if (btnDeselect) {
        btnDeselect.addEventListener('click', () => deselectCitaRows());
    }

    if (btnCancelarCita) {
        btnCancelarCita.addEventListener('click', () => {
            if (!selectedCitaRow) return;
            const id = selectedCitaRow.getAttribute('data-id');
            if (id) window.location.href = '/citas/cancelar?id=' + encodeURIComponent(id);
        });
    }

    if (btnVerMascota) {
        btnVerMascota.addEventListener('click', () => {
            if (!selectedCitaRow) return;
            const idMascota = selectedCitaRow.getAttribute('data-id-mascota');
            if (idMascota && idMascota !== '0') {
                window.location.href = '/mascotas/editar?id=' + encodeURIComponent(idMascota);
            } else {
                const mascota = selectedCitaRow.getAttribute('data-mascota') || '';
                window.location.href = '/mascotas?q=' + encodeURIComponent(mascota);
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && selectedCitaRow) {
            deselectCitaRows();
        }
    });

    // ── 2. Filtro rápido en vivo sobre la tabla de Citas ──────────────────── //
    const searchInput   = document.getElementById('cita-search-input');
    const noMatchesRow  = document.getElementById('citas-no-matches-row');
    const searchCountEl = document.getElementById('cita-search-count');

    if (searchInput && citaRows.length > 0) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.trim().toLowerCase();
            let visibles = 0;

            citaRows.forEach((row) => {
                const mascota = (row.getAttribute('data-mascota') || '').toLowerCase();
                const prop    = (row.getAttribute('data-propietario') || '').toLowerCase();
                const srv     = (row.getAttribute('data-servicio') || '').toLowerCase();
                const vet     = (row.getAttribute('data-veterinario') || '').toLowerCase();
                const fecha   = (row.getAttribute('data-fecha') || '').toLowerCase();
                const hora    = (row.getAttribute('data-hora') || '').toLowerCase();

                const coincide = !q
                    || mascota.includes(q)
                    || prop.includes(q)
                    || srv.includes(q)
                    || vet.includes(q)
                    || fecha.includes(q)
                    || hora.includes(q);

                row.style.display = coincide ? '' : 'none';

                if (coincide) {
                    visibles++;
                } else if (selectedCitaRow === row) {
                    deselectCitaRows();
                }
            });

            if (noMatchesRow) {
                noMatchesRow.style.display = visibles === 0 ? '' : 'none';
            }
            if (searchCountEl) {
                searchCountEl.innerHTML = `Mostrando <strong>${visibles}</strong> cita(s)`;
            }
        });
    }

    // ── 3. Paso 1: validar la selección antes de consultar horarios ───────── //
    const formConsulta = document.getElementById('form-consulta');
    if (formConsulta) {
        const campos = [
            { id: 'id_mascota',     mensaje: 'Selecciona una mascota.' },
            { id: 'id_servicio',    mensaje: 'Selecciona un servicio.' },
            { id: 'id_veterinario', mensaje: 'Selecciona un veterinario.' },
            { id: 'fecha',          mensaje: 'Selecciona una fecha.' },
        ];

        formConsulta.addEventListener('submit', (ev) => {
            ocultarError();
            campos.forEach(c => document.getElementById(c.id)?.classList.remove('is-invalid'));

            for (const c of campos) {
                const el = document.getElementById(c.id);
                if (el && el.value.trim() === '') {
                    ev.preventDefault();
                    el.classList.add('is-invalid');
                    el.focus();
                    mostrarError(c.mensaje);
                    return;
                }
            }

            const fecha = document.getElementById('fecha');
            if (fecha && fecha.min && fecha.value < fecha.min) {
                ev.preventDefault();
                fecha.classList.add('is-invalid');
                fecha.focus();
                mostrarError('No se pueden agendar citas en fechas pasadas.');
            }
        });
    }

    // ── 4. Paso 2: resumen y confirmación antes de guardar ────────────────── //
    const formConfirmar = document.getElementById('form-confirmar');
    if (formConfirmar) {
        const resumen     = document.getElementById('resumen-cita');
        const resumenHora = document.getElementById('resumen-hora');
        const radios      = Array.from(formConfirmar.querySelectorAll('input[name="hora_cita"]'));

        function horaSeleccionada() {
            const marcado = radios.find(r => r.checked);
            if (!marcado) return null;
            const etiqueta = formConfirmar.querySelector(`label[for="${marcado.id}"]`);
            return etiqueta ? etiqueta.textContent.trim() : marcado.value;
        }

        radios.forEach(r => r.addEventListener('change', () => {
            ocultarError();
            if (resumenHora) resumenHora.textContent = horaSeleccionada() || 'Selecciona un horario';
        }));

        formConfirmar.addEventListener('submit', (ev) => {
            ocultarError();
            const hora = horaSeleccionada();
            if (!hora) {
                ev.preventDefault();
                mostrarError('Selecciona uno de los horarios disponibles.');
                radios[0]?.focus();
                return;
            }

            const d = resumen ? resumen.dataset : {};
            const texto =
                '¿Confirmas esta cita?\n\n' +
                `Mascota: ${d.mascota}\n` +
                `Servicio: ${d.servicio}\n` +
                `Veterinario: ${d.veterinario}\n` +
                `Fecha: ${d.fecha}\n` +
                `Hora: ${hora}`;

            if (!window.confirm(texto)) {
                ev.preventDefault();
                return;
            }

            // Evitar doble envío tras confirmar
            const boton = formConfirmar.querySelector('button[type="submit"]');
            if (boton) {
                boton.disabled = true;
                boton.textContent = 'Guardando…';
            }
        });
    }

    // ── 5. Cancelación: evitar doble envío ────────────────────────────────── //
    const formCancelar = document.getElementById('form-cancelar');
    if (formCancelar) {
        formCancelar.addEventListener('submit', () => {
            const boton = formCancelar.querySelector('button[type="submit"]');
            if (boton) {
                boton.disabled = true;
                boton.textContent = 'Cancelando…';
            }
        });
    }
});
