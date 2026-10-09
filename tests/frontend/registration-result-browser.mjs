// Focused registration acceptance. Real allowlisted Laravel/PDO, no HAR, secrets or production writes.
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { mkdir, writeFile, rm } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import { origin, root, prepareRuntime, startServer, readDatabaseEvidence } from './registration-browser-runtime.mjs';

const run = 'QA-REGISTER-RESULT-' + randomBytes(6).toString('hex');
const output = resolve(root, 'storage/app/qa/registration-result', run);
const profile = resolve(output, 'profile');
const baseline = process.env.QA_REGISTER_REPRO_ONLY === '1';
const evidence = { run, origin, database: 'gintly_frontend_qa_rol03', baseline, checks: [], network: [], console: [], screenshots: [] };
let runtime, server, context, page, password, created = false, phase = 'preflight';
let scenario = 'real';
const requests = [];
const field = name => page.locator(`[name="${name}"]`);
const submit = () => page.locator('[data-register-submit]');
const check = (name, value = true) => { assert(value, name); evidence.checks.push(name); console.log('PASS ' + name); };
const shape = body => typeof body === 'object' && body !== null
    ? { top: Object.keys(body).sort(), data: body.data && typeof body.data === 'object' ? Object.keys(body.data).sort() : null }
    : { type: typeof body };

async function screenshot(name) {
    check('No horizontal overflow / visible focus: ' + name, await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1
        && (document.activeElement === document.body || document.activeElement.getClientRects().length > 0)));
    assert(await page.locator('input[type="text"][autocomplete*="password"]').count() === 0, 'Passwords must be masked');
    await page.screenshot({ path: resolve(output, name + '.png'), fullPage: true });
    evidence.screenshots.push(name + '.png');
}

async function review(suffix) {
    await page.goto(origin + '/register');
    await page.locator('[data-register-form]').waitFor({ state: 'visible' });
    await field('owner.first_name').fill('QA Registro');
    await field('owner.last_name').fill('Resultado');
    await field('owner.email').fill(run.toLowerCase() + '@example.test');
    await field('owner.password').fill(password);
    await field('owner.password_confirmation').fill(password);
    await submit().click();
    await field('business.name').fill(run + suffix);
    await field('business.timezone').selectOption('America/Managua');
    await submit().click();
    check('Review contains no password: ' + suffix, await page.locator('[data-register-stage="3"]').isVisible()
        && !(await page.locator('[data-register-stage="3"]').textContent()).includes(password));
}

function exact(request) {
    const body = request.postDataJSON();
    const headers = request.headers();
    check('Exact canonical request / credentials preserved / UUID / CSRF', Object.keys(body).sort().join() === 'business,owner'
        && Object.keys(body.business).sort().join() === 'name,timezone'
        && Object.keys(body.owner).sort().join() === 'email,first_name,last_name,password,password_confirmation'
        && body.owner.password === password && body.owner.password_confirmation === password
        && /^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/i.test(headers['idempotency-key'])
        && !!headers['x-xsrf-token'] && headers.accept === 'application/json');
}

async function publicReadOnly(chromium) {
    const published = await chromium.launch({ executablePath: runtime.env.QA_CHROME, headless: true });
    try {
        const readonly = await published.newContext();
        // Fail closed: not even an accidental POST is allowed in the public inspection.
        await readonly.route('**/*', route => ['GET', 'HEAD'].includes(route.request().method()) ? route.continue() : route.abort('blockedbyclient'));
        const tab = await readonly.newPage();
        const consoleMessages = [];
        tab.on('console', message => { if (message.text().includes('Mixed Content')) consoleMessages.push(message.text()); });
        const reply = await tab.goto('https://gintly-app-web.azurewebsites.net/register', { waitUntil: 'networkidle' });
        evidence.published = { url: tab.url(), status: reply.status(), contentType: reply.headers()['content-type'],
            ...(await tab.evaluate(() => ({ title: document.title, apiBase: document.querySelector('meta[name="api-base-url"]')?.content,
                login: document.querySelector('meta[name="login-url"]')?.content,
                steps: [...document.querySelectorAll('[data-register-progress]')].map(node => node.textContent.trim().replace(/\s+/g, ' ')),
                scripts: [...document.scripts].map(script => script.src).filter(Boolean) }))) };
        // Read-only GET to the same URL the wizard constructs. No registration body or secrets.
        evidence.published.readOnlyProbe = await tab.evaluate(async () => {
            const base = document.querySelector('meta[name="api-base-url"]').content;
            const url = new URL('auth/register', base.endsWith('/') ? base : base + '/').href;
            try {
                const response = await fetch(url, { method: 'GET', credentials: 'omit', headers: { Accept: 'application/json' } });
                return { method: 'GET', url, status: response.status(), contentType: response.headers.get('content-type') };
            } catch (error) { return { method: 'GET', url, status: null, name: error.name, error: error.message }; }
        });
        evidence.published.mixedContent = consoleMessages;
        console.log('PUBLISHED READ ONLY ' + JSON.stringify(evidence.published));
        await readonly.close();
    } finally { await published.close(); }
}

try {
    runtime = await prepareRuntime();
    const { chromium } = await import(pathToFileURL(runtime.env.QA_PLAYWRIGHT_MODULE).href);
    await mkdir(resolve(profile, 'Default'), { recursive: true });
    created = true;
    await writeFile(resolve(profile, 'Default/Preferences'), JSON.stringify({ credentials_enable_service: false, profile: { password_manager_enabled: false } }));
    if (process.env.QA_REGISTER_PUBLIC_READONLY === '1') {
        phase = 'public-read-only';
        await publicReadOnly(chromium);
    }
    server = await startServer(runtime.env);
    context = await chromium.launchPersistentContext(profile, { executablePath: runtime.env.QA_CHROME, headless: process.env.QA_HEADLESS === '1',
        viewport: { width: 1280, height: 900 }, args: ['--disable-save-password-bubble', '--disable-features=PasswordLeakDetection'] });
    evidence.browser = await context.browser().version();
    await context.route(origin + '/**', async route => { server.assertAlive(); await route.continue(); });
    page = await context.newPage();
    // The real CSRF handshake and Laravel's installed uncompromised-password
    // verifier can outlast a 15s UI wait. Do not abort a possibly committed test.
    page.setDefaultTimeout(65000);
    page.on('dialog', dialog => dialog.type() === 'beforeunload' ? dialog.accept() : dialog.dismiss());
    page.on('request', request => {
        if (request.method() === 'POST' && new URL(request.url()).pathname === '/api/v1/auth/register') requests.push(request);
    });
    page.on('response', response => {
        const url = new URL(response.url());
        if (url.origin === origin) {
            evidence.network.push({ scenario, method: response.request().method(), url: response.url(), status: response.status(), contentType: response.headers()['content-type'] || null });
        }
    });
    page.on('pageerror', error => evidence.console.push({ scenario, kind: 'exception', name: error.name }));
    page.on('requestfailed', request => evidence.network.push({ scenario, method: request.method(), url: request.url(),
        status: null, failure: request.failure()?.errorText }));
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) evidence.console.push({ scenario, kind: message.type(), resourceFailure: /Failed to load resource|net::ERR_/.test(message.text()) }); });
    password = '  QA!' + randomBytes(18).toString('hex') + '9  ';

    // Baseline: the current local contract must be observed before changing it.
    phase = 'real-success';
    await review('-success');
    const before = requests.length;
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    await page.route('**/api/v1/auth/register', async route => { await gate; await route.continue(); });
    const reply = page.waitForResponse(response => response.url() === origin + '/api/v1/auth/register');
    await submit().click();
    check('Synchronous loading/disabled before response', await submit().isDisabled() && await field('owner.password').isDisabled());
    await page.locator('[data-register-form]').evaluate(form => { form.requestSubmit(); form.requestSubmit(); });
    release();
    const registered = await reply;
    const body = await registered.json();
    evidence.realResult = { status: registered.status(), contentType: registered.headers()['content-type'], shape: shape(body) };
    check('Real HTTP201 exact data envelope', registered.status() === 201 && evidence.realResult.contentType.includes('application/json')
        && evidence.realResult.shape.top.join() === 'data' && evidence.realResult.shape.data.join() === 'business_slug,owner_email');
    await page.locator('[data-register-result]').waitFor({ state: 'visible' });
    check('Single initial POST and no double data extraction', requests.length === before + 1
        && await page.locator('[data-register-slug]').textContent() === body.data.business_slug);
    exact(requests.at(-1));
    check('Passwords cleared and result focused', await field('owner.password').inputValue() === '' && await field('owner.password_confirmation').inputValue() === ''
        && await page.locator('#registration-result-title').evaluate(element => document.activeElement === element));
    if (!baseline) check('Requested success headline and usable canonical login', await page.locator('#registration-result-title').textContent() === 'Negocio y cuenta propietaria creados'
        && await page.getByRole('link', { name: 'Iniciar sesión', exact: true }).last().getAttribute('href') === '/login');
    await page.unroute('**/api/v1/auth/register');
    await screenshot('result-1280');
    await page.setViewportSize({ width: 375, height: 900 });
    await screenshot('result-375');
    const me = await page.evaluate(async () => (await fetch('/api/v1/me', { credentials: 'same-origin', headers: { Accept: 'application/json' } })).status);
    check('No auto-login, me401', me === 401);
    await page.getByRole('link', { name: 'Iniciar sesión', exact: true }).last().click();
    check('Canonical login accessible without credentials in URL', page.url() === origin + '/login' && await page.locator('#loginForm').isVisible()
        && await field('password').inputValue() === '');

    phase = 'csrf-before-send';
    scenario = 'induced-handshake-network';
    await review('-handshake');
    const postsBeforeCsrf = requests.length;
    await page.route('**/sanctum/csrf-cookie', route => route.abort('failed'));
    await submit().click();
    await page.locator('[data-register-feedback]').waitFor({ state: 'visible' });
    const message = await page.locator('[data-register-message]').textContent();
    evidence.handshake = { posts: requests.length - postsBeforeCsrf, message };
    check('Failed CSRF handshake never sends a registrationPOST', requests.length === postsBeforeCsrf);
    if (!baseline) check('No uncertainty claim before any write', !message.includes('podría haberse creado') && message.includes('No se envió'));
    await page.unroute('**/sanctum/csrf-cookie');

    if (!baseline) {
        phase = 'real-validation';
        scenario = 'real-422';
        await review('-validation');
        // Force server validation, not a fake response: alter only the QA request email.
        await page.route('**/api/v1/auth/register', route => {
            const payload = route.request().postDataJSON();
            payload.owner.email = 'invalid';
            return route.continue({ postData: JSON.stringify(payload) });
        });
        const invalid = page.waitForResponse(response => response.url() === origin + '/api/v1/auth/register');
        await submit().click();
        check('Real backend422, no success', (await invalid).status() === 422);
        await field('owner.email').waitFor({ state: 'visible' });
        await page.waitForFunction(() => document.activeElement?.name === 'owner.email');
        check('422 field route/focus and exact passwords retained', await field('owner.email').getAttribute('aria-invalid') === 'true'
            && await field('owner.password').inputValue() === password && !(await page.locator('[data-register-result]').isVisible()));
        check('422 creates no business/owner/idempotency record', (await readDatabaseEvidence(runtime.env, run + '-validation')).fixtures.length === 0);
        const rejectedKey = requests.at(-1).headers()['idempotency-key'];
        await page.unroute('**/api/v1/auth/register');
        await field('owner.email').fill(run.toLowerCase() + '-corrected@example.test');
        await submit().click();
        await submit().click();
        const corrected = page.waitForResponse(response => response.url() === origin + '/api/v1/auth/register');
        await submit().click();
        check('422 correction, new logical attempt, real201', (await corrected).status() === 201
            && requests.at(-1).headers()['idempotency-key'] !== rejectedKey);
        await page.locator('[data-register-result]').waitFor({ state: 'visible' });
        exact(requests.at(-1));

        // A genuine server commit, then a lost browser reply. Replay is real, not a mocked201.
        phase = 'real-lost-response';
        scenario = 'real-commit-with-induced-response-loss';
        await review('-lost');
        const lossStart = requests.length;
        let persisted;
        await page.route('**/api/v1/auth/register', async route => {
            const response = await route.fetch();
            check('Server processed registration201 before response loss', response.status() === 201);
            persisted = (await response.json()).data;
            evidence.network.push({ scenario, method: 'POST', url: origin + '/api/v1/auth/register', status: response.status(),
                contentType: response.headers()['content-type'], delivery: 'lost-to-browser', shape: shape({ data: persisted }) });
            await route.abort('failed');
        });
        await submit().click();
        await page.locator('[data-register-feedback]').waitFor({ state: 'visible' });
        check('Actual uncertain result locks snapshot and exposes recovery', (await page.locator('[data-register-message]').textContent()).includes('podría haberse creado')
            && await field('business.name').isDisabled() && await page.locator('[data-register-back]').isDisabled());
        check('Read-only PDO confirms one committed fixture before replay', (await readDatabaseEvidence(runtime.env, run + '-lost')).fixtures.length === 1);
        const initial = requests.at(-1);
        await page.unroute('**/api/v1/auth/register');
        const replay = page.waitForResponse(response => response.url() === origin + '/api/v1/auth/register');
        await submit().click();
        check('Real idempotent replay201', (await replay).status() === 201);
        await page.locator('[data-register-result]').waitFor({ state: 'visible' });
        check('Same UUID and byte-for-byte snapshot; same public result', requests.length === lossStart + 2
            && requests.at(-1).headers()['idempotency-key'] === initial.headers()['idempotency-key']
            && requests.at(-1).postData() === initial.postData() && await page.locator('[data-register-slug]').textContent() === persisted.business_slug);
        await screenshot('recovered-375');

        phase = 'manual-login';
        scenario = 'real-manual-login';
        await page.getByRole('link', { name: 'Iniciar sesión', exact: true }).last().click();
        await field('business_slug').fill(persisted.business_slug);
        await field('email').fill(persisted.owner_email);
        await field('password').fill(password);
        const loginReply = page.waitForResponse(response => response.url() === origin + '/api/v1/auth/login');
        const meReply = page.waitForResponse(response => response.url() === origin + '/api/v1/me');
        await page.locator('#submitBtn').click();
        check('Separate manual UI login200 with exact password', (await loginReply).status() === 200);
        const identity = await meReply;
        const me = (await identity.json()).data;
        check('Authenticated me200 confirms real owner and business', identity.status() === 200 && me.role === 'ROL-01'
            && me.business.name === run + '-lost' && Array.isArray(me.capabilities) && me.capabilities.length > 0);
        // Let existing post-login navigation finish; do not change commercial gates.
        await page.locator('[data-billing-page][aria-busy="false"]').waitFor();
        scenario = 'real-authenticated-rejection';
        await review('-authenticated');
        const denied = page.waitForResponse(response => response.url() === origin + '/api/v1/auth/register');
        await submit().click();
        check('Authenticated register403, no auto logout', (await denied).status() === 403);
        await page.locator('[data-register-dashboard]').waitFor({ state: 'visible' });
        await page.locator('[data-register-dashboard]').click();
        await page.locator('[data-billing-restricted]').waitFor();
        const logout = page.waitForResponse(response => response.url() === origin + '/api/v1/auth/logout');
        await page.locator('[data-logout]').click();
        check('Canonical UI logout204', (await logout).status() === 204);
        await page.waitForURL(origin + '/login');
    }

    evidence.fixtures = (await readDatabaseEvidence(runtime.env, run)).fixtures;
    check('Read-only database: one business/owner/idempotency row per confirmed logical attempt', evidence.fixtures.length === (baseline ? 1 : 3)
        && evidence.fixtures.every(fixture => fixture.owners === 1 && fixture.registrations === 1));
    check('No unexpected browser exception/404', !evidence.console.some(row => row.kind === 'exception') && !evidence.network.some(row => row.status === 404));
    evidence.completed = true;
} catch (failure) {
    evidence.completed = false;
    evidence.phase = phase;
    evidence.failure = failure instanceof assert.AssertionError ? failure.message : 'Browser step failed; inspect sanitized phase/source';
    evidence.failureLocation = failure.stack?.split('\n').find(line => line.includes('registration-result-browser.mjs:'))?.trim();
    evidence.visibleFeedback = page && await page.locator('[data-register-message]').count()
        ? await page.locator('[data-register-message]').textContent().catch(() => null) : null;
    evidence.failurePage = page ? { url: page.url(), title: await page.title().catch(() => null),
        restrictedPresent: await page.locator('[data-billing-restricted]').count().catch(() => null),
        billingPresent: await page.locator('[data-billing-page]').count().catch(() => null),
        scripts: await page.evaluate(() => [...document.scripts].map(script => script.src).filter(Boolean)).catch(() => null) } : null;
    if (created && page) {
        await page.screenshot({ path: resolve(output, 'failure.png'), fullPage: true }).catch(() => {});
    }
    evidence.registrationRequests = requests.map(request => ({ method: request.method(), url: request.url() }));
    console.error('QA FAILED ' + JSON.stringify({ phase, message: evidence.failure, location: evidence.failureLocation }));
    process.exitCode = 1;
} finally {
    password = null;
    requests.length = 0;
    try { await context?.close(); } finally {
        await server?.stop();
        if (created) {
            assert(dirname(profile) === output, 'Only the owned QA profile may be removed');
            await rm(profile, { recursive: true, force: true });
            await writeFile(resolve(output, 'evidence.json'), JSON.stringify(evidence, null, 2));
        }
    }
    console.log(JSON.stringify({ run, checks: evidence.checks.length, completed: evidence.completed, exit: process.exitCode || 0, profileRemoved: created }));
}
