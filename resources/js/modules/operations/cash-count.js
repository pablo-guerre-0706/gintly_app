import { CashCounting } from './cash-counting';

export default function init() {
    const root = document.querySelector('[data-cash-count]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    void new CashCounting(root, 'count').init();
}
