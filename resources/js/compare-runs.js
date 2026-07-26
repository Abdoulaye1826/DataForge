import { Modal } from 'bootstrap';

/**
 * Comparer les exécutions (inspiré d'OctOpus) : coche 2 ou 3 analyses ML ou
 * tests statistiques déjà affichés sur la page et affiche un tableau
 * comparatif côte à côte - entièrement côté client, à partir des données
 * déjà présentes dans le DOM (data-compare-payload), sans aller-retour serveur.
 */
document.addEventListener('DOMContentLoaded', () => {
    const checkboxes = document.querySelectorAll('[data-compare-checkbox]');
    const bar = document.querySelector('[data-compare-bar]');
    const count = document.querySelector('[data-compare-count]');
    const openBtn = document.querySelector('[data-compare-open]');
    const modalEl = document.querySelector('[data-compare-modal]');
    const table = document.querySelector('[data-compare-table]');

    if (checkboxes.length === 0 || !bar) {
        return;
    }

    const MAX_SELECTION = 3;

    const selected = () => [...checkboxes].filter((cb) => cb.checked);

    const refresh = () => {
        const n = selected().length;
        bar.classList.toggle('d-none', n < 2);
        if (count) {
            count.textContent = n;
        }

        checkboxes.forEach((cb) => {
            cb.disabled = !cb.checked && n >= MAX_SELECTION;
        });
    };

    checkboxes.forEach((cb) => cb.addEventListener('change', refresh));

    openBtn?.addEventListener('click', () => {
        const payloads = selected().map((cb) => JSON.parse(cb.dataset.comparePayload));
        const labels = Array.from(new Set(payloads.flatMap((p) => Object.keys(p))));

        table.innerHTML = '';

        const head = document.createElement('tr');
        head.innerHTML = '<th></th>' + payloads.map((p) => `<th>${p.__title ?? ''}</th>`).join('');
        table.appendChild(head);

        labels.filter((label) => label !== '__title').forEach((label) => {
            const row = document.createElement('tr');
            const cells = payloads.map((p) => `<td>${p[label] ?? '—'}</td>`).join('');
            row.innerHTML = `<th class="text-secondary small fw-normal">${label}</th>${cells}`;
            table.appendChild(row);
        });

        Modal.getOrCreateInstance(modalEl).show();
    });

    refresh();
});
