import test from 'node:test';
import assert from 'node:assert/strict';
import { catalog, selection, subscription, checkout, billingError, priceText } from '../../resources/js/modules/billing/contracts.js';
import { createCheckoutAttempt } from '../../resources/js/modules/billing/attempt.js';
import { billingSelectionState } from '../../resources/js/modules/billing/selection-state.js';
import { SubscriptionPoller } from '../../resources/js/modules/billing/poller.js';
import { savePreference, readPreference, saveCheckout, readCheckout, clearBillingStorage } from '../../resources/js/core/billing-storage.js';
import { readFile } from 'node:fs/promises';
const plans = catalog({ data: [{ key: 'basic', name: 'Inicial', currency: 'NIO', prices: { monthly: { nio_minor: 116000, nio: '1160.00' }, annual: { nio_minor: 1392000, nio: '13920.00' } }, limits: { branches: 1, cash_sessions: 1 }, features: ['pos'] }] });
const payload = { plan: 'basic', period: 'monthly' }, key = '12345678-1234-4234-8234-123456789012';
const response = { data: { checkout_url: 'https://checkout.test/qa', plan_key: 'basic', period: 'monthly', status: 'created', expires_at: null } };
function harness(post, options = {}) { return createCheckoutAttempt({ plans, post, csrf: async () => {}, uuid: () => key, ...options }); }
test('Catalog and exact selections reject missing/foreign fields; money uses minor units', () => {
    assert.equal(priceText(plans[0], 'annual'), 'C$ 13,920.00');
    assert.throws(() => selection({ ...payload, business_id: 1 }, plans));
    assert.throws(() => selection({ plan: 'fake', period: 'monthly' }, plans));
    assert.throws(() => catalog({ data: [{ ...plans[0], currency: 'USD' }] }));
    assert.throws(() => catalog({ data: [{ ...plans[0], prices: {} }] }));
});
test('Access is supplied boolean, not inferred from status or dates', () => {
    const value = { status: 'canceled', grants_access: true, plan_key: 'basic', period: 'monthly', paid_until: '2030-01-01T00:00:00Z', pending_change: null };
    assert.equal(subscription({ data: value }).grants_access, true);
    assert.equal(subscription({ data: { ...value, status: 'active', grants_access: false } }).grants_access, false);
    assert.throws(() => subscription({ data: { ...value, grants_access: undefined } }));
});
test('Checkout only accepts complete same-selection HTTPS contract', () => {
    assert.equal(checkout(response, payload).checkout_url, response.data.checkout_url);
    for (const url of ['http://checkout.test', 'javascript:alert(1)', 'https://user:pass@checkout.test']) assert.throws(() => checkout({ data: { ...response.data, checkout_url: url } }, payload));
    assert.throws(() => checkout({ data: { ...response.data, plan_key: 'other' } }, payload));
});
test('Synchronous single flight and exact body/header; no provider data persisted', async () => {
    let release, calls = 0;
    const held = new Promise(resolve => { release = resolve; });
    const h = harness(async (path, body, options) => { calls++; assert.equal(path, '/billing/checkout'); assert.deepEqual(body, payload); assert.equal(options.headers['Idempotency-Key'], key); await held; return response; });
    const first = h.submit(payload); assert.equal(h.state, 'submitting'); assert.equal((await h.submit(payload)).ignored, true);
    release(); assert.equal((await first).state, 'checkout'); assert.equal(calls, 1);
});
for (const failure of [new Error('network'), { status: 500 }, { status: 503, code: 'BILLING_UNAVAILABLE' }, { status: 409, code: 'CHECKOUT_RESULT_UNKNOWN' }]) test('Uncertain attempt preserves immutable snapshot '+(failure.code ?? failure.status ?? 'network'), async () => {
    const calls = []; const h = harness(async (_, body, options) => { calls.push({ body, key: options.headers['Idempotency-Key'] }); if (calls.length === 1) throw failure; return response; });
    assert.equal((await h.submit(payload)).state, 'recoverable');
    await h.submit({ plan: 'different', period: 'annual' }); assert.deepEqual(calls[0], calls[1]); assert(Object.isFrozen(h.snapshot));
});
test('422 clears logical attempt; correction gets new key', async () => {
    let n = 0; const keys = []; const h = harness(async (_, __, options) => { keys.push(options.headers['Idempotency-Key']); if (keys.length === 1) throw { status: 422, errors: { plan: ['invalid'] } }; return response; }, { uuid: () => key.slice(0, -1)+(++n) });
    assert.equal((await h.submit(payload)).state, 'editing'); assert.equal(h.snapshot, null); await h.submit(payload); assert.notEqual(keys[0], keys[1]);
});
test('419 recovery belongs to attempt and replays at most once with same key', async () => {
    let count = 0, csrf = 0; const keys = [];
    const h = harness(async (_, __, options) => { keys.push(options.headers['Idempotency-Key']); count++; throw { status: 419 }; }, { csrf: async () => { csrf++; } });
    await h.submit(payload); assert.equal(count, 2); assert.equal(csrf, 2); assert.equal(keys[0], keys[1]);
});
test('Expired key requires explicit new attempt; other conflicts never change key', async () => {
    const h = harness(async () => { throw { status: 409, code: 'CHECKOUT_KEY_EXPIRED' }; });
    await h.submit(payload); assert.equal((await h.submit(payload)).ignored, true); assert.equal(h.newAfterExpiry(), true); assert.equal(h.snapshot, null);
    const blocked = harness(async () => { throw { status: 409, code: 'CHECKOUT_IDEMPOTENCY_CONFLICT' }; });
    await blocked.submit(payload); assert.equal(blocked.newAfterExpiry(), false); assert.equal((await blocked.submit(payload)).ignored, true);
});
test('429 respects Retry-After and no automatic submission', async () => {
    let now = 0, count = 0; const h = harness(async () => { count++; throw { status: 429, retryAfter: '10' }; }, { now: () => now });
    await h.submit(payload); assert.equal(h.retryAt, 10000); await h.submit(payload); assert.equal(count, 1); now = 10001; await h.submit(); assert.equal(count, 2);
});
test('Illegible response does not imply success or discard key', async () => {
    const h = harness(async () => '<html>error</html>'); assert.equal((await h.submit(payload)).state, 'recoverable'); assert.deepEqual(h.snapshot, payload);
});

test('Fresh billing selectors unlock; initialization and in-flight submission stay closed', () => {
    const editing = harness(async () => response);
    assert.deepEqual(billingSelectionState({ attempt: editing }), { disabled: false, recovering: false, message: '', recoveryMessage: '' });
    const loading = billingSelectionState({ attempt: null });
    assert(loading.disabled); assert.match(loading.message, /Actualizar estado/);
    const busy = billingSelectionState({ attempt: editing, busy: true });
    assert(busy.disabled); assert.match(busy.message, /recibir la respuesta/);
});

test('Restored UUID locks selectors with a nearby explanation and safe same-attempt recovery', async () => {
    const calls = [];
    const h = harness(async (_, body, options) => { calls.push({ body, key: options.headers['Idempotency-Key'] }); throw { status: 503, code: 'BILLING_UNAVAILABLE' }; }, { restore: { key, payload } });
    let choice = billingSelectionState({ attempt: h });
    assert(choice.disabled && choice.recovering); assert.match(choice.message, /bloqueados.*intento/); assert.match(choice.recoveryMessage, /mismo intento/);
    await h.submit({ plan: 'fake', period: 'annual' });
    choice = billingSelectionState({ attempt: h });
    assert(choice.disabled); assert.match(choice.recoveryMessage, /configuración o el proveedor/);
    await h.submit(); assert.deepEqual(calls[0], calls[1]); assert.deepEqual(calls[0], { body: payload, key });
});

test('Only server-confirmed expiry enables an explicit new selection; focus has a usable target', async () => {
    const h = harness(async () => { throw { status: 409, code: 'CHECKOUT_KEY_EXPIRED' }; });
    await h.submit(payload);
    assert.match(billingSelectionState({ attempt: h }).message, /servidor confirmó/);
    assert(h.newAfterExpiry()); assert.equal(billingSelectionState({ attempt: h }).disabled, false);
    assert.equal(h.failure, null);
    const source = await readFile(new URL('../../resources/js/modules/billing/index.js', import.meta.url), 'utf8');
    assert.match(source, /attempt\.newAfterExpiry\(\).*render\(\); form\.elements\.plan\.focus\(\)/);
});

test('Blocked authorization/idempotency conflicts explain review and never release the attempt', async () => {
    for (const error of [{ status: 403 }, { status: 409, code: 'CHECKOUT_IDEMPOTENCY_CONFLICT' }]) {
        const h = harness(async () => { throw error; });
        await h.submit(payload);
        const choice = billingSelectionState({ attempt: h });
        assert(choice.disabled); assert.match(choice.recoveryMessage, /revisión/);
        assert.equal(h.newAfterExpiry(), false); assert.deepEqual(h.snapshot, payload);
    }
});

test('Diagnostic failure keeps only public identifiers in memory, never changing stored UUID/payload', async () => {
    const persisted = [];
    const h = harness(async () => { throw { status: 503, code: 'BILLING_UNAVAILABLE', payload: { private: 'never store' }, message: 'provider details' }; }, { persist: value => persisted.push(value) });
    await h.submit(payload);
    assert.deepEqual(h.failure, { status: 503, code: 'BILLING_UNAVAILABLE' });
    assert(Object.isFrozen(h.failure)); assert.deepEqual(persisted, [{ key, snapshot: payload }]);
});

test('Demo and uncertain management never imply payment or unlock a checkout', () => {
    const h = harness(async () => response);
    const demo = billingSelectionState({ attempt: h, demo: true });
    assert(demo.disabled); assert.match(demo.message, /demostración/);
    assert.match(billingSelectionState({ attempt: h, mutationUnknown: true }).message, /Actualizar estado/);
});

test('Recovery is beside the selectors, outside the locked fieldset, with ARIA and non-submit controls', async () => {
    const blade = await readFile(new URL('../../resources/views/billing/index.blade.php', import.meta.url), 'utf8');
    assert(blade.indexOf('data-billing-recovery') < blade.indexOf('<fieldset data-billing-fields'));
    assert.equal((blade.match(/data-billing-recovery\b/g) || []).length, 1);
    assert.equal((blade.match(/data-checkout-retry\b/g) || []).length, 1);
    assert.match(blade, /id="billing-selection-help"[^>]*role="status"/);
    assert.match(blade, /name="plan"[^>]*aria-describedby="billing-selection-help billing-plan-error"/);
    assert.match(blade, /name="period"[^>]*aria-describedby="billing-selection-help billing-period-help billing-period-error"/);
    assert.match(blade, /type="button" data-checkout-retry/);
    assert.match(blade, /type="button" data-checkout-new/);
});
test('All domain codes have distinct curated messages and general403 is not billing', () => {
    for (const code of ['SUBSCRIPTION_REQUIRED', 'PLAN_FEATURE_UNAVAILABLE', 'CHECKOUT_IN_PROGRESS', 'CHECKOUT_KEY_EXPIRED', 'CHECKOUT_RESULT_UNKNOWN', 'CHECKOUT_IDEMPOTENCY_CONFLICT', 'SUBSCRIPTION_ALREADY_ACTIVE', 'NO_ACTIVE_SUBSCRIPTION', 'PLAN_CHANGE_INVALID', 'PLAN_LIMIT_EXCEEDED', 'BILLING_UNAVAILABLE', 'LIMIT_CHECK_UNAVAILABLE']) assert(billingError({ code }).length > 30);
    assert.match(billingError({ status: 403 }), /No lo interpretamos/);
    assert.match(billingError({ code: 'PLAN_LIMIT_EXCEEDED', message: 'Su plan permite un máximo de 1 sucursal(es) activa(s).' }), /máximo de 1 sucursal/);
});
test('Commercial recovery runs once outside billing; billing owns its feedback without duplicate toasts', async () => {
    const source = await readFile(new URL('../../resources/js/core/commercial-recovery.js', import.meta.url), 'utf8');
    const saved = { window: globalThis.window, document: globalThis.document };
    try {
        for (const pathname of ['/billing', '/dashboard']) {
            const listeners = [], redirects = [], messages = [];
            globalThis.window = { location: { pathname, assign: url => redirects.push(url) }, addEventListener: (_, callback) => listeners.push(callback), qaMessages: messages };
            globalThis.document = { querySelector: () => ({ content: '/billing' }) };
            // Replace only the notification dependency; execute the delivered event handler unchanged.
            const isolated = source.replace("import { notify } from './notifications';", 'const notify = message => window.qaMessages.push(message);');
            const module = await import('data:text/javascript,' + encodeURIComponent(isolated) + '#' + encodeURIComponent(pathname));
            module.initCommercialRecovery(); module.initCommercialRecovery(); assert.equal(listeners.length, 1);
            for (const code of ['SUBSCRIPTION_REQUIRED', 'SUBSCRIPTION_REQUIRED', 'PLAN_FEATURE_UNAVAILABLE', 'PLAN_FEATURE_UNAVAILABLE', 'FORBIDDEN']) listeners[0]({ detail: { code } });
            assert.equal(redirects.length, pathname === '/billing' ? 0 : 1);
            assert.equal(messages.length, pathname === '/billing' ? 0 : 1);
        }
    } finally { Object.assign(globalThis, saved); }
});
test('Storage contains only tenant-bound key and selection; logout clears; cross-tenant rejects', () => {
    const values = new Map(), storage = { getItem: k => values.get(k), setItem: (k,v) => values.set(k,v), removeItem: k => values.delete(k) };
    savePreference(payload, storage); assert.deepEqual(readPreference(storage), payload);
    saveCheckout(1, { key, snapshot: payload, checkout_url: 'secret' }, storage);
    assert.deepEqual(readCheckout(1, storage), { key, payload }); assert(![...values.values()].join().includes('secret'));
    assert.equal(readCheckout(2, storage), null); clearBillingStorage(storage); assert.equal(values.size, 0);
});
test('Polling max12, no overlaps, visibility pause, grants_access stops, Retry-After', async () => {
    let visible = true, calls = 0; const queued = []; const result = { grants_access: false };
    const poller = new SubscriptionPoller({ load: async () => { calls++; return result; }, visible: () => visible, onResult: () => {}, onError: () => {}, onExhausted: () => {}, schedule: (fn, delay) => { queued.push({fn,delay}); return queued.length; }, cancel: () => {} });
    poller.start(); await new Promise(setImmediate); assert.equal(calls, 1); visible = false; await queued.shift().fn(); assert.equal(calls, 1); visible = true;
    for (let i = 0; i < 11; i++) { await queued.shift().fn(); await new Promise(setImmediate); }
    assert.equal(calls, 12); assert(poller.stopped);
    poller.start(); result.grants_access = true; await new Promise(setImmediate); assert(poller.stopped);
});
test('Restart aborts obsolete polling generation', async () => {
    let resolve; let count = 0; const p = new SubscriptionPoller({ load: () => { count++; return new Promise(done => { resolve = done; }); }, visible: () => true, onResult: () => {}, onError: () => {}, onExhausted: () => {}, schedule: () => 1, cancel: () => {} });
    p.start(); const old = resolve; p.start(); old({grants_access:false}); await new Promise(setImmediate); assert.equal(count, 2); assert.equal(p.running, true); p.stop(); resolve({grants_access:false});
});
