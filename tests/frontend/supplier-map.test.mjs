import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { coordinate, position, mapRecords, filteredMap, mapCounts, supplierFocus, supplier, location, supplierAccess } from '../../resources/js/modules/suppliers/contracts.js';
import { NAVIGATION_GROUPS, authorizedNavigation, flattenedNavigation, isCurrentPageAuthorized } from '../../resources/js/shell/navigation.js';

const fixture = () => ({ data: [{ id: 11, name: 'Proveedor Álamo', status: 'aprobado', locations: [
    { id: 21, address: 'Managua, centro', latitude: '12.1000000', longitude: '-86.2000000', is_primary: true, quality: null, confirmed_at: '2026-10-05T12:00:00Z' },
    { id: 22, address: 'León, norte', latitude: '0.0000000', longitude: '0.0000000', is_primary: false, quality: 'manual', confirmed_at: '2026-10-05T12:00:00Z' },
] }] });

test('coordenadas: decimales y cero válidos, ausentes nunca se convierten en cero', () => {
    assert.equal(coordinate('0.0000000', 90), 0);
    assert.deepEqual(position({ latitude: '0', longitude: 0 }), [0, 0]);
    for (const value of [null, undefined, '']) assert.equal(coordinate(value, 90), null);
    assert.equal(position({ latitude: null, longitude: null }), null);
    for (const value of [true, false, ' ', 'NaN', 'Infinity', '1e2', 91, -91]) assert.throws(() => coordinate(value, 90));
    assert.throws(() => position({ latitude: '12', longitude: null }));
});

test('mapa: un marcador por ubicación confirmada, sin paginación ni heurísticas', () => {
    const rows = mapRecords(fixture());
    assert.equal(rows.length, 1); assert.equal(rows[0].locations.length, 2);
    assert.deepEqual(rows[0].locations[1].point, [0, 0]);
    assert.equal(new Set(rows[0].locations.map((item) => item.key)).size, 2);
    for (const status of ['pendiente', 'suspendido']) {
        const payload = fixture(); payload.data[0].status = status; assert.throws(() => mapRecords(payload));
    }
    const missing = fixture(); missing.data[0].locations[0].latitude = null; assert.throws(() => mapRecords(missing));
    const unconfirmed = fixture(); unconfirmed.data[0].locations[0].confirmed_at = null; assert.throws(() => mapRecords(unconfirmed));
    const duplicate = fixture(); duplicate.data[0].locations.push(duplicate.data[0].locations[0]); assert.throws(() => mapRecords(duplicate));
    assert.deepEqual(mapRecords({ data: [] }), []);
});

test('búsqueda y principal producen el mismo conjunto local de proveedores y ubicaciones', () => {
    const rows = mapRecords(fixture());
    assert.equal(filteredMap(rows, 'alamo')[0].locations.length, 2);
    assert.equal(filteredMap(rows, 'LEON')[0].locations[0].id, 22);
    assert.equal(filteredMap(rows, '', true)[0].locations[0].id, 21);
    assert.deepEqual(filteredMap(rows, 'sin resultados'), []);
    assert.equal(rows[0].locations.length, 2);
});

test('filtro por todas las palabras: ignora tildes, mayúsculas, comas y orden sin cruzar direcciones', () => {
    const rows = mapRecords(fixture());
    for (const query of ['PROVEEDOR ÁLAMO, Managua', 'centro / alamo; proveedor', 'managua... ÁLAMO']) {
        assert.deepEqual(filteredMap(rows, query).map((row) => row.locations.map((item) => item.id)), [[21]]);
    }
    assert.deepEqual(filteredMap(rows, 'alamo leon', true), []);
    assert.deepEqual(filteredMap(rows, 'managua norte'), []);
    assert.deepEqual(filteredMap(rows, 'alamo ausente'), []);
    assert.deepEqual(mapCounts(filteredMap(rows, ' , ; ')), { suppliers: 1, locations: 2 });
    assert.deepEqual(mapCounts(filteredMap(rows, '')), mapCounts(rows));
    assert.equal(rows[0].locations.length, 2);
});

test('Enter aplica sin navegación, limpiar restaura y los estados de consulta no ocultan errores', async () => {
    const view = await readFile(new URL('../../resources/views/suppliers/explore.blade.php', import.meta.url), 'utf8');
    const controller = await readFile(new URL('../../resources/js/modules/suppliers/explore.js', import.meta.url), 'utf8');
    assert.match(view, /Filtrar mis proveedores/); assert.match(view, /No busca negocios externos/);
    assert.match(view, /aria-describedby="supplier-map-search-help"/);
    assert.match(view, /type="submit"[^>]*>Aplicar filtro/); assert.match(view, /data-map-clear/);
    assert.match(controller, /'submit', \(event\) => \{ event.preventDefault\(\); this.filter\(\); \}/);
    assert.match(controller, /this.form.reset\(\); this.filter\(\); this.form.elements.search.focus\(\)/);
    assert.match(controller, /dataset.mapState = 'loading'/); assert.match(controller, /dataset.mapState = 'error'/);
    assert.match(controller, /'no-results' : 'empty'/); assert.match(controller, /No hay coincidencias/);
    assert.match(controller, /No hay proveedores elegibles/);
    assert.match(controller, /\[data-directory-link\]'\).hidden = !this.access.manage/);
    assert.match(view, /route\('panel.suppliers.index'\)/);
});

test('conteo distingue proveedores únicos de ubicaciones, también tras filtrar', () => {
    const rows = mapRecords(fixture());
    assert.deepEqual(mapCounts(rows), { suppliers: 1, locations: 2 });
    assert.deepEqual(mapCounts([...rows, ...rows]), { suppliers: 1, locations: 2 });
    assert.deepEqual(mapCounts(filteredMap(rows, '', true)), { suppliers: 1, locations: 1 });
    assert.deepEqual(mapCounts(filteredMap(rows, 'sin resultados')), { suppliers: 0, locations: 0 });
});

test('selección centra principal visible o ubicación única; las adicionales se encuadran sin inventar una principal', () => {
    const rows = mapRecords(fixture());
    assert.equal(supplierFocus(rows[0]).id, 21);
    assert.equal(supplierFocus(filteredMap(rows, 'León')[0]).id, 22);
    const additional = { ...rows[0], locations: rows[0].locations.map((item) => ({ ...item, is_primary: false })) };
    assert.equal(supplierFocus(additional), null);
});

test('pantalla dividida aislada: altura dinámica, panel desplazable, zoom inferior y retorno canónico', async () => {
    const view = await readFile(new URL('../../resources/views/suppliers/explore.blade.php', import.meta.url), 'utf8');
    const css = await readFile(new URL('../../resources/css/supplier-map.css', import.meta.url), 'utf8');
    const controller = await readFile(new URL('../../resources/js/modules/suppliers/explore.js', import.meta.url), 'utf8');
    const adapter = await readFile(new URL('../../resources/js/maps/provider-adapter.js', import.meta.url), 'utf8');
    assert.match(view, /aria-label="Volver al dashboard"/); assert.match(view, /route\('dashboard'\)/);
    assert.match(view, /Proveedores aprobados/); assert.doesNotMatch(view, /Explorar negocios|Overpass/);
    assert.match(css, /html\[data-page="suppliers\/explore"\]:has\(\[data-supplier-explorer\]\)/);
    assert.match(css, /grid-template-rows: auto minmax\(0, 1fr\) auto/);
    assert.match(css, /height: 100dvh/); assert.match(css, /clamp\(320px, 30%, 400px\)/);
    assert.match(css, /@media \(width < 1024px\)/);
    assert.match(css, /\.supplier-map-panel[^}]+overflow-y: auto/);
    assert.doesNotMatch(css, /!important/);
    assert.match(controller, /zoomPosition: 'bottomright'/); assert.match(controller, /supplierFocus\(record\)/);
    assert.match(controller, /aria-pressed/); assert.match(controller, /window\.requestAnimationFrame/);
    assert.match(controller, /\(width < 1024px\)/);
    assert.match(controller, /onReady: focus/); assert.match(adapter, /container.clientWidth && container.clientHeight/);
});

test('Resources explícitos: proveedor y ubicación rechazan contratos contradictorios', () => {
    const record = { id: 1, name: 'Proveedor', status: 'pendiente', is_active: true, email: null, phone: null, tax_id: null };
    assert.equal(supplier(record), record);
    assert.throws(() => supplier({ ...record, is_active: 'true' }));
    const item = { id: 2, supplier_id: 1, address: 'Dirección', latitude: null, longitude: null, confirmed: false, is_primary: false };
    assert.equal(location(item, 1), item);
    assert.throws(() => location(item, 9));
    assert.throws(() => location({ ...item, confirmed: true }, 1));
});

test('permiso real: dirección gestiona, solo propietario aprueba, operativo únicamente consulta', () => {
    const capabilities = ['proveedores.ver'];
    assert.deepEqual(supplierAccess({ role: 'ROL-01', capabilities }), { read: true, manage: true, approve: true });
    assert.deepEqual(supplierAccess({ role: 'ROL-02', capabilities }), { read: true, manage: true, approve: false });
    assert.deepEqual(supplierAccess({ role: 'ROL-03', capabilities }), { read: true, manage: false, approve: false });
    for (const context of [{ role: 'ROL-SYS', capabilities }, { role: 'ROL-02', capabilities: [] }]) {
        assert.deepEqual(supplierAccess(context), { read: false, manage: false, approve: false });
    }
});

test('sidebar, búsqueda y acceso directo del mapa exigen proveedores.ver; directorio sigue administrativo', () => {
    globalThis.window = { location: { origin: 'http://gintly.test' } };
    const urls = Object.fromEntries(NAVIGATION_GROUPS.flatMap((group) => group.items).map((item) => [item.urlKey, `http://gintly.test/${item.urlKey}`]));
    const context = { role: 'ROL-03', profiles: ['bodeguero'], capabilities: ['proveedores.ver'], branch: { id: 5 } };
    const items = flattenedNavigation(authorizedNavigation(context, urls));
    assert.equal(items.filter((item) => item.key === 'externalSuppliers').length, 1);
    assert.equal(isCurrentPageAuthorized(context, urls, 'http://gintly.test/suppliers/explore'), true);
    assert.equal(isCurrentPageAuthorized(context, urls, 'http://gintly.test/suppliers'), false);
    assert.equal(isCurrentPageAuthorized({ ...context, capabilities: ['inventario.ver'] }, urls, 'http://gintly.test/suppliers/explore'), false);
});

test('API: colección del mapa sin filtros y mutaciones sin reintento inseguro', async () => {
    const data = await readFile(new URL('../../resources/js/modules/suppliers/data.js', import.meta.url), 'utf8');
    assert.match(data, /read\('\/map\/suppliers', \{\}, signal\)/);
    assert.match(data, /expectedStatus: 201/); assert.match(data, /record.status !== 'pendiente'/);
    assert.doesNotMatch(data, /business_id|fetch\(|retry|initializeCsrf|per_page.*map\/suppliers/);
    const dialogs = await readFile(new URL('../../resources/js/modules/suppliers/dialogs.js', import.meta.url), 'utf8');
    assert.match(dialogs, /submitFormOnce/); assert.match(dialogs, /this.writing \|\| !this.access.manage/);
    assert.match(dialogs, /!this.access.approve/);
    assert.doesNotMatch(dialogs, /innerHTML|business_id|localStorage|sessionStorage/);
});
