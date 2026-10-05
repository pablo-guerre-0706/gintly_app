import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../../css/supplier-map.css';

// Raster visualization only. Address geocoding stays behind the authenticated API.
const TILE_URL = import.meta.env.VITE_MAP_TILE_URL || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = import.meta.env.VITE_MAP_ATTRIBUTION || '© OpenStreetMap contributors';
const INITIAL_VIEW = [12.12, -86.25]; // Geographic viewport, never a supplier marker.

export function createMapProvider(container, { onSelect = null, onPoint = null, onTileState = null } = {}) {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const map = L.map(container, { scrollWheelZoom: false, zoomAnimation: !reduced, fadeAnimation: !reduced, markerZoomAnimation: !reduced }).setView(INITIAL_VIEW, 6);
    const attribution = document.createElement('span'); attribution.textContent = ATTRIBUTION;
    if (!import.meta.env.VITE_MAP_ATTRIBUTION) {
        const link = document.createElement('a'); link.href = 'https://www.openstreetmap.org/copyright'; link.textContent = ATTRIBUTION;
        attribution.replaceChildren(link);
    }
    const layer = L.tileLayer(TILE_URL, { maxZoom: 19, attribution: attribution.innerHTML, keepBuffer: 1, referrerPolicy: 'strict-origin-when-cross-origin' }).addTo(map);
    const group = L.featureGroup().addTo(map);
    const markers = new Map(); let picked = null; let generationFailed = false;
    const observer = new ResizeObserver(() => map.invalidateSize({ pan: false })); observer.observe(container);
    layer.on('loading', () => { generationFailed = false; });
    layer.on('tileerror', () => { generationFailed = true; onTileState?.('error'); });
    layer.on('load', () => onTileState?.(generationFailed ? 'error' : 'ready'));
    if (onPoint) map.on('click', (event) => onPoint([event.latlng.lat, event.latlng.lng]));
    const icon = L.divIcon({ className: 'gintly-map-pin', html: '<span aria-hidden="true"></span>', iconSize: [44, 48], iconAnchor: [22, 44] });
    function fit() {
        if (group.getLayers().length) map.fitBounds(group.getBounds(), {
            paddingTopLeft: [64, 120], paddingBottomRight: [40, 40], maxZoom: 15, animate: !reduced,
        });
    }
    return {
        setLocations(locations) {
            group.clearLayers(); markers.clear();
            for (const location of locations) {
                const marker = L.marker(location.point, { icon, title: `${location.supplierName} · ${location.address}`, alt: `${location.supplierName} · ${location.address}`, keyboard: true, riseOnHover: true }).addTo(group);
                const label = document.createElement('span'); label.textContent = `${location.supplierName} · ${location.address}`;
                marker.bindTooltip(label).on('click', () => onSelect?.(location)); markers.set(location.key, marker);
                marker.getElement()?.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter' && event.key !== ' ') return;
                    event.preventDefault(); event.stopPropagation(); onSelect?.(location);
                });
            }
        },
        select(location, { focus = false, recenter = true } = {}) {
            for (const [key, marker] of markers) marker.getElement()?.classList.toggle('is-selected', key === location.key);
            if (recenter) map.setView(location.point, Math.max(map.getZoom(), 14), { animate: !reduced });
            if (focus) markers.get(location.key)?.getElement()?.focus({ preventScroll: true });
        },
        pick(point) {
            if (picked) map.removeLayer(picked);
            picked = point ? L.marker(point, { icon, keyboard: false }).addTo(map) : null;
            if (point) map.setView(point, Math.max(map.getZoom(), 13), { animate: false });
        },
        fit, resize: () => map.invalidateSize({ pan: false }), retryTiles: () => layer.redraw(),
        destroy() { observer.disconnect(); map.remove(); },
    };
}
