/**
 * Async queue (import, pipeline steps, ML, statistical tests, visualizations):
 * while any element marked [data-pending-poll] is on the page, reload
 * periodically until the backing job has settled the row to its final
 * status. A full reload rather than a partial AJAX refresh - simplest
 * correct option for a single-user/demo-scale app, and it naturally picks
 * up every kind of change (new charts, updated tables, etc.) without a
 * per-page polling endpoint.
 */
(function poll() {
    if (!document.querySelector('[data-pending-poll]')) {
        return;
    }

    setTimeout(() => window.location.reload(), 4000);
})();
