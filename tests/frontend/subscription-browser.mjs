// Opt-in QA acceptance: real Laravel HTTP, Sanctum/CSRF/Policies, isolated existing provider double.
// No HAR, traces, credentials or full request bodies persisted. Helpers are delivered under tests.
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdir, writeFile, rm } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { pathToFileURL } from 'node:url';
import { prepareRuntime, startServer, root, origin } from './registration-browser-runtime.mjs';
const execute = promisify(execFile);
const runtime = await prepareRuntime();
assert(runtime.env.QA_SUBSCRIPTION_BROWSER === '1', 'MOD-SUB opt-in required');
const run = 'QA-REGISTER-MODSUB-'+randomBytes(6).toString('hex');
const output = resolve(root, 'storage/app/qa/subscription-browser', run), profile = resolve(output, 'profile');
assert(dirname(profile) === output);
const evidence = { run, origin, database: 'gintly_frontend_qa_rol03', browser: null, checks: [], http: [], induced: [], screenshots: [], console: [], unexpected404: [], cleanup: null };
let documentNumber = 0; const meReads = new Map(), recoveryDocuments = new Set();
let server, context, page, password = '  QA!'+randomBytes(14).toString('hex')+'Aa9  ', fixtureCreated = false; const extraFixtures = [];
const email = run.toLowerCase()+'@example.test'; let slug, owner, branch, admins = [];
const check = (label, condition = true) => { assert(condition, label); evidence.checks.push(label); console.log('PASS '+label); };
async function db(action, token = '') {
    try { const { stdout } = await execute(runtime.env.QA_PHP || 'php', [resolve(root,'tests/frontend/subscription-browser-db.php'), action, token], {cwd:root,env:runtime.env,windowsHide:true,timeout:30000}); return JSON.parse(stdout.trim()); }
    catch { throw new Error('Guarded QA helper failed: '+action+'; inspect schema/guard, no secrets logged'); }
}
async function shot(name, region = null, viewportOnly = false) {
    const result = await page.evaluate(() => {
        const ids = [...document.querySelectorAll('[id]')].map(n=>n.id);
        const broken = [...document.querySelectorAll('[aria-controls],[aria-describedby],[aria-labelledby]')].some(n=>['aria-controls','aria-describedby','aria-labelledby'].some(k=>(n.getAttribute(k)||'').split(/\s+/).filter(Boolean).some(id=>!document.getElementById(id))));
        return { overflow: document.documentElement.scrollWidth > innerWidth+1, duplicate: ids.length !== new Set(ids).size, broken };
    });
    assert(!result.overflow && !result.duplicate && !result.broken, 'Responsive/IDs/ARIA '+name);
    const path=resolve(output,name+'.png');
    if (region) await page.locator(region).screenshot({path});
    else {
        const cdp=await context.newCDPSession(page),metrics=await cdp.send('Page.getLayoutMetrics');
        if (metrics.cssVisualViewport.zoom>1 && !viewportOnly) {
            // Native Page zoom uses browser DIP for capture clips; avoid a cropped half-width artifact.
            const zoom=metrics.cssVisualViewport.zoom;
            const capture=await cdp.send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width:metrics.cssContentSize.width*zoom,height:metrics.cssContentSize.height*zoom,scale:1}});
            await writeFile(path,Buffer.from(capture.data,'base64'));
        } else await page.screenshot({path,fullPage:!viewportOnly});
        await cdp.detach();
    }
    evidence.screenshots.push(name+'.png');
}
async function login(identity = email) {
    await page.goto(origin+'/login'); await page.locator('[name="business_slug"]').fill(slug); await page.locator('[name="email"]').fill(identity); await page.locator('[name="password"]').fill(password);
    await page.locator('#submitBtn').click(); await page.waitForURL(url=>url.pathname === '/billing' || url.pathname === '/dashboard');
}
async function ready() { await page.locator('[data-billing-page][aria-busy="false"]').waitFor(); }
async function post(path, data, headers = {}) {
    assert(new URL(page.url()).origin === origin, 'QA mutations require the guarded local origin');
    return page.evaluate(async ({path,data,headers}) => {
        const xsrf = document.cookie.split('; ').find(x=>x.startsWith('XSRF-TOKEN='))?.slice(11);
        const r = await fetch('/api/v1'+path,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-XSRF-TOKEN':decodeURIComponent(xsrf||''),...headers},body:JSON.stringify(data)});
        return {status:r.status,body:await r.json()};
    },{path,data,headers});
}
async function clearAttempt() { await page.evaluate(()=>sessionStorage.removeItem('gintly.billing.checkout')); }
async function induced(label, status, body, verify, header = {}) {
    await clearAttempt(); await page.goto(origin+'/billing'); await ready();
    let count = 0; const keys = [], bodies = [];
    await page.route('**/api/v1/billing/checkout', async route => { count++; keys.push(route.request().headers()['idempotency-key']); bodies.push(route.request().postDataJSON()); await route.fulfill({status,contentType:'application/json',body:JSON.stringify(body),headers:header}); });
    await page.locator('[data-billing-submit]').click(); await page.locator('[data-billing-notice]').waitFor();
    await page.waitForFunction(()=>document.querySelector('[data-billing-form]').getAttribute('aria-busy')==='false');
    await verify({count,keys,bodies}); await shot('induced-'+label); evidence.induced.push({label,status,requests:count});
    await page.unroute('**/api/v1/billing/checkout');
}
try {
    await db('preflight');
    await mkdir(resolve(profile,'Default'), {recursive:true});
    await writeFile(resolve(profile,'Default/Preferences'), JSON.stringify({credentials_enable_service:false,profile:{password_manager_enabled:false},autofill:{profile_enabled:false,credit_card_enabled:false}}));
    server = await startServer({...runtime.env, QA_BILLING_GATEWAY:'fake'},'tests/frontend/subscription-browser-router.php');
    const {chromium} = await import(pathToFileURL(runtime.env.QA_PLAYWRIGHT_MODULE).href);
    context = await chromium.launchPersistentContext(profile,{executablePath:runtime.env.QA_CHROME,headless:true,viewport:{width:1512,height:1000},args:['--no-first-run','--disable-save-password-bubble','--disable-features=PasswordManagerOnboarding,AutofillServerCommunication']});
    page = await context.newPage(); evidence.browser = context.browser()?.version() ?? 'Installed Chrome';
    page.on('framenavigated',frame=>{if(frame===page.mainFrame())documentNumber++;});
    page.on('request',request=>{if(new URL(request.url()).pathname==='/api/v1/me')meReads.set(documentNumber,(meReads.get(documentNumber)??0)+1);});
    page.on('console', message => { if (['warning','error'].includes(message.type()) && !message.text().includes('Failed to load resource')) evidence.console.push({type:message.type(),text:message.text().slice(0,120)}); });
    page.on('pageerror', ()=>evidence.console.push({type:'pageerror',text:'JavaScript exception'}));
    page.on('response', response=> { const u=new URL(response.url()); if (u.origin===origin && u.pathname.startsWith('/api/v1/')) evidence.http.push({method:response.request().method(),path:u.pathname,status:response.status()}); if (u.origin===origin && response.status()===404) evidence.unexpected404.push(u.pathname); });
    await page.goto(origin+'/');
    check('Canonical public catalog has three plans and no trial/USDequivalents', await page.locator('[data-public-plan]').count()===3 && !(await page.locator('[data-public-plans]').textContent()).includes('Prueba Gratis'));
    check('Unselected annual toggle has visible dark text',await page.locator('[data-public-period="annual"]').evaluate(node=>getComputedStyle(node).color!=='rgb(255, 255, 255)' && node.getAttribute('aria-pressed')==='false'));
    for(const width of [375,768,1024,1280,1512]){await page.setViewportSize({width,height:1000});await shot('public-plans-'+width,'[data-public-plans]');}
    await page.locator('[data-public-period="annual"]').click();
    check('Unselected monthly toggle keeps visible dark text',await page.locator('[data-public-period="monthly"]').evaluate(node=>getComputedStyle(node).color!=='rgb(255, 255, 255)' && node.getAttribute('aria-pressed')==='false'));
    check('Annual shows total upfront', (await page.locator('[data-public-plan="comercio"] [data-public-price="annual"]').textContent()).includes('27,360.00'));
    await page.locator('[data-public-select="comercio"]').click();
    check('Landing selection navigates to canonical registration', new URL(page.url()).pathname==='/register');
    for (const [name,value] of Object.entries({'owner.first_name':'QA Suscripción','owner.last_name':'Propietario','owner.email':email,'owner.password':password,'owner.password_confirmation':password})) await page.locator(`[name="${name}"]`).fill(value);
    await page.locator('[data-register-submit]').click(); await page.locator('[name="business.name"]').fill(run); await page.locator('[name="business.timezone"]').selectOption('America/Managua'); await page.locator('[data-register-submit]').click();
    let registrationBody;
    page.once('request',()=>{});
    const registered = page.waitForResponse(r=>r.url().endsWith('/api/v1/auth/register') && r.request().method()==='POST');
    const observe = r=>{ if(r.url().endsWith('/api/v1/auth/register')) registrationBody = r.postDataJSON(); }; page.on('request',observe);
    await page.locator('[data-register-submit]').click(); const reg=await registered; check('Canonical registration real201', reg.status()===201); fixtureCreated=true;
    page.off('request',observe);
    check('Registration body excludes plan/period/payment', Object.keys(registrationBody).sort().join()==='business,owner' && Object.keys(registrationBody.business).sort().join()==='name,timezone' && registrationBody.owner.password===password); registrationBody=null;
    await page.locator('[data-register-result]').waitFor(); slug=(await page.locator('[data-register-slug]').textContent()).trim();
    check('Registration does not auto-login', (await page.request.get(origin+'/api/v1/me',{headers:{Accept:'application/json'}})).status()===401);
    check('Public landing does not expose authenticated plans API',(await page.request.get(origin+'/api/v1/billing/plans',{headers:{Accept:'application/json'}})).status()===401);
    await login(); await ready();
    check('Unpaid login goes to contracting; preference survived', await page.locator('[name="plan"]').inputValue()==='comercio' && await page.locator('[name="period"]').inputValue()==='annual');
    for(const width of [375,768,1024,1280,1512]){await page.setViewportSize({width,height:1000});await shot('contracting-'+width);}
    await page.locator('[name="plan"]').focus();await page.keyboard.press('Tab');check('Contracting keyboard Tab reaches period',await page.locator('[name="period"]').evaluate(n=>n===document.activeElement));await page.keyboard.press('Shift+Tab');check('Contracting keyboard ShiftTab returns to plan',await page.locator('[name="plan"]').evaluate(n=>n===document.activeElement));
    const me = await (await page.request.get(origin+'/api/v1/me',{headers:{Accept:'application/json',Origin:origin,Referer:origin+'/billing'}})).json(); owner=me.data.id; check('Identity from real /me: ROL01 and tenant',me.data.role==='ROL-01' && me.data.business.name===run);
    const direct = await page.request.get(origin+'/dashboard',{headers:{Accept:'text/html'}}); evidence.directHtml={status:direct.status(),contentType:direct.headers()['content-type']}; check('Direct panel HTTP403 HTML exits safely',direct.status()===403 && (await direct.text()).includes('Ir a contratación y suscripción'));
    const apiGate = await page.request.get(origin+'/api/v1/dashboard/kpis',{headers:{Accept:'application/json',Origin:origin,Referer:origin+'/billing'}}); check('Operational API retains JSON SUBSCRIPTION_REQUIRED',apiGate.status()===403 && (await apiGate.json()).code==='SUBSCRIPTION_REQUIRED');
    for (const path of ['/me','/billing/plans','/billing/subscription']) {
        const match='**/api/v1'+path;
        await page.route(match,route=>route.fulfill({status:503,contentType:'application/json',body:'{"message":"Controlled bootstrap outage"}'}));
        await page.goto(origin+'/billing'); await ready(); recoveryDocuments.add(documentNumber);
        check('Initial failure stays closed '+path, await page.locator('[data-billing-enter]').isHidden() && await page.locator('[data-billing-submit]').isDisabled());
        check('Initial failure permits explicit recovery '+path, !(await page.locator('[data-billing-refresh]').isDisabled()));
        await page.unroute(match); await page.locator('[data-billing-refresh]').click();
        await page.locator('[data-billing-submit]').waitFor({state:'visible'});
        await page.waitForFunction(()=>!document.querySelector('[data-billing-submit]').disabled);
        check('Initial recovery reloads required sources '+path,(await page.locator('[name="plan"] option').count())===3);
        evidence.induced.push({label:'bootstrap-'+path,status:503});
    }
    const captured=[]; let release; const held=new Promise(done=>{release=done;});
    await page.route('**/api/v1/billing/checkout',async route=>{captured.push({key:route.request().headers()['idempotency-key'],body:route.request().postDataJSON()}); const result=await route.fetch(); check('Hosted checkout real201 with isolated provider double',result.status()===201); await held; await route.abort('failed');});
    await page.locator('[data-billing-submit]').focus();await page.keyboard.press('Enter'); await page.waitForFunction(()=>document.querySelector('[data-billing-form]').getAttribute('aria-busy')==='true');
    await page.locator('[data-billing-form]').evaluate(form=>{form.requestSubmit();form.requestSubmit();});
    check('Checkout synchronous double-submit blocked',await page.locator('[data-billing-submit]').isDisabled()); release(); await page.waitForFunction(()=>document.querySelector('[data-billing-form]').getAttribute('aria-busy')==='false');
    check('One exact checkout body, UUID and preserved snapshot after lost response',captured.length===1 && Object.keys(captured[0].body).sort().join()==='period,plan' && /^[\da-f-]{36}$/i.test(captured[0].key) && await page.locator('[name="plan"]').isDisabled());
    check('One persisted checkout after lost response',(await db('counts',run)).checkouts===1);
    await page.unroute('**/api/v1/billing/checkout');
    await page.route('https://checkout.test/**',route=>route.fulfill({status:200,contentType:'text/html',body:'<title>Proveedor QA aislado</title><h1>Checkout alojado del doble de pruebas; no es un pago.</h1>'}));
    const replays=[]; const observer=r=>{if(r.url().endsWith('/api/v1/billing/checkout')) replays.push(r.headers()['idempotency-key']);};page.on('request',observer);
    await page.locator('[data-checkout-retry]').click(); await page.waitForURL('https://checkout.test/**'); page.off('request',observer);
    check('Lost real201 replay uses same key without duplicate',(await db('counts',run)).checkouts===1 && replays[0]===captured[0].key);
    await page.goto(origin+'/billing/return'); await ready();
    const otherSelection=await post('/billing/checkout',{plan:'basic',period:'monthly'},{'Idempotency-Key':crypto.randomUUID()});check('Real open-checkout409 CHECKOUT_IN_PROGRESS',otherSelection.status===409&&otherSelection.body.code==='CHECKOUT_IN_PROGRESS');
    const keyConflict=await post('/billing/checkout',{plan:'basic',period:'monthly'},{'Idempotency-Key':captured[0].key});check('Real same-key409 CHECKOUT_IDEMPOTENCY_CONFLICT',keyConflict.status===409&&keyConflict.body.code==='CHECKOUT_IDEMPOTENCY_CONFLICT');
    evidence.returnState={mode:await page.locator('[data-billing-page]').getAttribute('data-mode'),enterVisible:await page.locator('[data-billing-enter]').isVisible(),notice:await page.locator('[data-billing-notice]').textContent()};
    check('Return alone does not grant access',!evidence.returnState.enterVisible && evidence.returnState.notice.includes('Confirmación pendiente'));
    for(const width of [375,768,1024,1280,1512]){await page.setViewportSize({width,height:1000});await shot('pending-'+width);}
    // Timer acceleration only; all 12 state responses still come from real Laravel.
    await page.clock.install();const pollStart=evidence.http.filter(x=>x.path==='/api/v1/billing/subscription').length;
    await page.goto(origin+'/billing/return');await ready();
    for(let i=0;i<11;i++){const next=page.waitForResponse(r=>r.url().endsWith('/billing/subscription'));await page.clock.runFor(5000);await next;await page.waitForTimeout(50);}
    await page.getByText('Confirmación pendiente. Se completaron las 12 consultas', {exact:false}).waitFor();
    check('Return browser cycle stops at12 without paid access',evidence.http.filter(x=>x.path==='/api/v1/billing/subscription').length-pollStart===12);
    evidence.induced.push({label:'poll-clock-accelerated',realResponses:12});await page.clock.resume();
    await db('expire-key',run); await page.goto(origin+'/billing'); await ready(); await page.locator('[data-checkout-retry]').click(); await page.locator('[data-checkout-new]').waitFor();
    check('Expired real409 requires explicit new attempt',await page.locator('[data-checkout-new]').isVisible()); await page.locator('[data-checkout-new]').click(); check('Explicit new attempt unlocks selection',!(await page.locator('[name="plan"]').isDisabled()));
    await induced('422',422,{message:'Revisa los campos',errors:{plan:['Selección no válida']}},async()=>{check('422 focuses plan and permits correction',await page.locator('[name="plan"]').evaluate(n=>n===document.activeElement && n.getAttribute('aria-invalid')==='true'));});
    for(const [code,status] of [['CHECKOUT_IN_PROGRESS',409],['CHECKOUT_IDEMPOTENCY_CONFLICT',409],['CHECKOUT_KEY_EXPIRED',409],['CHECKOUT_RESULT_UNKNOWN',409],['SUBSCRIPTION_ALREADY_ACTIVE',409],['BILLING_UNAVAILABLE',503],['LIMIT_CHECK_UNAVAILABLE',503],['SUBSCRIPTION_REQUIRED',403],['PLAN_FEATURE_UNAVAILABLE',403],['FORBIDDEN',403],['NO_ACTIVE_SUBSCRIPTION',409],['PLAN_CHANGE_INVALID',422]]) await induced(code,status,{code,message:'Controlled QA response'},async()=>{check('Visible domain feedback '+code,(await page.locator('[data-billing-notice]').textContent()).length>30);if(code==='PLAN_FEATURE_UNAVAILABLE')check('Billing feedback does not duplicate a global toast',await page.locator('#gintly-toast-container article').count()===0);});
    await induced('429',429,{message:'Temporarily limited'},async()=>{check('429 disables recovery for Retry-After',await page.locator('[data-checkout-retry]').isDisabled());},{'Retry-After':'1'});
    await induced('500',500,{message:'Do not expose details'},async()=>{check('500 conserves attempt without auto-replay',await page.locator('[name="plan"]').isDisabled());});
    await clearAttempt(); await page.goto(origin+'/billing');await ready();let retries=0;const retryKeys=[];
    await page.route('**/api/v1/billing/checkout',async route=>{retries++;retryKeys.push(route.request().headers()['idempotency-key']);await route.fulfill({status:419,contentType:'application/json',body:'{"message":"Expired"}'});});
    await page.locator('[data-billing-submit]').click();await page.waitForFunction(()=>document.querySelector('[data-billing-form]').getAttribute('aria-busy')==='false');
    check('419 at most one bounded CSRF replay',retries===2 && retryKeys[0]===retryKeys[1]); evidence.induced.push({label:'419',requests:retries});await page.unroute('**/api/v1/billing/checkout');
    await clearAttempt();await page.goto(origin+'/billing');await ready(); await page.route('**/api/v1/billing/checkout',route=>route.fulfill({status:201,contentType:'text/html',body:'<h1>Illegible</h1>'}));await page.locator('[data-billing-submit]').click();await page.waitForFunction(()=>document.querySelector('[data-billing-form]').getAttribute('aria-busy')==='false');check('Illegible201 never redirects or claims payment',new URL(page.url()).pathname==='/billing' && await page.locator('[data-billing-recovery]').isVisible());evidence.induced.push({label:'illegible201'});await page.unroute('**/api/v1/billing/checkout');
    // Genuine configured backend + existing provider double, not frontend activation.
    await db('activate',run); await clearAttempt(); await page.goto(origin+'/billing'); await ready(); check('Paid local fixture grants access',await page.locator('[data-billing-enter]').isVisible());
    await page.locator('[data-billing-enter]').click(); await page.waitForURL('**/dashboard'); check('Owner dashboard regression',await page.locator('[data-owner-dashboard]').count()>0 || (await page.textContent('main')).includes('Indicadores'));
    await page.goto(origin+'/billing'); await ready();
    await page.locator('[name="plan"]').selectOption('cadena'); await page.locator('[name="period"]').selectOption('monthly');
    await page.locator('[data-billing-submit]').click(); await page.locator('[role="dialog"]').waitFor();
    check('Management dialog locks scroll',await page.evaluate(()=>getComputedStyle(document.body).overflow==='hidden' || getComputedStyle(document.documentElement).overflow==='hidden'));
    for(const width of [375,768,1024,1280,1512]){await page.setViewportSize({width,height:900});await shot('confirmation-'+width,null,true);}
    await page.locator('[data-confirm-no]').focus();await page.keyboard.press('Tab');check('Dialog Tab follows controls',await page.locator('[data-confirm-yes]').evaluate(n=>n===document.activeElement));await page.keyboard.press('Tab');check('Dialog Tab stays inside modal',await page.locator('[data-confirm-no]').evaluate(n=>n===document.activeElement));await page.keyboard.press('Shift+Tab');check('Dialog ShiftTab trap',await page.locator('[data-confirm-yes]').evaluate(n=>n===document.activeElement));
    await page.keyboard.press('Escape');check('Escape restores dialog focus',await page.locator('[data-billing-submit]').evaluate(n=>n===document.activeElement));
    const changed=page.waitForResponse(r=>r.url().endsWith('/billing/subscription/change'));await page.locator('[data-billing-submit]').click();await page.locator('[data-confirm-yes]').click();check('Change real200', (await changed).status()===200); await page.locator('[data-subscription-pending]').waitFor();check('Downgrade remains pending with date', (await page.locator('[data-subscription-pending]').textContent()).includes('Fecha efectiva'));
    const branch1 = await post('/branches',{name:run+'-Sucursal1',address:'Dirección QA',manager_user_id:owner,opened_at:new Date().toISOString().slice(0,10),is_active:true});check('Branch QA fixture via real contract',branch1.status===201);branch=branch1.body.data.id;
    check('Second branch fixture for real usage limit',(await post('/branches',{name:run+'-Sucursal2',address:'Dirección QA',manager_user_id:owner,opened_at:new Date().toISOString().slice(0,10),is_active:true})).status===201);
    await page.locator('[name="plan"]').selectOption('basic');const limited=page.waitForResponse(r=>r.url().endsWith('/billing/subscription/change'));await page.locator('[data-billing-submit]').click();await page.locator('[data-confirm-yes]').click();const limit=await limited;check('Downgrade real409 PLAN_LIMIT_EXCEEDED without removing resources',limit.status()===409 && (await limit.json()).code==='PLAN_LIMIT_EXCEEDED');
    for (const [role,alias] of [['ROL-02','admin'],['ROL-03','operator']]) {
        const identity=run.toLowerCase()+'-'+alias+'@example.test'; const made=await post('/users',{name:run+'-'+alias,email:identity,password,password_confirmation:password,role,...(role==='ROL-03'?{branch_id:branch,profiles:['cajero']}: {})}); check('Authorized QA '+role+' creation',made.status===201);admins.push({role,identity});
    }
    const canceled=page.waitForResponse(r=>r.url().endsWith('/billing/subscription/cancel'));await page.locator('[data-cancel-renewal]').click();await page.locator('[data-confirm-yes]').click();const cancel=await canceled;check('Cancel real200 preserves paid access',cancel.status()===200 && (await cancel.json()).data.grants_access===true);
    await page.waitForFunction(()=>document.querySelector('[data-billing-form]').getAttribute('aria-busy')==='false');
    for (const width of [375,768,1024,1280,1512]) {await page.setViewportSize({width,height:1000});await shot('management-'+width);}
    await page.setViewportSize({width:1512,height:1000}); const cdp=await context.newCDPSession(page);await cdp.send('Emulation.clearDeviceMetricsOverride');const settings=await context.newPage();await settings.goto('chrome://settings/appearance');await settings.locator('#zoomLevel').selectOption('2');await page.bringToFront();const metrics=await cdp.send('Page.getLayoutMetrics');check('Real Chrome zoom200%',metrics.cssVisualViewport.zoom===2);await shot('management-native-zoom200');await settings.locator('#zoomLevel').selectOption('1');await settings.close();await cdp.detach();
    await page.locator('[data-logout]').click();await page.waitForURL('**/login');check('Logout clears commercial storage',await page.evaluate(()=>!sessionStorage.getItem('gintly.billing.checkout') && !sessionStorage.getItem('gintly.billing.preference')));
    for(const account of admins){await login(account.identity);await page.goto(origin+'/billing');await ready();check(account.role+' commercial readonly',await page.locator('[data-billing-form]').count()===0 && await page.locator('[data-billing-readonly]').isVisible());check(account.role+' Backend rejects commercial mutation',(await post('/billing/subscription/cancel',null)).status===403);await page.locator('[data-billing-enter]').click();await page.waitForURL('**/dashboard');await page.locator(account.role==='ROL-02'?'[data-admin-dashboard]':'[data-operator-dashboard]').waitFor({state:'visible'});check(account.role+' correct operational dashboard remains authorized');await page.goto(origin+'/billing');await ready();await page.locator('[data-logout]').click();await page.waitForURL('**/login');}
    // Missing credentials: real production gateway class with no key; never an external request.
    await server.stop();server=await startServer({...runtime.env,QA_BILLING_GATEWAY:'unavailable'},'tests/frontend/subscription-browser-router.php');
    await login();await page.goto(origin+'/billing');await ready();
    // A paid fixture must reject new checkout even when gateway unavailable (real409).
    check('Active subscription never opens duplicate checkout',(await post('/billing/checkout',{plan:'basic',period:'monthly'},{'Idempotency-Key':crypto.randomUUID()})).status===409);
    await db('expire-subscription',run);await clearAttempt();await page.goto(origin+'/billing');await ready();const unavailable=page.waitForResponse(r=>r.url().endsWith('/billing/checkout'));await page.locator('[data-billing-submit]').click();const failedGateway=await unavailable;check('Real missing-key gateway503 BILLING_UNAVAILABLE',failedGateway.status()===503 && (await failedGateway.json()).code==='BILLING_UNAVAILABLE');await page.waitForFunction(()=>document.querySelector('[data-billing-form]').getAttribute('aria-busy')==='false');check('Missing-key failure has no false checkout success',await page.locator('[data-billing-recovery]').isVisible());await shot('real-billing-unavailable');
    await page.locator('[data-logout]').click();await page.waitForURL('**/login');
    const second='QA-REGISTER-MODSUB-'+randomBytes(6).toString('hex'),otherEmail=second.toLowerCase()+'@example.test';
    const other=await post('/auth/register',{business:{name:second,timezone:'America/Managua'},owner:{first_name:'QA Segundo',last_name:'Negocio',email:otherEmail,password,password_confirmation:password}},{'Idempotency-Key':crypto.randomUUID()});check('Second isolated QA tenant registered201',other.status===201);extraFixtures.push(second);slug=other.body.data.business_slug;
    await login(otherEmail);await ready();check('Tenant switch has no paid state or prior attempt',(await page.locator('[data-subscription-status]').textContent())==='Sin suscripción' && await page.evaluate(()=>!sessionStorage.getItem('gintly.billing.checkout')));await page.locator('[data-logout]').click();await page.waitForURL('**/login');
    check('No unexpected404',evidence.unexpected404.length===0);check('No JavaScript console warnings/errors',evidence.console.length===0);check('At most one normal /me per document',[...meReads.entries()].every(([document,count])=>recoveryDocuments.has(document)||count<=1));evidence.meReadsPerDocument=[...meReads.entries()].map(([document,count])=>({document,count,inducedRecovery:recoveryDocuments.has(document)}));
    for(const token of extraFixtures){await db('cleanup',token);}extraFixtures.length=0;
    evidence.cleanup=await db('cleanup',run);fixtureCreated=false;check('Only this stage token cleaned',evidence.cleanup.action==='cleanup');
} catch(error){
    // Playwright action logs can include fill arguments: never persist/print them.
    evidence.failure=error instanceof assert.AssertionError ? error.message : 'Browser interaction failed; inspect the last passed check and sanitized evidence';
    evidence.failureLocation=error.stack?.split('\n').find(line=>line.includes('subscription-browser.mjs:'))?.trim();
    console.error('QA FAILED: '+evidence.failure);if(evidence.failureLocation)console.error(evidence.failureLocation);process.exitCode=1;
}
finally {
    password=null;
    await context?.close();await server?.stop();
    if(fixtureCreated){try{evidence.cleanup=await db('cleanup',run);}catch{evidence.cleanup='Exact new fixture retained; cleanup failed safely';}}
    for(const token of extraFixtures){try{await db('cleanup',token);}catch{evidence.extraFixtureRetained=token;}}
    await mkdir(output,{recursive:true});await writeFile(resolve(output,'evidence.json'),JSON.stringify(evidence,null,2));
    const resolved=resolve(profile);assert(resolved.startsWith(output+'/') || resolved.startsWith(output+'\\'));await rm(resolved,{recursive:true,force:true});
    console.log(JSON.stringify({run,checks:evidence.checks.length,induced:evidence.induced.length,exit:process.exitCode??0,profileRemoved:true,cleanup:evidence.cleanup?.action??evidence.cleanup}));
}
