import { initNotifications } from './core/notifications';
import { initLoading } from './core/loading';
import { initPanelShell } from './shell/panel-shell';

const modules = import.meta.glob([
    './modules/catalog/products.js',
    './modules/customers/index.js',
    './modules/dashboard/index.js',
    './modules/administration/anomalies.js',
    './modules/administration/cash-sessions.js',
    './modules/administration/reconciliations.js',
    './modules/finance/cash-closing.js',
    './modules/hubs/index.js',
    './modules/inventory/reconciliation.js',
    './modules/landing/index.js',
    './modules/organization/branches/index.js',
    './modules/organization/users/access.js',
    './modules/organization/users/create.js',
    './modules/organization/users/index.js',
    './modules/operations/cash.js',
    './modules/operations/dispatches.js',
    './modules/operations/physical-count.js',
    './modules/operations/receivables.js',
    './modules/operations/resource-list.js',
    './modules/operations/stock-transfer-create.js',
    './modules/operations/purchase-order-create.js',
    './modules/operations/goods-receipt-create.js',
    './modules/operations/sales-return-create.js',
    './modules/pos/index.js',
    './modules/reports/summary.js',
    './modules/supervision/resource-list.js',
    './modules/suppliers/explore.js',
]);

initNotifications();
initLoading();

async function bootPage() {
    const shellReady = await initPanelShell();
    if (shellReady === false) return;

    const page = document.documentElement.dataset.page?.trim();

    if (!page) {
        return;
    }

    const key = `./modules/${page}.js`;
    const loader = modules[key];

    if (!loader) {
        console.warn(`[Gintly] Página sin módulo JS registrado: ${page}`);
        return;
    }

    try {
        const module = await loader();

        if (typeof module.default === 'function') {
            await module.default();
        }
    } catch (error) {
        console.error(`[Gintly] Error iniciando ${page}:`, error);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        bootPage,
        { once: true },
    );
} else {
    bootPage();
}
