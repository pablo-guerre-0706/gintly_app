import { initNotifications } from './core/notifications';
import { initLoading } from './core/loading';

const modules = import.meta.glob([
    './modules/catalog/products.js',
    './modules/customers/index.js',
    './modules/finance/cash-closing.js',
    './modules/inventory/reconciliation.js',
    './modules/pos/index.js',
]);

initNotifications();
initLoading();

async function bootPage() {
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
