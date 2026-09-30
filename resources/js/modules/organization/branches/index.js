import { api, ApiError } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { element, responseMessage } from '../shared';

const state = { page: 1, search: '', request: null };

function date(value) {
    if (!value) return 'Sin fecha';
    const parsed = new Date(`${value}T00:00:00`);
    return Number.isNaN(parsed.getTime())
        ? 'Sin fecha'
        : new Intl.DateTimeFormat('es-NI', { dateStyle: 'medium' }).format(parsed);
}

function renderState(root, message, { error = false, retry = false } = {}) {
    const container = root.querySelector('[data-branches-state]');
    container.replaceChildren(element('p', {
        className: error ? 'text-sm font-semibold text-red-700' : 'text-sm text-gintly-text-secondary',
        text: message,
    }));

    if (retry) container.append(element('button', {
        className: 'mt-4 min-h-11 rounded-xl bg-gintly-brand px-4 text-sm font-bold text-white',
        text: 'Reintentar',
        attributes: { type: 'button', 'data-branches-retry': '' },
    }));
    container.hidden = false;
}

function row(branch) {
    const tr = element('tr', { className: 'text-gintly-text-secondary' });
    const name = element('td', { className: 'px-5 py-4 font-bold text-gintly-text-primary sm:px-6', text: branch.name || 'Sin nombre' });
    const address = element('td', { className: 'max-w-md px-5 py-4', text: branch.address || 'Sin dirección' });
    const manager = element('td', { className: 'whitespace-nowrap px-5 py-4', text: branch.manager_user_id ? `Usuario #${branch.manager_user_id}` : 'Sin responsable' });
    const opened = element('td', { className: 'whitespace-nowrap px-5 py-4', text: date(branch.opened_at) });
    const status = element('td', { className: 'px-5 py-4 sm:px-6' });
    status.append(element('span', {
        className: branch.is_active
            ? 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800'
            : 'inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-xs font-bold text-slate-700',
        text: branch.is_active ? 'Activa' : 'Inactiva',
    }));
    tr.append(name, address, manager, opened, status);
    return tr;
}

function renderBranches(root, response) {
    const branches = Array.isArray(response?.data) ? response.data : [];
    const meta = response?.meta ?? {};
    root.querySelector('[data-branches-body]').replaceChildren(...branches.map(row));
    root.querySelector('[data-branches-summary]').textContent = `${meta.total ?? branches.length} sucursal${(meta.total ?? branches.length) === 1 ? '' : 'es'}`;

    if (branches.length === 0) renderState(root, state.search ? 'No hay sucursales que coincidan con la búsqueda.' : 'No hay sucursales disponibles.');
    else root.querySelector('[data-branches-state]').hidden = true;

    const current = Number(meta.current_page ?? state.page);
    const last = Number(meta.last_page ?? current);
    state.page = current;
    const pagination = root.querySelector('[data-branches-pagination]');
    pagination.hidden = last <= 1;
    pagination.querySelector('[data-page-label]').textContent = `Página ${current} de ${last}`;
    pagination.querySelector('[data-page-previous]').disabled = current <= 1;
    pagination.querySelector('[data-page-next]').disabled = current >= last;
}

async function load(root) {
    state.request?.abort();
    const controller = new AbortController();
    state.request = controller;
    renderState(root, 'Cargando sucursales…');
    root.querySelector('[data-branches-pagination]').hidden = true;

    try {
        const response = await api.get(root.dataset.branchesEndpoint, {
            page: state.page,
            per_page: 20,
            search: state.search || undefined,
            sort: 'name',
            direction: 'asc',
        }, { signal: controller.signal, dispatchErrors: false });
        renderBranches(root, response);
    } catch (error) {
        if (error instanceof ApiError && error.code === 'request_aborted') return;
        root.querySelector('[data-branches-body]').replaceChildren();
        root.querySelector('[data-branches-summary]').textContent = 'Consulta no disponible';
        renderState(root, responseMessage(error, 'No fue posible consultar las sucursales.'), { error: true, retry: true });
    } finally {
        if (state.request === controller) state.request = null;
    }
}

async function authorizeAndLoad(root, refresh = false) {
    try {
        const context = await getSessionContext({ refresh });
        if (!context.capabilities.includes('sucursales.gestionar')) {
            root.dataset.authorized = 'false';
            renderState(root, 'No tienes autorización para consultar sucursales.', { error: true });
            return;
        }
        root.dataset.authorized = 'true';
        await load(root);
    } catch (error) {
        delete root.dataset.authorized;
        renderState(root, responseMessage(error, 'No fue posible validar tu acceso.'), { error: true, retry: true });
    }
}

export default async function init() {
    const root = document.querySelector('[data-branches-root]');
    if (!root || root.dataset.initialized === 'true') return;

    root.dataset.initialized = 'true';
    const searchForm = root.querySelector('[data-branches-search]');
    const searchError = root.querySelector('[data-branches-search-error]');

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
        void load(root);
    });

    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-branches-retry]')) {
            void (root.dataset.authorized === 'true' ? load(root) : authorizeAndLoad(root, true));
        }
        else if (event.target.closest('[data-page-previous]')) {
            state.page = Math.max(1, state.page - 1);
            void load(root);
        } else if (event.target.closest('[data-page-next]')) {
            state.page += 1;
            void load(root);
        }
    });

    await authorizeAndLoad(root);
}
