/**
 * Module Compréhension: drives the raw-data table view (search, sortable
 * column headers, pagination) against the JSON endpoint backed by
 * browse_table.py. Cell values are inserted via textContent, not innerHTML -
 * they come straight from the user's own uploaded file, but nothing here
 * should ever trust arbitrary content enough to parse it as markup.
 */
function initGrid(container) {
    const rowsUrl = container.dataset.rowsUrl;
    const body = container.querySelector('[data-grid-body]');
    const countEl = container.querySelector('[data-grid-count]');
    const prevBtn = container.querySelector('[data-grid-prev]');
    const nextBtn = container.querySelector('[data-grid-next]');
    const searchInput = document.querySelector('[data-grid-search]');
    const headCells = Array.from(container.querySelectorAll('[data-grid-sort]'));

    const state = {
        page: parseInt(container.dataset.page, 10) || 1,
        perPage: parseInt(container.dataset.perPage, 10) || 25,
        total: parseInt(container.dataset.total, 10) || 0,
        search: '',
        sortColumn: null,
        sortDirection: 'asc',
    };

    let searchTimer = null;
    let requestSeq = 0;

    function renderRows(columns, rows) {
        body.innerHTML = '';

        if (rows.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = columns.length;
            td.className = 'ps-3 small text-secondary';
            td.textContent = 'Aucune ligne.';
            tr.appendChild(td);
            body.appendChild(tr);
            return;
        }

        rows.forEach((row) => {
            const tr = document.createElement('tr');
            columns.forEach((column) => {
                const td = document.createElement('td');
                td.className = 'ps-3 small';
                const value = row[column];
                td.textContent = value === null || value === undefined ? '—' : String(value);
                tr.appendChild(td);
            });
            body.appendChild(tr);
        });
    }

    function updatePager() {
        const lastPage = Math.max(Math.ceil(state.total / state.perPage), 1);
        const start = state.total === 0 ? 0 : (state.page - 1) * state.perPage + 1;
        const end = Math.min(state.page * state.perPage, state.total);
        countEl.textContent = `${start}-${end} sur ${state.total} ligne(s)`;
        prevBtn.disabled = state.page <= 1;
        nextBtn.disabled = state.page >= lastPage;
    }

    function updateSortArrows() {
        headCells.forEach((th) => {
            const arrow = th.querySelector('.data-grid-sort-arrow');
            if (th.dataset.gridSort === state.sortColumn) {
                arrow.textContent = state.sortDirection === 'asc' ? '▲' : '▼';
            } else {
                arrow.textContent = '';
            }
        });
    }

    async function refresh() {
        const seq = ++requestSeq;
        const params = new URLSearchParams({
            page: state.page,
            per_page: state.perPage,
            search: state.search,
            sort_direction: state.sortDirection,
        });
        if (state.sortColumn) {
            params.set('sort_column', state.sortColumn);
        }

        const response = await fetch(`${rowsUrl}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        // A newer request (later search keystroke, sort click...) may have
        // started and already resolved while this one was in flight -
        // discard a response that no longer matches the latest request.
        if (seq !== requestSeq || !response.ok) {
            return;
        }

        const data = await response.json();
        if (seq !== requestSeq) {
            return;
        }

        state.total = data.total;
        state.page = data.page;
        renderRows(data.columns, data.rows);
        updatePager();
        updateSortArrows();
    }

    searchInput?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.search = searchInput.value;
            state.page = 1;
            refresh();
        }, 300);
    });

    prevBtn.addEventListener('click', () => {
        if (state.page > 1) {
            state.page -= 1;
            refresh();
        }
    });

    nextBtn.addEventListener('click', () => {
        state.page += 1;
        refresh();
    });

    headCells.forEach((th) => {
        th.addEventListener('click', () => {
            const column = th.dataset.gridSort;
            if (state.sortColumn === column) {
                state.sortDirection = state.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                state.sortColumn = column;
                state.sortDirection = 'asc';
            }
            state.page = 1;
            refresh();
        });
    });

    updatePager();
    updateSortArrows();
}

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('table-browser');
    if (container) {
        initGrid(container);
    }
});
