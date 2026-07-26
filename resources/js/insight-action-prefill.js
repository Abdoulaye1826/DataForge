import { Modal } from 'bootstrap';

/**
 * Module IA proactive (§4): landing on a page via an insight's suggested
 * action button (?prefill_type=...&prefill_params=...) opens the relevant
 * "create" modal already on that page and pre-fills its fields, reusing the
 * existing df-dynamic-select/data-steps reveal mechanism (transform-modal.js)
 * rather than duplicating it - dispatching a real "change" event on the type
 * select is what makes the right fields appear and become enabled.
 *
 * Nothing is ever submitted automatically: the user still has to review the
 * pre-filled form and click the real submit button themselves.
 */
const CONFIGS = {
    forecast: { modalSelector: '#newMlAnalysisModal', typeField: 'analysis_type', typeValue: 'forecast' },
    statistical_test: { modalSelector: '#newStatisticalTestModal', typeField: 'test_type' },
    visualization: { modalSelector: '#newVisualizationModal', typeField: 'chart_type' },
    cleaning_step: { modalSelector: null, typeField: 'step_type' },
};

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const type = params.get('prefill_type');
    const config = CONFIGS[type];

    if (!config) {
        return;
    }

    let data = {};
    try {
        data = JSON.parse(params.get('prefill_params') || '{}');
    } catch {
        return;
    }

    const tableId = params.get('prefill_table_id');
    const modalSelector = config.modalSelector ?? (tableId ? `#transformModal${tableId}` : null);
    const modalEl = modalSelector ? document.querySelector(modalSelector) : null;

    if (!modalEl) {
        return;
    }

    const form = modalEl.querySelector('form');
    const typeSelect = form.querySelector(`[name="${config.typeField}"]`);

    if (!typeSelect) {
        return;
    }

    typeSelect.value = config.typeValue ?? data[config.typeField] ?? '';
    typeSelect.dispatchEvent(new Event('change', { bubbles: true }));

    Object.entries(data).forEach(([key, value]) => {
        if (key === config.typeField) {
            return;
        }

        const field = form.querySelector(`[name="${key}"], [name="${key}[]"]`);

        if (!field) {
            return;
        }

        if (field.multiple && Array.isArray(value)) {
            [...field.options].forEach((option) => {
                option.selected = value.includes(option.value);
            });
        } else {
            field.value = value;
        }
    });

    Modal.getOrCreateInstance(modalEl).show();
});
