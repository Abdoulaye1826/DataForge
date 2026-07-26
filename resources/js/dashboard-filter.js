/**
 * Module Dashboard "filtre global": one column picker (+ value, or a date
 * range) that re-fetches and redraws every chart widget whose underlying
 * table actually has that column - other widgets are left untouched, since
 * a dashboard can mix charts built from different tables.
 */
function columnMeta(columns, name) {
    return columns.find((column) => column.name === name);
}

function currentFilterParams(columns, columnSelect, valueSelect, startInput, endInput) {
    const meta = columnMeta(columns, columnSelect.value);
    if (!meta) {
        return null;
    }

    if (meta.type === 'categorical' && valueSelect.value) {
        return { meta, params: { filter_column: meta.name, filter_operator: 'eq', filter_value: valueSelect.value } };
    }

    if (meta.type === 'date' && (startInput.value || endInput.value)) {
        const params = { filter_column: meta.name, filter_operator: 'between' };
        if (startInput.value) params.filter_start = startInput.value;
        if (endInput.value) params.filter_end = endInput.value;
        return { meta, params };
    }

    return { meta, params: null };
}

function initDashboardFilter(bar) {
    const columns = JSON.parse(bar.dataset.columns || '[]');
    const columnSelect = bar.querySelector('[data-filter-column]');
    const valueSelect = bar.querySelector('[data-filter-value]');
    const startInput = bar.querySelector('[data-filter-start]');
    const endInput = bar.querySelector('[data-filter-end]');
    const sep = bar.querySelector('[data-filter-date-sep]');
    const resetBtn = bar.querySelector('[data-filter-reset]');
    const status = bar.querySelector('[data-filter-status]');
    let requestSeq = 0;

    function updateFieldVisibility() {
        const meta = columnMeta(columns, columnSelect.value);
        const isCategorical = meta?.type === 'categorical';
        const isDate = meta?.type === 'date';

        valueSelect.classList.toggle('d-none', !isCategorical);
        startInput.classList.toggle('d-none', !isDate);
        endInput.classList.toggle('d-none', !isDate);
        sep.classList.toggle('d-none', !isDate);

        if (isCategorical) {
            valueSelect.innerHTML = '<option value="">—</option>';
            meta.values.forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                valueSelect.appendChild(option);
            });
        }
    }

    async function applyFilter() {
        // A later call (another keystroke/selection) may finish before this
        // one - guard so a stale response never overwrites a newer render.
        const seq = ++requestSeq;

        const result = currentFilterParams(columns, columnSelect, valueSelect, startInput, endInput);
        const widgetEls = document.querySelectorAll('[data-widget-data-url]');

        let affected = 0;

        for (const el of widgetEls) {
            const tableId = parseInt(el.dataset.widgetTableId, 10);
            const applies = result?.meta && result.meta.table_ids.includes(tableId) && result.params;

            const url = new URL(el.dataset.widgetDataUrl, window.location.origin);
            if (applies) {
                Object.entries(result.params).forEach(([key, value]) => url.searchParams.set(key, value));
                affected += 1;
            }

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (seq !== requestSeq || !response.ok) {
                    continue;
                }
                const json = await response.json();
                if (seq !== requestSeq) {
                    continue;
                }
                const container = el.querySelector('[data-chart-type]');
                if (container && json.chart_type) {
                    window.DataForgeCharts.updateChart(container, json.data);
                }
            } catch (error) {
                console.error('Dashboard filter refresh failed', error);
            }
        }

        if (seq === requestSeq) {
            status.textContent = result?.params ? `${affected} widget(s) filtré(s)` : '';
        }
    }

    columnSelect.addEventListener('change', () => {
        updateFieldVisibility();
        applyFilter();
    });
    valueSelect.addEventListener('change', applyFilter);
    startInput.addEventListener('change', applyFilter);
    endInput.addEventListener('change', applyFilter);
    resetBtn.addEventListener('click', () => {
        columnSelect.value = '';
        updateFieldVisibility();
        applyFilter();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const bar = document.getElementById('dashboard-filter');
    if (bar) {
        initDashboardFilter(bar);
    }
});
