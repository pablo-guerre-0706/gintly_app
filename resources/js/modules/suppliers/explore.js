import { getSessionContext } from '@/core/session-context';
import { ApiError } from '@/core/api-client';
import { createMapProvider } from '@/maps/provider-adapter';
import { filteredMap, mapCounts, supplierFocus, supplierAccess } from './contracts';
import { fetchMap } from './data';
import { SupplierDialogs } from './dialogs';
import { el, button, message, notice, date, isAbort } from './ui';

function queryMessage(error) {
    if (error instanceof ApiError && error.status >= 500) return 'No pudimos consultar los proveedores. El servidor no está disponible; reintenta la consulta.';
    if (error instanceof ApiError && error.status === 0) return 'No pudimos consultar los proveedores. Revisa tu conexión y reintenta la consulta.';
    return message(error);
}

class SupplierMap {
    constructor(root, context) {
        this.root = root; this.access = supplierAccess(context); this.records = []; this.visible = []; this.selection = null; this.selectedSupplierId = null; this.request = null; this.loading = false;
        this.form = root.querySelector('[data-map-filters]'); this.detailPanel = root.querySelector('[data-map-detail]');
        this.dialogs = new SupplierDialogs(root, context, async (value) => { notice(root, value); await this.load({ replace: true }); });
        this.mountMap();
    }
    mountMap() {
        try {
            this.map = createMapProvider(this.root.querySelector('[data-map-canvas]'), {
                zoomPosition: 'bottomright',
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
        this.root.querySelector('[data-map-clear]').addEventListener('click', () => {
            this.form.reset(); this.filter(); this.form.elements.search.focus();
        });
        this.root.querySelector('[data-supplier-refresh]').addEventListener('click', () => { void this.load(); });
        this.root.querySelector('[data-map-retry]').addEventListener('click', () => { void this.load(); });
        this.root.querySelector('[data-tiles-retry]').addEventListener('click', () => {
            if (!this.map) { this.mountMap(); this.filter(); this.map?.fit(); }
            else this.map.retryTiles();
        });
        this.root.querySelector('[data-map-fit]').addEventListener('click', () => {
            if (window.matchMedia('(width < 1024px)').matches) this.view('map', { fit: false, onReady: () => {
                this.map?.fit(); this.root.querySelector('[data-map-canvas]').focus({ preventScroll: true });
            } });
            else this.map?.fit();
        });
        this.root.querySelector('[data-mobile-map]').addEventListener('click', () => this.view('map'));
        this.root.querySelector('[data-mobile-list]').addEventListener('click', () => this.view('list'));
        this.root.querySelector('[data-list-column]').addEventListener('keydown', (event) => {
            if (event.key !== 'Escape' || !window.matchMedia('(width < 1024px)').matches) return;
            event.preventDefault(); this.view('map', { fit: false }); this.root.querySelector('[data-mobile-list]').focus();
        });
        this.root.querySelector('[data-map-list]').addEventListener('click', (event) => {
            const locationTrigger = event.target.closest('[data-location-key]');
            if (locationTrigger) {
                const location = this.visible.flatMap((record) => record.locations).find((item) => item.key === locationTrigger.dataset.locationKey);
                if (location) this.select(location, false);
                return;
            }
            const supplierTrigger = event.target.closest('[data-select-supplier]');
            if (supplierTrigger) {
                const record = this.visible.find((item) => item.id === Number(supplierTrigger.dataset.selectSupplier));
                if (record) this.selectSupplier(record);
                return;
            }
            if (event.target.closest('[data-close-detail]')) {
                this.detailPanel.hidden = true; this.updateSelection();
                this.root.querySelector(`[data-select-supplier="${this.selectedSupplierId}"]`)?.focus({ preventScroll: true });
            }
        });
        window.addEventListener('pagehide', () => { this.request?.abort(); this.map?.destroy(); }, { once: true });
        return this.load();
    }
    view(mode, { fit = true, onReady = null } = {}) {
        this.root.dataset.mobileView = mode;
        this.root.querySelector('[data-mobile-map]').setAttribute('aria-pressed', String(mode === 'map'));
        this.root.querySelector('[data-mobile-list]').setAttribute('aria-pressed', String(mode === 'list'));
        if (mode === 'map') window.requestAnimationFrame(() => {
            if (this.root.dataset.mobileView !== 'map') return;
            this.map?.resize(); if (onReady) onReady(); else if (fit) this.map?.fit();
        });
    }
    async load({ replace = false } = {}) {
        if (this.loading && !replace) return;
        this.loading = true; this.request?.abort(); const controller = new AbortController(); this.request = controller;
        this.root.dataset.mapState = 'loading';
        this.root.querySelector('[data-map-count]').textContent = 'Consultando proveedores…';
        this.root.setAttribute('aria-busy', 'true'); this.root.querySelector('[data-supplier-refresh]').disabled = true;
        this.root.querySelector('[data-map-loading]').hidden = false; this.root.querySelector('[data-map-error]').hidden = true;
        this.root.querySelector('[data-map-content]').hidden = true;
        try {
            this.records = await fetchMap(controller.signal);
            if (controller.signal.aborted) return;
            this.root.querySelector('[data-map-content]').hidden = false; this.filter(); this.map?.resize(); this.map?.fit();
        } catch (error) {
            if (!isAbort(error)) {
                this.root.dataset.mapState = 'error';
                this.records = []; this.visible = []; this.selection = null; this.selectedSupplierId = null;
                this.map?.setLocations([]); this.detailPanel.hidden = true; this.root.querySelector('[data-map-fit]').disabled = true;
                this.root.querySelector('[data-map-count]').textContent = 'Consulta no disponible';
                this.root.querySelector('[data-map-error-message]').textContent = queryMessage(error); this.root.querySelector('[data-map-error]').hidden = false;
            }
        } finally {
            if (this.request === controller) { this.loading = false; this.root.setAttribute('aria-busy', 'false'); this.root.querySelector('[data-map-loading]').hidden = true; this.root.querySelector('[data-supplier-refresh]').disabled = false; }
        }
    }
    filter() {
        if (this.root.querySelector('[data-map-content]').hidden) return;
        this.visible = filteredMap(this.records, this.form.elements.search.value, this.form.elements.primary.checked);
        const locations = this.visible.flatMap((record) => record.locations);
        this.map?.setLocations(locations); this.root.querySelector('[data-map-fit]').disabled = locations.length === 0 || !this.map;
        const counts = mapCounts(this.visible);
        this.root.querySelector('[data-map-count]').textContent = `${counts.suppliers} ${counts.suppliers === 1 ? 'proveedor' : 'proveedores'} · ${counts.locations} ${counts.locations === 1 ? 'ubicación confirmada' : 'ubicaciones confirmadas'}`;
        const empty = this.root.querySelector('[data-map-empty]'); empty.hidden = locations.length > 0;
        this.root.dataset.mapState = locations.length ? 'ready' : (this.records.length ? 'no-results' : 'empty');
        this.root.querySelector('[data-map-empty-title]').textContent = this.records.length ? 'No hay coincidencias' : 'No hay proveedores elegibles';
        this.root.querySelector('[data-map-empty-message]').textContent = this.records.length
            ? 'Prueba otras palabras del nombre o la dirección, o limpia los filtros para ver todos tus proveedores elegibles.'
            : 'El mapa solo muestra proveedores de tu negocio aprobados y activos, con al menos una ubicación confirmada. Un candidato pendiente o una dirección sin confirmar no aparece aquí.';
        const output = this.root.querySelector('[data-map-list]');
        this.detailPanel.remove(); this.detailPanel.hidden = true;
        output.replaceChildren(...this.visible.map((record) => {
            const card = el('article', '', 'supplier-map-card rounded-xl border border-slate-200 p-3'); card.dataset.supplierCard = String(record.id);
            const heading = el('h3'); const select = button(record.name, 'select-supplier', 'w-full break-words text-left font-semibold');
            select.dataset.selectSupplier = String(record.id); select.setAttribute('aria-controls', 'supplier-map-detail'); heading.append(select);
            const badge = el('p', '✓ Seleccionado', 'mt-1 text-xs font-semibold text-gintly-brand'); badge.dataset.selectionBadge = ''; badge.hidden = true;
            card.append(heading, badge, el('p', `${record.locations.length} ${record.locations.length === 1 ? 'ubicación disponible' : 'ubicaciones disponibles'}`, 'mt-2 text-xs text-gintly-text-secondary'));
            for (const location of record.locations) {
                const control = button(`${location.is_primary ? 'Principal · ' : ''}${location.address}`, 'select', 'mt-2 w-full break-words text-left text-xs');
                control.dataset.locationKey = location.key; control.setAttribute('aria-controls', 'supplier-map-detail'); card.append(control);
            }
            return card;
        }));
        output.append(this.detailPanel);
        const selected = locations.find((item) => item.key === this.selection?.key);
        if (selected) { this.detail(selected); this.map?.select(selected, { recenter: false }); }
        else {
            this.selection = null;
            const record = this.visible.find((item) => item.id === this.selectedSupplierId);
            if (record) this.map?.selectLocations(record.locations, { recenter: false });
            else this.selectedSupplierId = null;
        }
        this.updateSelection();
    }
    selectSupplier(record) {
        const location = supplierFocus(record);
        if (location) { this.select(location, false); return; }
        this.selectedSupplierId = record.id; this.selection = null; this.detailPanel.hidden = true;
        this.updateSelection();
        const focus = () => { this.map?.selectLocations(record.locations); this.root.querySelector('[data-map-canvas]').focus({ preventScroll: true }); };
        if (window.matchMedia('(width < 1024px)').matches) this.view('map', { fit: false, onReady: focus });
        else this.map?.selectLocations(record.locations);
    }
    updateSelection() {
        this.root.querySelectorAll('[data-supplier-card]').forEach((card) => {
            const selected = Number(card.dataset.supplierCard) === this.selectedSupplierId;
            card.dataset.selected = String(selected); card.querySelector('[data-selection-badge]').hidden = !selected;
            const control = card.querySelector('[data-select-supplier]'); control.setAttribute('aria-pressed', String(selected));
            control.setAttribute('aria-expanded', String(selected && !this.detailPanel.hidden));
        });
        this.root.querySelectorAll('[data-location-key]').forEach((control) => {
            const selected = control.dataset.locationKey === this.selection?.key;
            control.setAttribute('aria-pressed', String(selected)); control.setAttribute('aria-expanded', String(selected && !this.detailPanel.hidden));
        });
    }
    select(location, fromMarker) {
        this.selection = location; this.selectedSupplierId = location.supplierId;
        const focus = () => this.map?.select(location, { focus: !fromMarker });
        if (!fromMarker && window.matchMedia('(width < 1024px)').matches) this.view('map', { fit: false, onReady: focus });
        else focus();
        this.detail(location); this.updateSelection();
        if (fromMarker) {
            this.view('list'); const trigger = this.root.querySelector(`[data-location-key="${location.key}"]`); trigger?.focus({ preventScroll: true });
            const panel = this.root.querySelector('[data-list-column]');
            if (trigger) panel.scrollTop += trigger.getBoundingClientRect().top - panel.getBoundingClientRect().top - 20;
        }
    }
    detail(location) {
        const detail = this.detailPanel; detail.hidden = false;
        const close = button('Ocultar detalle', 'close-detail', 'mt-3 w-full'); close.dataset.closeDetail = '';
        detail.replaceChildren(el('h4', 'Detalle de ubicación', 'text-sm font-bold'), el('p', location.address, 'mt-2 break-words text-sm'),
            el('p', `${location.is_primary ? 'Ubicación principal' : 'Ubicación adicional'} · Confirmada: ${date(location.confirmed_at)}`, 'mt-2 text-sm text-gintly-text-secondary'),
            el('p', `Latitud ${location.point[0]} · Longitud ${location.point[1]}${location.quality ? ` · Calidad: ${location.quality}` : ''}`, 'mt-2 break-words text-xs'), close);
        this.root.querySelector(`[data-supplier-card="${location.supplierId}"]`)?.append(detail);
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
