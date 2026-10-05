import { api, ApiError } from '@/core/api-client';
import { getSessionContext } from '@/core/session-context';
import { element, fetchPaginatedCollection, mutate, responseMessage } from '../shared';

const state = { page: 1, search: '', active: '', request: null, managers: new Map(), branches: new Map(), pending: new Set(), menuTrigger: null };

function closeMenu(root, restoreFocus = false) {
    const menu = root.querySelector('[data-branch-menu]');
    menu.hidden = true;
    state.menuTrigger?.setAttribute('aria-expanded', 'false');
    if (restoreFocus && state.menuTrigger?.isConnected) state.menuTrigger.focus();
    state.menuTrigger = null;
}

function openMenu(root, trigger) {
    closeMenu(root);
    const id = Number(trigger.dataset.branchMenuTrigger);
    const branch = state.branches.get(id);
    if (!branch) return;
    const menu = root.querySelector('[data-branch-menu]');
    menu.querySelector('[data-branch-menu-edit]').href = root.dataset.editUrlTemplate.replace('__BRANCH__', String(id));
    const toggle = menu.querySelector('[data-branch-toggle]');
    toggle.textContent = branch.is_active ? 'Desactivar' : 'Activar';
    toggle.dataset.branchToggle = String(id);
    menu.querySelector('[data-branch-delete]').dataset.branchDelete = String(id);
    menu.hidden = false;
    const rect = trigger.getBoundingClientRect();
    const width = menu.getBoundingClientRect().width;
    menu.style.left = `${Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8))}px`;
    menu.style.top = `${Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - menu.getBoundingClientRect().height - 8))}px`;
    state.menuTrigger = trigger;
    trigger.setAttribute('aria-expanded', 'true');
    menu.querySelector('a').focus();
}

function date(value) {
    if (!value) return 'Sin fecha';
    const parsed = new Date(`${value}T00:00:00`);
    return Number.isNaN(parsed.getTime()) ? 'Sin fecha' : new Intl.DateTimeFormat('es-NI', { dateStyle: 'medium' }).format(parsed);
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

function notifyState(root, message, error = false) {
    const notice = root.querySelector('[data-branches-notice]');
    notice.textContent = message;
    notice.classList.toggle('border-emerald-200', !error);
    notice.classList.toggle('bg-emerald-50', !error);
    notice.classList.toggle('text-emerald-900', !error);
    notice.classList.toggle('border-red-200', error);
    notice.classList.toggle('bg-red-50', error);
    notice.classList.toggle('text-red-900', error);
    notice.hidden = false;
    notice.focus();
}

function row(root, branch) {
    const tr = element('tr', { className: 'text-gintly-text-secondary', attributes: { 'data-branch-id': branch.id } });
    const name = element('td', { className: 'px-5 py-4 font-bold text-gintly-text-primary sm:px-6', text: branch.name });
    const address = element('td', { className: 'max-w-md px-5 py-4', text: branch.address });
    const managerName = state.managers.get(branch.manager_user_id);
    const manager = element('td', { className: 'whitespace-nowrap px-5 py-4', text: managerName ?? (branch.manager_user_id ? `Usuario #${branch.manager_user_id}` : 'Sin responsable') });
    const opened = element('td', { className: 'whitespace-nowrap px-5 py-4', text: date(branch.opened_at) });
    const status = element('td', { className: 'px-5 py-4 sm:px-6' });
    status.append(element('span', {
        className: branch.is_active ? 'inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800' : 'inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-xs font-bold text-slate-700',
        text: branch.is_active ? 'Activa' : 'Inactiva',
    }));
    const actions = element('td', { className: 'px-5 py-4 sm:px-6' });
    actions.append(element('button', { className: 'grid size-11 place-items-center rounded-xl border border-slate-300 font-bold focus-visible:ring-2 focus-visible:ring-gintly-brand', text: '⋯', attributes: { type: 'button', 'data-branch-menu-trigger': branch.id, 'aria-label': `Acciones de ${branch.name}`, 'aria-haspopup': 'menu', 'aria-controls': 'branch-actions-menu', 'aria-expanded': 'false' } }));
    tr.append(name, address, manager, opened, status, actions);
    return tr;
}

function renderBranches(root, response) {
    closeMenu(root);
    if (!Array.isArray(response?.data) || !response?.meta || !response?.links) throw new TypeError('El servidor no devolvió sucursales paginadas.');
    const branches = response.data;
    state.branches = new Map(branches.map((branch) => [Number(branch.id), branch]));
    root.querySelector('[data-branches-body]').replaceChildren(...branches.map((branch) => row(root, branch)));
    const total = Number(response.meta.total);
    root.querySelector('[data-branches-summary]').textContent = `${total} sucursal${total === 1 ? '' : 'es'}`;
    if (!branches.length) renderState(root, 'No hay sucursales para los filtros seleccionados.');
    else root.querySelector('[data-branches-state]').hidden = true;
    const current = Number(response.meta.current_page);
    const last = Number(response.meta.last_page);
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
            page: state.page, per_page: 20, search: state.search || undefined,
            is_active: state.active === '' ? undefined : state.active,
            sort: 'name', direction: 'asc',
        }, { signal: controller.signal, dispatchErrors: false });
        if (!controller.signal.aborted) renderBranches(root, response);
    } catch (error) {
        if (controller.signal.aborted) return;
        root.querySelector('[data-branches-body]').replaceChildren();
        root.querySelector('[data-branches-summary]').textContent = 'Consulta no disponible';
        renderState(root, responseMessage(error, 'No fue posible consultar las sucursales.'), { error: true, retry: true });
    } finally {
        if (state.request === controller) state.request = null;
    }
}

async function change(root, id, action) {
    closeMenu(root);
    const branch = state.branches.get(id);
    if (!branch || state.pending.has(id)) return;
    if (action === 'delete' && !window.confirm(`¿Eliminar la sucursal «${branch.name}»? Se dará de baja únicamente si no tiene bodegas, cajas o usuarios dependientes.`)) return;
    if (action === 'toggle' && branch.is_active && !window.confirm(`¿Desactivar la sucursal «${branch.name}»?`)) return;
    state.pending.add(id);
    const tr = [...root.querySelectorAll('[data-branch-id]')].find((node) => Number(node.dataset.branchId) === id);
    tr?.setAttribute('aria-busy', 'true');
    tr?.querySelectorAll('button').forEach((button) => { button.disabled = true; });
    try {
        if (action === 'delete') {
            const result = await mutate('delete', `${root.dataset.branchesEndpoint}/${id}`, null, { expectedStatus: 204 });
            if (result !== null) throw new TypeError('La baja no devolvió una respuesta vacía; verifica el directorio.');
            notifyState(root, `Sucursal «${branch.name}» eliminada.`);
        } else {
            const result = await mutate('patch', `${root.dataset.branchesEndpoint}/${id}`, { is_active: !branch.is_active }, { expectedStatus: 200 });
            if (result?.data?.id !== id || result.data.is_active !== !branch.is_active) throw new TypeError('El servidor no confirmó el nuevo estado; verifica el directorio.');
            notifyState(root, `Sucursal «${branch.name}» ${result.data.is_active ? 'activada' : 'desactivada'}.`);
        }
        await load(root);
    } catch (error) {
        const message = error instanceof ApiError && error.status === 409 && error.code === 'ERR-02B'
            ? 'No se eliminó la sucursal: tiene bodegas, cajas o usuarios dependientes.'
            : responseMessage(error, 'La operación no fue confirmada. Verifica el directorio antes de reintentar.');
        notifyState(root, message, true);
        tr?.querySelectorAll('button').forEach((button) => { button.disabled = false; });
    } finally {
        state.pending.delete(id);
        tr?.removeAttribute('aria-busy');
    }
}

async function authorizeAndLoad(root) {
    try {
        const context = await getSessionContext();
        if (!['ROL-01', 'ROL-02'].includes(context.role) || !context.capabilities.includes('sucursales.gestionar')) {
            renderState(root, 'No tienes autorización para consultar sucursales.', { error: true });
            return;
        }
        root.dataset.authorized = 'true';
        root.querySelector('[data-branches-create]').hidden = false;
        const users = await fetchPaginatedCollection(root.dataset.usersEndpoint, { per_page: 100, sort: 'name', direction: 'asc' }, { dispatchErrors: false });
        state.managers = new Map(users.filter((user) => Number.isInteger(user.id)).map((user) => [user.id, user.name]));
        await load(root);
    } catch (error) {
        renderState(root, responseMessage(error, 'No fue posible validar el acceso o cargar responsables.'), { error: true, retry: true });
    }
}

export default function init() {
    const root = document.querySelector('[data-branches-root]');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';
    const searchForm = root.querySelector('[data-branches-search]');
    const searchError = root.querySelector('[data-branches-search-error]');
    const result = new URLSearchParams(window.location.search);
    if (result.get('created') === '1') notifyState(root, 'Sucursal creada correctamente.');
    else if (result.get('updated') === '1') notifyState(root, 'Sucursal actualizada correctamente.');
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
        state.active = searchForm.elements.is_active.value;
        state.page = 1;
        void load(root);
    });
    searchForm.elements.is_active.addEventListener('change', () => {
        state.active = searchForm.elements.is_active.value;
        state.page = 1;
        if (root.dataset.authorized === 'true') void load(root);
    });
    root.addEventListener('click', (event) => {
        const target = event.target.closest('button');
        if (!target) return;
        if (target.matches('[data-branch-menu-trigger]')) { openMenu(root, target); return; }
        if (target.matches('[data-branches-retry]')) void (root.dataset.authorized === 'true' ? load(root) : authorizeAndLoad(root));
        else if (target.matches('[data-page-previous]')) { state.page = Math.max(1, state.page - 1); void load(root); }
        else if (target.matches('[data-page-next]')) { state.page += 1; void load(root); }
        else if (target.matches('[data-branch-toggle]')) void change(root, Number(target.dataset.branchToggle), 'toggle');
        else if (target.matches('[data-branch-delete]')) void change(root, Number(target.dataset.branchDelete), 'delete');
    });
    document.addEventListener('pointerdown', (event) => {
        if (state.menuTrigger && !event.target.closest('[data-branch-menu], [data-branch-menu-trigger]')) closeMenu(root);
    });
    document.addEventListener('keydown', (event) => {
        if (!state.menuTrigger) return;
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeMenu(root, true); }
        if (event.key === 'Tab' && !event.target.closest('[data-branch-menu]')) closeMenu(root);
        if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key) && event.target.closest('[data-branch-menu]')) {
            event.preventDefault();
            const items = [...root.querySelectorAll('[data-branch-menu] a, [data-branch-menu] button')];
            const current = items.indexOf(document.activeElement);
            const index = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1
                : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[index]?.focus();
        }
    }, true);
    window.addEventListener('resize', () => closeMenu(root));
    root.querySelector('[data-branches-body]').closest('.overflow-x-auto')?.addEventListener('scroll', () => closeMenu(root));
    return authorizeAndLoad(root);
}
