/**
 * Palette de commandes (Ctrl/Cmd+K) - quick navigation across projects and
 * common actions, inspired by the "Type a command..." pattern seen in
 * OctOpus. Data is embedded server-side (see layouts/app.blade.php) rather
 * than fetched, since a user's project list is small and already cheap to
 * load on every page.
 */
document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('df-command-palette-data');
    const palette = document.querySelector('[data-command-palette]');
    const trigger = document.querySelector('[data-command-trigger]');
    const backdrop = document.querySelector('[data-command-backdrop]');
    const input = document.querySelector('[data-command-input]');
    const results = document.querySelector('[data-command-results]');

    if (!dataEl || !palette || !input || !results) {
        return;
    }

    const data = JSON.parse(dataEl.textContent);
    const items = [...data.global, ...data.project, ...data.projects];
    let activeIndex = 0;

    const open = () => {
        palette.hidden = false;
        input.value = '';
        render(items);
        input.focus();
    };

    const close = () => {
        palette.hidden = true;
    };

    const render = (list) => {
        results.innerHTML = '';
        activeIndex = 0;

        if (list.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'df-command-empty';
            empty.textContent = 'Aucun résultat.';
            results.appendChild(empty);
            return;
        }

        list.forEach((item, index) => {
            const li = document.createElement('li');
            li.className = 'df-command-item' + (index === 0 ? ' active' : '');
            li.dataset.url = item.url;
            li.innerHTML = `<span>${item.label}</span><span class="df-command-hint">${item.hint}</span>`;
            li.addEventListener('mouseenter', () => setActive(index));
            li.addEventListener('click', () => go(item.url));
            results.appendChild(li);
        });
    };

    const setActive = (index) => {
        const children = [...results.children];
        children.forEach((el) => el.classList.remove('active'));
        activeIndex = (index + children.length) % children.length;
        children[activeIndex]?.classList.add('active');
    };

    const go = (url) => {
        window.location.href = url;
    };

    const filter = () => {
        const query = input.value.trim().toLowerCase();
        if (query === '') {
            render(items);
            return;
        }
        render(items.filter((item) => item.label.toLowerCase().includes(query)));
    };

    trigger?.addEventListener('click', open);
    backdrop?.addEventListener('click', close);

    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            palette.hidden ? open() : close();
            return;
        }

        if (palette.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            close();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(activeIndex - 1);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const active = results.children[activeIndex];
            if (active?.dataset.url) {
                go(active.dataset.url);
            }
        }
    });

    input.addEventListener('input', filter);
});
