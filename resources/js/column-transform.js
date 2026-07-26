import { Modal } from 'bootstrap';

/**
 * Module config par colonne (inspiré de Power Query, page Données) : cliquer
 * sur l'icône ⚙ d'un en-tête ouvre la modale de transformation partagée
 * (voir components/transform-modal.blade.php), déjà scopée sur cette colonne
 * une fois l'opération choisie, avec les suggestions IA de l'IA pour cette
 * colonne affichées en haut si elle en a. Le tri au clic sur le nom de
 * colonne (data-grid-sort, voir data-grid.js) n'est pas affecté - c'est un
 * bouton distinct dans le même en-tête.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function loadSuggestions() {
    const el = document.getElementById('df-column-suggestions-data');
    if (!el) {
        return [];
    }

    try {
        return JSON.parse(el.textContent);
    } catch {
        return [];
    }
}

function hiddenInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
}

function submitAction(url) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    form.appendChild(hiddenInput('_token', csrfToken()));
    document.body.appendChild(form);
    form.submit();
}

function renderColumnSuggestions(modal, columnName, suggestions) {
    const panel = modal.querySelector('[data-column-suggestions-panel]');
    const list = modal.querySelector('[data-column-suggestions-list]');
    const matches = suggestions.filter((s) => s.columns.includes(columnName));

    list.innerHTML = '';

    if (matches.length === 0) {
        panel.classList.add('d-none');
        return;
    }

    matches.forEach((suggestion) => {
        const card = document.createElement('div');
        card.className = 'df-column-suggestion-card';

        const title = document.createElement('div');
        title.className = 'small fw-semibold';
        title.textContent = suggestion.step_type_label;

        const rationale = document.createElement('div');
        rationale.className = 'small text-secondary fst-italic mb-2';
        rationale.textContent = `💡 ${suggestion.rationale}`;

        const actions = document.createElement('div');
        actions.className = 'd-flex gap-1';

        const acceptBtn = document.createElement('button');
        acceptBtn.type = 'button';
        acceptBtn.className = 'btn btn-outline-primary btn-sm';
        acceptBtn.textContent = 'Accepter';
        acceptBtn.addEventListener('click', () => submitAction(suggestion.accept_url));

        const rejectBtn = document.createElement('button');
        rejectBtn.type = 'button';
        rejectBtn.className = 'btn btn-outline-secondary btn-sm';
        rejectBtn.textContent = 'Rejeter';
        rejectBtn.addEventListener('click', () => submitAction(suggestion.reject_url));

        actions.appendChild(acceptBtn);
        actions.appendChild(rejectBtn);
        card.appendChild(title);
        card.appendChild(rationale);
        card.appendChild(actions);
        list.appendChild(card);
    });

    panel.classList.remove('d-none');
}

/**
 * Bouton "changer le type" du menu déroulant de l'en-tête : soumet
 * directement une étape convert_type sans passer par la modale générale -
 * l'action pointe vers la même route que le formulaire de transformation
 * (data-transform-form), lu une fois sur la page plutôt que reconstruit ici.
 */
function submitQuickConvert(actionUrl, columnName, targetType) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = actionUrl;
    form.appendChild(hiddenInput('_token', csrfToken()));
    form.appendChild(hiddenInput('step_type', 'convert_type'));
    form.appendChild(hiddenInput('column', columnName));
    form.appendChild(hiddenInput('target_type', targetType));
    form.appendChild(hiddenInput('return_to_data', '1'));
    document.body.appendChild(form);
    form.submit();
}

document.addEventListener('DOMContentLoaded', () => {
    const transformForm = document.querySelector('[data-transform-form]');

    document.querySelectorAll('[data-quick-convert-column]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            if (!transformForm) {
                return;
            }
            submitQuickConvert(transformForm.action, button.dataset.quickConvertColumn, button.dataset.quickConvertTarget);
        });
    });

    const configureButtons = document.querySelectorAll('[data-column-configure]');

    if (configureButtons.length === 0) {
        return;
    }

    const suggestions = loadSuggestions();

    configureButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();

            const columnName = button.dataset.columnConfigure;
            const form = document.querySelector('[data-transform-form]');
            const modal = form?.closest('.modal');
            if (!form || !modal) {
                return;
            }

            const select = form.querySelector('.df-transform-select');
            const returnField = form.querySelector('[data-return-to-data]');
            const title = modal.querySelector('[data-transform-title]');

            modal.dataset.pendingColumn = columnName;
            returnField.value = '1';
            if (title) {
                title.textContent = `Configurer la colonne « ${columnName} »`;
            }

            // Repart d'un état vierge à chaque ouverture - dispatché AVANT le
            // rendu des suggestions pour que transform-modal.js (importé
            // plus tôt, donc exécuté en premier sur cet évènement) masque
            // déjà tous les champs d'opération avant que l'utilisateur n'en
            // choisisse une nouvelle.
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));

            renderColumnSuggestions(modal, columnName, suggestions);

            Modal.getOrCreateInstance(modal).show();
        });
    });

    // Une fois les champs révélés pour l'opération choisie (voir
    // transform-modal.js, qui tourne avant ce listener puisqu'importé avant
    // dans app.js), préremplit le champ colonne concerné avec la colonne
    // visée par l'ouverture - l'utilisateur reste libre de le changer.
    document.addEventListener('change', (event) => {
        if (!event.target.matches('.df-transform-select')) {
            return;
        }

        const modal = event.target.closest('.modal');
        const columnName = modal?.dataset.pendingColumn;
        if (!columnName) {
            return;
        }

        const singleColumn = modal.querySelector('select[name="column"]:not(:disabled)');
        if (singleColumn && [...singleColumn.options].some((o) => o.value === columnName)) {
            singleColumn.value = columnName;
        }

        const oldName = modal.querySelector('select[name="old_name"]:not(:disabled)');
        if (oldName && [...oldName.options].some((o) => o.value === columnName)) {
            oldName.value = columnName;
        }

        const multiColumns = modal.querySelector('select[name="columns[]"]:not(:disabled)');
        if (multiColumns) {
            [...multiColumns.options].forEach((option) => {
                option.selected = option.value === columnName;
            });
        }
    });
});
