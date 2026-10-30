/**
 * Agenda del Veterinario y Consulta Veterinaria — US-06 (T-047, TEC-01, UI-01)
 * Homologado con la selección interactiva por filas y barra contextual de Horarios, Propietarios y Mascotas.
 */
document.addEventListener('DOMContentLoaded', () => {
    // ── 1. Selección de filas y filtro rápido en Mi Agenda (/veterinario/agenda) ── //
    const agendaRows      = document.querySelectorAll('.agenda-row');
    const actionBar       = document.getElementById('selection-action-bar');
    const summarySpan     = document.getElementById('selection-summary');
    const policyHintSpan  = document.getElementById('selection-policy-hint');
    const btnAtender      = document.getElementById('btn-atender-selected');
    const btnVerMascota   = document.getElementById('btn-ver-mascota-agenda');
    const btnCancelAgenda = document.getElementById('btn-cancel-agenda');

    let selectedAgendaRow = null;

    function deselectAgendaRows() {
        agendaRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });
        selectedAgendaRow = null;
        if (actionBar) actionBar.style.display = 'none';
    }

    function selectAgendaRow(row) {
        if (selectedAgendaRow === row) {
            deselectAgendaRows();
            return;
        }

        agendaRows.forEach((r) => {
            r.classList.remove('is-selected');
            r.setAttribute('aria-pressed', 'false');
        });

        row.classList.add('is-selected');
        row.setAttribute('aria-pressed', 'true');
        selectedAgendaRow = row;

        const resumen = row.getAttribute('data-resumen') || 'Cita seleccionada';
        const estado  = row.getAttribute('data-estado') || '';
        const srv     = row.getAttribute('data-servicio') || '';
        const prop    = row.getAttribute('data-propietario') || '';

        if (summarySpan) summarySpan.textContent = resumen;

        if (policyHintSpan) {
            if (estado === 'Programada') {
                policyHintSpan.textContent = `${srv} · Propietario: ${prop}`;
            } else if (estado === 'Atendida') {
                policyHintSpan.textContent = 'Consulta ya registrada (Cita Atendida)';
            } else {
                policyHintSpan.textContent = 'Cita Cancelada';
            }
        }

        if (btnAtender) {
            btnAtender.style.display = (estado === 'Programada') ? 'inline-block' : 'none';
        }

        if (actionBar) {
            actionBar.style.display = 'flex';
        }
    }

    agendaRows.forEach((row) => {
        row.addEventListener('click', function () {
            selectAgendaRow(this);
        });

        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectAgendaRow(this);
            }
        });
    });

    if (btnCancelAgenda) {
        btnCancelAgenda.addEventListener('click', () => deselectAgendaRows());
    }

    if (btnAtender) {
        btnAtender.addEventListener('click', () => {
            if (!selectedAgendaRow) return;
            const id = selectedAgendaRow.getAttribute('data-id');
            if (id) {
                window.location.href = '/consultas/crear?id_cita=' + encodeURIComponent(id);
            }
        });
    }

    if (btnVerMascota) {
        btnVerMascota.addEventListener('click', () => {
            if (!selectedAgendaRow) return;
            const mascota = selectedAgendaRow.getAttribute('data-mascota') || '';
            window.location.href = '/mascotas?q=' + encodeURIComponent(mascota);
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && selectedAgendaRow) {
            deselectAgendaRows();
        }
    });

    const agendaSearchInput = document.getElementById('agenda-search-input');
    const agendaNoMatches   = document.getElementById('agenda-no-matches-row');
    const agendaCountEl     = document.getElementById('agenda-search-count');

    if (agendaSearchInput && agendaRows.length > 0) {
        agendaSearchInput.addEventListener('input', () => {
            const q = agendaSearchInput.value.trim().toLowerCase();
            let visibles = 0;

            agendaRows.forEach((row) => {
                const mascota = (row.getAttribute('data-mascota') || '').toLowerCase();
                const especie = (row.getAttribute('data-especie') || '').toLowerCase();
                const prop    = (row.getAttribute('data-propietario') || '').toLowerCase();
                const srv     = (row.getAttribute('data-servicio') || '').toLowerCase();
                const fecha   = (row.getAttribute('data-fecha') || '').toLowerCase();
                const hora    = (row.getAttribute('data-hora') || '').toLowerCase();

                const coincide = !q
                    || mascota.includes(q)
                    || especie.includes(q)
                    || prop.includes(q)
                    || srv.includes(q)
                    || fecha.includes(q)
                    || hora.includes(q);

                row.style.display = coincide ? '' : 'none';

                if (coincide) {
                    visibles++;
                } else if (selectedAgendaRow === row) {
                    deselectAgendaRows();
                }
            });

            if (agendaNoMatches) {
                agendaNoMatches.style.display = visibles === 0 ? '' : 'none';
            }
            if (agendaCountEl) {
                agendaCountEl.innerHTML = `Mostrando <strong>${visibles}</strong> cita(s)`;
            }
        });
    }

    // ── 2. Formulario de Consulta Veterinaria (/consultas/crear) ──────────── //
    const form = document.getElementById('form-consulta');
    if (!form) return;

    const areas = Array.from(form.querySelectorAll('textarea[data-contador]'));

    function actualizarContador(area) {
        const salida = document.getElementById(area.dataset.contador);
        if (!salida) return;

        const max   = parseInt(area.dataset.max, 10) || area.maxLength;
        const largo = area.value.length;

        salida.textContent = `${largo} / ${max}`;
        salida.classList.toggle('limite', largo >= max);
        salida.classList.toggle('cerca', largo < max && largo >= max * 0.9);
    }

    areas.forEach(area => {
        actualizarContador(area);
        area.addEventListener('input', () => actualizarContador(area));
    });

    const restaurado = form.dataset.restaurado === '1';
    const inicial = new Map(areas.map(a => [a.id, restaurado ? '' : a.value]));
    let enviando  = false;

    function hayCambios() {
        return areas.some(a => a.value !== inicial.get(a.id));
    }

    window.addEventListener('beforeunload', (ev) => {
        if (enviando || !hayCambios()) return;
        ev.preventDefault();
        ev.returnValue = '';
    });

    form.addEventListener('submit', () => {
        enviando = true;

        const boton = form.querySelector('button[type="submit"]');
        if (boton) {
            boton.disabled = true;
            boton.textContent = 'Guardando…';
        }
    });
});
