import 'bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const validationSummary = document.querySelector('[data-validation-summary]');

    if (validationSummary) {
        validationSummary.focus();
    }

    const confirmationModal = document.getElementById('confirmationModal');

    if (!confirmationModal) {
        return;
    }

    let formToSubmit = null;
    const title = confirmationModal.querySelector('#confirmationModalTitle');
    const message = confirmationModal.querySelector('#confirmationModalMessage');
    const submitButton = confirmationModal.querySelector('[data-confirm-submit]');

    confirmationModal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        const formId = trigger?.getAttribute('data-confirm-form');

        formToSubmit = formId ? document.getElementById(formId) : null;
        title.textContent = trigger?.getAttribute('data-confirm-title') || 'Confirm action';
        message.textContent = trigger?.getAttribute('data-confirm-message') || 'Are you sure you want to continue?';
        submitButton.textContent = trigger?.getAttribute('data-confirm-button') || 'Confirm';
        submitButton.className = `btn ${trigger?.getAttribute('data-confirm-class') || 'btn-danger'}`;
    });

    confirmationModal.addEventListener('hidden.bs.modal', () => {
        formToSubmit = null;
    });

    submitButton.addEventListener('click', () => {
        if (formToSubmit) {
            formToSubmit.requestSubmit();
        }
    });
});
