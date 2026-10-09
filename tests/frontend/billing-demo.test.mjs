import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { subscription } from '../../resources/js/modules/billing/contracts.js';

const none = { status: 'none', grants_access: false, plan_key: null, period: null, paid_until: null };
const demo = { ...none, grants_access: true, access_source: 'demo', demo_access: {
    plan_key: 'cadena', starts_at: '2026-10-08T12:00:00Z', expires_at: '2026-10-15T12:00:00Z',
} };

test('Explicit demo permits login to continue without pretending payment or subscription', () => {
    assert.equal(subscription({ data: demo }).grants_access, true);
    assert.equal(subscription({ data: none }).grants_access, false);
    assert.equal(subscription({ data: demo }).paid_until, null);
    assert.throws(() => subscription({ data: { ...none, grants_access: true } }));
});

test('Incomplete or contradictory demo contracts fail closed; expiry is decided by server', () => {
    for (const value of [
        { ...demo, demo_access: null }, { ...demo, grants_access: false },
        { ...demo, access_source: null }, { ...demo, demo_access: { ...demo.demo_access, plan_key: '' } },
        { ...demo, demo_access: { ...demo.demo_access, expires_at: null } },
        { ...demo, demo_access: { ...demo.demo_access, expires_at: demo.demo_access.starts_at } },
        { ...demo, paid_until: demo.demo_access.expires_at },
    ]) assert.throws(() => subscription({ data: value }));
    // Presentation never grants or revokes access based on the browser clock.
    assert.equal(subscription({ data: demo }).grants_access, true);
});

test('Demo presentation hides commercial mutations and labels grant honestly', async () => {
    const source = await readFile(new URL('../../resources/js/modules/billing/index.js', import.meta.url), 'utf8');
    const view = await readFile(new URL('../../resources/js/modules/billing/view.js', import.meta.url), 'utf8');
    assert.match(source, /billing-owner'\)\.hidden = demoActive\(\)/);
    assert.match(source, /async function checkoutSubmit\(\)\s*\{\s*if \(demoActive\(\)/);
    assert.match(source, /async function management\([^)]*\)\s*\{\s*if \(demoActive\(\)/);
    assert.match(view, /Acceso de evaluación · sin pago/);
    assert.match(view, /Demostración hasta/);
    assert.match(source, /if \(state\.grants_access\) window\.location\.replace\(root\.dataset\.dashboardUrl\)/);
});
