// Focused QA: real auth/catalog/status, induced checkout failures, no provider requests or payments.
import assert from 'node:assert/strict';
import { randomBytes, createHash } from 'node:crypto';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { prepareRuntime, startServer, root, origin } from './registration-browser-runtime.mjs';
import { waitForRegistrationShell } from './registration-shell.mjs';
import { priceText } from '../../resources/js/modules/billing/contracts.js';

const diagnose = process.argv[2] === '--diagnose';
assert(process.argv.length <= (diagnose ? 3 : 2), 'Only --diagnose is supported');
const execute = promisify(execFile);
const runtime = await prepareRuntime();
assert(runtime.env.QA_SUBSCRIPTION_BROWSER === '1', 'Explicit MOD-SUB QA opt-in required');
const run = 'QA-REGISTER-MODSUB-' + randomBytes(6).toString('hex');
const output = resolve(root, 'storage/app/qa/billing-selectors', run);
const report = { run, origin, database: 'gintly_frontend_qa_rol03', diagnose, checks: [], http: [],
    induced: [], console: [], unexpected404: [], screenshots: [], cleanup: null };
let server, browser, page, password, created = false, phase = 'preflight';
let outcome = { status: 503, code: 'BILLING_UNAVAILABLE' };
const submissions = [];
const check = (label, value = true) => { assert(value, label); report.checks.push(label); console.log('PASS ' + label); };
async function db(action) {
    const { stdout } = await execute(runtime.env.QA_PHP || 'php', [resolve(root, 'tests/frontend/subscription-browser-db.php'), action, run],
        { cwd: root, env: runtime.env, windowsHide: true, timeout: 30000 });
    return JSON.parse(stdout.trim());
}
async function ready() {
    await page.waitForFunction(() => document.querySelector('[data-billing-page]')?.getAttribute('aria-busy') === 'false');
    assert.equal(await page.locator('[data-billing-page]').getAttribute('data-initialized'), 'true');
}
async function shot(name) {
    check('No horizontal overflow: ' + name, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    await page.screenshot({ path: resolve(output, name + '.png'), fullPage: true });
    report.screenshots.push(name + '.png');
}
async function nativeChoice(name, index) {
    const control = page.locator(`[name="${name}"]`);
    await control.scrollIntoViewIfNeeded();
    check('No overlay blocks ' + name, await control.evaluate(node => {
        const box = node.getBoundingClientRect();
        return document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2) === node;
    }));
    await control.click(); // Real native dropdown, not selectOption or a DOM value assignment.
    await page.keyboard.press('Home');
    for (let i = 0; i < index; i++) await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    const expected = await control.locator('option').nth(index).getAttribute('value');
    check('Mouse + keyboard native selection: ' + name + '/' + expected, await control.inputValue() === expected);
}
async function checkout() {
    const before = submissions.length;
    await page.locator('[data-billing-submit]').click();
    await page.waitForFunction(() => document.querySelector('[data-billing-form]')?.getAttribute('aria-busy') === 'false');
    check('One initial checkout submission', submissions.length === before + 1);
}
async function recover() {
    const before = submissions.length;
    await page.locator('[data-checkout-retry]').click();
    await page.waitForFunction(() => document.querySelector('[data-billing-form]')?.getAttribute('aria-busy') === 'false');
    check('One explicit recovery submission', submissions.length === before + 1);
    assert.deepEqual(submissions.at(-1), submissions.at(-2), 'Recovery must preserve UUID and snapshot');
}
try {
    await db('preflight');
    await mkdir(output, { recursive: true });
    server = await startServer(runtime.env);
    const { chromium } = await import(pathToFileURL(runtime.env.QA_PLAYWRIGHT_MODULE).href);
    browser = await chromium.launch({ executablePath: runtime.env.QA_CHROME, headless: true });
    report.browser = browser.version();
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    await context.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (['GET', 'HEAD'].includes(request.method())) return route.continue();
        if (url.origin === origin && ['/api/v1/auth/register', '/api/v1/auth/login', '/api/v1/auth/logout'].includes(url.pathname)) return route.continue();
        if (url.origin === origin && url.pathname === '/api/v1/billing/checkout' && request.method() === 'POST') {
            const body = request.postDataJSON(), headers = request.headers();
            assert.deepEqual(Object.keys(body).sort(), ['period', 'plan']);
            assert.match(headers['idempotency-key'], /^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/i);
            assert(headers['x-xsrf-token']);
            submissions.push({ key: headers['idempotency-key'], body });
            report.induced.push({ method: 'POST', path: url.pathname, status: outcome.status, code: outcome.code,
                fingerprint: createHash('sha256').update(headers['idempotency-key']).digest('hex').slice(0, 12) });
            return route.fulfill({ status: outcome.status, contentType: 'application/json', body: JSON.stringify({ code: outcome.code, message: 'Controlled QA response', ...(outcome.errors ? { errors: outcome.errors } : {}) }) });
        }
        return route.abort('blockedbyclient'); // Never call the provider or a different mutation.
    });
    page = await context.newPage();
    page.setDefaultTimeout(65000);
    page.on('dialog', dialog => dialog.type() === 'beforeunload' ? dialog.accept() : dialog.dismiss());
    page.on('pageerror', error => report.console.push({ kind: 'pageerror', name: error.name }));
    page.on('console', message => {
        if (['warning', 'error'].includes(message.type())) report.console.push({ kind: message.type(), expectedResourceFailure: /Failed to load resource/.test(message.text()) });
    });
    page.on('response', response => {
        const url = new URL(response.url());
        if (url.origin === origin) {
            report.http.push({ method: response.request().method(), path: url.pathname, status: response.status() });
            if (response.status() === 404) report.unexpected404.push(url.pathname);
        }
    });
    phase = 'real-registration';
    password = '  QA!' + randomBytes(16).toString('hex') + '9  ';
    await page.goto(origin + '/register'); await waitForRegistrationShell(page);
    const email = run.toLowerCase() + '@example.test';
    for (const [name, value] of Object.entries({ 'owner.first_name': 'QA', 'owner.last_name': 'Selectores', 'owner.email': email,
        'owner.password': password, 'owner.password_confirmation': password })) await page.locator(`[name="${name}"]`).fill(value);
    await page.locator('[data-register-submit]').click();
    await page.locator('[name="business.name"]').fill(run);
    await page.locator('[name="business.timezone"]').selectOption('America/Managua');
    await page.locator('[data-register-submit]').click();
    const registered = page.waitForResponse(r => new URL(r.url()).pathname === '/api/v1/auth/register' && r.request().method() === 'POST');
    await page.locator('[data-register-submit]').click();
    const registration = await registered;
    check('Real canonical registration 201', registration.status() === 201); created = true;
    const slug = (await registration.json()).data.business_slug;
    phase = 'real-login';
    await page.goto(origin + '/login');
    for (const [name, value] of Object.entries({ business_slug: slug, email, password })) await page.locator(`[name="${name}"]`).fill(value);
    const catalogResponse = page.waitForResponse(r => new URL(r.url()).pathname === '/api/v1/billing/plans' && r.status() === 200);
    await page.locator('#submitBtn').click();
    const offeredPlans = (await (await catalogResponse).json()).data;
    await page.waitForURL(url => url.pathname === '/billing'); await ready(); password = null;
    check('Real owner billing authorization', await page.locator('[data-billing-page]').getAttribute('data-can-manage') === 'true');
    check('Real catalog: three plans / two periods', await page.locator('[name="plan"] option').count() === 3 && await page.locator('[name="period"] option').count() === 2);
    check('Fresh fields enabled', await page.locator('[name="plan"]').isEnabled() && await page.locator('[name="period"]').isEnabled());
    check('Production assets initialize billing without hot', await page.evaluate(() => [...document.scripts].filter(n => n.src).every(n => !n.src.includes(':5173') && !n.src.includes('@vite/client'))));
    check('No JavaScript errors on fresh page', report.console.length === 0);
    const restrictedPage = await page.request.get(origin + '/dashboard', { headers: { Accept: 'text/html' } });
    check('Unpaid owner retains the commercial HTML gate', restrictedPage.status() === 403);
    const restrictedApi = await page.request.get(origin + '/api/v1/dashboard/kpis', { headers: { Accept: 'application/json', Origin: origin, Referer: origin + '/billing' } });
    check('Unpaid owner retains SUBSCRIPTION_REQUIRED', restrictedApi.status() === 403 && (await restrictedApi.json()).code === 'SUBSCRIPTION_REQUIRED');
    report.http.push({ method: 'GET', path: '/dashboard', status: restrictedPage.status() },
        { method: 'GET', path: '/api/v1/dashboard/kpis', status: restrictedApi.status() });
    await page.locator('[name="plan"]').focus(); await page.keyboard.press('Tab');
    check('Tab reaches Periodicidad', await page.locator('[name="period"]').evaluate(node => node === document.activeElement));
    await page.keyboard.press('Shift+Tab');
    check('Shift+Tab returns to Plan', await page.locator('[name="plan"]').evaluate(node => node === document.activeElement));
    phase = 'six-combinations';
    for (let plan = 0; plan < 3; plan++) for (let period = 0; period < 2; period++) {
        await nativeChoice('plan', plan); await nativeChoice('period', period);
        const name = await page.locator('[name="plan"] option').nth(plan).textContent();
        const label = await page.locator('[name="period"] option').nth(period).textContent();
        const text = await page.locator('[data-billing-selection]').textContent();
        const planValue = await page.locator('[name="plan"]').inputValue(), periodValue = await page.locator('[name="period"]').inputValue();
        check('Selection summary ' + plan + '/' + period, text.includes(name) && text.includes(label)
            && text.includes(priceText(offeredPlans.find(value => value.key === planValue), periodValue)));
    }
    await shot('fresh-six-combinations');
    phase = 'induced-uncertain-checkout';
    await checkout();
    check('503 preserves disabled immutable fields', await page.locator('[name="plan"]').isDisabled() && await page.locator('[name="period"]').isDisabled());
    const first = submissions[0];
    await page.reload(); await ready();
    check('Reload restores exact snapshot', await page.locator('[name="plan"]').inputValue() === first.body.plan && await page.locator('[name="period"]').inputValue() === first.body.period);
    report.lock = await page.locator('[name="plan"]').evaluate(node => ({ inheritedDisabled: node.matches(':disabled'),
        fieldsetDisabled: node.closest('fieldset').disabled, options: node.options.length,
        nearbyReason: document.querySelector('[data-billing-selection-help]')?.textContent ?? null,
        recoveryVisible: !!document.querySelector('[data-billing-recovery]')?.getClientRects().length }));
    await shot('restored-locked');
    if (!diagnose) {
        check('Disabled selectors explain preserved attempt nearby', report.lock.nearbyReason?.includes('bloquead') && report.lock.nearbyReason.includes('intento'));
        for (const width of [375, 768, 1024, 1280, 1512]) {
            await page.setViewportSize({ width, height: 900 }); await shot('locked-' + width);
        }
    }
    await recover();
    outcome = { status: 409, code: 'CHECKOUT_RESULT_UNKNOWN' }; await recover();
    check('Unknown result keeps same attempt', await page.locator('[name="plan"]').isDisabled());
    outcome = { status: 409, code: 'CHECKOUT_KEY_EXPIRED' }; await recover();
    check('Only server expiry enables explicit new attempt', await page.locator('[data-checkout-new]').isVisible());
    await page.locator('[data-checkout-new]').click();
    check('Explicit expiry recovery unlocks both fields', await page.locator('[name="plan"]').isEnabled() && await page.locator('[name="period"]').isEnabled());
    if (!diagnose) check('Recovery restores useful focus', await page.locator('[name="plan"]').evaluate(node => node === document.activeElement));
    outcome = { status: 422, code: null, errors: { plan: ['Selección no válida en la prueba controlada.'] } }; await checkout();
    const rejectedKey = submissions.at(-1).key;
    check('422 permits correction with field focus', await page.locator('[name="plan"]').isEnabled()
        && await page.locator('[name="plan"]').evaluate(node => node === document.activeElement && node.getAttribute('aria-invalid') === 'true'));
    outcome = { status: 409, code: 'CHECKOUT_IDEMPOTENCY_CONFLICT' }; await checkout();
    check('Only correction after422 creates another logical UUID', rejectedKey !== submissions.at(-1).key);
    check('No UUID evasion on idempotency conflict', await page.locator('[name="plan"]').isDisabled() && await page.locator('[data-checkout-retry]').isDisabled() && await page.locator('[data-checkout-new]').isHidden());
    if (!diagnose) check('Conflict recovery explains review requirement', (await page.locator('[data-checkout-recovery-message]').textContent()).includes('revisión'));
    const counts = await db('counts');
    check('No provider checkout or payment persisted', counts.checkouts === 0);
    check('No unexpected404', report.unexpected404.length === 0);
    check('Only expected induced HTTP console failures', report.console.every(row => row.expectedResourceFailure));
    phase = 'logout';
    const logout = page.waitForResponse(r => new URL(r.url()).pathname === '/api/v1/auth/logout');
    await page.locator('[data-logout]').click(); check('Real logout204', (await logout).status() === 204);
    report.result = 'passed';
} catch (error) {
    report.result = 'failed'; report.failure = { phase, name: error.name };
    console.error('Billing selector acceptance failed at ' + phase + '; no secrets or response bodies printed.');
    process.exitCode = 1;
} finally {
    password = null;
    if (browser) await browser.close();
    if (server) await server.stop();
    if (created) {
        try { report.cleanup = await db('cleanup'); }
        catch { report.cleanup = { retained: run }; process.exitCode = 1; }
    } else report.cleanup = { retainedIfCreated: run };
    await writeFile(resolve(output, 'evidence.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ result: report.result, checks: report.checks.length, fixture: run, cleanup: report.cleanup }));
}
