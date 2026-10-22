/**
 * Componente Reutilizable: Buscador Dinámico de Tablas (TEC-01, UI-01)
 *
 * Permite filtrar en tiempo real cualquier tabla HTML por sus filas,
 * con soporte para normalización de tildes/acentos, contador de coincidencias,
 * mensaje de "sin resultados" y botón de limpiar.
 */

window.initTableSearch = function(config) {
    const searchId        = config.searchId || 'search';
    const tableBodyId     = config.tableBodyId;
    const rowSelector     = config.rowSelector || 'tr';
    const noMatchesRowId  = config.noMatchesRowId;
    const entityNameSing  = config.entityNameSingular || 'registro';
    const entityNamePlur  = config.entityNamePlural || 'registros';
    const getSearchText   = config.getSearchText || null;

    const searchInput  = document.getElementById(`${searchId}-input`);
    const clearBtn     = document.getElementById(`${searchId}-clear`);
    const countDisplay = document.getElementById(`${searchId}-count`);
    const tableBody    = document.getElementById(tableBodyId);
    const noMatchesRow = noMatchesRowId ? document.getElementById(noMatchesRowId) : null;

    if (!searchInput || !tableBody) return;

    const rows = Array.from(tableBody.querySelectorAll(rowSelector));
    const totalCount = rows.length;

    // Normalizar texto para búsqueda flexible e insensible a acentos/mayúsculas
    function normalizar(texto) {
        return (texto || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function escapeHtml(string) {
        const div = document.createElement('div');
        div.textContent = string;
        return div.innerHTML;
    }

    // Actualizar indicador de coincidencias
    function actualizarContador(visibles, query) {
        if (!countDisplay) return;
        if (!query) {
            countDisplay.textContent = `Total: ${totalCount} ${totalCount === 1 ? entityNameSing : entityNamePlur} ${totalCount === 1 ? 'registrado' : 'registrados'}`;
        } else {
            countDisplay.innerHTML = `Mostrando <strong>${visibles}</strong> de ${totalCount} ${totalCount === 1 ? entityNameSing : entityNamePlur} para: <em>"${escapeHtml(query)}"</em>`;
        }
    }

    // Filtrar filas
    function filtrar() {
        const query = searchInput.value.trim();
        const normQuery = normalizar(query);

        if (clearBtn) {
            clearBtn.style.display = query.length > 0 ? 'inline-block' : 'none';
        }

        if (normQuery === '') {
            rows.forEach(r => r.style.display = '');
            if (noMatchesRow) noMatchesRow.style.display = 'none';
            actualizarContador(totalCount, '');
            return;
        }

        let visibles = 0;
        rows.forEach(row => {
            let rowText = '';
            if (typeof getSearchText === 'function') {
                rowText = getSearchText(row);
            } else {
                rowText = row.textContent;
            }

            const match = normalizar(rowText).includes(normQuery);
            if (match) {
                row.style.display = '';
                visibles++;
            } else {
                row.style.display = 'none';
            }
        });

        if (noMatchesRow) {
            noMatchesRow.style.display = visibles === 0 ? '' : 'none';
        }

        actualizarContador(visibles, query);
    }

    // Event listeners
    searchInput.addEventListener('input', filtrar);

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            filtrar();
            searchInput.focus();
        });
    }

    // Inicializar contador al cargar
    actualizarContador(totalCount, '');
};

