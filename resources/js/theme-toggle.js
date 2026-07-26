/**
 * Click handler for the sidebar's light/dark toggle. The initial theme
 * resolution (stored choice, else system preference) happens in a small
 * inline script in the <head> of layouts/app.blade.php instead - it has to
 * run synchronously before first paint to avoid a flash of the wrong theme,
 * which a bundled/deferred module like this one can't guarantee.
 */
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (!toggle) {
        return;
    }

    const root = document.documentElement;
    const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-bs-theme', next);
    localStorage.setItem('df-theme', next);
});
