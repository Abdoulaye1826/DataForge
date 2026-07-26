/**
 * Module Import: drag-and-drop file picker backed by the same hidden
 * <input type="file" multiple> the form already submits - purely a frontend
 * affordance, no backend change needed. Repeated drops/browses append to the
 * current selection instead of replacing it, since FileList is immutable and
 * has to be rebuilt through a DataTransfer each time.
 */
function formatSize(bytes) {
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
}

function extensionOf(name) {
    const parts = name.split('.');
    return parts.length > 1 ? parts.pop().toUpperCase() : '';
}

function initDropzone(zone) {
    const input = zone.querySelector('[data-dropzone-input]');
    const idle = zone.querySelector('[data-dropzone-idle]');
    const list = zone.querySelector('[data-dropzone-list]');

    function render() {
        const files = Array.from(input.files || []);

        if (files.length === 0) {
            idle.classList.remove('d-none');
            list.classList.add('d-none');
            list.innerHTML = '';
            return;
        }

        idle.classList.add('d-none');
        list.classList.remove('d-none');
        list.innerHTML = '';

        files.forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'df-dropzone-file';

            const badge = document.createElement('span');
            badge.className = 'df-dropzone-ext';
            badge.textContent = extensionOf(file.name);

            const name = document.createElement('span');
            name.className = 'df-dropzone-name';
            name.textContent = file.name;

            const size = document.createElement('span');
            size.className = 'df-dropzone-size';
            size.textContent = formatSize(file.size);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'df-dropzone-remove';
            remove.setAttribute('aria-label', `Retirer ${file.name}`);
            remove.textContent = '×';
            remove.addEventListener('click', (event) => {
                event.stopPropagation();
                setFiles(files.filter((_, i) => i !== index));
            });

            li.append(badge, name, size, remove);
            list.appendChild(li);
        });

        const more = document.createElement('li');
        more.className = 'df-dropzone-more';
        more.textContent = '+ ajouter d\'autres fichiers';
        list.appendChild(more);
    }

    function setFiles(files) {
        const transfer = new DataTransfer();
        files.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        render();
    }

    function addFiles(newFiles) {
        setFiles([...Array.from(input.files || []), ...Array.from(newFiles)]);
    }

    zone.addEventListener('click', () => input.click());
    input.addEventListener('click', (event) => event.stopPropagation());
    input.addEventListener('change', () => render());

    ['dragenter', 'dragover'].forEach((type) => {
        zone.addEventListener(type, (event) => {
            event.preventDefault();
            zone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'dragend'].forEach((type) => {
        zone.addEventListener(type, () => zone.classList.remove('is-dragover'));
    });

    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        zone.classList.remove('is-dragover');
        if (event.dataTransfer?.files?.length) {
            addFiles(event.dataTransfer.files);
        }
    });

    render();
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-dropzone]').forEach(initDropzone);
});
