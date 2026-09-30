import { ApiError } from '@/core/api-client';
import { getSessionContext, SessionContextError } from '@/core/session-context';

function list(value) {
    return String(value ?? '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);
}

function isAuthorized(element, context) {
    const roles = list(element.dataset.roles);
    const capabilities = list(element.dataset.capabilities);
    const anyCapabilities = list(element.dataset.anyCapabilities);

    if (roles.length > 0 && !roles.includes(context.role)) return false;
    if (capabilities.length > 0 && !capabilities.every((capability) => context.capabilities.includes(capability))) return false;
    if (anyCapabilities.length > 0 && !anyCapabilities.some((capability) => context.capabilities.includes(capability))) return false;

    return roles.length > 0 || capabilities.length > 0 || anyCapabilities.length > 0;
}

function errorMessage(error) {
    if (error instanceof SessionContextError) return error.message;
    if (error instanceof ApiError && error.status === 403) return 'No tienes autorización para consultar esta sección.';
    if (error instanceof ApiError && error.status === 0) return 'No fue posible conectar con el servidor.';

    return 'No fue posible validar los módulos disponibles.';
}

export default async function init() {
    const root = document.querySelector('[data-hub-root]');

    if (!root || root.dataset.initialized === 'true') return;

    root.dataset.initialized = 'true';

    const grid = root.querySelector('[data-hub-grid]');
    const loading = root.querySelector('[data-hub-loading]');
    const empty = root.querySelector('[data-hub-empty]');
    const items = Array.from(root.querySelectorAll('[data-hub-item]'));

    try {
        const context = await getSessionContext();
        let visible = 0;

        items.forEach((item) => {
            const authorized = isAuthorized(item, context);
            item.hidden = !authorized;
            if (authorized) visible += 1;
        });

        loading.hidden = true;
        grid.hidden = visible === 0;
        empty.hidden = visible > 0;
    } catch (error) {
        loading.hidden = true;
        grid.hidden = true;
        empty.hidden = false;
        empty.querySelector('h2').textContent = 'No fue posible cargar la sección';
        empty.querySelector('p').textContent = errorMessage(error);
    }
}
