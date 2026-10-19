'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const api=require('../xiaoman-inquiry-center/purpose-consent.js');
function fixture(state='loading'){
 const dl=[],docListeners={},windowListeners={},timers=[];
 function on(map,name,fn){(map[name]||(map[name]=[])).push(fn);}
 const d={readyState:state,addEventListener:(name,fn)=>on(docListeners,name,fn),dispatchEvent:e=>{for(const fn of docListeners[e.type]||[])fn(e);}};
 const w={document:d,dataLayer:dl,addEventListener:(name,fn)=>on(windowListeners,name,fn),setTimeout:(fn,ms)=>{assert.equal(ms,0);timers.push(fn);},CustomEvent:function(type){this.type=type;}};
 return{w,d,dl,docListeners,windowListeners,emitDoc:name=>d.dispatchEvent({type:name}),emitWindow:name=>{for(const fn of windowListeners[name]||[])fn({type:name});},flush:()=>{const todo=timers.splice(0);todo.forEach(fn=>fn());},readyCount:()=>dl.filter(x=>x.event==='xi_cmp_ready').length};
}
test('DOM Ready before CMP: same-state late initialization wakes and coalesces without a GCM transition',()=>{
 const f=fixture();api.start(f.w);assert.equal(f.readyCount(),0);assert.equal(Object.keys(f.windowListeners).length,0);
 f.d.readyState='interactive';f.emitDoc('DOMContentLoaded');f.flush();assert.equal(f.readyCount(),1);
 // No Google change listener is called: late CMP lifecycle alone must retry.
 f.emitWindow('consentApi');f.emitDoc('RCB/Apply/Interactive');f.emitDoc('RCB/OptIn/All');f.flush();assert.equal(f.readyCount(),2);
 for(const packet of f.dl)assert.deepEqual(Object.keys(packet),['event']);
});
test('later grant and withdrawal wake independently; purpose notifications do not form a feedback loop',()=>{
 const f=fixture('complete');const bridge=api.start(f.w);f.flush();
 f.emitDoc('RCB/OptIn/All');f.flush();assert.equal(f.readyCount(),2);
 f.emitDoc('RCB/OptOut/All');f.flush();assert.equal(f.readyCount(),3);
 const packet={protocol:'gtm-purpose-v1',container:'GTM-56DJ383V',defaultStatesVerified:true,alwaysFireDisabledVerified:true,states:{analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'}};
 assert.equal(bridge.install(packet.container,packet.protocol,()=>packet),true);f.flush();assert.equal(f.readyCount(),3);
});
test('bridge arriving after DOM Ready installs immediately once and duplicate load does not add listeners',()=>{
 const f=fixture('complete');const bridge=api.start(f.w);assert.equal(f.readyCount(),0);f.flush();assert.equal(f.readyCount(),1);
 const before=Object.values(f.docListeners).map(x=>x.length);assert.equal(api.start(f.w),null);assert.equal(f.w.XIPurposeConsent,bridge);assert.deepEqual(Object.values(f.docListeners).map(x=>x.length),before);
 f.emitDoc('DOMContentLoaded');f.emitDoc('DOMContentLoaded');f.flush();assert.equal(f.readyCount(),1);
});
test('unknown global is preserved and receives no watcher or internal event side effects',()=>{
 const f=fixture('complete');const unknown={unknown:true};f.w.XIPurposeConsent=unknown;assert.equal(api.start(f.w),null);f.flush();assert.equal(f.w.XIPurposeConsent,unknown);assert.deepEqual(f.dl,[]);assert.deepEqual(f.docListeners,{});assert.deepEqual(f.windowListeners,{});
});
