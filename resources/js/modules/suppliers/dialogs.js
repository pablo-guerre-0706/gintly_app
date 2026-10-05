import { createFocusTrap } from '@/core/focus-trap';
import { lockScroll, unlockScroll } from '@/core/scroll-lock';
import { clearFormErrors, showFormErrors, submitFormOnce } from '@/core/form-ui';
import { createMapProvider } from '@/maps/provider-adapter';
import { coordinate, position, supplierAccess } from './contracts';
import { fetchLocations, createSupplier, saveLocation, geocodeLocation, confirmLocation, changeStatus } from './data';
import { el, button, message, date, isAbort } from './ui';

export class SupplierDialogs {
    constructor(root, context, onUpdated) {
        this.root = root; this.dialog = root.querySelector('[data-supplier-dialog]'); this.access = supplierAccess(context); this.onUpdated = onUpdated;
        this.owner = Symbol('supplier-dialog'); this.request = null; this.picker = null; this.writing = false; this.record = null;
        this.candidate = this.dialog.querySelector('[data-candidate-form]'); this.address = this.dialog.querySelector('[data-location-form]');
        this.coords = this.dialog.querySelector('[data-coordinate-form]'); this.status = this.dialog.querySelector('[data-status-form]');
        this.trap = createFocusTrap(this.dialog, { onEscape: () => { if (!this.writing) this.dialog.close(); } });
        this.dialog.querySelector('[data-dialog-close]').addEventListener('click', () => { if (!this.writing) this.dialog.close(); });
        this.dialog.addEventListener('cancel', (event) => { if (this.writing) event.preventDefault(); });
        this.dialog.addEventListener('close', () => {
            this.request?.abort(); this.picker?.destroy(); this.picker = null; this.trap.deactivate(); unlockScroll(this.owner);
            (this.opener?.isConnected ? this.opener : this.root.querySelector('[data-supplier-refresh]'))?.focus();
        });
        this.candidate.addEventListener('submit', (event) => { event.preventDefault(); void this.create(); });
        this.address.addEventListener('submit', (event) => { event.preventDefault(); void this.saveAddress(); });
        this.coords.addEventListener('submit', (event) => { event.preventDefault(); void this.confirm(); });
        this.status.addEventListener('submit', (event) => { event.preventDefault(); void this.saveStatus(); });
        this.dialog.querySelector('[data-new-location]').addEventListener('click', () => this.edit(null));
        this.dialog.querySelector('[data-dialog-retry]').addEventListener('click', () => { void this.loadLocations(); });
        this.dialog.querySelector('[data-location-list]').addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-supplier-action]'); if (!trigger || this.writing) return;
            const item = this.locations.find((row) => row.id === Number(trigger.dataset.id)); if (!item) return;
            if (trigger.dataset.supplierAction === 'edit') this.edit(item);
            if (trigger.dataset.supplierAction === 'confirm') this.editPoint(item);
            if (trigger.dataset.supplierAction === 'geocode') void this.geocode(item, trigger);
        });
    }

    open(mode, record, opener) {
        if (this.dialog.open || !this.access.manage || (mode === 'status' && !this.access.approve)) return;
        this.record = record; this.opener = opener;
        for (const form of [this.candidate, this.address, this.coords, this.status]) { form.reset(); form.hidden = true; clearFormErrors(form); }
        this.dialog.querySelector('[data-dialog-notice]').hidden = true; this.dialog.querySelector('[data-dialog-retry]').hidden = true;
        this.dialog.querySelector('[data-locations-panel]').hidden = mode !== 'locations';
        this.dialog.querySelector('[data-dialog-context]').textContent = record?.name ?? 'Datos maestros del negocio';
        this.dialog.querySelector('#supplier-dialog-title').textContent = mode === 'create' ? 'Registrar candidato' : mode === 'status' ? 'Validar proveedor' : 'Gestionar ubicaciones';
        if (mode === 'create') this.candidate.hidden = false;
        if (mode === 'status') {
            this.action = record.status === 'aprobado' ? 'suspend' : 'approve'; this.status.hidden = false;
            this.status.querySelector('[data-status-submit]').textContent = this.action === 'approve' ? 'Aprobar proveedor' : 'Suspender proveedor';
            this.status.querySelector('[data-status-description]').textContent = this.action === 'approve'
                ? 'Aprobar permite operar con el proveedor, pero no confirma automáticamente sus ubicaciones.' : 'Suspender retira del mapa todas sus ubicaciones. La evidencia se conserva.';
        }
        this.dialog.showModal(); lockScroll(this.owner); this.trap.activate(this.dialog.querySelector('[data-dialog-close]'));
        if (mode === 'locations') void this.loadLocations();
    }

    feedback(value) { const output = this.dialog.querySelector('[data-dialog-notice]'); output.textContent = value; output.hidden = false; }

    async loadLocations({ fromMutation = false } = {}) {
        if (this.writing && !fromMutation) return;
        this.request?.abort(); const controller = new AbortController(); this.request = controller;
        this.feedback('Consultando ubicaciones…'); this.dialog.setAttribute('aria-busy', 'true');
        this.dialog.querySelector('[data-location-list]').replaceChildren(); this.address.hidden = true; this.coords.hidden = true;
        try {
            const records = await fetchLocations(this.record.id, controller.signal);
            if (controller.signal.aborted) return;
            this.locations = records;
            const output = this.dialog.querySelector('[data-location-list]');
            output.replaceChildren(...records.map((item) => {
                const card = el('article', '', 'rounded-xl border border-slate-200 p-4');
                card.append(el('h3', item.address, 'break-words font-semibold'), el('p', `${item.is_primary ? 'Principal · ' : ''}${item.confirmed ? `Confirmada: ${date(item.confirmed_at)}` : 'Pendiente de confirmación'}`, 'mt-2 text-sm text-gintly-text-secondary'));
                const point = position(item); card.append(el('p', point ? `Coordenadas: ${point.join(', ')} · Origen: ${item.geocode_source ?? 'No informado'}` : 'Sin coordenadas', 'mt-2 text-xs'));
                const actions = el('div', '', 'mt-3 flex flex-wrap gap-2');
                for (const [label, action] of [['Editar dirección', 'edit'], ['Geocodificar', 'geocode'], ['Confirmar punto', 'confirm']]) { const control = button(label, action); control.dataset.id = String(item.id); actions.append(control); }
                card.append(actions); return card;
            }));
            this.feedback(records.length ? 'Editar una dirección invalida el marcador anterior. La geocodificación no sustituye tu confirmación.' : 'Este proveedor todavía no tiene direcciones. Añade la primera ubicación.');
        } catch (error) { if (!isAbort(error)) this.feedback(message(error)); }
        finally { if (this.request === controller) { this.dialog.setAttribute('aria-busy', 'false'); this.dialog.querySelector('[data-dialog-retry]').hidden = false; } }
    }

    edit(item) {
        this.locationId = item?.id ?? null; this.address.reset(); clearFormErrors(this.address);
        this.address.elements.address.value = item?.address ?? ''; this.address.elements.is_primary.checked = item?.is_primary ?? false;
        this.address.querySelector('[data-location-form-title]').textContent = item ? 'Editar dirección' : 'Añadir dirección';
        this.coords.hidden = true; this.address.hidden = false; this.address.elements.address.focus();
    }

    editPoint(item) {
        this.locationId = item.id; this.coords.reset(); clearFormErrors(this.coords); this.address.hidden = true; this.coords.hidden = false;
        const point = position(item);
        this.coords.elements.latitude.value = item.latitude ?? ''; this.coords.elements.longitude.value = item.longitude ?? '';
        this.picker?.destroy(); this.picker = null;
        try { this.picker = createMapProvider(this.dialog.querySelector('[data-point-map]'), {
            onPoint: (value) => { this.coords.elements.latitude.value = value[0].toFixed(7); this.coords.elements.longitude.value = value[1].toFixed(7); this.picker.pick(value); },
            onTileState: (state) => { this.dialog.querySelector('[data-point-map-error]').hidden = state !== 'error'; },
        }); this.picker.pick(point); }
        catch { this.dialog.querySelector('[data-point-map-error]').hidden = false; }
        this.coords.elements.latitude.focus();
    }

    async run(form, trigger, send, success) {
        if (this.writing || !this.access.manage) return;
        this.writing = true;
        const controls = [...this.dialog.querySelectorAll('button')].map((control) => [control, control.disabled]);
        controls.forEach(([control]) => { control.disabled = true; });
        const original = trigger.textContent; trigger.textContent = 'Procesando…';
        let result = null;
        await submitFormOnce(form, trigger, send, {
            onSuccess: (response) => { result = response; },
            onError: (error) => {
                this.feedback(message(error));
                if (form.hidden) this.dialog.querySelector('[data-dialog-notice]').focus();
            },
        });
        try { if (result) await success(result); }
        finally {
            this.writing = false; controls.forEach(([control, disabled]) => { control.disabled = disabled; }); trigger.textContent = original;
            if (result && this.dialog.open) this.dialog.querySelector('[data-new-location]').focus();
        }
    }

    async create() {
        if (!this.candidate.reportValidity()) return;
        const payload = Object.fromEntries(['name', 'tax_id', 'email', 'phone'].map((field) => [field, this.candidate.elements[field].value.trim() || null]));
        payload.email = payload.email?.toLowerCase() ?? null;
        await this.run(this.candidate, this.candidate.querySelector('button'), () => createSupplier(payload), async () => { this.dialog.close(); await this.onUpdated('Candidato registrado. Estado pendiente de aprobación.'); });
    }
    async saveAddress() {
        if (!this.address.reportValidity()) return;
        const payload = { address: this.address.elements.address.value.trim(), is_primary: this.address.elements.is_primary.checked };
        await this.run(this.address, this.address.querySelector('button'), () => saveLocation(this.record.id, this.locationId, payload), async () => {
            await this.onUpdated('Dirección guardada. Si cambió, sus coordenadas necesitan una nueva confirmación.'); await this.loadLocations({ fromMutation: true });
        });
    }
    async confirm() {
        if (!this.coords.reportValidity()) return;
        const errors = {}; const payload = {};
        for (const [field, limit] of [['latitude', 90], ['longitude', 180]]) {
            const value = this.coords.elements[field].value.trim();
            try { if (coordinate(value, limit) === null) throw new Error(); payload[field] = value; } catch { errors[field] = [`Introduce una coordenada entre −${limit} y ${limit}.`]; }
        }
        if (Object.keys(errors).length) { showFormErrors(this.coords, errors); return; }
        await this.run(this.coords, this.coords.querySelector('button'), () => confirmLocation(this.record.id, this.locationId, payload), async (result) => {
            await this.onUpdated(`Ubicación confirmada. ${result.latitude}, ${result.longitude}. Solo aparece en el mapa si el proveedor está aprobado y activo.`); await this.loadLocations({ fromMutation: true });
        });
    }
    async geocode(item, trigger) {
        this.address.hidden = true; this.coords.hidden = true;
        await this.run(this.address, trigger, () => geocodeLocation(this.record.id, item.id), async (result) => {
            const feedback = position(result) ? 'El servidor devolvió coordenadas. Confirma explícitamente el punto antes de utilizarlo en el mapa.' : 'La geocodificación no obtuvo coordenadas. Puedes confirmar el punto manualmente.';
            await this.onUpdated(feedback); await this.loadLocations({ fromMutation: true }); this.feedback(feedback);
        });
    }
    async saveStatus() {
        if (!this.access.approve || !this.status.reportValidity()) return;
        await this.run(this.status, this.status.querySelector('button'), () => changeStatus(this.record.id, this.action), async () => { this.dialog.close(); await this.onUpdated(this.action === 'approve' ? 'Proveedor aprobado. Las ubicaciones requieren confirmación independiente.' : 'Proveedor suspendido y retirado del mapa.'); });
    }
}
