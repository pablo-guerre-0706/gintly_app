import { api, ApiError } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { element, formatDate, responseMessage, ROLE_LABELS } from '../shared';

const state = {
    page: 1,
    search: '',
    request: null,
    context: null,
};

function renderState(root, message, { error = false, retry = false } = {}) {
    const container = root.querySelector('[data-users-state]');
    container.replaceChildren();

    const text = element('p', {
        className: error ? 'text-sm font-semibold text-red-700' : 'text-sm text-gintly-text-secondary',
        text: message,
    });
    container.append(text);

    if (retry) {
        const button = element('button', {
            className: 'mt-4 min-h-11 rounded-xl bg-gintly-brand px-4 text-sm font-bold text-white',
            text: 'Reintentar',
            attributes: { type: 'button', 'data-users-retry': '' },
        });
        container.append(button);
    }

    container.hidden = false;
}

function userRow(root, user) {
    const row = element('tr', { className: 'text-gintly-text-secondary' });
    const identity = element('td', { className: 'px-5 py-4 sm:px-6' });
    identity.append(
        element('p', { className: 'font-bold text-gintly-text-primary', text: user.name || 'Sin nombre' }),
        element('p', { className: 'mt-1 break-all text-xs', text: user.email || 'Sin correo' }),
    );

    const role = element('td', { className: 'whitespace-nowrap px-5 py-4', text: ROLE_LABELS[user.role] ?? 'Sin rol visible' });
    const branch = element('td', { className: 'whitespace-nowrap px-5 py-4', text: user.branch_id ? `Sucursal #${user.branch_id}` : 'Sin sucursal' });
    const status = element('td', { className: 'whitespace-nowrap px-5 py-4' });
    status.append(element('span', {
        className: user.is_active
            ? 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800'
            : 'inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-xs font-bold text-slate-700',
        text: user.is_active ? 'Activo' : 'Inactivo',
    }));

    const lastLogin = element('td', { className: 'whitespace-nowrap px-5 py-4 text-xs', text: formatDate(user.last_login_at) });
    const actions = element('td', { className: 'px-5 py-4 text-right sm:px-6' });

    if (state.context.capabilities.includes('usuarios.gestionar')) {
        const template = root.dataset.accessUrlTemplate;
        actions.append(element('a', {
            className: 'inline-flex min-h-11 items-center rounded-xl border border-gintly-brand px-4 text-sm font-bold text-gintly-brand transition hover:bg-gintly-brand/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gintly-brand',
            text: 'Administrar acceso',
            attributes: { href: template.replace('__USER__', String(user.id)) },
        }));
    } else {
        actions.append(element('span', { className: 'text-xs text-slate-500', text: 'Solo consulta' }));
    }

    row.append(identity, role, branch, status, lastLogin, actions);
    return row;
}

function renderUsers(root, response) {
    const users = Array.isArray(response?.data) ? response.data : [];
    const meta = response?.meta ?? {};
    const body = root.querySelector('[data-users-body]');
    const container = root.querySelector('[data-users-state]');
    const pagination = root.querySelector('[data-users-pagination]');
    const summary = root.querySelector('[data-users-summary]');

    body.replaceChildren(...users.map((user) => userRow(root, user)));
    summary.textContent = `${meta.total ?? users.length} usuario${(meta.total ?? users.length) === 1 ? '' : 's'}`;

    if (users.length === 0) {
        renderState(root, state.search ? 'No hay usuarios que coincidan con la búsqueda.' : 'No hay usuarios disponibles.');
    } else {
        container.hidden = true;
    }

    const current = Number(meta.current_page ?? state.page);
    const last = Number(meta.last_page ?? current);
    state.page = current;

    pagination.hidden = last <= 1;
    pagination.querySelector('[data-page-label]').textContent = `Página ${current} de ${last}`;
    pagination.querySelector('[data-page-previous]').disabled = current <= 1;
    pagination.querySelector('[data-page-next]').disabled = current >= last;
}

async function loadUsers(root) {
    state.request?.abort();
    const controller = new AbortController();
    state.request = controller;
    renderState(root, 'Cargando usuarios…');
    root.querySelector('[data-users-pagination]').hidden = true;

    try {
        const response = await api.get(root.dataset.usersEndpoint, {
            page: state.page,
            per_page: 20,
            search: state.search || undefined,
            sort: 'created_at',
            direction: 'desc',
        }, {
            signal: controller.signal,
            dispatchErrors: false,
        });
        renderUsers(root, response);
    } catch (error) {
        if (error instanceof ApiError && error.code === 'request_aborted') return;
        root.querySelector('[data-users-body]').replaceChildren();
        root.querySelector('[data-users-summary]').textContent = 'Consulta no disponible';
        renderState(root, responseMessage(error, 'No fue posible consultar los usuarios.'), { error: true, retry: true });
    } finally {
        if (state.request === controller) state.request = null;
    }
}

async function authorizeAndLoad(root, refresh = false) {
    try {
        state.context = await getSessionContext({ refresh });

        if (!state.context.capabilities.includes('usuarios.ver')) {
            renderState(root, 'No tienes autorización para consultar usuarios.', { error: true });
            return;
        }

        root.querySelector('[data-create-user]').hidden = !state.context.capabilities.includes('usuarios.gestionar');
        await loadUsers(root);
    } catch (error) {
        state.context = null;
        renderState(root, responseMessage(error, 'No fue posible validar tu acceso.'), { error: true, retry: true });
    }
}

export default async function init() {
    const root = document.querySelector('[data-users-root]');
    if (!root || root.dataset.initialized === 'true') return;

    root.dataset.initialized = 'true';
    const searchForm = root.querySelector('[data-users-search]');
    const searchError = root.querySelector('[data-users-search-error]');

    searchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const query = searchForm.elements.search.value.trim();

        if (query.length === 1) {
            searchError.textContent = 'Escribe al menos 2 caracteres para buscar.';
            searchError.hidden = false;
            searchForm.elements.search.focus();
            return;
        }

        searchError.hidden = true;
        state.search = query;
        state.page = 1;
        void loadUsers(root);
    });

    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-users-retry]')) {
            void (state.context ? loadUsers(root) : authorizeAndLoad(root, true));
            return;
        }

        if (event.target.closest('[data-page-previous]')) {
            state.page = Math.max(1, state.page - 1);
            void loadUsers(root);
            return;
        }

        if (event.target.closest('[data-page-next]')) {
            state.page += 1;
            void loadUsers(root);
        }
    });

    await authorizeAndLoad(root);
}
