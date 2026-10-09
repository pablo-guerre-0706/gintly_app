// Real registration/login/dashboard + explicit evaluation grants. No checkout, mock payments or provider calls.
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdir, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { prepareRuntime, startServer, root, origin } from './registration-browser-runtime.mjs';
import { waitForRegistrationShell } from './registration-shell.mjs';

assert(process.argv.length === 2, 'No arguments supported');
const runtime = await prepareRuntime();
assert(runtime.env.QA_SUBSCRIPTION_BROWSER === '1' && runtime.env.QA_EVALUATION_BROWSER === '1', 'Explicit evaluation QA required');
const execute = promisify(execFile), run = 'QA-REGISTER-EVAL-' + randomBytes(6).toString('hex');
const output = resolve(root, 'storage/app/qa/evaluation', run);
const activeEnv = Object.freeze({ ...runtime.env, BILLING_DEPLOYMENT_PURPOSE: 'demo', BILLING_PROVIDER_MODE: 'test',
    BILLING_DEMO_ACCESS_ENABLED: 'true', BILLING_DEMO_MULTIPLE_BUSINESSES: 'true',
    BILLING_EVALUATION_REGISTRATION_ENABLED: 'true', BILLING_EVALUATION_DAYS: '7', BILLING_EVALUATION_PLAN: 'cadena',
    BILLING_EVALUATION_STARTS_AT: new Date(Date.now() - 3600000).toISOString().replace(/\.\d{3}Z$/, 'Z'),
    BILLING_EVALUATION_ENDS_AT: new Date(Date.now() + 3600000).toISOString().replace(/\.\d{3}Z$/, 'Z') });
const report = { run, database: 'gintly_frontend_qa_rol03', origin, checks: [], http: [], console: [], cleanup: null };
let server, browser, phase = 'preflight';
const fixtures = [];
const check = (name, okay = true) => { assert(okay, name); report.checks.push(name); console.log('PASS ' + name); };
async function db(action) {
    const { stdout } = await execute(runtime.env.QA_PHP || 'php', [resolve(root, 'tests/frontend/evaluation-browser-db.php'), action, run],
        { cwd: root, env: activeEnv, windowsHide: true, timeout: 30000 });
    return JSON.parse(stdout.trim());
}
const headers = { Accept: 'application/json', Origin: origin, Referer: origin + '/billing' };
async function get(fixture, path) {
    const response = await fixture.context.request.get(origin + path, { headers });
    report.http.push({ method: 'GET', path, status: response.status() });
    return response;
}
async function register(suffix, expectedStatus = 201) {
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    // All real writes are limited to canonical registration/authentication. Commercial mutations never leave QA.
    await context.route('**/*', route => {
        const request = route.request(), url = new URL(request.url());
        if (url.origin === origin && (['GET', 'HEAD'].includes(request.method())
            || (request.method() === 'POST' && ['/api/v1/auth/register', '/api/v1/auth/login', '/api/v1/auth/logout'].includes(url.pathname)))) return route.continue();
        return route.abort('blockedbyclient');
    });
    const page = await context.newPage(); page.setDefaultTimeout(65000);
    page.on('dialog', dialog => dialog.type() === 'beforeunload' ? dialog.accept() : dialog.dismiss());
    page.on('pageerror', error => report.console.push({ kind: 'pageerror', name: error.name }));
    page.on('console', message => { if (['warning', 'error'].includes(message.type())) report.console.push({ kind: message.type(), resource: /Failed to load resource/.test(message.text()) }); });
    page.on('response', response => {
        const url = new URL(response.url());
        if (url.origin === origin) report.http.push({ method: response.request().method(), path: url.pathname, status: response.status() });
    });
    const name = run + '-' + suffix, email = name.toLowerCase() + '@example.test';
    const fixture = { name, email, password: '  QA!' + randomBytes(16).toString('hex') + '9  ', page, context };
    fixtures.push(fixture);
    await page.goto(origin + '/register'); await waitForRegistrationShell(page);
    for (const [name, value] of Object.entries({ 'owner.first_name': 'QA', 'owner.last_name': 'Evaluacion', 'owner.email': email,
        'owner.password': fixture.password, 'owner.password_confirmation': fixture.password })) await page.locator(`[name="${name}"]`).fill(value);
    await page.locator('[data-register-submit]').click();
    await page.locator('[name="business.name"]').fill(name);
    await page.locator('[name="business.timezone"]').selectOption('America/Managua');
    await page.locator('[data-register-submit]').click();
    const wait = page.waitForResponse(response => new URL(response.url()).pathname === '/api/v1/auth/register');
    await page.locator('[data-register-submit]').click();
    const response = await wait;
    check(suffix + ': canonical registration ' + expectedStatus, response.status() === expectedStatus);
    if (expectedStatus !== 201) return fixture;
    fixture.slug = (await response.json()).data.business_slug;
    await page.locator('[data-register-result]').waitFor({ state: 'visible' });
    check(suffix + ': no auto-login', (await get(fixture, '/api/v1/me')).status() === 401);
    const before = (await db('inspect')).fixtures.find(row => row.name === name);
    let payload = response.request().postDataJSON();
    const key = response.request().headers()['idempotency-key'], csrf = response.request().headers()['x-xsrf-token'];
    check(suffix + ': CSRF and UUID header', !!csrf && /^[a-f0-9-]{36}$/i.test(key));
    const replay = await context.request.post(origin + '/api/v1/auth/register', { headers: { ...headers, 'Idempotency-Key': key, 'X-XSRF-TOKEN': csrf }, data: payload });
    report.http.push({ method: 'POST', path: '/api/v1/auth/register', status: replay.status(), replay: true });
    check(suffix + ': replay 201, same public result', replay.status() === 201 && (await replay.json()).data.business_slug === fixture.slug);
    payload = null;
    const after = (await db('inspect')).fixtures.find(row => row.name === name);
    check(suffix + ': one owner/registration, unchanged grant on replay', after.users === 1 && after.registrations === 1 && JSON.stringify(before) === JSON.stringify(after));
    fixture.id = after.id; fixture.ownerId = after.owner_id;
    return fixture;
}
async function login(fixture, destination) {
    const { page } = fixture;
    await page.goto(origin + '/login');
    for (const [name, value] of Object.entries({ business_slug: fixture.slug, email: fixture.email, password: fixture.password })) await page.locator(`[name="${name}"]`).fill(value);
    const response = page.waitForResponse(value => new URL(value.url()).pathname === '/api/v1/auth/login');
    await page.locator('#loginForm button[type="submit"]').click();
    check(fixture.name.at(-1) + ': manual login 200', (await response).status() === 200);
    await page.waitForURL(url => url.pathname === destination);
    if (destination === '/dashboard') await page.locator('[data-owner-dashboard]').waitFor({ state: 'visible' });
    else await page.waitForFunction(() => document.querySelector('[data-billing-page]')?.getAttribute('aria-busy') === 'false');
    const me = await get(fixture, '/api/v1/me');
    const identity = (await me.json()).data;
    check(fixture.name.at(-1) + ': tenant and ROL-01 capabilities', me.status() === 200 && identity.business.id === fixture.id
        && identity.id === fixture.ownerId && identity.role === 'ROL-01' && identity.capabilities.includes('panel.ver'));
}
try {
    await mkdir(output, { recursive: true });
    const initial = await db('preflight');
    server = await startServer(activeEnv);
    const { chromium } = await import(pathToFileURL(runtime.env.QA_PLAYWRIGHT_MODULE).href);
    browser = await chromium.launch({ executablePath: runtime.env.QA_CHROME, headless: true }); report.browser = browser.version();
    phase = 'two-evaluation-registrations';
    const a = await register('a'), b = await register('b');
    const enrolled = await db('inspect');
    check('Two independent, explicit expiring grants', enrolled.fixtures.length === 2 && enrolled.fixtures.every(row => row.grant && Date.parse(row.grant.expires_at) > Date.parse(row.grant.starts_at)));
    check('No paid subscription, checkout or payment', Object.values(enrolled.commercial_rows).every(value => value === 0));
    phase = 'manual-logins-dashboards';
    for (const fixture of [a, b]) {
        await login(fixture, '/dashboard');
        const state = (await (await get(fixture, '/api/v1/billing/subscription')).json()).data;
        check(fixture.name.at(-1) + ': evaluation is not a paid subscription', state.grants_access === true && state.access_source === 'demo' && state.status === 'none' && state.paid_until === null && state.demo_access.expires_at);
        await fixture.page.screenshot({ path: resolve(output, 'dashboard-' + fixture.name.at(-1) + '.png'), fullPage: true });
    }
    phase = 'tenant-isolation';
    for (const [own, other] of [[a, b], [b, a]]) {
        const business = await get(own, '/api/v1/business?business_id=' + other.id);
        check('Tenant query cannot switch ' + own.name.at(-1), business.status() === 200 && (await business.json()).data.id === own.id);
        const user = await get(own, '/api/v1/users/' + other.ownerId);
        check('Cross-tenant owner hidden ' + own.name.at(-1), [403, 404].includes(user.status()));
    }
    phase = 'expiry'; await db('expire');
    const expired = await get(a, '/api/v1/dashboard/kpis');
    check('Expired grant blocks API', expired.status() === 403 && (await expired.json()).code === 'SUBSCRIPTION_REQUIRED');
    const blockedPage = await a.page.goto(origin + '/dashboard');
    check('Expired grant blocks HTML', blockedPage.status() === 403);
    check('Independent grant remains usable', (await get(b, '/api/v1/dashboard/kpis')).status() === 200);
    const expiredState = (await (await get(a, '/api/v1/billing/subscription')).json()).data;
    check('Expired grant returns normal contracting state', expiredState.grants_access === false && !expiredState.demo_access);
    phase = 'closed-window'; await server.stop();
    server = await startServer(Object.freeze({ ...activeEnv,
        BILLING_EVALUATION_STARTS_AT: '2020-01-01T00:00:00Z', BILLING_EVALUATION_ENDS_AT: '2020-01-02T00:00:00Z' }));
    const c = await register('c');
    const outside = (await db('inspect')).fixtures.find(row => row.name === c.name);
    check('Outside window creates no grant', outside.grant === null);
    await login(c, '/billing');
    check('Existing B survives enrollment close', (await get(b, '/api/v1/dashboard/kpis')).status() === 200);
    phase = 'administrative-batch';
    await db('batch-grant');
    check('CLI batch restores explicit grants for A/B', (await get(a, '/api/v1/dashboard/kpis')).status() === 200 && (await get(b, '/api/v1/dashboard/kpis')).status() === 200);
    check('Other business C remains blocked', (await get(c, '/api/v1/dashboard/kpis')).status() === 403);
    await db('batch-revoke');
    check('CLI batch revocation blocks A/B', (await get(a, '/api/v1/dashboard/kpis')).status() === 403 && (await get(b, '/api/v1/dashboard/kpis')).status() === 403);
    phase = 'atomic-failure'; await server.stop();
    server = await startServer(Object.freeze({ ...activeEnv, BILLING_EVALUATION_PLAN: 'invalid-qa-plan' }));
    await register('d', 500);
    const rolledBack = await db('inspect');
    check('Invalid enabled grant rolls back business/owner/idempotency', !rolledBack.fixtures.some(row => row.name === run + '-d')
        && rolledBack.orphan_users === 0 && rolledBack.orphan_registrations === 0);
    const final = await db('inspect');
    check('Existing demo unchanged', initial.demo_fingerprint === final.demo_fingerprint);
    check('No paid evidence created', Object.values(final.commercial_rows).every(value => value === 0));
    check('No unexpected 404', report.http.filter(row => row.status === 404).every(row => row.path.startsWith('/api/v1/users/')));
    check('No JavaScript exceptions or warnings', report.console.every(row => row.kind === 'error' && row.resource));
    phase = 'logout';
    for (const fixture of [a, b, c]) {
        await fixture.page.goto(origin + '/billing');
        const response = fixture.page.waitForResponse(value => new URL(value.url()).pathname === '/api/v1/auth/logout');
        await fixture.page.locator('[data-logout]').click(); check('Logout ' + fixture.name.at(-1) + ' 204', (await response).status() === 204);
    }
    report.result = 'passed';
} catch (error) {
    report.result = 'failed'; report.failure = { phase, name: error.name };
    console.error('Evaluation acceptance failed at ' + phase + '; no secrets printed.'); process.exitCode = 1;
} finally {
    for (const fixture of fixtures) fixture.password = null;
    if (browser) await browser.close();
    if (server) await server.stop();
    try {
        report.cleanup = await db('cleanup');
        assert(report.cleanup.remaining_fixture_count === 0 && report.cleanup.orphan_users === 0 && report.cleanup.orphan_registrations === 0, 'Own fixture cleanup incomplete');
    } catch { report.cleanup = { retained: run }; process.exitCode = 1; }
    await writeFile(resolve(output, 'evidence.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ result: report.result, checks: report.checks.length, run, cleanup: report.cleanup?.action ?? 'retained' }));
}
