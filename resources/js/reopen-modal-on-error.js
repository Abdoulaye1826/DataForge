import { Modal } from 'bootstrap';

/**
 * Reopens a modal left open by a failed form submission: the server
 * redirects back with validation errors, but the page reload closes any
 * modal that was open. Blade marks which modal to reopen via
 * data-reopen-modal-on-error so this stays generic across pages/modals.
 */
document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.querySelector('[data-reopen-modal-on-error]');

    if (modalEl) {
        Modal.getOrCreateInstance(modalEl).show();
    }
});
