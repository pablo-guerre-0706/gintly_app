// Explicit local QA reproduction. Real Laravel/CSRF/provider; never follows or pays a checkout.
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { origin, root, readDatabaseEvidence, startServer } from './registration-browser-runtime.mjs';

const env = Object.freeze({ ...process.env, APP_DEBUG: 'false', SESSION_DRIVER: 'file',
    SESSION_CONNECTION: '', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'file',
    SANCTUM_STATEFUL_DOMAINS: '127.0.0.1:8840', QA_CANONICAL_BUILT_ASSETS: '1' });
const reproduce = process.argv[2] === '--reproduce';
assert(process.argv.length <= (reproduce ? 3 : 2), 'Only --reproduce is supported');
assert(env.QA_SUBSCRIPTION_BROWSER === '1' && env.QA_REGISTRATION_BROWSER === '1', 'Explicit QA opt-ins required');

// PHP boots the actual configuration and checks PDO before any HTTP/domain write.
async function inspect(token = '') {
    assert(!token || /^QA-REGISTER-MODSUB-[a-f0-9]{12}$/.test(token));
    const source = `<?php
    require 'vendor/autoload.php'; $app=require 'bootstrap/app.php';
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    require 'tests/frontend/subscription-qa-guard.php'; subscriptionQaGuard();
    if(config('billing.deployment_purpose')!=='demo' || app(App\\Services\\Billing\\BillingMode::class)->activeMode()->value!=='test'
      || config('billing.api_base')!=='https://api.lemonsqueezy.com/v1')throw new RuntimeException('TEST provider guard rejected');
    foreach(['checkout_intents','plan_subscriptions','billing_webhook_events','subscription_payments'] as $table)
      if(!Illuminate\\Support\\Facades\\Schema::hasTable($table))throw new RuntimeException('Billing QA schema missing');
    $variant=(string)config('billing.variants.test.basic.monthly');
    if(!ctype_digit($variant) || !ctype_digit((string)config('billing.store_id')) || !config('billing.api_key'))
      throw new RuntimeException('Provider configuration missing');
    $report=['safe_qa'=>true,'database'=>'gintly_frontend_qa_rol03','mode'=>'test',
      'store_id'=>config('billing.store_id'),'variant_id'=>$variant];
    $token='${token}';
    if($token==='') {
      $response=Illuminate\\Support\\Facades\\Http::withToken(config('billing.api_key'))->accept('application/vnd.api+json')
        ->timeout(15)->get(config('billing.api_base').'/variants/'.$variant);
      $report['variant_http']=$response->status();
    }else{
      $business=App\\Models\\Business::query()->where('name',$token)->firstOrFail();
      $report['fixture']=$token; $report['business_id']=$business->id;
      $report['owner_link_valid']=App\\Models\\User::query()->where('id',$business->owner_user_id)->where('business_id',$business->id)->exists();
      $intents=App\\Models\\CheckoutIntent::query()->where('business_id',$business->id)->get();
      $report['intent_count']=$intents->count();
      $report['intents']=[];
      foreach($intents as $intent) {
        $provider=app(App\\Services\\Billing\\LemonSqueezyGateway::class)->findCheckoutByIntentKey($intent->idempotency_key);
        $report['intents'][]=['id'=>$intent->id,'state'=>$intent->status,
          'key_fingerprint'=>substr(hash('sha256',$intent->idempotency_key),0,12),
          'plan'=>$intent->plan_key,'period'=>$intent->period->value,'variant_id'=>$intent->provider_variant_id,
          'created_at'=>$intent->created_at->toIso8601String(),'updated_at'=>$intent->updated_at->toIso8601String(),
          'provider_outcome'=>$provider['outcome'],'url_present'=>(bool)$intent->checkout_url];
      }
      $report['provider_log_events']=[];
      foreach(file('storage/logs/laravel.log') as $line) {
        if(preg_match('/^\\[([^\\]]+)\\] \\w+\\.ERROR: Lemon Squeezy checkout HTTP (\\d+)/',$line,$match))
          $report['provider_log_events'][]=['at'=>$match[1],'http'=>(int)$match[2]];
      }
      $report['provider_log_events']=array_slice($report['provider_log_events'],-3);
      $report['paid_access']=app(App\\Services\\Billing\\CommercialAccess::class)->hasPaidAccess($business->id);
    }
    echo json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;`;
    return new Promise((resolve, reject) => {
        const child = spawn(env.QA_PHP || 'php', [], { cwd: root, env, windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
        let output = ''; const timer = setTimeout(() => child.kill(), 45000);
        child.stdout.on('data', chunk => { output += chunk; });
        child.stderr.on('data', () => {}); // Never persist PHP exception messages/connection details.
        child.once('error', () => { clearTimeout(timer); reject(new Error('PHP inspection could not start')); });
        child.once('exit', code => {
            clearTimeout(timer);
            if (code !== 0) return reject(new Error('Guarded PHP inspection failed; no secrets printed'));
            try { resolve(JSON.parse(output.trim())); } catch { reject(new Error('Invalid sanitized PHP report')); }
        });
        child.stdin.end(source);
    });
}

let server;
const cookies = new Map();
async function request(path, method = 'GET', body, extra = {}) {
    const headers = { Accept: 'application/json', Origin: origin, Referer: origin + '/billing', ...extra };
    if (cookies.size) headers.Cookie = [...cookies].map(([key, value]) => `${key}=${value}`).join('; ');
    if (body) headers['Content-Type'] = 'application/json';
    if (method === 'POST') {
        assert(cookies.has('XSRF-TOKEN'), 'Real CSRF cookie required');
        headers['X-XSRF-TOKEN'] = decodeURIComponent(cookies.get('XSRF-TOKEN'));
    }
    const response = await fetch(origin + path, { method, headers, body: body ? JSON.stringify(body) : undefined,
        redirect: 'manual', signal: AbortSignal.timeout(25000) });
    for (const cookie of response.headers.getSetCookie()) {
        const pair = cookie.split(';')[0], position = pair.indexOf('=');
        cookies.set(pair.slice(0, position), pair.slice(position + 1));
    }
    const text = await response.text(); let json = null;
    try { json = JSON.parse(text); } catch { /* Never print an HTML/error body. */ }
    const report = { method, path, http: response.status, content_type: response.headers.get('Content-Type') };
    if (path === '/api/v1/billing/checkout' && json?.code === 'BILLING_UNAVAILABLE') {
        const expectedMessage = 'La contratación no está disponible en este momento. Intente más tarde.';
        report.response = { code: json.code,
            message: json.message === expectedMessage ? json.message : 'Mensaje no estándar omitido por seguridad' };
    }
    console.log(JSON.stringify(report));
    return { response, json };
}

try {
    await readDatabaseEvidence(env); // Existing guard confirms effective config + PDO + required seeds.
    const before = await inspect(); console.log(JSON.stringify(before));
    if (reproduce) {
        assert(before.variant_http === 404, 'Stop: this executable only reproduces the confirmed missing-variant case');
        server = await startServer(env);
        const token = 'QA-REGISTER-MODSUB-' + randomUUID().replaceAll('-', '').slice(0, 12);
        console.log(JSON.stringify({ fixture: token, notice: 'Retained intentionally for read-only evidence; no automatic retry or cleanup' }));
        const email = token.toLowerCase() + '@example.test';
        let password = 'Qa-Checkout-9!' + randomUUID();
        const csrf = await request('/sanctum/csrf-cookie'); assert.equal(csrf.response.status, 204);
        const registered = await request('/api/v1/auth/register', 'POST', {
            business: { name: token, timezone: 'America/Managua' },
            owner: { first_name: 'QA', last_name: 'Checkout', email, password, password_confirmation: password },
        }, { 'Idempotency-Key': randomUUID() });
        assert.equal(registered.response.status, 201, 'Registration not confirmed; stop without retry');
        assert.equal(typeof registered.json?.data?.business_slug, 'string');
        const login = await request('/api/v1/auth/login', 'POST', {
            business_slug: registered.json.data.business_slug, email, password,
        });
        password = null;
        assert.equal(login.response.status, 200);
        await readDatabaseEvidence(env, token); // Recheck destination immediately before checkout.
        const key = randomUUID();
        const checkout = await request('/api/v1/billing/checkout', 'POST', { plan: 'basic', period: 'monthly' }, { 'Idempotency-Key': key });
        assert.equal(checkout.response.status, 503);
        assert.equal(checkout.json?.code, 'BILLING_UNAVAILABLE');
        const after = await inspect(token); console.log(JSON.stringify(after));
        assert.equal(after.intent_count, 1);
        assert.equal(after.intents[0].state, 'uncertain');
        assert.equal(after.intents[0].provider_outcome, 'absent');
        assert.equal(after.paid_access, false);
        const logout = await request('/api/v1/auth/logout', 'POST'); assert.equal(logout.response.status, 204);
        cookies.clear();
    }
} catch {
    console.error('Billing diagnostic stopped; inspect the last sanitized stage. No automatic retry; keep any printed fixture.');
    process.exitCode = 1;
} finally {
    cookies.clear(); await server?.stop();
}
