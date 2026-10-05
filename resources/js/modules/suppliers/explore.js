import { getSessionContext } from '@/core/session-context';
import { createMapProvider } from '@/maps/provider-adapter';
import { filteredMap, supplierAccess } from './contracts';
import { fetchMap } from './data';
import { SupplierDialogs } from './dialogs';
import { el, button, message, notice, date, isAbort } from './ui';

class SupplierMap {
    constructor(root, context) {
        this.root = root; this.access = supplierAccess(context); this.records = []; this.visible = []; this.selection = null; this.request = null; this.loading = false;
        this.form = root.querySelector('[data-map-filters]');
        this.dialogs = new SupplierDialogs(root, context, async (value) => { notice(root, value); await this.load({ replace: true }); });
        this.mountMap();
    }
    mountMap() {
        try {
            this.map = createMapProvider(this.root.querySelector('[data-map-canvas]'), {
                onSelect: (location) => this.select(location, true),
                onTileState: (state) => { this.root.querySelector('[data-map-tiles-error]').hidden = state !== 'error'; },
            });
        } catch { this.map = null; this.root.querySelector('[data-map-tiles-error]').hidden = false; }
    }
    init() {
        this.root.querySelector('[data-directory-link]').hidden = !this.access.manage;
        this.form.addEventListener('submit', (event) => { event.preventDefault(); this.filter(); });
        this.form.addEventListener('input', () => this.filter());
        this.form.addEventListener('change', () => this.filter());
        this.root.querySelector('[data-supplier-refresh]').addEventListener('click', () => { void this.load(); });
        this.root.querySelector('[data-map-retry]').addEventListener('click', () => { void this.load(); });
        this.root.querySelector('[data-tiles-retry]').addEventListener('click', () => {
            if (!this.map) { this.mountMap(); this.map?.setLocations(this.visible.flatMap((record) => record.locations)); this.map?.fit(); }
            else this.map.retryTiles();
        });
        this.root.querySelector('[data-map-fit]').addEventListener('click', () => this.map?.fit());
        this.root.querySelector('[data-mobile-map]').addEventListener('click', () => this.view('map'));
        this.root.querySelector('[data-mobile-list]').addEventListener('click', () => this.view('list'));
        this.root.querySelector('[data-map-list]').addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-location-key]'); if (!trigger) return;
            const location = this.visible.flatMap((record) => record.locations).find((item) => item.key === trigger.dataset.locationKey);
            if (location) this.select(location, false);
        });
        window.addEventListener('pagehide', () => { this.request?.abort(); this.map?.destroy(); }, { once: true });
        return this.load();
    }
    view(mode, { fit = true } = {}) {
        this.root.dataset.mobileView = mode;
        this.root.querySelector('[data-mobile-map]').setAttribute('aria-pressed', String(mode === 'map'));
        this.root.querySelector('[data-mobile-list]').setAttribute('aria-pressed', String(mode === 'list'));
        if (mode === 'map') { this.map?.resize(); if (fit) this.map?.fit(); }
    }
    async load({ replace = false } = {}) {
        if (this.loading && !replace) return;
        this.loading = true; this.request?.abort(); const controller = new AbortController(); this.request = controller;
        this.root.setAttribute('aria-busy', 'true'); this.root.querySelector('[data-supplier-refresh]').disabled = true;
        this.root.querySelector('[data-map-loading]').hidden = false; this.root.querySelector('[data-map-error]').hidden = true;
        this.root.querySelector('[data-map-content]').hidden = true;
        try {
            this.records = await fetchMap(controller.signal);
            if (controller.signal.aborted) return;
            this.root.querySelector('[data-map-content]').hidden = false; this.filter(); this.map?.resize(); this.map?.fit();
        } catch (error) {
            if (!isAbort(error)) { this.map?.setLocations([]); this.root.querySelector('[data-map-error-message]').textContent = message(error); this.root.querySelector('[data-map-error]').hidden = false; }
        } finally {
            if (this.request === controller) { this.loading = false; this.root.setAttribute('aria-busy', 'false'); this.root.querySelector('[data-map-loading]').hidden = true; this.root.querySelector('[data-supplier-refresh]').disabled = false; }
        }
    }
    filter() {
        this.visible = filteredMap(this.records, this.form.elements.search.value, this.form.elements.primary.checked);
        const locations = this.visible.flatMap((record) => record.locations);
        this.map?.setLocations(locations); this.root.querySelector('[data-map-fit]').disabled = locations.length === 0 || !this.map;
        this.root.querySelector('[data-map-count]').textContent = `${this.visible.length} proveedores · ${locations.length} ubicaciones confirmadas`;
        const empty = this.root.querySelector('[data-map-empty]'); empty.hidden = locations.length > 0;
        empty.textContent = this.records.length ? 'Sin coincidencias para esta búsqueda o filtro.' : 'No hay proveedores aprobados y activos con ubicaciones confirmadas. Gestiona su aprobación y confirmación desde el directorio.';
        const output = this.root.querySelector('[data-map-list]');
        output.replaceChildren(...this.visible.map((record) => {
            const card = el('article', '', 'rounded-2xl border border-gintly-border bg-white p-5');
            card.append(el('h2', record.name, 'break-words text-lg font-semibold'), el('p', `Aprobado · ${record.locations.length} ubicaciones en este resultado`, 'mt-1 text-xs text-gintly-text-secondary'));
            for (const location of record.locations) {
                const control = button(`${location.is_primary ? 'Principal · ' : ''}${location.address}`, 'select', 'mt-3 w-full break-words text-left');
                control.dataset.locationKey = location.key; control.setAttribute('aria-pressed', String(location.key === this.selection?.key)); card.append(control);
            }
            return card;
        }));
        const selected = locations.find((item) => item.key === this.selection?.key);
        if (selected) { this.detail(selected); this.map?.select(selected, { recenter: false }); }
        else { this.selection = null; this.root.querySelector('[data-map-detail]').hidden = true; }
    }
    select(location, fromMarker) {
        this.selection = location;
        if (!fromMarker && window.matchMedia('(max-width: 767px)').matches) this.view('map', { fit: false });
        this.map?.select(location, { focus: !fromMarker });
        this.root.querySelectorAll('[data-location-key]').forEach((control) => control.setAttribute('aria-pressed', String(control.dataset.locationKey === location.key)));
        this.detail(location);
        if (fromMarker) { this.view('list'); this.root.querySelector(`[data-location-key="${location.key}"]`)?.focus({ preventScroll: true }); }
    }
    detail(location) {
        const detail = this.root.querySelector('[data-map-detail]'); detail.hidden = false;
        detail.replaceChildren(el('h2', location.supplierName, 'text-lg font-bold'), el('p', location.address, 'mt-2 break-words text-sm'),
            el('p', `${location.is_primary ? 'Ubicación principal' : 'Ubicación adicional'} · Confirmada: ${date(location.confirmed_at)}`, 'mt-2 text-sm text-gintly-text-secondary'),
            el('p', `Latitud ${location.point[0]} · Longitud ${location.point[1]}${location.quality ? ` · Calidad: ${location.quality}` : ''}`, 'mt-2 text-xs'));
        if (this.access.manage) {
            const manage = button('Gestionar ubicaciones', 'locations', 'mt-4');
            manage.addEventListener('click', () => this.dialogs.open('locations', this.records.find((record) => record.id === location.supplierId), manage)); detail.append(manage);
        }
    }
}

export default async function init() {
    const root = document.querySelector('[data-supplier-explorer]'); if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true'; const context = await getSessionContext();
    if (!supplierAccess(context).read) { notice(root, 'Tu cuenta no tiene autorización para consultar este mapa.', true); root.querySelector('[data-map-loading]').hidden = true; return; }
    return new SupplierMap(root, context).init();
}
