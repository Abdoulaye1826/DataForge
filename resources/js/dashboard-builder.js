/**
 * Drag/resize grid for the Dashboard builder (see dashboards/show.blade.php).
 * Every move/resize is persisted immediately via a PUT to the widget's own
 * update URL - GridStack itself never talks to the server, it just tells us
 * what changed.
 */
import { GridStack } from 'gridstack';
import 'gridstack/dist/gridstack.min.css';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function persist(item) {
    const url = item.el.dataset.updateUrl;
    if (!url) {
        return;
    }

    fetch(url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ x: item.x, y: item.y, w: item.w, h: item.h }),
    });
}

function initDashboardGrid() {
    const el = document.getElementById('dashboard-grid');
    if (!el) {
        return;
    }

    const grid = GridStack.init({ cellHeight: 80, margin: 8, float: true }, el);

    grid.on('change', (event, items) => {
        (items || []).forEach(persist);
    });
}

document.addEventListener('DOMContentLoaded', initDashboardGrid);
