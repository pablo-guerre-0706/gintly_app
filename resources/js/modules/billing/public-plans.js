import { readPreference, savePreference } from '../../core/billing-storage.js';
export function initPublicPlans() {
    const root = document.querySelector('[data-public-plans]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    let period = readPreference()?.period ?? 'monthly';
    const render = () => {
        root.querySelectorAll('[data-public-period]').forEach(button => {
            const selected = button.dataset.publicPeriod === period;
            button.setAttribute('aria-pressed', String(selected));
            button.classList.toggle('bg-gintly-sidebar', selected); button.classList.toggle('text-white', selected);
            button.classList.toggle('text-gintly-sidebar', !selected);
        });
        root.querySelectorAll('[data-public-price]').forEach(node => { node.hidden = node.dataset.publicPrice !== period; });
    };
    root.addEventListener('click', event => {
        const toggle = event.target.closest('[data-public-period]');
        if (toggle) { period = toggle.dataset.publicPeriod; render(); }
        const link = event.target.closest('[data-public-select]');
        if (link) savePreference({ plan: link.dataset.publicSelect, period });
    });
    render();
}
