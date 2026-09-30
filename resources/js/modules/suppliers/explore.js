import { createMapProvider } from '@/maps/provider-adapter';

export default function init() {
    const root = document.querySelector('[data-supplier-explorer]');
    if (!root || root.dataset.initialized === 'true') return;

    root.dataset.initialized = 'true';
    const provider = createMapProvider();
    const status = root.querySelector('[data-map-status]');
    const message = root.querySelector('[data-map-status-message]');

    if (!provider.configured) {
        status.dataset.providerState = 'unavailable';
        message.textContent = 'La búsqueda geográfica permanece desactivada hasta que se apruebe un proveedor, su política de costos y una clave restringida por origen.';
    }
}
