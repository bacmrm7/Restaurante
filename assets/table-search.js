(() => {
    const normalize = value => value
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    document.querySelectorAll('[data-table-search]').forEach(input => {
        const table = document.querySelector(input.dataset.tableSearch);
        const status = input.closest('.table-tools')?.querySelector('.table-search-status');
        if (!table) return;

        input.addEventListener('input', () => {
            const query = normalize(input.value);
            let visibleRows = 0;
            let searchableRows = 0;

            table.querySelectorAll('tbody tr').forEach(row => {
                if (row.querySelector('.empty-state')) {
                    row.hidden = Boolean(query);
                    return;
                }

                searchableRows += 1;
                const matches = !query || normalize(row.textContent).includes(query);
                row.hidden = !matches;
                if (matches) visibleRows += 1;
            });

            if (status) {
                status.textContent = query && visibleRows === 0
                    ? 'No se encontraron coincidencias.'
                    : query
                        ? `${visibleRows} de ${searchableRows} filas`
                        : '';
            }
        });
    });
})();