import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';

const root = new URL('../../', import.meta.url);
const source = path => readFile(new URL(path, root), 'utf8');

test('Container excludes local Vite pointers and QA artifacts', async () => {
    const ignore = await source('.dockerignore');
    for (const path of ['public/hot', 'public/hot.*', 'storage/app/qa', 'storage/app/integration']) {
        assert(ignore.split(/\r?\n/).includes(path), `Missing Docker exclusion: ${path}`);
    }
});

test('Image checks PHP syntax without booting the application or migrating', async () => {
    const docker = await source('Dockerfile');
    assert.match(docker, /find app bootstrap config routes[^\n]+-print0/);
    assert.match(docker, /xargs -0 -n 1 php -l/);
    assert.match(docker, /test ! -e public\/hot/);
    assert.match(docker, /test -s public\/build\/manifest\.json/);
    assert.doesNotMatch(docker, /artisan\s+(?:migrate|db:seed)|key:generate/);
});

test('Final image removes hot pointers after copying assets and still verifies their absence', async () => {
    const docker = await source('Dockerfile');
    const runtime = docker.slice(docker.indexOf('FROM php:8.3-apache'));
    const cleanup = runtime.indexOf('RUN rm -f public/hot public/hot.*');
    assert(cleanup > runtime.lastIndexOf('COPY '), 'Cleanup must follow every final-stage copy');
    assert(cleanup < runtime.indexOf('test ! -e public/hot'), 'Absence check must run after cleanup');
});

test('Published image has an immutable source tag and revision label', async () => {
    const [docker, workflow] = await Promise.all([source('Dockerfile'), source('.github/workflows/deploy.yml')]);
    assert.match(docker, /ARG GIT_SHA=unknown/);
    assert.match(docker, /LABEL org\.opencontainers\.image\.revision=\$GIT_SHA/);
    assert.match(workflow, /--build-arg GIT_SHA="\$\{\{ github\.sha \}\}"/);
    assert.match(workflow, /docker push gintlyregistry\.azurecr\.io\/gintly-app:\$\{\{ github\.sha \}\}/);
    assert.doesNotMatch(workflow, /artisan migrate|az webapp restart/);
});

test('QA guard reports rejection without an uninitialized action masking the cause', async () => {
    const helper = await source('tests/frontend/subscription-browser-db.php');
    assert(helper.indexOf('$action =') < helper.indexOf('try {'));
    assert.match(helper, /subscriptionQaGuard\(\)/);
});

test('Built-assets QA requires the guarded router without changing the real Vite pointer', async () => {
    const [runtime, router] = await Promise.all([
        source('tests/frontend/registration-browser-runtime.mjs'), source('tests/frontend/canonical-browser-router.php'),
    ]);
    assert.match(runtime, /Built-assets opt-in requires the guarded canonical router/);
    assert.match(runtime, /QA_SUBSCRIPTION_BROWSER === '1'/);
    assert.match(router, /subscriptionQaGuard\(\)/);
    assert.match(router, /useHotFile\(\$unusedHot\)/);
    assert.doesNotMatch(router, /subscriptionQaProvider\(|FakeSubscriptionGateway|unlink\(|DB::.*(?:insert|update|delete)/);
});

test('Container wires the existing startup script and retains Apache as its default command', async () => {
    const docker = await source('Dockerfile');
    assert.match(docker, /COPY entrypoint\.sh \/usr\/local\/bin\/gintly-entrypoint/);
    assert.match(docker, /chmod 755 \/usr\/local\/bin\/gintly-entrypoint/);
    assert.match(docker, /bash -n \/usr\/local\/bin\/gintly-entrypoint/);
    assert.match(docker, /ENTRYPOINT \["\/usr\/local\/bin\/gintly-entrypoint"\]/);
    assert.match(docker, /CMD \["apache2-foreground"\]/);
});

test('SSH uses only App Service internal port and validates configuration before startup', async () => {
    const [docker, ssh, startup] = await Promise.all([
        source('Dockerfile'), source('sshd_config'), source('entrypoint.sh'),
    ]);
    assert.match(docker, /openssh-server/);
    assert.match(docker, /COPY sshd_config \/etc\/ssh\/sshd_config/);
    assert.match(docker, /EXPOSE 80 2222/);
    assert.match(docker, /sshd -t[\s\S]*rm -f \/etc\/ssh\/ssh_host_\*/);
    assert.match(ssh, /^Port 2222$/m);
    assert.match(ssh, /^AllowUsers root$/m);
    assert.match(ssh, /^PermitEmptyPasswords no$/m);
    assert.match(ssh, /^X11Forwarding no$/m);
    assert.match(ssh, /^AllowTcpForwarding no$/m);
    assert.match(ssh, /^Ciphers \+aes128-cbc$/m);
    assert.match(ssh, /^MACs \+hmac-sha1$/m);
    assert.match(startup, /ssh-keygen -A[\s\S]*sshd -t[\s\S]*sshd -e/);
    const compose = await source('docker-compose.yml');
    assert.doesNotMatch(compose, /(?:ports:|expose:)[\s\S]*2222/);
});

test('Startup caches runtime configuration without migrations, shared-cache flushes or secret logging', async () => {
    const startup = await source('entrypoint.sh');
    assert.match(startup, /set -Eeuo pipefail/);
    assert.match(startup, /cd \/var\/www\/html/);
    assert(startup.indexOf('chown -R') < startup.indexOf('php artisan config:clear'));
    assert.match(startup, /config:clear[\s\S]*config:cache[\s\S]*route:cache[\s\S]*view:cache/);
    assert.doesNotMatch(startup, /artisan\s+(?:migrate|db:seed|cache:clear|key:generate)|set -x|printenv|\beval\b/);
    assert.match(startup, /exec docker-php-entrypoint "\$@"/);
});

// Bash is the image's real interpreter. All external commands below are doubles:
// no PHP, daemon, application cache, permission or database is touched on the host.
const bash = process.env.QA_BASH_BIN || 'bash';
const bashProbe = spawnSync(bash, ['--version'], { encoding: 'utf8', timeout: 5000 });
const bashSkip = bashProbe.error || bashProbe.status !== 0
    ? 'Bash unavailable: set QA_BASH_BIN to its executable, or run on Linux.'
    : false;

async function runStartup({ fail = '', args = [] } = {}) {
    const startup = (await source('entrypoint.sh')).replace(/\r\n/g, '\n');
    const doubles = `
trace() { printf 'QA_TRACE %s\\n' "$*"; }
cd() { trace "cd $*"; }
mkdir() { trace "mkdir $*"; }
chown() { trace "chown $*"; }
chmod() { trace "chmod $*"; }
ssh-keygen() { trace "ssh-keygen $*"; }
function /usr/sbin/sshd() {
    trace "sshd $*"
    if [[ "$*" == '-t' && "\${QA_FAIL_STAGE-}" == 'ssh' ]]; then return 46; fi
}
php() {
    trace "php $*"
    if [[ "$*" == 'artisan config:cache' ]]; then
        [[ "\${QA_STARTUP_VALUE-}" == 'azure-runtime-injected' ]] || return 99
        if [[ "\${QA_FAIL_STAGE-}" == 'config' ]]; then return 47; fi
    fi
    return 0
}
exec() { trace "exec $*"; }
`;
    const result = spawnSync(bash, ['-s', '--', ...args], {
        input: doubles + startup,
        encoding: 'utf8',
        timeout: 10000,
        env: { ...process.env, QA_STARTUP_VALUE: 'azure-runtime-injected', QA_FAIL_STAGE: fail },
    });
    if (result.error) throw result.error;
    return {
        ...result,
        commands: result.stdout.split(/\r?\n/).filter(line => line.startsWith('QA_TRACE '))
            .map(line => line.slice('QA_TRACE '.length)),
    };
}

test('Bash parses the entrypoint intended for the final image', { skip: bashSkip }, async () => {
    const result = spawnSync(bash, ['-n'], {
        input: (await source('entrypoint.sh')).replace(/\r\n/g, '\n'),
        encoding: 'utf8', timeout: 5000,
    });
    assert.equal(result.status, 0, result.stderr);
});

test('Startup uses injected environment and prepares SSH and caches before Apache', { skip: bashSkip }, async () => {
    const result = await runStartup();
    assert.equal(result.status, 0, result.stderr);
    assert.equal(result.commands[0], 'cd /var/www/html');
    assert(result.commands.indexOf('sshd -t') < result.commands.indexOf('sshd -e'));
    assert(result.commands.indexOf('sshd -e') < result.commands.indexOf('php artisan config:cache'));
    assert.deepEqual(result.commands.filter(command => command.startsWith('php ')), [
        'php artisan config:clear', 'php artisan config:cache',
        'php artisan route:cache', 'php artisan view:cache',
    ]);
    assert.equal(result.commands.at(-1), 'exec docker-php-entrypoint apache2-foreground');
    assert.doesNotMatch(result.stdout + result.stderr, /azure-runtime-injected/);
});

test('Explicit startup arguments are preserved for the PHP image entrypoint', { skip: bashSkip }, async () => {
    const result = await runStartup({ args: ['apache2-foreground', '-D', 'FOREGROUND'] });
    assert.equal(result.status, 0, result.stderr);
    assert.equal(result.commands.at(-1), 'exec docker-php-entrypoint apache2-foreground -D FOREGROUND');
});

test('Cache failure remains a failure and does not start Apache', { skip: bashSkip }, async () => {
    const result = await runStartup({ fail: 'config' });
    assert.equal(result.status, 47);
    assert.match(result.stderr, /stage=laravel-cache exit=47/);
    assert(!result.commands.includes('php artisan route:cache'));
    assert(!result.commands.some(command => command.startsWith('exec ')));
});

test('Invalid SSH configuration stops startup before caches or Apache', { skip: bashSkip }, async () => {
    const result = await runStartup({ fail: 'ssh' });
    assert.equal(result.status, 46);
    assert.match(result.stderr, /stage=ssh exit=46/);
    assert(!result.commands.includes('sshd -e'));
    assert(!result.commands.some(command => command.startsWith('php ') || command.startsWith('exec ')));
});
