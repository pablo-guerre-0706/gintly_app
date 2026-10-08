// Focused registration QA. Real Laravel session/CSRF, exact allowlisted database.
// No traces, HAR, request bodies, cookies or passwords are logged or captured.
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { mkdir, writeFile, rm } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { pathToFileURL } from 'node:url';
import { origin, root, prepareRuntime, startServer, readDatabaseEvidence } from './registration-browser-runtime.mjs';

const run = 'QA-REGISTER-PASSWORD-' + randomBytes(6).toString('hex');
const output = resolve(root, 'storage/app/qa/registration-password', run);
const profile = resolve(output, 'profile');
const evidence = { run, origin, database: 'gintly_frontend_qa_rol03', checks: [], http: [], console: [], screenshots: [] };
let runtime, context, page, server, password, created = false, phase = 'preflight';
const field = name => page.locator(`[name="${name}"]`);
const error = name => page.locator(`[data-register-error="${name}"]`);
const check = (name, value = true) => { assert(value, name); evidence.checks.push(name); console.log('PASS ' + name); };
const stage = async () => Number(await page.locator('[data-register-stage]:visible').getAttribute('data-register-stage'));
const shown = async name => await error(name).isVisible();

async function snapshot(name) {
    assert(await page.locator('input[type="text"][autocomplete="new-password"]').count() === 0, 'Credentials must be masked');
    const layout = await page.evaluate(() => ({ overflow: document.documentElement.scrollWidth > innerWidth + 1,
        focused: document.activeElement === document.body || document.activeElement.getClientRects().length > 0 }));
    check('Layout/focus ' + name, !layout.overflow && layout.focused);
    await page.screenshot({ path: resolve(output, name + '.png'), fullPage: true });
    evidence.screenshots.push(name + '.png');
}

try {
    runtime = await prepareRuntime();
    const { chromium } = await import(pathToFileURL(runtime.env.QA_PLAYWRIGHT_MODULE).href);
    server = await startServer(runtime.env);
    await mkdir(resolve(profile, 'Default'), { recursive: true });
    created = true;
    await writeFile(resolve(profile, 'Default/Preferences'), JSON.stringify({ credentials_enable_service: false,
        profile: { password_manager_enabled: false }, autofill: { profile_enabled: false, credit_card_enabled: false } }));
    context = await chromium.launchPersistentContext(profile, { executablePath: runtime.env.QA_CHROME,
        headless: process.env.QA_HEADLESS === '1', viewport: { width: 1280, height: 900 },
        args: ['--disable-save-password-bubble', '--disable-features=PasswordLeakDetection'], permissions: ['clipboard-read', 'clipboard-write'] });
    evidence.browser = await context.browser().version();
    await context.route(origin + '/**', async route => { server.assertAlive(); await route.continue(); });
    page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('dialog', dialog => dialog.type() === 'beforeunload' ? dialog.accept() : dialog.dismiss());
    page.on('console', message => { if (['error', 'warning'].includes(message.type())) evidence.console.push({ type: message.type(), resourceFailure: /Failed to load resource|net::ERR_/.test(message.text()) }); });
    page.on('pageerror', () => evidence.console.push({ type: 'pageerror' }));
    page.on('response', response => { const url = new URL(response.url()); if (url.origin === origin) evidence.http.push({ method: response.request().method(), path: url.pathname, status: response.status() }); });
    phase = 'local-form';
    await page.goto(origin + '/register');
    await page.locator('[data-register-form]').waitFor({ state: 'visible' });
    const submit = page.locator('[data-register-submit]');
    const back = page.locator('[data-register-back]');
    check('Canonical four stages without phone/legacy steps', await page.locator('[data-register-progress]').allTextContents().then(texts => texts.map(text => text.replace(/\d+\./g, '').trim()).join('|') === 'Cuenta|Negocio|Revisión|Resultado')
        && await page.locator('input[type="tel"], [name="telefono"]').count() === 0);
    check('Password inputs have no maxlength/pattern/custom validity', await field('owner.password').evaluate(element => !element.hasAttribute('maxlength') && !element.hasAttribute('pattern') && !element.validity.customError));
    check('Both password fields support new-password autocomplete', await field('owner.password').getAttribute('autocomplete') === 'new-password' && await field('owner.password_confirmation').getAttribute('autocomplete') === 'new-password');
    await field('owner.first_name').fill('QA Registro');
    await field('owner.last_name').fill('Contraseña');
    await field('owner.email').fill(run.toLowerCase() + '@example.test');

    await field('owner.password').fill('LetrasSolamenteSuficientes');
    await field('owner.password_confirmation').fill('LetrasSolamenteSuficientes');
    await submit.click();
    check('Long matching password still fails actual complexity', await stage() === 1 && await shown('owner.password') && !await shown('owner.password_confirmation'));
    check('Local validation focuses password without permanent disabled button', await field('owner.password').evaluate(element => element === document.activeElement) && await submit.isEnabled());
    const valid = 'QA#' + randomBytes(10).toString('hex') + '9';
    await field('owner.password').fill(valid);
    await field('owner.password_confirmation').fill(valid);
    evidence.correctedErrorBeforeSubmit = await shown('owner.password');
    if (process.env.QA_PASSWORD_REPRO_ONLY === '1') {
        check('Baseline records whether corrected error remains', typeof evidence.correctedErrorBeforeSubmit === 'boolean');
        await submit.click();
        check('Baseline valid password advances despite hidden business fields', await stage() === 2);
        console.log('BASELINE ' + JSON.stringify({ correctedErrorBeforeSubmit: evidence.correctedErrorBeforeSubmit, advanced: true }));
    } else {
        check('Corrected policy and confirmation errors disappear immediately', !evidence.correctedErrorBeforeSubmit && !await shown('owner.password_confirmation'));
        await submit.click();
        check('Valid current step advances without requiring hidden business name', await stage() === 2 && await field('business.name').inputValue() === '');
        await back.click();
        await field('owner.password_confirmation').fill(valid + 'different');
        await submit.click();
        check('Different confirmation is independent of policy', await stage() === 1 && !await shown('owner.password') && await shown('owner.password_confirmation'));
        await field('owner.password_confirmation').fill(valid);
        check('Confirmation correction clears stale error', !await shown('owner.password_confirmation'));
        await field('owner.password').fill(valid + 'changed');
        check('Changing original recomputes existing confirmation', await shown('owner.password_confirmation'));
        await field('owner.password_confirmation').fill(valid + 'changed');
        check('Changing confirmation recomputes original match', !await shown('owner.password_confirmation'));

        // Real clipboard paste, not merely Playwright fill; cleared immediately.
        await page.evaluate(text => navigator.clipboard.writeText(text), valid);
        try { await field('owner.password').fill(''); await field('owner.password').focus(); await page.keyboard.press('Control+V'); }
        finally { await page.evaluate(() => navigator.clipboard.writeText('')); }
        check('Actual paste preserves characters accepted by Backend', await field('owner.password').inputValue() === valid);
        await field('owner.password_confirmation').fill(valid);
        await submit.click();
        check('Pasted password advances', await stage() === 2);
        await back.click();

        // No saved password profile: emulate an autofill change event without input.
        password = '  QA!' + randomBytes(18).toString('hex') + '7  ';
        await page.evaluate(value => { for (const name of ['owner.password','owner.password_confirmation']) { const input = document.querySelector(`[name="${name}"]`); input.value = value; input.dispatchEvent(new Event('change', { bubbles: true })); } }, password);
        check('Change-only autofill preserves exact leading/trailing spaces', await field('owner.password').inputValue() === password && await field('owner.password_confirmation').inputValue() === password);
        evidence.autofill = 'new-password attributes and induced change-only autofill; no saved-password manager';
        await snapshot('account-1280');
        await submit.click();
        check('Change-only autofill advances on current values', await stage() === 2);
        await field('business.name').fill(run);
        await field('business.timezone').selectOption('America/Managua');
        await submit.click();
        check('Review excludes passwords and has normalized owner/business', await stage() === 3 && await page.locator('[data-register-review="business.name"]').textContent() === run && !(await page.locator('[data-register-stage="3"]').textContent()).includes(password));

        // Server-exclusive validation: explicit induced 422, no domain write.
        await page.route('**/api/v1/auth/register', route => route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ message: 'QA validation', errors: { 'owner.password': ['Validación exclusiva del servidor.'], 'owner.password_confirmation': ['Confirmación rechazada por el servidor.'] } }) }));
        await submit.click();
        await page.locator('[data-register-feedback]').waitFor({ state: 'visible' });
        check('Server 422 maps both password paths and preserves exact values', await stage() === 1 && await shown('owner.password') && await shown('owner.password_confirmation') && await field('owner.password').inputValue() === password && await field('owner.password_confirmation').inputValue() === password);
        check('Server-only error is retained when unrelated fields change', await field('owner.email').fill(run.toLowerCase() + '@example.test').then(() => shown('owner.password')));
        await page.unroute('**/api/v1/auth/register');
        // Explicit edit creates a new logical attempt after 422, no weakening of policy.
        await field('owner.password').fill(password + 'a');
        await field('owner.password_confirmation').fill(password);
        await field('owner.password').fill(password);
        check('Editing password clears its obsolete server error', !await shown('owner.password') && !await shown('owner.password_confirmation'));
        await submit.click(); await submit.click();
        check('Correction returns to review with same useful values', await stage() === 3);
        await page.setViewportSize({ width: 375, height: 900 });
        await snapshot('review-375');
        const posts = [];
        page.on('request', request => { if (new URL(request.url()).pathname === '/api/v1/auth/register' && request.method() === 'POST') { const body = request.postDataJSON(); check('Real canonical payload and exact spaced passwords', Object.keys(body).sort().join() === 'business,owner' && Object.keys(body.business).sort().join() === 'name,timezone' && Object.keys(body.owner).sort().join() === 'email,first_name,last_name,password,password_confirmation' && body.owner.password === password && body.owner.password_confirmation === password && !!request.headers()['x-xsrf-token'] && /^[\da-f]{8}-[\da-f]{4}-4[\da-f]{3}-[89ab][\da-f]{3}-[\da-f]{12}$/i.test(request.headers()['idempotency-key'])); posts.push(true); } });
        phase = 'real-registration';
        const reply = page.waitForResponse(response => response.url() === origin + '/api/v1/auth/register' && response.request().method() === 'POST');
        await submit.click();
        check('Real registration201 with CSRF active', (await reply).status() === 201);
        await page.locator('[data-register-result]').waitFor({ state: 'visible' });
        check('One real initial POST, passwords erased after success', posts.length === 1 && await field('owner.password').inputValue() === '' && await field('owner.password_confirmation').inputValue() === '');
        const slug = await page.locator('[data-register-slug]').textContent();
        await snapshot('result-375');
        const unauthenticated = await page.evaluate(async () => (await fetch('/api/v1/me', { credentials: 'same-origin', headers: { Accept: 'application/json' } })).status);
        check('No automatic login', unauthenticated === 401);
        await page.getByRole('link', { name: 'Iniciar sesión', exact: true }).last().click();
        await field('business_slug').fill(slug);
        await field('email').fill(run.toLowerCase() + '@example.test');
        await field('password').fill(password);
        const login = page.waitForResponse(response => response.url() === origin + '/api/v1/auth/login' && response.request().method() === 'POST');
        await page.locator('#loginForm button[type="submit"]').click();
        check('Explicit login200 with same exact spaced password', (await login).status() === 200);
        await page.waitForURL(origin + '/billing/access');
        await page.locator('[data-logout]').waitFor({ state: 'visible' });
        const logout = page.waitForResponse(response => response.url() === origin + '/api/v1/auth/logout');
        await page.locator('[data-logout]').click();
        check('Logout204', (await logout).status() === 204);
        evidence.fixtures = (await readDatabaseEvidence(runtime.env, run)).fixtures;
        check('One intentionally retained QA business, owner and idempotency row', evidence.fixtures.length === 1 && evidence.fixtures[0].owners === 1 && evidence.fixtures[0].registrations === 1);
        check('No unexpected404 or JavaScript console failures', !evidence.http.some(reply => reply.status === 404) && !evidence.console.some(message => !message.resourceFailure));
    }
    evidence.completed = true;
} catch (failure) {
    evidence.completed = false;
    evidence.failure = failure instanceof assert.AssertionError ? failure.message : 'Browser step failed; see sanitized phase/source location';
    evidence.phase = phase;
    evidence.failureLocation = failure.stack?.split('\n').find(line => line.includes('registration-password-browser.mjs:'))?.trim();
    console.error('QA FAILED ' + JSON.stringify({ phase, message: evidence.failure, location: evidence.failureLocation }));
    process.exitCode = 1;
} finally {
    password = null;
    try { await context?.close(); } finally {
        await server?.stop();
        if (created) {
            assert(dirname(profile) === output, 'Only the owned profile may be removed');
            await rm(profile, { recursive: true, force: true });
            await writeFile(resolve(output, 'evidence.json'), JSON.stringify(evidence, null, 2));
        }
    }
    console.log(JSON.stringify({ run, checks: evidence.checks.length, completed: evidence.completed, exit: process.exitCode || 0, profileRemoved: created }));
}
