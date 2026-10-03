import { api, ApiError, initializeCsrf } from '@/core/api-client';
import { OPERATIVE_RESOURCES } from './resource-config';

function message(error) {
    if (!(error instanceof ApiError)) return error instanceof TypeError ? error.message : 'No fue posible cargar la información.';
    if (error.status === 403) return 'No tienes autorización para consultar este recurso.';
    if (error.status === 419) return 'La sesión de seguridad expiró.';
    if (error.status === 429) return 'Se alcanzó el límite temporal de solicitudes.';
    if (error.status === 0) return 'No fue posible conectar con el servidor.';
    if (error.status >= 500) return 'El servidor no pudo completar la consulta.';
    return error.message;
}

async function get(path, query, signal) {
    const options = { dispatchErrors: false, signal };
    try { return await api.get(path, query, options); }
    catch (error) {
        if (!(error instanceof ApiError) || error.status !== 419 || signal.aborted) throw error;
        await initializeCsrf({ dispatchErrors: false });
        return api.get(path, query, options);
    }
}

function validPage(payload) {
    if (!payload || !Array.isArray(payload.data) || !payload.meta || typeof payload.meta !== 'object') {
        throw new TypeError('El servidor no devolvió una colección paginada válida.');
    }
    return payload;
}

class ResourceList {
    constructor(root, config) {
        this.root = root;
        this.config = config;
        this.page = 1;
        this.lastPage = 1;
        this.controller = null;
        this.timer = null;
        this.pending = null;
        this.activeKey = null;
    }

    init() {
        this.root.querySelector('[data-operative-search-wrap]').hidden = !this.config.search;
        if (this.config.search) {
            this.root.querySelector('[data-operative-search-label]').textContent = this.config.searchLabel;
            this.root.querySelector('[data-operative-search]').placeholder = this.config.searchPlaceholder;
        }
        const head = this.root.querySelector('[data-operative-head]');
        head.replaceChildren(...this.config.columns.map((column) => {
            const th = document.createElement('th');
            th.className = 'px-5 py-3 font-semibold';
            th.scope = 'col';
            th.textContent = column;
            return th;
        }));
        this.root.addEventListener('input', (event) => {
            if (!event.target.matches('[data-operative-search]')) return;
            window.clearTimeout(this.timer);
            this.timer = window.setTimeout(() => { this.page = 1; void this.load(); }, 300);
        });
        this.root.addEventListener('click', (event) => {
            const direction = event.target.closest('[data-page-direction]')?.dataset.pageDirection;
            if (direction === 'previous' && this.page > 1) this.page -= 1;
            else if (direction === 'next' && this.page < this.lastPage) this.page += 1;
            else if (event.target.closest('[data-operative-retry]')) return void this.load();
            else return;
            void this.load();
        });
        return this.load();
    }

    setState(text, { error = false, retry = false } = {}) {
        const state = this.root.querySelector('[data-operative-state]');
        state.hidden = false;
        state.className = `p-5 text-sm sm:p-6 ${error ? 'text-red-800' : 'text-gintly-text-secondary'}`;
        state.replaceChildren(document.createTextNode(text));
        if (retry) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'mt-4 block min-h-11 rounded-xl border border-red-300 bg-white px-4 font-semibold';
            button.dataset.operativeRetry = '';
            button.textContent = 'Reintentar';
            state.appendChild(button);
        }
        this.root.querySelector('[data-operative-table-wrap]').hidden = true;
        this.root.querySelector('[data-operative-pagination]').hidden = true;
    }

    render(payload) {
        const body = this.root.querySelector('[data-operative-body]');
        body.replaceChildren(...payload.data.map((record) => {
            const row = document.createElement('tr');
            row.className = 'text-gintly-text-primary';
            this.config.map(record).forEach((value) => {
                const cell = document.createElement('td');
                cell.className = 'px-5 py-4 align-top';
                cell.textContent = String(value ?? '—');
                row.appendChild(cell);
            });
            return row;
        }));
        this.lastPage = Number(payload.meta.last_page ?? 1);
        this.page = Number(payload.meta.current_page ?? this.page);
        this.root.querySelector('[data-operative-state]').hidden = true;
        this.root.querySelector('[data-operative-table-wrap]').hidden = false;
        const pagination = this.root.querySelector('[data-operative-pagination]');
        pagination.hidden = this.lastPage <= 1;
        pagination.querySelector('[data-page-label]').textContent = `Página ${this.page} de ${this.lastPage}`;
        pagination.querySelector('[data-page-direction="previous"]').disabled = this.page <= 1;
        pagination.querySelector('[data-page-direction="next"]').disabled = this.page >= this.lastPage;
        this.root.querySelector('[data-operative-summary]').textContent = `${payload.meta.total ?? payload.data.length} registros en tu alcance operativo`;
    }

    load() {
        const search = this.config.search ? this.root.querySelector('[data-operative-search]').value.trim() : '';
        const key = `${this.page}:${search}`;
        if (this.pending && this.activeKey === key) return this.pending;
        this.controller?.abort();
        const controller = new AbortController();
        this.controller = controller;
        this.activeKey = key;
        this.root.setAttribute('aria-busy', 'true');
        this.setState('Cargando información…');
        const query = { page: this.page, per_page: 20 };
        if (this.config.search && search.length >= 2) query.search = search;
        const pending = get(this.config.endpoint, query, controller.signal)
            .then(validPage)
            .then((payload) => {
                if (controller.signal.aborted) return;
                if (payload.data.length) this.render(payload);
                else this.setState('No hay registros disponibles en tu sucursal.');
            })
            .catch((error) => {
                if (controller.signal.aborted) return;
                this.setState(message(error), { error: true, retry: true });
            })
            .finally(() => {
                if (this.controller !== controller) return;
                this.pending = null;
                this.root.setAttribute('aria-busy', 'false');
            });
        this.pending = pending;
        return pending;
    }
}

export default function init() {
    const root = document.querySelector('[data-operative-resource-list]');
    if (!root || root.dataset.initialized === 'true') return;
    const config = OPERATIVE_RESOURCES[root.dataset.resourceType];
    if (!config) throw new TypeError('Tipo de recurso operativo no soportado.');
    root.dataset.initialized = 'true';
    return new ResourceList(root, config).init();
}
