import { CashCounting } from './cash-counting';

export default function init() {
    const root = document.querySelector('[data-cash-close]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    const page = new CashCounting(root, 'close');
    const dialog = root.querySelector('[data-cash-confirm-dialog]');
    dialog.querySelector('[data-cash-confirm-cancel]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-cash-confirm-submit]').addEventListener('click', () => {
        try { void page.persist({ ...page.grid.payload(), closing_notes: page.form.elements.namedItem('closing_notes').value.trim() || null }); }
        catch (error) { dialog.close(); const message = root.querySelector('[data-cash-field-error]'); message.textContent = error.message; message.hidden = false; }
    });
    void page.init();
}
