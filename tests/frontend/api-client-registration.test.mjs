import test from 'node:test';
import assert from 'node:assert/strict';
import { api, ApiError, initializeCsrf } from '../../resources/js/core/api-client.js';
import { createRegistrationAttempt } from '../../resources/js/modules/registration/attempt.js';

async function environment(run, { origin = 'http://localhost:8840', apiBase = 'http://localhost:8840/api/v1' } = {}) {
    const original = { window: globalThis.window, document: globalThis.document, fetch: globalThis.fetch };
    const calls = []; const redirects = []; const events = [];
    globalThis.window = { location: { origin, assign: (url) => redirects.push(url) }, setTimeout, clearTimeout };
    globalThis.document = { cookie: 'XSRF-TOKEN=qa%3Dcsrf', querySelector: (selector) => {
        if (selector.includes('api-base-url')) return { content: apiBase };
        if (selector.includes('login-url')) return { content: '/login' };
        return null;
    }, dispatchEvent: (event) => events.push(event) };
    try { await run({ calls, redirects, events }); }
    finally { for (const [key, value] of Object.entries(original)) { if (value === undefined) delete globalThis[key]; else globalThis[key] = value; } }
}

test('cliente real con Fetch mock: handshake, JSON exacto, cookies, CSRF y UUID por header', () => environment(async ({ calls }) => {
    globalThis.fetch = async (url, options) => {
        calls.push({ url: String(url), options });
        return calls.length === 1 ? new Response(null, { status: 204 }) : new Response(JSON.stringify({ data: { business_slug: 'qa', owner_email: 'qa@example.test' } }), { status: 201 });
    };
    await initializeCsrf({ dispatchErrors: false });
    const body = { business: { name: 'QA', timezone: 'UTC' }, owner: { first_name: 'QA', last_name: 'Owner', email: 'qa@example.test', password: ' preserved !12 ', password_confirmation: ' preserved !12 ' } };
    const result = await api.post('/auth/register', body, { headers: { 'Idempotency-Key': '12345678-1234-4234-8234-123456789012' }, expectedStatus: 201, dispatchErrors: false, redirectOn401: false });
    assert.deepEqual(result, { data: { business_slug: 'qa', owner_email: 'qa@example.test' } }); // No unwrapping in the client.
    assert.equal(calls[0].url, 'http://localhost:8840/sanctum/csrf-cookie'); assert.equal(calls[1].url, 'http://localhost:8840/api/v1/auth/register');
    const { options } = calls[1]; assert.equal(options.method, 'POST'); assert.equal(options.credentials, 'same-origin');
    assert.equal(options.headers.get('Accept'), 'application/json'); assert.equal(options.headers.get('Content-Type'), 'application/json');
    assert.equal(options.headers.get('X-XSRF-TOKEN'), 'qa=csrf'); assert.match(options.headers.get('Idempotency-Key'), /^12345678-/);
    assert.deepEqual(JSON.parse(options.body), body); assert.equal(options.headers.get('Authorization'), null);
}));

test('HTTPS: base relativa conserva origen, cookies, CSRF y envelope sin doble extracción', () => environment(async ({ calls }) => {
    globalThis.fetch = async (url, options) => {
        calls.push({ url: String(url), options });
        return options.method === 'POST'
            ? new Response(JSON.stringify({ data: { business_slug: 'qa', owner_email: 'qa@example.test' } }), { status: 201 })
            : new Response(null, { status: 204 });
    };
    const machine = createRegistrationAttempt({ post: api.post, csrf: () => initializeCsrf({ dispatchErrors: false }),
        uuid: () => '12345678-1234-4234-8234-123456789012' });
    const result = await machine.submit({ business: { name: 'QA', timezone: 'UTC' }, owner: {} });
    assert.equal(result.state, 'success');
    assert.deepEqual(result.result, { business_slug: 'qa', owner_email: 'qa@example.test' });
    assert.equal(calls[0].url, 'https://registration.example.test/sanctum/csrf-cookie');
    assert.equal(calls[1].url, 'https://registration.example.test/api/v1/auth/register');
    assert.equal(calls[1].options.credentials, 'same-origin');
    assert.equal(calls[1].options.headers.get('X-XSRF-TOKEN'), 'qa=csrf');
}, { origin: 'https://registration.example.test', apiBase: '/api/v1' }));

test('cliente: Retry-After preservado; 401 público no redirige y 204 no parsea JSON', () => environment(async ({ redirects }) => {
    globalThis.fetch = async () => new Response('{"message":"wait"}', { status: 429, headers: { 'Retry-After': '17' } });
    await assert.rejects(api.post('/auth/register', {}, { dispatchErrors: false }), (error) => error instanceof ApiError && error.retryAfter === '17');
    globalThis.fetch = async () => new Response('{"message":"private"}', { status: 401 });
    await assert.rejects(api.post('/auth/register', {}, { dispatchErrors: false, redirectOn401: false })); assert.deepEqual(redirects, []);
    await assert.rejects(api.get('/me', {}, { dispatchErrors: false })); assert.deepEqual(redirects, ['/login']);
    globalThis.fetch = async () => ({ status: 204, ok: true, text: () => { throw new Error('204 must not parse'); } });
    assert.equal(await api.post('/auth/logout'), null);
}));

test('cliente: éxito HTTP distinto de 201 no confirma el registro; errores y timeout no se repiten', () => environment(async ({ calls }) => {
    globalThis.fetch = async (_url, options) => { calls.push(options); return new Response('{"data":{}}', { status: 200 }); };
    await assert.rejects(api.post('/auth/register', {}, { expectedStatus: 201, dispatchErrors: false }), (error) => error.code === 'unexpected_status' && error.status === 200);
    globalThis.fetch = async () => { throw new TypeError('network'); };
    await assert.rejects(api.post('/auth/register', {}, { dispatchErrors: false }), (error) => error.status === 0);
    globalThis.fetch = async (_url, { signal }) => new Promise((_resolve, reject) => signal.addEventListener('abort', () => reject(new DOMException('timeout', 'AbortError')), { once: true }));
    await assert.rejects(api.post('/auth/register', {}, { timeout: 5, dispatchErrors: false }), (error) => error.code === 'request_aborted');
    assert.equal(calls.length, 1);
}));
