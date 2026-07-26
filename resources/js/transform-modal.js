/**
 * Shows/hides the parameter fields relevant to the selected option in any
 * "pick one, reveal its fields" modal - the transformation modal
 * (.df-transform-select, see components/transform-modal.blade.php) and the
 * visualization builder (.df-dynamic-select, see visualizations/index).
 * Each field group carries a data-steps="a,b,c" list of option values it
 * applies to; only the ones matching the current selection stay visible
 * (and only they get submitted as non-disabled, so unrelated params never
 * reach the server).
 */
document.addEventListener('change', (event) => {
    if (!event.target.matches('.df-transform-select, .df-dynamic-select')) {
        return;
    }

    const select = event.target;
    const modal = select.closest('.modal');
    const selected = select.value;

    modal.querySelectorAll('.df-field').forEach((field) => {
        const steps = (field.dataset.steps || '').split(',');
        const visible = steps.includes(selected);

        field.classList.toggle('d-none', !visible);
        field.querySelectorAll('input, select, textarea').forEach((input) => {
            input.disabled = !visible;
        });
    });
});

/**
 * Dashboard KPI widget form: the column select lists every column from every
 * table (each option tagged data-table-id) so it can be filtered down to the
 * table actually picked in the sibling .df-kpi-table select - otherwise
 * nothing stops picking a column that doesn't belong to the chosen table.
 */
document.addEventListener('change', (event) => {
    if (!event.target.matches('.df-kpi-table')) {
        return;
    }

    const modal = event.target.closest('.modal');
    const tableId = event.target.value;
    const columnSelect = modal.querySelector('select[name="kpi_column"]');

    columnSelect.value = '';
    columnSelect.querySelectorAll('.df-kpi-column-option').forEach((option) => {
        const matches = option.dataset.tableId === tableId;
        option.classList.toggle('d-none', !matches);
        option.disabled = !matches;
    });
});

/**
 * Statistical test modal (t-test): the "groupe A/B" text inputs need to know
 * which actual values exist in the chosen group column - each <option> in
 * .df-group-column carries its column's distinct values as JSON so we don't
 * need an AJAX round-trip just to populate an autocomplete list.
 */
document.addEventListener('change', (event) => {
    if (!event.target.matches('.df-group-column')) {
        return;
    }

    const select = event.target;
    const modal = select.closest('.modal');
    const selectedOption = select.selectedOptions[0];
    const values = selectedOption?.dataset.values ? JSON.parse(selectedOption.dataset.values) : [];

    const datalist = modal.querySelector('#groupValuesList');
    datalist.innerHTML = '';
    values.forEach((value) => {
        const option = document.createElement('option');
        option.value = value;
        datalist.appendChild(option);
    });

    const hint = modal.querySelector('[data-group-values-hint]');
    if (hint) {
        hint.textContent = values.length ? values.join(', ') : '—';
    }
});

/**
 * Module Contexte métier: any select with data-reveal-other="#selector"
 * shows/enables that field only when "other" is picked - used independently
 * by the domain and objective selects (two separate reveals in the same
 * form, unlike the df-dynamic-select pattern above which drives one shared
 * field set from a single select).
 */
document.addEventListener('change', (event) => {
    const select = event.target.closest('[data-reveal-other]');
    if (!select) {
        return;
    }

    const field = document.querySelector(select.dataset.revealOther);
    if (!field) {
        return;
    }

    const visible = select.value === 'other';
    field.classList.toggle('d-none', !visible);
    field.querySelectorAll('input, select, textarea').forEach((input) => {
        input.disabled = !visible;
    });
});
