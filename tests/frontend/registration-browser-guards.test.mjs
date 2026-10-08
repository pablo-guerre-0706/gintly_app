import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { resolve } from 'node:path';
import { validateEnvironment, assertPortAvailable, root } from './registration-browser-runtime.mjs';

const allowed = { QA_REGISTRATION_BROWSER: '1', APP_ENV: 'local', APP_URL: 'http://127.0.0.1:8840',
    DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_DATABASE: 'gintly_frontend_qa_rol03',
    QA_PLAYWRIGHT_MODULE: resolve(root, '../qa-tools/playwright/index.mjs'), QA_CHROME: resolve(root, '../qa-tools/chrome') };

test('Allowlisted local/testing environment passes the first gate', () => {
    validateEnvironment(allowed);
    validateEnvironment({ ...allowed, APP_ENV: 'testing' });
});
for (const [name, change] of [
    ['missing opt-in', { QA_REGISTRATION_BROWSER: undefined }],
    ['production environment', { APP_ENV: 'production' }],
    ['ordinary local database', { DB_DATABASE: 'gintly' }],
    ['remote database host', { DB_HOST: 'db.example.test' }],
    ['different localhost URL', { APP_URL: 'http://127.0.0.1:8770' }],
    ['different driver', { DB_CONNECTION: 'sqlite' }],
    ['alternate DB URL', { DB_URL: 'mysql://unapproved' }],
    ['alternate socket', { DB_SOCKET: '/unapproved.sock' }],
    ['relative Playwright path', { QA_PLAYWRIGHT_MODULE: 'node_modules/playwright/index.mjs' }],
    ['Playwright installed inside the application', { QA_PLAYWRIGHT_MODULE: resolve(root, 'node_modules/playwright/index.mjs') }],
    ['missing Chrome binary', { QA_CHROME: undefined }],
]) test('Preflight rejects ' + name + ' before starting processes', () => {
    assert.throws(() => validateEnvironment({ ...allowed, ...change }));
});

test('Occupied port is rejected; no attachment to an unknown local server', async () => {
    const server = createServer();
    await new Promise(accept => server.listen(0, '127.0.0.1', accept));
    const port = server.address().port;
    try { await assert.rejects(assertPortAvailable(port), /QA port occupied/); }
    finally { await new Promise(accept => server.close(accept)); }
    await assertPortAvailable(port);
});

test('All permanent acceptance helpers are delivered under tests, not QA artifacts', async () => {
    const source = await readFile(resolve(root, 'tests/frontend/registration-browser.mjs'), 'utf8');
    const runtime = await readFile(resolve(root, 'tests/frontend/registration-browser-runtime.mjs'), 'utf8');
    const php = await readFile(resolve(root, 'tests/frontend/registration-browser-db.php'), 'utf8');
    assert(source.includes("from './registration-browser-runtime.mjs'"));
    assert(!source.includes('registration-zoom') && !runtime.includes('storage/app/qa/registration-browser-db.php'));
    assert(runtime.includes('tests/frontend/registration-browser-db.php'));
    assert(php.includes("getPdo()->query('SELECT DATABASE()')") && php.includes('$connection->getConfig()'));
    assert(php.includes("PHP_SAPI !== 'cli'") && php.includes("config('app.url')"));
    assert(!/->(insert|update|delete|truncate)\(/.test(php), 'The PHP helper is read-only');
    assert(source.indexOf('await prepareRuntime()') < source.indexOf('await startServer('));
    assert(source.indexOf('await startServer(') < source.indexOf('await mkdir(output'));
    assert(source.includes('await context?.close()') && source.includes('await server?.stop()') && source.includes('await rm(profile,'));
    assert(!source.includes('C:/Users/') && !source.includes('C:/Program Files/'), 'No personal machine fallback');
});
