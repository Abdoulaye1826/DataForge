/**
 * Module Insights: switches between "Par catégorie" (default, groups by
 * insight type) and "Par priorité" (flattens every category, sorted by
 * severity) - both views are already server-rendered, this just toggles
 * which one is visible so there is no extra request or state to manage.
 */
document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-insight-view-btn]');
    if (!button) {
        return;
    }

    const toggle = button.closest('[data-insight-view-toggle]');
    const card = toggle.closest('.df-card');
    const view = button.dataset.insightViewBtn;

    toggle.querySelectorAll('[data-insight-view-btn]').forEach((btn) => {
        btn.classList.toggle('active', btn === button);
    });

    card.querySelectorAll('[data-insight-view]').forEach((panel) => {
        panel.classList.toggle('d-none', panel.dataset.insightView !== view);
    });
});
