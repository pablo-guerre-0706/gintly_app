// Explicit opt-in real-browser QA, outside the application bundle. No HAR/trace,
// request bodies, cookies or passwords are written to evidence or console.
// Starts its own allowlisted QA server and uses an externally installed Playwright.
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { mkdir, writeFile, rm } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { pathToFileURL } from 'node:url';
import { origin, root, prepareRuntime, startServer, readDatabaseEvidence } from './registration-browser-runtime.mjs';

let runtime, chromium;
try {
    runtime = await prepareRuntime();
    ({ chromium } = await import(pathToFileURL(runtime.env.QA_PLAYWRIGHT_MODULE).href));
    assert(typeof chromium?.launchPersistentContext === 'function', 'External Playwright Chromium API required');
} catch (error) {
    // Runtime guards expose only curated messages; never forward PHP stderr/SQL.
    console.error('QA NOT STARTED: ' + error.message);
    process.exit(1);
}
if (process.env.QA_PREFLIGHT_ONLY === '1') {
    console.log('QA PREFLIGHT OK: effective local connection, allowlisted database, URL, schema, assets, tools and free port verified. No server or browser started.');
    process.exit(0);
}
const run = 'QA-REGISTER-BROWSER-' + randomBytes(6).toString('hex');
const output = resolve(root, 'storage/app/qa/registration-browser', run);
let password = null;
const email = run.toLowerCase() + '@example.test';
const evidence = { run, origin, database: runtime.before.database, checks: [], screenshots: [], network: [], console: [], fixtures: [],
    mode: process.env.QA_ERRORS_ONLY === '1' ? 'induced-errors-and-real-legacy' : 'full-real-browser',
    preflight: { effectiveConnection: true, schema: true, ownedServer: false } };
// This generated, isolated Chrome profile enables real Page zoom, disables password
// saving/autofill and is removed even if browser launch fails. Never use a human profile.
const profile = resolve(output, 'browser-profile');
assert(dirname(profile) === output, 'Temporary profile must remain inside QA evidence directory');
let context, page, server, outputCreated = false;
let scenario = 'real';
const requests = [];
const field = name => page.locator(`[name="${name}"]`);
let submit, back;
function check(name, value = true) { assert(value, name); evidence.checks.push(name); console.log('PASS ' + name); }
async function layout() {
    const result = await page.evaluate(() => {
        const ids = [...document.querySelectorAll('[id]')].map(e => e.id);
        const broken = [...document.querySelectorAll('[aria-controls],[aria-describedby],[aria-labelledby]')].some(e =>
            ['aria-controls', 'aria-describedby', 'aria-labelledby'].some(a => (e.getAttribute(a) || '').split(/\s+/).filter(Boolean).some(id => !document.getElementById(id))));
        return { overflow: document.documentElement.scrollWidth > innerWidth + 1, unique: new Set(ids).size === ids.length, broken,
            activeVisible: document.activeElement === document.body || document.activeElement.getClientRects().length > 0 };
    });
    assert(!result.overflow && result.unique && !result.broken && result.activeVisible, 'Layout, IDs, ARIA and focus must remain valid');
}
async function screenshot(name) {
    // Never capture unmasked credentials, even when testing the visibility toggle.
    assert(await page.locator('input[type="text"][autocomplete*="password"]').count() === 0, 'Password must be masked');
    await layout();
    const path = resolve(output, name + '.png');
    const cdp = await context.newCDPSession(page);
    const metrics = await cdp.send('Page.getLayoutMetrics');
    if (metrics.cssVisualViewport.zoom > 1) {
        // CDP's capture clip uses browser DIP, not zoomed CSS pixels. Playwright's
        // fullPage clip would otherwise cut a real 200% screenshot in half.
        const zoom = metrics.cssVisualViewport.zoom;
        const capture = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true,
            clip: { x: 0, y: 0, width: metrics.cssContentSize.width * zoom, height: metrics.cssContentSize.height * zoom, scale: 1 } });
        await writeFile(path, Buffer.from(capture.data, 'base64'));
    } else await page.screenshot({ path, fullPage: true });
    await cdp.detach();
    evidence.screenshots.push(name + '.png');
}
async function start(suffix = '') {
    await page.goto(origin + '/register');
    await page.locator('[data-register-form]').waitFor({ state: 'visible' });
    await field('owner.first_name').fill('  QA   Registro  ');
    await field('owner.last_name').fill('Propietario');
    await field('owner.email').fill(email);
    await field('owner.password').fill(password);
    await field('owner.password_confirmation').fill(password);
    await submit.click();
    await field('business.name').fill(run + suffix);
    await field('business.timezone').selectOption('America/Managua');
    await submit.click();
}
function exactRequest(request) {
    const body = request.postDataJSON();
    const headers = request.headers();
    assert(Object.keys(body).sort().join() === 'business,owner', 'Exact top-level payload');
    assert(Object.keys(body.business).sort().join() === 'name,timezone', 'Exact business payload');
    assert(Object.keys(body.owner).sort().join() === 'email,first_name,last_name,password,password_confirmation', 'Exact owner payload');
    assert(body.owner.password === password && body.owner.password_confirmation === password, 'Password spaces preserved');
    assert(/^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/i.test(headers['idempotency-key']), 'UUID header');
    assert(headers['content-type'] === 'application/json' && headers.accept === 'application/json' && !!headers['x-xsrf-token'], 'JSON and CSRF headers');
}
async function noSuccess({ authenticated = false } = {}) {
    assert(!(await page.locator('[data-register-result]').isVisible()), 'No false success');
    assert(await page.locator('[data-register-feedback]').isVisible(), 'Error feedback visible');
    assert((await page.locator('[data-register-dashboard]').isVisible()) === authenticated, 'Dashboard link only for an authenticated-session rejection');
    await layout();
}
async function induced(name, response, verify) {
    scenario = 'induced-' + name;
    await start('-' + name);
    const before = requests.length;
    await page.route('**/api/v1/auth/register', async route => {
        if (response.abort) await route.abort('failed');
        else await route.fulfill({ status: response.status, contentType: response.contentType || 'application/json',
            headers: response.headers || {}, body: typeof response.body === 'string' ? response.body : JSON.stringify(response.body || { message: 'QA induced response' }) });
    });
    await submit.click();
    await page.locator('[data-register-feedback]').waitFor({ state: 'visible' });
    await noSuccess({ authenticated: response.status === 403 });
    await verify(before);
    check('Visible recovery: ' + name);
    await page.unroute('**/api/v1/auth/register');
}

try {
    server = await startServer(runtime.env);
    evidence.preflight.ownedServer = true;
    await mkdir(output, { recursive: true });
    outputCreated = true;
    await mkdir(resolve(profile, 'Default'), { recursive: true });
    await writeFile(resolve(profile, 'Default/Preferences'), JSON.stringify({ credentials_enable_service: false,
        profile: { password_manager_enabled: false }, autofill: { profile_enabled: false, credit_card_enabled: false } }));
    context = await chromium.launchPersistentContext(profile, { executablePath: runtime.env.QA_CHROME,
        headless: process.env.QA_HEADLESS === '1', args: ['--disable-save-password-bubble', '--disable-features=PasswordLeakDetection'],
        viewport: { width: 1512, height: 1000 }, permissions: ['clipboard-read', 'clipboard-write'] });
    evidence.browser = await context.browser().version();
    await context.route(origin + '/**', async route => {
        try { server.assertAlive(); } catch { await route.abort(); return; }
        await route.continue();
    });
    page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('dialog', dialog => dialog.type() === 'beforeunload' ? dialog.accept() : dialog.dismiss());
    page.on('request', request => {
        if (new URL(request.url()).pathname === '/api/v1/auth/register' && request.method() === 'POST') requests.push(request);
    });
    page.on('response', response => {
        const url = new URL(response.url());
        if (url.origin === origin) evidence.network.push({ scenario, method: response.request().method(), path: url.pathname, status: response.status() });
    });
    page.on('console', message => {
        if (['error', 'warning'].includes(message.type())) evidence.console.push({ scenario, type: message.type(), resourceFailure: /Failed to load resource|net::ERR_/.test(message.text()) });
    });
    page.on('pageerror', () => evidence.console.push({ scenario, type: 'pageerror', resourceFailure: false }));
    submit = page.locator('[data-register-submit]');
    back = page.locator('[data-register-back]');
    password = '  QA!' + randomBytes(18).toString('hex') + '7  ';
    if (process.env.QA_ERRORS_ONLY !== '1') {
    // Real entry from the landing, not directly from a test-only route.
    await page.goto(origin + '/');
    await page.getByRole('link', { name: 'Regístrate', exact: true }).first().click();
    await page.locator('[data-register-form]').waitFor({ state: 'visible' });
    check('Landing entry and exactly four stages', await page.locator('[data-register-progress]').count() === 4);
    await submit.click();
    check('Local error focuses first visible field', await field('owner.first_name').evaluate(e => e === document.activeElement && e.getAttribute('aria-invalid') === 'true'));
    await page.keyboard.press('Tab');
    check('Tab follows field order', await field('owner.last_name').evaluate(e => e === document.activeElement));
    await page.keyboard.press('Shift+Tab');
    check('Shift+Tab and focus outline', await field('owner.first_name').evaluate(e => e === document.activeElement && getComputedStyle(e).outlineStyle !== 'none'));
    await start();
    check('Review excludes passwords', !(await page.locator('[data-register-stage="3"]').textContent()).includes(password));
    check('Review uses normalized identity', (await page.locator('[data-register-review="owner.name"]').textContent()) === 'QA Registro Propietario');
    await back.click();
    check('Business stage includes editable timezone', await field('business.timezone').inputValue() === 'America/Managua');
    await back.click();
    check('Back preserves exact password', await field('owner.password').inputValue() === password);
    await page.locator('[data-password-toggle="owner-password"]').click();
    check('Accessible password toggle', await field('owner.password').getAttribute('type') === 'text');
    await page.locator('[data-password-toggle="owner-password"]').click();
    for (const width of [375, 768, 1024, 1280, 1512]) {
        await page.setViewportSize({ width, height: 900 });
        await screenshot('account-' + width);
        await submit.focus(); await page.keyboard.press('Enter');
        check('Stage focus at business ' + width, await page.locator('[data-register-stage="2"] [data-register-heading]').evaluate(e => e === document.activeElement));
        await screenshot('business-' + width);
        await submit.click(); await screenshot('review-' + width);
        await back.click(); await back.click();
    }
    check('No registration during stage navigation', requests.length === 0);
    await page.setViewportSize({ width: 1512, height: 1000 });
    // Change Chrome's own Page zoom setting, not CSS zoom, DPR or viewport emulation.
    const nativeCdp = await context.newCDPSession(page);
    await nativeCdp.send('Emulation.clearDeviceMetricsOverride');
    const settings = await context.newPage();
    await settings.goto('chrome://settings/appearance');
    await settings.locator('#zoomLevel').selectOption('2');
    await page.bringToFront();
    const nativeMetrics = await nativeCdp.send('Page.getLayoutMetrics');
    evidence.nativeZoom = { setting: await settings.locator('#zoomLevel').inputValue(), effective: nativeMetrics.cssVisualViewport.zoom };
    check('Real Chrome Page zoom 200%', evidence.nativeZoom.setting === '2' && evidence.nativeZoom.effective === 2);
    await screenshot('native-zoom-200-account');
    await submit.click(); await screenshot('native-zoom-200-business'); await submit.click(); await screenshot('native-zoom-200-review');
    // Hold the real POST before forwarding, proving synchronous disable and spinner.
    let release;
    const held = new Promise(resolve => { release = resolve; });
    await page.route('**/api/v1/auth/register', async route => { await held; await route.continue(); });
    const firstCount = requests.length;
    await submit.click();
    await page.waitForFunction(() => document.querySelector('[data-register-form]').getAttribute('aria-busy') === 'true');
    check('Loading disables all fields and submit', await submit.isDisabled() && await field('owner.email').isDisabled());
    await page.locator('[data-register-form]').evaluate(form => { form.requestSubmit(); form.requestSubmit(); });
    await screenshot('submitting-1512');
    const registered = page.waitForResponse(r => new URL(r.url()).pathname === '/api/v1/auth/register');
    release();
    const registration = await registered;
    check('Real initial registration HTTP 201', registration.status() === 201);
    const publicResult = (await registration.json()).data;
    await page.locator('[data-register-result]').waitFor({ state: 'visible' });
    check('Only one initial POST', requests.length - firstCount === 1);
    exactRequest(requests.at(-1)); check('Exact payload, UUID, cookies/CSRF and unchanged password');
    check('Result from real envelope and useful focus', (await page.locator('[data-register-slug]').textContent()) === publicResult.business_slug
        && (await page.locator('[data-register-email]').textContent()) === publicResult.owner_email
        && await page.locator('#registration-result-title').evaluate(e => e === document.activeElement));
    check('Passwords cleared after success', await field('owner.password').inputValue() === '' && await field('owner.password_confirmation').inputValue() === '');
    evidence.fixtures.push(run);
    await page.unroute('**/api/v1/auth/register');
    await screenshot('native-zoom-200-result');
    await settings.locator('#zoomLevel').selectOption('1');
    await settings.close();
    for (const width of [375, 768, 1024, 1280, 1512]) { await page.setViewportSize({ width, height: 900 }); await screenshot('result-' + width); }
    await page.locator('[data-register-copy]').click();
    check('Clipboard copy real browser', await page.evaluate(() => navigator.clipboard.readText()) === publicResult.business_slug);
    // Permission denial is intentionally induced; result remains manually selectable.
    await page.evaluate(() => Object.defineProperty(navigator, 'clipboard', { configurable: true, value: undefined }));
    await page.locator('[data-register-copy]').click();
    check('Clipboard unavailable has honest fallback', (await page.locator('[data-register-status]').textContent()).includes('manualmente'));
    const preLogin = await page.evaluate(async () => (await fetch('/api/v1/me', { headers: { Accept: 'application/json' } })).status);
    check('No auto login: real me HTTP 401', preLogin === 401);
    check('No authentication persisted by application', await page.evaluate(() => localStorage.length === 0 && sessionStorage.length === 0));
    const beforeReload = requests.length;
    await page.reload(); await page.locator('[data-register-form]').waitFor({ state: 'visible' });
    check('Reload never creates again or persists passwords', requests.length === beforeReload && await field('owner.password').inputValue() === '');
    // Explicit UI login preserves the leading/trailing spaces too.
    await page.getByRole('link', { name: 'Iniciar sesión', exact: true }).click();
    await page.locator('#business_slug').fill(publicResult.business_slug);
    await page.locator('#email').fill(publicResult.owner_email);
    await page.locator('#password').fill(password);
    const loginReply = page.waitForResponse(r => new URL(r.url()).pathname === '/api/v1/auth/login');
    const meReply = page.waitForResponse(r => new URL(r.url()).pathname === '/api/v1/me');
    await page.locator('#submitBtn').click();
    check('Manual UI login HTTP 200', (await loginReply).status() === 200);
    const meResponse = await meReply;
    const me = (await meResponse.json()).data;
    check('Manual login me HTTP 200 ROL-01 and business context', meResponse.status() === 200 && me.role === 'ROL-01'
        && me.business.name === run && Array.isArray(me.capabilities) && me.capabilities.length > 0);
    // MOD-SUB: newly registered/unpaid owner reaches contracting, not an operational dashboard.
    await page.locator('[data-billing-page][aria-busy="false"]').waitFor();
    await start('-authenticated');
    const activeRejected = page.waitForResponse(r => new URL(r.url()).pathname === '/api/v1/auth/register');
    await submit.click();
    check('Real active human session register HTTP 403', (await activeRejected).status() === 403);
    await page.locator('[data-register-dashboard]').waitFor({ state: 'visible' });
    check('403 does not logout', await page.evaluate(async () => (await fetch('/api/v1/me', { headers: { Accept: 'application/json' } })).status) === 200);
    await screenshot('active-session-403');
    await page.locator('[data-register-dashboard]').click();
    await page.locator('[data-billing-restricted]').waitFor();
    const logoutReply = page.waitForResponse(r => new URL(r.url()).pathname === '/api/v1/auth/logout');
    await page.locator('[data-logout]').click();
    check('UI logout real HTTP 204', (await logoutReply).status() === 204);
    await page.waitForURL('**/login');
    // Server commits the second fixture, but the browser loses only the reply.
    scenario = 'real-lost-response';
    await start('-lost');
    let persistedResult;
    await page.route('**/api/v1/auth/register', async route => {
        const reply = await route.fetch();
        assert(reply.status() === 201, 'Server must persist before response loss');
        persistedResult = (await reply.json()).data;
        await route.abort('failed');
    });
    const beforeLoss = requests.length;
    await submit.click(); await page.locator('[data-register-feedback]').waitFor({ state: 'visible' });
    await noSuccess();
    check('Committed response loss locks snapshot', await field('business.name').isDisabled() && await back.isDisabled());
    const original = requests.at(-1);
    await page.unroute('**/api/v1/auth/register');
    const replayReply = page.waitForResponse(r => new URL(r.url()).pathname === '/api/v1/auth/register');
    await submit.click();
    check('Lost-response replay real HTTP 201', (await replayReply).status() === 201);
    await page.locator('[data-register-result]').waitFor({ state: 'visible' });
    check('Replay same UUID and exact snapshot', requests.length - beforeLoss === 2
        && requests.at(-1).headers()['idempotency-key'] === original.headers()['idempotency-key'] && requests.at(-1).postData() === original.postData());
    check('Replay same public result', await page.locator('[data-register-slug]').textContent() === persistedResult.business_slug);
    evidence.fixtures.push(run + '-lost');
    await screenshot('recovered-real-result');
    }
    // All following POST outcomes are controlled intercepts, not fake real evidence.
    await induced('403', { status: 403 }, async () => {
        check('403 dashboard link visible and creation blocked', await submit.isDisabled() && await page.locator('[data-register-dashboard]').isVisible());
    });
    await induced('422', { status: 422, body: { errors: { 'owner.email': ['QA correo rechazado: ' + 'Mensaje largo seguro. '.repeat(12)], 'business.name': ['QA nombre rechazado'] } } }, async before => {
        evidence.validationFocus = await page.evaluate(() => ({ field: document.activeElement?.name,
            invalid: document.querySelector('[name="owner.email"]').getAttribute('aria-invalid') }));
        await page.waitForFunction(() => document.activeElement?.name === 'owner.email');
        check('422 focuses literal owner.email and keeps data', await field('owner.email').evaluate(e => e === document.activeElement) && await field('owner.password').inputValue() === password);
        await page.setViewportSize({ width: 375, height: 900 }); await screenshot('long-error-375');
        await page.setViewportSize({ width: 1512, height: 1000 }); await screenshot('long-error-1512');
        const oldKey = requests.at(-1).headers()['idempotency-key'];
        await field('owner.email').fill(email.replace('@', '+correction@'));
        await submit.click(); await submit.click(); await submit.click();
        await page.waitForFunction(() => document.activeElement?.name === 'owner.email');
        check('422 correction creates new UUID only after correction', requests.length - before === 2 && requests.at(-1).headers()['idempotency-key'] !== oldKey);
    });
    for (const code of ['REGISTRATION_IDEMPOTENCY_CONFLICT', 'BUSINESS_SLUG_CONFLICT']) {
        await induced(code, { status: 409, body: { code } }, async before => {
            if (code.startsWith('REGISTRATION')) check('409 conflict cannot rotate key', await submit.isDisabled());
            else { const first = requests.at(-1); await submit.click(); await page.locator('[data-register-feedback]').waitFor({ state: 'visible' });
                check('409 slug keeps key/body', requests.length - before === 2 && requests.at(-1).headers()['idempotency-key'] === first.headers()['idempotency-key'] && requests.at(-1).postData() === first.postData()); }
        });
    }
    await induced('419', { status: 419 }, async before => {
        check('419 one automatic replay only', requests.length - before === 2 && requests.at(-1).headers()['idempotency-key'] === requests.at(-2).headers()['idempotency-key']
            && requests.at(-1).postData() === requests.at(-2).postData());
    });
    await induced('429', { status: 429, headers: { 'Retry-After': '2' } }, async before => {
        check('429 visible wait and disabled retry', await submit.isDisabled() && (await page.locator('[data-register-wait]').textContent()).includes('segundos'));
        await page.waitForFunction(() => !document.querySelector('[data-register-submit]').disabled);
        check('429 no automatic retry loop', requests.length - before === 1);
    });
    for (const [name, response] of [['500', { status: 500 }], ['network', { abort: true }], ['unreadable', { status: 201, body: '{invalid', contentType: 'application/json' }]]) {
        await induced(name, response, async () => { check(name + ' cannot edit uncertain snapshot', await field('business.name').isDisabled()); });
    }
    scenario = 'real-legacy';
    for (let step = 1; step <= 7; step++) {
        check('Legacy GET store ' + step + ' HTTP 410', (await context.request.get(origin + '/register/step/' + step + '/store')).status() === 410);
        check('Legacy step ' + step + ' HTTP 302', (await context.request.get(origin + '/register/step/' + step, { maxRedirects: 0 })).status() === 302);
    }
    check('Legacy POST HTTP 405', (await context.request.post(origin + '/register/step/6/store')).status() === 405);
    check('No unexpected 404', !evidence.network.some(row => row.status === 404));
    check('No unexpected console warnings/errors on real successful paths', !evidence.console.some(row => row.scenario === 'real' && !row.resourceFailure));
    server.assertAlive();
    const after = await readDatabaseEvidence(runtime.env, run);
    check('Legacy table unchanged in the actual QA database', after.legacy_rows === runtime.before.legacy_rows);
    if (process.env.QA_ERRORS_ONLY === '1') {
        check('Errors-only mode created no domain fixture', after.fixtures.length === 0);
    } else {
        check('Real initial/replayed registrations each persist exactly one fixture', after.fixtures.length === 2
            && after.fixtures.every(row => evidence.fixtures.includes(row.name) && row.owners === 1 && row.registrations === 1));
    }
    evidence.databaseVerification = after;
    evidence.completed = true;
} catch (error) {
    // Do not print Playwright call logs, fill arguments or assert values (secrets).
    evidence.completed = false;
    evidence.failure = error instanceof assert.AssertionError ? error.message : 'Browser interaction failed; inspect last passed check and sanitized evidence';
    evidence.failureLocation = error.stack?.split('\n').find(line => line.includes('registration-browser.mjs:'))?.trim();
    console.error('QA NOT COMPLETE: ' + evidence.failure);
    console.error(evidence.failureLocation || 'No source location');
    process.exitCode = 1;
} finally {
    password = null;
    requests.length = 0;
    try { await context?.close(); } finally {
        await server?.stop();
        if (outputCreated) {
            assert(dirname(profile) === output, 'Validated temporary QA profile only');
            await rm(profile, { recursive: true, force: true });
            await writeFile(resolve(output, 'evidence.json'), JSON.stringify(evidence, null, 2));
            console.log('Evidence: storage/app/qa/registration-browser/' + run + '/evidence.json');
        }
    }
}
