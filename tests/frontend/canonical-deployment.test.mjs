import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

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
