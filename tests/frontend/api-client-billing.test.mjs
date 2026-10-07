import test from 'node:test';
import assert from 'node:assert/strict';
import { api } from '../../resources/js/core/api-client.js';
async function setup(run) {
    const saved={window:globalThis.window,document:globalThis.document,fetch:globalThis.fetch};const events=[],requests=[];
    globalThis.window={location:{origin:'http://localhost:8840',assign:()=>{}},setTimeout,clearTimeout,dispatchEvent:event=>events.push(event)};
    globalThis.document={cookie:'XSRF-TOKEN=qa',querySelector:()=>null,dispatchEvent:event=>events.push(event)};
    try{await run({events,requests});}finally{Object.assign(globalThis,saved);}
}
test('Commercial codes remain available with dispatchErrors:false; generic403 is not commercial',()=>setup(async ({events})=>{
    for(const code of ['SUBSCRIPTION_REQUIRED','PLAN_FEATURE_UNAVAILABLE']){
        globalThis.fetch=async()=>new Response(JSON.stringify({code,message:'QA'}),{status:403});
        await assert.rejects(api.get('/stock',{}, {dispatchErrors:false}),error=>error.code===code);
    }
    assert.equal(events.length,2);assert(events.every(e=>e.type==='gintly:commercial-error'));
    globalThis.fetch=async()=>new Response('{"code":"FORBIDDEN"}',{status:403});await assert.rejects(api.get('/stock',{}, {dispatchErrors:false}));assert.equal(events.length,2);
}));
test('Commercial cancel sends no extra body; no automatic mutations on errors',()=>setup(async ({requests})=>{
    globalThis.fetch=async(_,options)=>{requests.push(options);return new Response('{"data":{}}',{status:200});};
    await api.post('/billing/subscription/cancel',null,{dispatchErrors:false,expectedStatus:200});assert.equal(requests[0].body,undefined);assert.equal(requests[0].headers.get('X-XSRF-TOKEN'),'qa');
    globalThis.fetch=async(_,options)=>{requests.push(options);return new Response('{"code":"BILLING_UNAVAILABLE"}',{status:503});};
    await assert.rejects(api.post('/billing/checkout',{plan:'basic',period:'monthly'},{dispatchErrors:false}));assert.equal(requests.length,2);
}));
