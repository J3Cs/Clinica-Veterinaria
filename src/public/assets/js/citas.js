/**
 * Agendamiento de citas — US-04 (T-035, TEC-01, UI-01)
 *
 * Mejora progresiva con JavaScript nativo: valida la selección antes de enviar
 * y pide confirmar un resumen antes de guardar. No hace peticiones asíncronas:
 * sin JavaScript el flujo funciona igual con solicitudes PHP normales, y la
 * validación definitiva siempre ocurre en el servidor.
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

    // ── Paso 1: validar la selección antes de consultar horarios ──────────── //
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

    // ── Paso 2: resumen y confirmación antes de guardar ───────────────────── //
    const formConfirmar = document.getElementById('form-confirmar');
    if (formConfirmar) {
        const resumen    = document.getElementById('resumen-cita');
        const resumenHora = document.getElementById('resumen-hora');
        const radios     = Array.from(formConfirmar.querySelectorAll('input[name="hora_cita"]'));

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
    // ── Cancelación: evitar doble envío ───────────────────────────────────── //
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
