/**
 * Module "Connecteurs SQL" (projects/show.blade.php) : préremplit le port par
 * défaut selon le moteur choisi, et charge à la demande la liste des tables
 * distantes d'une connexion enregistrée (GET JSON, voir
 * DatabaseConnectionController::tables()) pour proposer un import direct
 * sans recharger la page tant qu'on n'a pas cliqué "Importer".
 */

const DEFAULT_PORTS = { pgsql: 5432, mysql: 3306 };

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function initPortAutofill() {
    const driverSelect = document.querySelector('[data-db-driver-select]');
    const portInput = document.querySelector('[data-db-port-input]');

    if (!driverSelect || !portInput) {
        return;
    }

    driverSelect.addEventListener('change', () => {
        const defaultPort = DEFAULT_PORTS[driverSelect.value];
        if (defaultPort && (portInput.value === '' || Object.values(DEFAULT_PORTS).includes(Number(portInput.value)))) {
            portInput.value = defaultPort;
        }
    });
}

function hiddenInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
}

function submitImport(importUrl, tableName) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = importUrl;
    form.appendChild(hiddenInput('_token', csrfToken()));
    form.appendChild(hiddenInput('table_name', tableName));
    document.body.appendChild(form);
    form.submit();
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
}

function renderTables(listEl, tables, importUrl) {
    if (tables.length === 0) {
        listEl.innerHTML = '<p class="text-secondary small mb-0">Aucune table trouvée sur cette base.</p>';
        return;
    }

    // Les noms de table viennent du catalogue de la base connectée, pas de
    // notre propre schéma - échappés avant insertion dans le DOM au cas où
    // l'utilisateur se connecte à une base dont il ne maîtrise pas tout le
    // contenu (identifiants avec guillemets/caractères spéciaux autorisés
    // par certains moteurs).
    const rows = tables.map((t) => `
        <tr>
            <td class="fw-semibold small">${escapeHtml(t.name)}</td>
            <td class="small text-secondary">${t.column_count} colonnes</td>
            <td class="small text-secondary">${t.row_count.toLocaleString('fr-FR')} lignes</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-primary" data-db-import-table="${escapeHtml(t.name)}">Importer</button>
            </td>
        </tr>
    `).join('');

    listEl.innerHTML = `
        <table class="table table-sm mb-0">
            <tbody>${rows}</tbody>
        </table>
    `;

    listEl.querySelectorAll('[data-db-import-table]').forEach((button) => {
        button.addEventListener('click', () => submitImport(importUrl, button.dataset.dbImportTable));
    });
}

function initConnectionBrowsing() {
    document.querySelectorAll('[data-db-connection-browse]').forEach((button) => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-db-connection-row]');
            const tablesRow = row.nextElementSibling;
            const listEl = tablesRow.querySelector('[data-db-connection-tables-list]');
            const tablesUrl = row.dataset.dbConnectionTablesUrl;
            const importUrl = row.dataset.dbConnectionImportUrl;

            const nowHidden = !tablesRow.classList.contains('d-none');
            if (nowHidden) {
                tablesRow.classList.add('d-none');
                return;
            }

            tablesRow.classList.remove('d-none');

            if (listEl.dataset.loaded === 'true') {
                return;
            }

            listEl.innerHTML = '<p class="text-secondary small mb-0">Chargement...</p>';

            fetch(tablesUrl, { headers: { Accept: 'application/json' } })
                .then((response) => response.json().then((data) => ({ ok: response.ok, data })))
                .then(({ ok, data }) => {
                    if (!ok) {
                        listEl.innerHTML = `<p class="text-danger small mb-0">${data.message ?? 'Erreur de connexion.'}</p>`;
                        return;
                    }
                    listEl.dataset.loaded = 'true';
                    renderTables(listEl, data.tables, importUrl);
                })
                .catch(() => {
                    listEl.innerHTML = '<p class="text-danger small mb-0">Erreur réseau.</p>';
                });
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initPortAutofill();
    initConnectionBrowsing();
});
