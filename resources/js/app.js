import { initNotifications } from './core/notifications';
import { initLoading } from './core/loading';

const modules = import.meta.glob('./modules/*/.js');

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
        console.error(`[Gintly] Módulo JS no encontrado: ${key}`);
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

