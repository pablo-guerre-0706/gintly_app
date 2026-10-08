import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { webcrypto } from 'node:crypto';
import { FIELDS, registrationPayload, validateRegistration, registrationResult, firstErrorField, retryDelay } from '../../resources/js/modules/registration/contract.js';
import { createRegistrationAttempt, secureUuid } from '../../resources/js/modules/registration/attempt.js';

const values = () => ({ 'business.name': '  QA Registro  ', 'business.timezone': 'America/Managua', 'owner.first_name': ' María   José ', 'owner.last_name': ' Cruz ',
    'owner.email': ' QA@example.test ', 'owner.password': '  QA!Password93672  ', 'owner.password_confirmation': '  QA!Password93672  ', '_token': 'ignored', 'plan': 'ignored' });
const payload = () => registrationPayload(values());
const success = () => ({ data: { business_slug: 'qa-registro', owner_email: 'qa@example.test' } });
const failure = (status, extras = {}) => Object.assign(new Error('External diagnostic must not leak'), { status, ...extras });
let sequence = 0;
const uuid = () => `12345678-1234-4234-8234-${String(++sequence).padStart(12, '0')}`;

test('payload: allowlist exacta, normalización y contraseña con espacios intacta', () => {
    const body = payload();
    assert.deepEqual(Object.keys(body), ['business', 'owner']);
    assert.deepEqual(Object.keys(body.business), ['name', 'timezone']);
    assert.deepEqual(Object.keys(body.owner), ['first_name', 'last_name', 'email', 'password', 'password_confirmation']);
    assert.equal(body.business.name, 'QA Registro'); assert.equal(body.owner.first_name, 'María José');
    assert.equal(body.owner.email, 'qa@example.test'); assert.equal(body.owner.password, values()['owner.password']);
    assert.equal(body.owner.password_confirmation, body.owner.password);
});

test('validación: política real, límites combinados, email, zona horaria y confirmación', () => {
    assert.deepEqual(validateRegistration(payload(), ['America/Managua']), {});
    const body = payload(); body.owner.first_name = 'á'.repeat(145); body.owner.last_name = 'Cruz Ortiz'; body.business.name = 'x'.repeat(151);
    body.owner.email = 'wrong'; body.owner.password_confirmation = body.owner.password.trim(); body.business.timezone = 'Unknown';
    const errors = validateRegistration(body, ['America/Managua']);
    for (const path of ['owner.first_name', 'owner.email', 'owner.password_confirmation', 'business.name', 'business.timezone']) assert.ok(errors[path]);
    for (const password of ['short1!', 'OnlyLettersLonger', '123456789012!', 'withoutSymbols12']) {
        const invalid = payload(); invalid.owner.password = invalid.owner.password_confirmation = password;
        assert.ok(validateRegistration(invalid, ['America/Managua'])['owner.password']);
    }
    assert.equal(firstErrorField({ 'business.name': ['Invalid'], 'owner.email': ['Invalid'] }), 'owner.email');
    assert.equal(FIELDS['business.timezone'].step, 2); assert.equal(FIELDS['owner.email'].step, 1);
});

test('contraseña: Unicode/símbolos/espacios admitidos, sin whitelist legacy ni máximo artificial', () => {
    for (const password of ['Registro#2026-ABC', 'Registro+2026-ABC', 'Registro2026 ABC', 'Árbol#2026'.repeat(2), 'a1!'.repeat(200)]) {
        const body = payload(); body.owner.password = body.owner.password_confirmation = password;
        assert.equal(validateRegistration(body, ['America/Managua'])['owner.password'], undefined);
        assert.equal(validateRegistration(body, ['America/Managua'])['owner.password_confirmation'], undefined);
    }
    const body = payload(); body.owner.password = body.owner.password_confirmation = 'abcdefghijklmnop';
    const errors = validateRegistration(body, ['America/Managua']);
    assert.ok(errors['owner.password']);
    assert.equal(errors['owner.password_confirmation'], undefined);
});

test('UUID: criptográfico v4, fallback seguro y cierre si no hay fuente segura', () => {
    for (const crypto of [webcrypto, { getRandomValues: webcrypto.getRandomValues.bind(webcrypto) }]) assert.match(secureUuid(crypto), /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    assert.throws(() => secureUuid({}));
});

test('submit: bloqueo síncrono incluso antes del handshake, una escritura', async () => {
    let release; const handshake = new Promise((resolve) => { release = resolve; }); const calls = [];
    const machine = createRegistrationAttempt({ uuid, csrf: () => handshake, post: async (...args) => { calls.push(args); return success(); } });
    const first = machine.submit(payload()); assert.equal(machine.state, 'submitting');
    assert.equal((await machine.submit(payload())).ignored, true); release();
    const result = await first; assert.equal(result.state, 'success'); assert.equal(calls.length, 1);
    assert.equal(calls[0][0], '/auth/register'); assert.equal(calls[0][2].expectedStatus, 201);
    assert.match(calls[0][2].headers['Idempotency-Key'], /^12345678-/);
    assert.equal(calls[0][2].redirectOn401, false); assert.equal(calls[0][2].dispatchErrors, false);
    assert.equal((await machine.submit(payload())).ignored, true);
    assert.deepEqual(Object.keys(result), ['state', 'retryAt', 'result']);
});

test('handshake inicial fallido: no POST, no afirmación de posible alta; permite corregir', async () => {
    let handshakes = 0; let writes = 0;
    const machine = createRegistrationAttempt({ uuid, csrf: async () => { if (++handshakes === 1) throw failure(0); },
        post: async () => { writes++; return success(); } });
    const rejected = await machine.submit(payload());
    assert.equal(rejected.state, 'editing'); assert.equal(writes, 0);
    assert.match(rejected.message, /No se envió/); assert.doesNotMatch(rejected.message, /podría haberse creado/);
    assert.equal((await machine.submit(payload())).state, 'success'); assert.equal(writes, 1);
});

test('handshake fallido después de un POST incierto nunca libera el snapshot ni rota la clave', async () => {
    let handshakes = 0; const calls = [];
    const machine = createRegistrationAttempt({ uuid, csrf: async () => { if (++handshakes === 2) throw failure(0); },
        post: async (...args) => { calls.push(args); if (calls.length === 1) throw failure(0); return success(); } });
    assert.equal((await machine.submit(payload())).state, 'uncertain');
    assert.equal((await machine.submit()).state, 'uncertain'); assert.equal(calls.length, 1);
    assert.equal((await machine.submit()).state, 'success');
    assert.strictEqual(calls[0][1], calls[1][1]); assert.equal(calls[0][2].headers['Idempotency-Key'], calls[1][2].headers['Idempotency-Key']);
});

test('404/405: diagnóstico de ruta, sin éxito, sin nueva clave automática', async () => {
    for (const status of [404, 405]) {
        const calls = [];
        const machine = createRegistrationAttempt({ uuid, csrf: async () => {}, post: async (...args) => { calls.push(args); throw failure(status); } });
        const rejected = await machine.submit(payload());
        assert.equal(rejected.state, 'recoverable'); assert.match(rejected.message, new RegExp('HTTP ' + status));
        assert.doesNotMatch(rejected.message, /podría haberse creado/);
        await machine.submit(); assert.strictEqual(calls[0][1], calls[1][1]);
        assert.equal(calls[0][2].headers['Idempotency-Key'], calls[1][2].headers['Idempotency-Key']);
    }
});

for (const error of [failure(0), failure(500), failure(503), failure(0, { code: 'request_aborted' })]) {
    test(`incertidumbre ${error.status}/${error.code ?? 'default'}: mismo snapshot y clave; sin edición silenciosa`, async () => {
        const calls = []; let count = 0;
        const machine = createRegistrationAttempt({ uuid, csrf: async () => {}, post: async (...args) => { calls.push(args); if (++count === 1) throw error; return success(); } });
        const original = payload(); assert.equal((await machine.submit(original)).state, 'uncertain');
        original.business.name = 'Changed'; const changed = payload(); changed.owner.password = 'Different';
        assert.equal((await machine.submit(changed)).state, 'success');
        assert.strictEqual(calls[0][1], calls[1][1]); assert.equal(calls[0][1].business.name, 'QA Registro');
        assert.equal(calls[0][2].headers['Idempotency-Key'], calls[1][2].headers['Idempotency-Key']);
        assert.ok(Object.isFrozen(calls[0][1].owner));
    });
}

test('422: mapea rutas literales, libera intento y permite corrección con nueva clave', async () => {
    const calls = []; let count = 0;
    const machine = createRegistrationAttempt({ uuid, csrf: async () => {}, post: async (...args) => { calls.push(args); if (++count === 1) throw failure(422, { errors: { 'owner.email': ['Invalid'] } }); return success(); } });
    const rejected = await machine.submit(payload()); assert.equal(rejected.state, 'editing'); assert.equal(firstErrorField(rejected.errors), 'owner.email');
    const corrected = payload(); corrected.owner.email = 'corrected@example.test'; await machine.submit(corrected);
    assert.notEqual(calls[0][2].headers['Idempotency-Key'], calls[1][2].headers['Idempotency-Key']); assert.equal(calls[1][1].owner.email, corrected.owner.email);
});

test('403: no logout, no nueva creación y estado autenticado explícito', async () => {
    let count = 0; const machine = createRegistrationAttempt({ uuid, csrf: async () => {}, post: async () => { count++; throw failure(403); } });
    assert.equal((await machine.submit(payload())).state, 'forbidden'); assert.equal((await machine.submit(payload())).ignored, true); assert.equal(count, 1);
});

test('409 idempotencia: no rotación automática ni reenvío; 409 slug: replay mismo intento', async () => {
    let count = 0; const blocked = createRegistrationAttempt({ uuid, csrf: async () => {}, post: async () => { count++; throw failure(409, { code: 'REGISTRATION_IDEMPOTENCY_CONFLICT' }); } });
    assert.equal((await blocked.submit(payload())).state, 'conflict'); assert.equal((await blocked.submit()).ignored, true); assert.equal(count, 1);
    const calls = []; const slug = createRegistrationAttempt({ uuid, csrf: async () => {}, post: async (...args) => { calls.push(args); if (calls.length === 1) throw failure(409, { code: 'BUSINESS_SLUG_CONFLICT' }); return success(); } });
    assert.equal((await slug.submit(payload())).state, 'recoverable'); await slug.submit(); assert.equal(calls[0][2].headers['Idempotency-Key'], calls[1][2].headers['Idempotency-Key']);
});

test('419: una renovación y como máximo dos POST idénticos; segundo 419 recuperable', async () => {
    const calls = []; let handshakes = 0;
    const machine = createRegistrationAttempt({ uuid, csrf: async () => { handshakes++; }, post: async (...args) => { calls.push(args); throw failure(419); } });
    assert.equal((await machine.submit(payload())).state, 'recoverable'); assert.equal(calls.length, 2); assert.equal(handshakes, 2);
    assert.strictEqual(calls[0][1], calls[1][1]); assert.deepEqual(calls[0][2], calls[1][2]);
});

test('419 seguido de éxito: solo replay automático seguro', async () => {
    let count = 0; const machine = createRegistrationAttempt({ uuid, csrf: async () => {}, post: async () => { if (++count === 1) throw failure(419); return success(); } });
    assert.equal((await machine.submit(payload())).state, 'success'); assert.equal(count, 2);
});

test('429: Retry-After segundos/fecha; no bucle ni envío antes de plazo', async () => {
    let time = 100000; let count = 0; const keys = [];
    const machine = createRegistrationAttempt({ uuid, now: () => time, csrf: async () => {}, post: async (_path, _body, options) => { keys.push(options.headers['Idempotency-Key']); if (++count === 1) throw failure(429, { retryAfter: '10' }); return success(); } });
    assert.equal((await machine.submit(payload())).retryAt, 110000); assert.equal((await machine.submit()).ignored, true); assert.equal(count, 1);
    time = 110000; await machine.submit(); assert.equal(keys[0], keys[1]);
    assert.equal(retryDelay(new Date(120000).toUTCString(), 100000), 20000); assert.equal(retryDelay(null), 60000);
});

test('201 ilegible/inesperado: nunca éxito y replay manual con el mismo UUID', async () => {
    for (const response of ['<html>Error</html>', null, {}, { data: { token: 'not accepted' } }, { data: { ...success().data, token: 'not accepted' } }]) {
        let count = 0; const calls = []; const machine = createRegistrationAttempt({ uuid, csrf: async () => {}, post: async (...args) => { calls.push(args); return ++count === 1 ? response : success(); } });
        assert.equal((await machine.submit(payload())).state, 'uncertain'); assert.equal(count, 1); await machine.submit();
        assert.equal(calls[0][2].headers['Idempotency-Key'], calls[1][2].headers['Idempotency-Key']);
    }
    assert.deepEqual(registrationResult(success()), success().data);
});

test('UI, legacy y seguridad: sin persistencia, auto-login, passwords en resumen ni billing', async () => {
    const root = new URL('../../', import.meta.url);
    const [wizard, view, web, client, login] = await Promise.all(['resources/js/modules/registration/wizard.js', 'resources/views/auth/register.blade.php', 'routes/web.php', 'resources/js/core/api-client.js', 'resources/js/modules/security/auth.js'].map((file) => readFile(new URL(file, root), 'utf8')));
    assert.doesNotMatch(wizard, /localStorage|sessionStorage|indexedDB|console\.|auth\/login|innerHTML|registration_wizard/);
    assert.match(wizard, /erasePasswords\(\)/); assert.match(wizard, /machine\.state === 'submitting'/);
    assert.match(view, /data-register-dashboard hidden/);
    assert.match(wizard, /\[data-register-dashboard\]'\)\.hidden = machine\.state !== 'forbidden'/);
    assert.doesNotMatch(view, /data-register-review="owner.password|name="_token|name="business_id|name="plan|name="payment/);
    assert.match(web, /Route::view\('\/', 'auth\.register'\)/); assert.doesNotMatch(web, /RegisterWizardController/); assert.match(web, /abort\(410/);
    assert.match(client, /credentials: 'same-origin'/); assert.match(client, /'X-XSRF-TOKEN'/); assert.match(client, /retryAfter: response.headers.get\('Retry-After'\)/);
    assert.match(login, /expectedStatus: 200/); assert.match(login, /error.status !== 419/);
    assert.doesNotMatch(wizard, /BUSINESS_SLUG_CONFLICT/); // manejo de dominio aislado en attempt.js
});
