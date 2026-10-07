// Test infrastructure only. Own the PHP process: never attach to an unknown localhost server.
import assert from 'node:assert/strict';
import { execFile, spawn } from 'node:child_process';
import { access, readFile } from 'node:fs/promises';
import { createServer } from 'node:net';
import { dirname, isAbsolute, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

export const origin = 'http://127.0.0.1:8840';
export const database = 'gintly_frontend_qa_rol03';
export const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const execute = promisify(execFile);

export function validateEnvironment(env) {
    assert(env.QA_REGISTRATION_BROWSER === '1', 'Explicit QA opt-in required');
    assert(['local', 'testing'].includes(env.APP_ENV), 'APP_ENV must be local/testing');
    assert(env.APP_URL === origin, 'Expected APP_URL is http://127.0.0.1:8840');
    assert(env.DB_CONNECTION === 'mysql' && env.DB_HOST === '127.0.0.1' && env.DB_DATABASE === database,
        'Explicit allowlisted MySQL QA destination required');
    assert(!env.DB_URL && !env.DB_SOCKET, 'Alternate DB URL/socket is not allowed');
    assert(env.QA_PLAYWRIGHT_MODULE && isAbsolute(env.QA_PLAYWRIGHT_MODULE), 'Absolute external QA_PLAYWRIGHT_MODULE required');
    const packagePath = relative(root, resolve(env.QA_PLAYWRIGHT_MODULE));
    assert(packagePath.startsWith('..' + sep) || isAbsolute(packagePath), 'QA Playwright must be installed outside the application');
    assert(env.QA_CHROME && isAbsolute(env.QA_CHROME), 'Absolute installed QA_CHROME binary required');
}

export async function assertPortAvailable(port = 8840) {
    await new Promise((accept, reject) => {
        const probe = createServer();
        probe.once('error', () => reject(new Error('QA port occupied; stop its owner explicitly before acceptance')));
        probe.listen({ host: '127.0.0.1', port, exclusive: true }, () => probe.close(accept));
    });
}

export async function readDatabaseEvidence(env, prefix) {
    let stdout;
    try {
        ({ stdout } = await execute(env.QA_PHP || 'php', [resolve(root, 'tests/frontend/registration-browser-db.php'), ...(prefix ? [prefix] : [])],
            { cwd: root, env, timeout: 30000, windowsHide: true, maxBuffer: 1024 * 1024 }));
    } catch (error) {
        const stage = error.stderr?.match(/QA check failed at ([a-z-]+);/)?.[1];
        throw new Error(stage ? 'PHP QA preflight rejected ' + stage + '; no acceptance writes permitted'
            : 'PHP QA helper could not run; verify QA_PHP and the delivered helper/schema. No acceptance writes permitted');
    }
    let report;
    try { report = JSON.parse(stdout.trim()); } catch { throw new Error('PHP QA preflight returned an invalid report'); }
    assert(report.safe_qa === true && report.database === database && report.origin === origin
        && ['local', 'testing'].includes(report.environment), 'PHP did not confirm effective allowlisted configuration');
    return report;
}

export async function prepareRuntime(sourceEnv = process.env) {
    validateEnvironment(sourceEnv);
    const [major, minor] = process.versions.node.split('.').map(Number);
    assert(major > 22 || (major === 22 && minor >= 12), 'Node 22.12+ required (project Vite pipeline)');
    // Same immutable process environment goes to the guard and the single PHP server.
    // Do not modify .env, clear a cached config or rely on its unverified values.
    const env = Object.freeze({ ...sourceEnv, APP_DEBUG: 'false', SESSION_DRIVER: 'file', SESSION_CONNECTION: '',
        SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'file',
        SANCTUM_STATEFUL_DOMAINS: '127.0.0.1:8840', PHP_CLI_SERVER_WORKERS: '1' });
    for (const file of ['artisan', 'vendor/autoload.php', 'bootstrap/app.php', 'public/index.php',
        'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php', 'tests/frontend/registration-browser-db.php']) {
        try { await access(resolve(root, file)); } catch { throw new Error('Missing test/project dependency: ' + file); }
    }
    await access(env.QA_CHROME).catch(() => { throw new Error('Configured Chrome binary does not exist'); });
    await access(env.QA_PLAYWRIGHT_MODULE).catch(() => { throw new Error('External Playwright module does not exist'); });
    await assertPortAvailable();
    // A dev-server hot pointer would bypass the built, delivered assets.
    let hot = false;
    try { await access(resolve(root, 'public/hot')); hot = true; } catch { /* No hot pointer. */ }
    assert(!hot, 'Remove only the owned public/hot pointer / stop Vite dev before acceptance');
    let manifest;
    try { manifest = JSON.parse(await readFile(resolve(root, 'public/build/manifest.json'), 'utf8')); }
    catch { throw new Error('Build assets first with npm run build'); }
    for (const key of ['resources/css/app.css', 'resources/js/modules/registration/wizard.js', 'resources/js/modules/security/auth.js']) {
        assert(manifest[key]?.file, 'Required built Vite entry missing');
    }
    for (const entry of Object.values(manifest)) {
        for (const file of [entry.file, ...(entry.css || [])]) await access(resolve(root, 'public/build', file));
        for (const key of entry.imports || []) assert(manifest[key], 'Built manifest import missing');
    }
    const before = await readDatabaseEvidence(env);
    return { env, before };
}

export async function startServer(env) {
    await assertPortAvailable();
    const child = spawn(env.QA_PHP || 'php', ['-S', '127.0.0.1:8840',
        resolve(root, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
    { cwd: resolve(root, 'public'), env, windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    // The direct, single PHP process must announce its own successful bind, not
    // merely answer a health request that an unrelated local process could serve.
    const closed = new Promise(accept => { child.once('exit', accept); child.once('error', accept); });
    async function stop() {
        if (child.exitCode === null && !child.killed) child.kill();
        await closed;
    }
    try {
        await new Promise((accept, reject) => {
            const timer = setTimeout(() => reject(new Error('Owned PHP server did not start within 15 seconds')), 15000);
            let startup = '';
            const failed = () => { clearTimeout(timer); reject(new Error('Owned PHP server failed to bind/start')); };
            child.once('error', failed); child.once('exit', failed);
            child.stderr.on('data', chunk => {
                startup = (startup + chunk.toString()).slice(-2048);
                if (/Development Server \(http:\/\/127\.0\.0\.1:8840\) started/.test(startup)) {
                    clearTimeout(timer); accept();
                }
                // Never persist PHP output: exception text could contain connection details.
            });
        });
        assert(child.exitCode === null && !child.killed, 'Owned server must remain alive');
        return { stop, assertAlive: () => assert(child.exitCode === null && !child.killed, 'Owned QA server stopped; acceptance aborted') };
    } catch (error) { await stop(); throw error; }
}
