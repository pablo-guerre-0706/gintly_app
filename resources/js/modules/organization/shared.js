import { api, ApiError, initializeCsrf } from '@/core/api-client';

export const ROLE_LABELS = Object.freeze({
    'ROL-01': 'Propietario',
    'ROL-02': 'Administrador',
    'ROL-03': 'Operativo',
});

const ROLE_LEVELS = Object.freeze({
    'ROL-01': 3,
    'ROL-02': 2,
    'ROL-03': 1,
});

export function grantableRoles(actorRole) {
    const actorLevel = ROLE_LEVELS[actorRole] ?? 0;

    return Object.entries(ROLE_LEVELS)
        .filter(([, level]) => level <= actorLevel)
        .map(([value]) => ({ value, label: ROLE_LABELS[value] }));
}

export async function mutate(method, path, data, options = {}) {
    const send = () => api[method](path, data, { dispatchErrors: false, ...options });

    try {
        return await send();
    } catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419) throw error;

        await initializeCsrf({ dispatchErrors: false });
        return send();
    }
}

export async function fetchPaginatedCollection(path, query = {}, options = {}) {
    const items = [];
    let page = 1;
    let lastPage = 1;

    do {
        const response = await api.get(path, { ...query, page }, options);

        if (!Array.isArray(response?.data)) {
            throw new Error('El servidor no devolvió una colección paginada válida.');
        }

        items.push(...response.data);

        const reportedLastPage = Number(response?.meta?.last_page ?? page);
        lastPage = Number.isInteger(reportedLastPage) && reportedLastPage >= page
            ? reportedLastPage
            : page;
        page += 1;
    } while (page <= lastPage);

    return items;
}

export async function fetchActiveBranches(path) {
    const branches = await fetchPaginatedCollection(
        path,
        { per_page: 100, is_active: true, sort: 'name', direction: 'asc' },
        { dispatchErrors: false },
    );
    return branches.filter((branch) => branch.is_active === true && Number.isInteger(branch.id));
}

export function responseMessage(error, fallback = 'No fue posible completar la operación.') {
    if (!(error instanceof ApiError)) return fallback;
    if (error.status === 0) return 'No fue posible conectar con el servidor. Conservamos los datos para que puedas reintentar.';
    if (error.status === 401) return 'La sesión expiró. Inicia sesión nuevamente.';
    if (error.status === 403) return error.message || 'No tienes autorización para realizar esta operación.';
    if (error.status === 409) return error.message || 'La operación entra en conflicto con el estado actual.';
    if (error.status === 419) return 'La protección de la sesión expiró y no pudo recuperarse. Recarga la página.';
    if (error.status === 422) return error.message || 'Revisa los campos indicados.';
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes. Espera un momento y reintenta.';
    if (error.status >= 500) return 'El servidor no pudo completar la operación. Puedes reintentar sin perder los datos.';

    return error.message || fallback;
}

export function clearFieldErrors(form) {
    form.querySelectorAll('[aria-invalid="true"]').forEach((field) => field.removeAttribute('aria-invalid'));
    form.querySelectorAll('[data-error-for]').forEach((element) => {
        element.textContent = '';
        element.hidden = true;
    });
}

function fieldFor(form, name) {
    return form.elements.namedItem(name)
        ?? form.elements.namedItem(`${name}[]`)
        ?? form.elements.namedItem(name.split('.')[0]);
}

export function showFieldErrors(form, errors = {}) {
    clearFieldErrors(form);
    let first = null;

    Object.entries(errors).forEach(([key, messages]) => {
        const normalized = key.startsWith('profiles.') ? 'profiles' : key;
        const field = fieldFor(form, normalized);
        const error = form.querySelector(`[data-error-for="${normalized}"]`);
        const message = Array.isArray(messages) ? messages[0] : messages;

        if (field instanceof RadioNodeList) {
            Array.from(field).forEach((control) => control.setAttribute('aria-invalid', 'true'));
            first ??= field[0];
        } else if (field instanceof HTMLElement) {
            field.setAttribute('aria-invalid', 'true');
            first ??= field;
        }

        if (error && message) {
            error.textContent = String(message);
            error.hidden = false;
        }
    });

    first?.focus();
    return first;
}

export function setButtonBusy(button, busy, busyText = 'Guardando…') {
    if (!button) return;

    if (busy) {
        button.dataset.originalText = button.textContent.trim();
        button.textContent = busyText;
    } else if (button.dataset.originalText) {
        button.textContent = button.dataset.originalText;
        delete button.dataset.originalText;
    }

    button.disabled = busy;
    button.setAttribute('aria-busy', String(busy));
}

export function formatDate(value) {
    if (!value) return 'Sin registro';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Sin registro';

    return new Intl.DateTimeFormat('es-NI', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

export function element(tag, { className = '', text = '', attributes = {} } = {}) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== '') node.textContent = String(text);

    Object.entries(attributes).forEach(([name, value]) => {
        if (value !== null && value !== undefined) node.setAttribute(name, String(value));
    });

    return node;
}
