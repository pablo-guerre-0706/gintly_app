import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { CashHistorySource, historyFilters } from '../../resources/js/modules/operations/cash-history-source.js';
import { OpeningVerificationError, openVerifiedSession } from '../../resources/js/modules/operations/cash-opening-flow.js';

const payload = { cash_register_id: 2, opening_amount: '1000.00', opening_amount_usd: '20.00' };
const session = { id: 9, status: 'abierta', cash_register_id: 2, opening_amount: '1000.00', opening_amount_usd: '20.00' };
const httpError = (status) => Object.assign(new Error(`HTTP ${status}`), { status });
const records = Array.from({ length: 53 }, (_, i) => ({
    id: i + 1, status: i % 3 === 0 ? 'abierta' : i % 3 === 1 ? 'cerrada' : 'descuadrada',
    closed_at: i % 3 === 0 ? null : new Date(Date.UTC(2026, 0, 1, 0, i)).toISOString(),
}));

function paginatedReader(calls) {
    return async (path, query) => {
        assert.equal(path, '/cash-sessions'); calls.push(query);
        const filtered = records.filter((record) => !query.status || record.status === query.status)
            .sort((a, b) => b.id - a.id);
        return { data: filtered.slice((query.page - 1) * query.per_page, query.page * query.per_page),
            meta: { current_page: query.page, last_page: Math.max(1, Math.ceil(filtered.length / query.per_page)), total: filtered.length } };
    };
}

test('cada vista se traduce exclusivamente a filtros API reales', async () => {
    const expected = { all: undefined, openings: undefined, open: 'abierta', unbalanced: 'descuadrada' };
    for (const [view, status] of Object.entries(expected)) {
        const calls = []; const source = new CashHistorySource(paginatedReader(calls));
        await source.load(historyFilters([['view', view], ['from', '2026-01-01'], ['cash_register_id', '2'], ['business_id', '999']]));
        assert.equal(calls[0].status, status); assert.equal(calls[0].from, '2026-01-01');
        assert.equal(calls[0].cash_register_id, '2'); assert.equal(calls[0].view, undefined); assert.equal(calls[0].business_id, undefined);
    }
});

test('Cierres combina TODAS las páginas pertinentes sin filtrar una página mixta', async () => {
    const calls = []; const source = new CashHistorySource(paginatedReader(calls), () => {}, 5);
    const expected = records.filter((record) => record.closed_at).sort((a, b) => b.id - a.id);
    const result = [];
    for (let page = 1; page <= Math.ceil(expected.length / 5); page += 1) {
        const response = await source.load({ view: 'closings' }, page);
        assert.equal(response.meta.total, expected.length); result.push(...response.data);
    }
    assert.deepEqual(result.map((record) => record.id), expected.map((record) => record.id));
    assert.equal(new Set(result.map((record) => record.id)).size, result.length);
    assert.ok(calls.every((query) => ['cerrada', 'descuadrada'].includes(query.status) && query.sort === 'closed_at'));
    assert.equal(new Set(calls.map((query) => `${query.status}-${query.page}`)).size, calls.length);
});

test('Cierres carga solo prefijos necesarios y conserva los filtros de cajero/caja/período', async () => {
    const calls = []; const source = new CashHistorySource(paginatedReader(calls), () => {}, 5);
    const filters = { view: 'closings', opened_by: '3', cash_register_id: '2', from: '2026-01-01', to: '2026-01-31' };
    await source.load(filters, 1);
    assert.equal(calls.length, 2); assert.ok(calls.every((query) => query.page === 1 && query.opened_by === '3' && query.to === filters.to));
    await source.load(filters, 2); assert.equal(calls.length, 4);
    source.reset(); await source.load(filters, 1); assert.equal(calls.length, 6);
});

test('lista vacía legítima y fallo de un flujo de cierres no producen un éxito parcial', async () => {
    const empty = new CashHistorySource(async () => ({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } }));
    assert.deepEqual((await empty.load({ view: 'closings' })).meta, { current_page: 1, last_page: 1, total: 0 });
    const broken = new CashHistorySource(async (_, query) => {
        if (query.status === 'descuadrada') throw httpError(500);
        return { data: [], meta: { current_page: 1, last_page: 1, total: 0 } };
    });
    await assert.rejects(broken.load({ view: 'closings' }), /HTTP 500/);
});

test('el validador de alcance se aplica a cada página del historial', async () => {
    const source = new CashHistorySource(paginatedReader([]), () => { throw new Error('Fuera de sucursal'); });
    await assert.rejects(source.load({ view: 'all' }), /Fuera de sucursal/);
});

test('201 exige consultar la sesión activa y confirmar ID, caja y fondos antes del éxito', async () => {
    const calls = []; let reads = 0;
    const result = await openVerifiedSession({ payload,
        current: async () => { calls.push('GET current'); return ++reads === 1 ? null : session; },
        post: async (input) => { assert.deepEqual(input, payload); calls.push('POST'); return { data: session }; },
    });
    assert.equal(result.kind, 'created'); assert.deepEqual(calls, ['GET current', 'POST', 'GET current']);
});

test('una pestaña obsoleta con sesión abierta no vuelve a ejecutar POST', async () => {
    const result = await openVerifiedSession({ payload, current: async () => session, post: async () => assert.fail('No debe abrir de nuevo') });
    assert.equal(result.kind, 'existing');
});

test('409 y 500 recuperan una sesión persistida sin repetir el POST', async () => {
    for (const status of [409, 500, 503, 0]) {
        let reads = 0; let writes = 0;
        const result = await openVerifiedSession({ payload, current: async () => ++reads === 1 ? null : session,
            post: async () => { writes += 1; throw httpError(status); } });
        assert.equal(result.kind, 'recovered'); assert.equal(reads, 2); assert.equal(writes, 1);
    }
});

test('409 sin sesión confirma la ausencia antes de devolver el error y preservar importes', async () => {
    let reads = 0;
    await assert.rejects(openVerifiedSession({ payload, current: async () => { reads += 1; return null; }, post: async () => { throw httpError(409); } }), /HTTP 409/);
    assert.equal(reads, 2); assert.equal(payload.opening_amount, '1000.00');
});

test('422 es fallo concreto, no dispara reconciliación adicional ni reintento automático', async () => {
    let reads = 0; let writes = 0;
    await assert.rejects(openVerifiedSession({ payload, current: async () => { reads += 1; return null; }, post: async () => { writes += 1; throw httpError(422); } }), /HTTP 422/);
    assert.equal(reads, 1); assert.equal(writes, 1);
});

test('resultado incierto o 201 contradictorio bloquean otra apertura hasta verificar', async () => {
    let reads = 0;
    await assert.rejects(openVerifiedSession({ payload, current: async () => { if (++reads === 1) return null; throw httpError(503); }, post: async () => { throw httpError(0); } }), OpeningVerificationError);
    await assert.rejects(openVerifiedSession({ payload, current: async () => null, post: async () => ({ data: session }) }), OpeningVerificationError);
});

test('markup final tiene cinco vistas, sin tabla rígida ni columnas mixtas; apertura limpia antes de navegar', async () => {
    for (const file of ['operations/cash-history', 'administration/cash-sessions']) {
        const html = await readFile(new URL(`../../resources/views/${file}.blade.php`, import.meta.url), 'utf8');
        for (const label of ['Todas', 'Aperturas', 'Cierres', 'Abiertas', 'Descuadradas']) assert.ok(html.includes(`>${label}</option>`));
        assert.ok(!html.includes('name="status"')); assert.ok(!html.includes('min-w-['));
        assert.ok(!html.includes('Apertura / cierre')); assert.ok(!html.includes('Fondo NIO / USD'));
        if (file === 'administration/cash-sessions') {
            for (const field of ['cash_register_id', 'opened_by']) {
                assert.match(html, new RegExp(`<select class="[^"]*w-full min-w-0[^"]*" name="${field}"`));
            }
        }
    }
    const source = await readFile(new URL('../../resources/js/modules/operations/cash-open.js', import.meta.url), 'utf8');
    assert.match(source, /this\.form\.reset\(\); this\.grid\?\.reset\(\);[\s\S]*window\.location\.assign\(cashUrls\(\)\.summary\)/);
});
