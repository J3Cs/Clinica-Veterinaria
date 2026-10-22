/**
 * Buscador dinámico de Mascotas en tiempo real (US-03, TEC-01, UI-01)
 *
 * Filtra la tabla de mascotas y muestra sugerencias interactivas
 * según lo que el usuario va escribiendo (por nombre de mascota o propietario).
 */

document.addEventListener('DOMContentLoaded', () => {
    const searchInput      = document.getElementById('pet-search-input');
    const clearBtn         = document.getElementById('pet-search-clear');
    const dropdown         = document.getElementById('pet-search-dropdown');
    const countDisplay     = document.getElementById('pet-search-count');
    const tableBody        = document.getElementById('pets-table-body');
    const noMatchesRow     = document.getElementById('pets-no-matches-row');

    if (!searchInput || !tableBody) return;

    const rows = Array.from(tableBody.querySelectorAll('tr.pet-row'));
    const totalMascotas = rows.length;

    // Normalizar texto (elimina tildes y caracteres diacríticos para búsqueda flexible)
    function normalizar(texto) {
        return (texto || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    // Actualizar contador visual
    function actualizarContador(visibles, query) {
        if (!countDisplay) return;
        if (!query) {
            countDisplay.textContent = `Total: ${totalMascotas} mascota${totalMascotas === 1 ? '' : 's'} registrada${totalMascotas === 1 ? '' : 's'}`;
        } else {
            countDisplay.innerHTML = `Mostrando <strong>${visibles}</strong> de ${totalMascotas} mascota${totalMascotas === 1 ? '' : 's'} para: <em>"${escapeHtml(query)}"</em>`;
        }
    }

    function escapeHtml(string) {
        const div = document.createElement('div');
        div.textContent = string;
        return div.innerHTML;
    }

    // Filtrar tabla y construir dropdown
    function filtrar() {
        const query = searchInput.value.trim();
        const normQuery = normalizar(query);

        // Mostrar / ocultar botón limpiar
        if (clearBtn) {
            clearBtn.style.display = query.length > 0 ? 'inline-block' : 'none';
        }

        // Si está vacío, mostrar todas las filas y ocultar dropdown
        if (normQuery === '') {
            rows.forEach(r => r.style.display = '');
            if (noMatchesRow) noMatchesRow.style.display = 'none';
            if (dropdown) {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
            }
            actualizarContador(totalMascotas, '');
            return;
        }

        let visibles = 0;
        const matches = [];

        rows.forEach(row => {
            const nombre = normalizar(row.getAttribute('data-nombre'));
            const propietario = normalizar(row.getAttribute('data-propietario'));
            const dui = normalizar(row.getAttribute('data-dui'));
            const especie = normalizar(row.getAttribute('data-especie'));
            const raza = normalizar(row.getAttribute('data-raza'));

            const coincideMascota = nombre.includes(normQuery);
            const coincidePropietario = propietario.includes(normQuery) || dui.includes(normQuery);
            const coincideEspecie = especie.includes(normQuery) || raza.includes(normQuery);

            if (coincideMascota || coincidePropietario || coincideEspecie) {
                row.style.display = '';
                visibles++;

                // Determinar qué coincidió para la sugerencia
                let motivo = 'Mascota';
                if (coincidePropietario) {
                    motivo = 'Propietario';
                } else if (coincideEspecie && !coincideMascota) {
                    motivo = 'Especie/Raza';
                }

                matches.push({
                    nombreOriginal: row.getAttribute('data-nombre'),
                    propietarioOriginal: row.getAttribute('data-propietario'),
                    duiOriginal: row.getAttribute('data-dui'),
                    especieOriginal: row.getAttribute('data-especie'),
                    motivo: motivo,
                });
            } else {
                row.style.display = 'none';
            }
        });

        // Mostrar u ocultar fila de sin resultados en la tabla
        if (noMatchesRow) {
            noMatchesRow.style.display = visibles === 0 ? '' : 'none';
        }

        actualizarContador(visibles, query);

        // Construir listado interactivo en el dropdown (máximo 6 sugerencias)
        if (dropdown) {
            dropdown.innerHTML = '';
            if (matches.length > 0) {
                matches.slice(0, 6).forEach(item => {
                    const el = document.createElement('div');
                    el.className = 'search-dropdown-item';
                    el.setAttribute('role', 'button');
                    el.tabIndex = 0;
                    el.innerHTML = `
                        <div>
                            <div class="search-dropdown-title">🐾 ${escapeHtml(item.nombreOriginal)}</div>
                            <div class="search-dropdown-sub">Dueño: ${escapeHtml(item.propietarioOriginal)} &middot; DUI: <code>${escapeHtml(item.duiOriginal || '—')}</code></div>
                        </div>
                        <span class="search-match-badge">${escapeHtml(item.motivo)}</span>
                    `;

                    // Al hacer clic en una sugerencia, fijar búsqueda en esa mascota
                    el.addEventListener('click', () => {
                        searchInput.value = item.nombreOriginal;
                        dropdown.style.display = 'none';
                        filtrar();
                    });

                    dropdown.appendChild(el);
                });
                dropdown.style.display = 'block';
            } else {
                dropdown.innerHTML = `
                    <div class="search-dropdown-item" style="cursor: default; color: var(--color-text-muted);">
                        <span>No se encontraron mascotas o propietarios con "<em>${escapeHtml(query)}</em>"</span>
                    </div>
                `;
                dropdown.style.display = 'block';
            }
        }
    }

    // Eventos del Input
    searchInput.addEventListener('input', filtrar);
    searchInput.addEventListener('focus', () => {
        if (searchInput.value.trim().length > 0) {
            filtrar();
        }
    });

    // Limpiar con botón
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            searchInput.focus();
            filtrar();
        });
    }

    // Tecla Escape para limpiar y cerrar
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (dropdown) dropdown.style.display = 'none';
            if (searchInput.value !== '') {
                searchInput.value = '';
                filtrar();
            }
        }
    });

    // Cerrar dropdown al hacer clic fuera
    document.addEventListener('click', (e) => {
        if (!dropdown) return;
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Inicializar contador
    actualizarContador(totalMascotas, '');
});
