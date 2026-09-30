export function setSectionState(id, state, message = null) {
    const section = document.querySelector(`[data-dashboard-section="${id}"]`);
    if (!section) return;

    const loading = section.querySelector('[data-section-loading]');
    const error = section.querySelector('[data-section-error]');
    const empty = section.querySelector('[data-section-empty]');
    const content = section.querySelector('[data-section-content]');
    const errorMessage = section.querySelector('[data-section-error-message]');

    loading.hidden = state !== 'loading';
    error.hidden = state !== 'error';
    empty.hidden = state !== 'empty';
    content.hidden = state !== 'ready';
    section.setAttribute('aria-busy', String(state === 'loading'));

    if (state === 'error' && message && errorMessage) {
        errorMessage.textContent = message;
    }

    if (state === 'empty' && message && empty) {
        empty.textContent = message;
    }
}
