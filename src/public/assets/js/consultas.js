/**
 * Consulta veterinaria — US-06 (T-047, TEC-01, UI-01)
 *
 * Mejora progresiva con JavaScript nativo:
 *  - contador de caracteres por campo;
 *  - advertencia al abandonar la página con cambios sin guardar.
 * No bloquea el envío normal del formulario ni hace peticiones asíncronas:
 * sin JavaScript todo funciona igual y el servidor valida siempre.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-consulta');
    if (!form) return;

    const areas = Array.from(form.querySelectorAll('textarea[data-contador]'));

    // ── Contadores ────────────────────────────────────────────────────────── //
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

    // ── Advertencia de cambios sin guardar ────────────────────────────────── //
    // Si el servidor devolvió lo escrito tras un error, ese texto aún no está guardado.
    const restaurado = form.dataset.restaurado === '1';
    const inicial = new Map(areas.map(a => [a.id, restaurado ? '' : a.value]));
    let enviando  = false;

    function hayCambios() {
        return areas.some(a => a.value !== inicial.get(a.id));
    }

    window.addEventListener('beforeunload', (ev) => {
        if (enviando || !hayCambios()) return;
        ev.preventDefault();
        ev.returnValue = '';   // el navegador muestra su propio mensaje
    });

    form.addEventListener('submit', () => {
        enviando = true;       // el envío normal nunca se bloquea

        const boton = form.querySelector('button[type="submit"]');
        if (boton) {
            boton.disabled = true;
            boton.textContent = 'Guardando…';
        }
    });
});
