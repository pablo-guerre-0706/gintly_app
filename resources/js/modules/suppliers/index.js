import { getSessionContext } from '@/core/session-context';
import { supplierAccess } from './contracts';
import { fetchDirectory } from './data';
import { SupplierDialogs } from './dialogs';
import { el, button, message, notice, isAbort } from './ui';

class SupplierDirectory {
    constructor(root, context) {
        this.root = root; this.access = supplierAccess(context); this.records = []; this.page = 1; this.meta = null; this.request = null;
        this.form = root.querySelector('[data-directory-filters]');
        this.dialogs = new SupplierDialogs(root, context, async (value) => { notice(root, value); await this.load(); });
    }
    init() {
        const create = this.root.querySelector('[data-candidate-create]'); create.hidden = !this.access.manage;
        create.addEventListener('click', () => this.dialogs.open('create', null, create));
        this.root.querySelector('[data-supplier-refresh]').addEventListener('click', () => { void this.load(); });
        this.root.querySelector('[data-directory-retry]').addEventListener('click', () => { void this.load(); });
        this.form.addEventListener('submit', (event) => { event.preventDefault(); if (this.form.reportValidity()) { this.page = 1; void this.load(); } });
        for (const [key, delta] of [['prev', -1], ['next', 1]]) this.root.querySelector(`[data-directory-${key}]`).addEventListener('click', () => { if (this.request) return; this.page += delta; void this.load(); });
        this.root.querySelector('[data-directory-records]').addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-supplier-action]'); if (!trigger) return;
            const record = this.records.find((row) => row.id === Number(trigger.dataset.id)); if (record) this.dialogs.open(trigger.dataset.supplierAction, record, trigger);
        });
        window.addEventListener('pagehide', () => this.request?.abort(), { once: true }); return this.load();
    }
    async load() {
        this.request?.abort(); const controller = new AbortController(); this.request = controller;
        this.root.setAttribute('aria-busy', 'true'); this.root.querySelector('[data-directory-loading]').hidden = false;
        for (const key of ['records', 'error', 'empty', 'pagination']) this.root.querySelector(`[data-directory-${key}]`).hidden = true;
        this.root.querySelector('[data-supplier-refresh]').disabled = true;
        const query = Object.fromEntries(new FormData(this.form)); query.page = this.page; query.per_page = 12;
        try {
            const response = await fetchDirectory(query, controller.signal); if (controller.signal.aborted) return;
            this.records = response.data; this.meta = response.meta; this.page = this.meta.current_page;
            this.root.querySelector('[data-directory-records]').replaceChildren(...this.records.map((record) => this.card(record)));
            this.root.querySelector('[data-directory-records]').hidden = !this.records.length;
            this.root.querySelector('[data-directory-empty]').hidden = this.records.length > 0;
            this.root.querySelector('[data-directory-pagination]').hidden = !this.records.length;
            this.root.querySelector('[data-directory-page]').textContent = `${this.meta.total} proveedores · Página ${this.page} de ${this.meta.last_page}`;
            this.root.querySelector('[data-directory-prev]').disabled = this.page <= 1; this.root.querySelector('[data-directory-next]').disabled = this.page >= this.meta.last_page;
        } catch (error) { if (!isAbort(error)) { this.root.querySelector('[data-directory-error-message]').textContent = message(error); this.root.querySelector('[data-directory-error]').hidden = false; } }
        finally { if (this.request === controller) { this.request = null; this.root.setAttribute('aria-busy', 'false'); this.root.querySelector('[data-directory-loading]').hidden = true; this.root.querySelector('[data-supplier-refresh]').disabled = false; } }
    }
    card(record) {
        const card = el('article', '', 'min-w-0 rounded-2xl border border-gintly-border bg-white p-5');
        card.append(el('h2', record.name, 'break-words text-lg font-bold'), el('p', `${record.status_label} · ${record.is_active ? 'Activo' : 'Inactivo'}`, 'mt-2 text-sm font-semibold text-gintly-brand'));
        for (const [label, value] of [['Identificación fiscal', record.tax_id], ['Correo', record.email], ['Teléfono', record.phone]]) if (value) card.append(el('p', `${label}: ${value}`, 'mt-2 break-words text-sm text-gintly-text-secondary'));
        if (this.access.manage) {
            const actions = el('div', '', 'mt-4 flex flex-wrap gap-2');
            const locations = button('Ubicaciones', 'locations'); locations.dataset.id = String(record.id); actions.append(locations);
            if (this.access.approve) { const status = button(record.status === 'aprobado' ? 'Suspender' : 'Aprobar', 'status'); status.dataset.id = String(record.id); actions.append(status); }
            card.append(actions);
        }
        return card;
    }
}

export default async function init() {
    const root = document.querySelector('[data-supplier-directory]'); if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true'; const context = await getSessionContext();
    if (!supplierAccess(context).manage) { notice(root, 'La gestión de proveedores está reservada a dirección y administración.', true); root.querySelector('[data-directory-loading]').hidden = true; return; }
    return new SupplierDirectory(root, context).init();
}
