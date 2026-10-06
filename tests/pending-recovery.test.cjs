const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
const source=fs.readFileSync(path.resolve(__dirname,'../xiaoman-inquiry-center/conversions.js'),'utf8');
// Frozen beta.8 JS, SHA256 1c8279e214e91236c586178fbb60e951d56139f48f814ba0af1f45a51c861251.
// Self-contained historical fixture; all current behavior assertions use the actual plugin above.
const baseline="/* REVIEW CANDIDATE ONLY. Requires matching owner/release PHP and schema migration. */\n(function(root,factory){'use strict';var api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else api.start(root);})(typeof window!=='undefined'?window:null,function(){\n 'use strict';\n function granted(w,name){try{var v=name&&w.consentApi.consentSync(name);return !!(v&&v.cookie&&v.consentGiven&&v.cookieOptIn);}catch(_){return false;}}\n function consent(w,c){return {analytics_storage:granted(w,c.analytics_service)?'granted':'denied',ad_storage:granted(w,c.ads_service)?'granted':'denied',ad_user_data:granted(w,c.user_data_service)?'granted':'denied',ad_personalization:'denied'};}\n function contact(href){try{var u=new URL(href);if(u.protocol==='mailto:')return 'email_click';if(u.protocol==='https:'&&((u.hostname==='wa.me'&&/^\\/[0-9]+\\/?$/.test(u.pathname))||(u.hostname==='api.whatsapp.com'&&/^\\/send\\/?$/.test(u.pathname))))return 'whatsapp_click';}catch(_){}return '';}\n function start(w){\n  if(!w||!w.XIConversionConfig)return;var c=w.XIConversionConfig;\n  if(w.location.hostname!==c.site_host||/[?&](bricks|brickspreview)=/.test(w.location.search))return;\n  // Separate from the public consent API: repeated asset/start calls must not bind contact listeners again.\n  if(w.__XI_CONVERSIONS_INITIALIZED__)return;w.__XI_CONVERSIONS_INITIALIZED__=true;\n  var d=w.document,p=new URLSearchParams(w.location.search),number=p.get('inquiry_id')||'',ticket=p.get('xi_receipt')||'',closed=false,timer=null;\n  var jobs={ga4:{busy:false,done:false,unused:null},ads:{busy:false,done:false,unused:null}};\n  var thank=Array.isArray(c.thank_you_routes)&&c.thank_you_routes.indexOf(w.location.pathname.replace(/\\/+$/,'')+'/')!==-1&&p.get('form')==='quick_quote';\n  // Preserve beta.7 URL privacy. Recovery is only within this page lifecycle; no browser storage.\n  if(p.has('xi_receipt')){p.delete('xi_receipt');w.history.replaceState(w.history.state,'',w.location.pathname+(p.toString()?'?'+p:'')+w.location.hash);}\n  function scrubLink(a){try{var u=new URL(a.href,w.location.href);if(u.searchParams.has('xi_receipt')){u.searchParams.delete('xi_receipt');a.href=u.href;}}catch(_){} }\n  function scrubLinks(){d.querySelectorAll('a[href*=\"xi_receipt=\"]').forEach(scrubLink);}\n  scrubLinks();d.addEventListener('DOMContentLoaded',scrubLinks);\n  w.XIConversions={consents:function(){return consent(w,c);}};\n  function allowed(channel){return !closed&&typeof w.gtag==='function'&&consent(w,c)[channel==='ga4'?'analytics_storage':'ad_storage']==='granted';}\n  function request(data,keepalive){return w.fetch(c.owned_endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',keepalive:!!keepalive,headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}).then(function(r){return r.json();});}\n  function ack(channel,owner){request({op:'ack',receipt:ticket,inquiry_id:number,channel:channel,claim_owner:owner},true).catch(function(){});}\n  function tagReady(channel){\n   // Official target-specific get callback is stronger than merely finding the queue function.\n   // It proves handler responsiveness, not network delivery or report attribution.\n   return new Promise(function(resolve){\n    if(!allowed(channel)){resolve(false);return;}\n    var settled=false,expiry=w.setTimeout(function(){finish(false);},3000);\n    function finish(ok){if(settled)return;settled=true;w.clearTimeout(expiry);resolve(ok&&allowed(channel));}\n    try{w.gtag('get',channel==='ga4'?c.ga4_id:c.ads_id,channel==='ga4'?'client_id':'gclid',function(value){\n     // Ads get may validly return undefined for a visitor without an ad click.\n     finish(channel==='ads'||(typeof value==='string'&&value!==''));\n    });}catch(_){finish(false);}\n   });\n  }\n  function releaseUnused(channel){\n   var job=jobs[channel],r=job.unused;if(!r)return Promise.resolve();\n   return request({op:'release_unsent',receipt:ticket,inquiry_id:number,channel:channel,claim_owner:r.claim_owner},true).then(function(result){\n    if(result.success&&result.data&&result.data.released){job.unused=null;job.phase='pending';}\n    else if(result.data&&result.data.code==='receipt'){job.done=true;job.phase='expired';}\n    // A lost release response retains the known-unused owner; only release may be retried.\n   }).catch(function(){job.phase='release_response_unknown';});\n  }\n  function transmit(channel,r){\n   var job=jobs[channel];if(!allowed(channel))return false;\n   var params={send_to:r.send_to,inquiry_id:r.inquiry_id,form_type:r.form_type,test_lead:r.is_test,event_callback:function(){ack(channel,r.claim_owner);},event_timeout:2000};\n   var userSet=false;\n   try{\n    if(channel==='ads'){\n     params.transaction_id=r.inquiry_id;\n     if(r.user_data&&consent(w,c).ad_user_data==='granted'){w.gtag('set','user_data',r.user_data);userSet=true;}\n    }\n    // From this point onward, even a thrown error may occur after queueing. Do not release.\n    job.done=true;job.unused=null;job.phase='event_attempted';\n    w.gtag('event',channel==='ads'?'conversion':'generate_lead',params);\n   }catch(_){job.phase=job.done?'event_outcome_unknown':'not_attempted';}\n   finally{if(userSet)try{w.gtag('set','user_data',null);}catch(_){} }\n   return job.done;\n  }\n  function dispatch(channel){\n   var job=jobs[channel],suffix=number.slice((c.number_prefix||'').length);\n   // Old cached HTML/PHP must not consume a receipt before revealing an incompatible response.\n   if(c.receipt_protocol!=='owned-v1'||!c.owned_endpoint)return;\n   if(!thank||!c.number_prefix||number.indexOf(c.number_prefix)!==0||!/^[1-9][0-9]*$/.test(suffix)||!/^[a-f0-9]{64}$/.test(ticket)||job.busy||job.done)return;\n   // Release a definitely-unused owned claim even after consent withdrawal; never send Google data.\n   if(job.unused){job.busy=true;releaseUnused(channel).finally(function(){job.busy=false;});return;}\n   if(!allowed(channel))return;job.busy=true;\n   tagReady(channel).then(function(ready){\n    if(!ready||!allowed(channel))return;job.phase='claim_inflight';\n    return request({op:'claim',receipt:ticket,inquiry_id:number,channel:channel,consent:consent(w,c),protocol:c.receipt_protocol}).then(function(result){\n     if(result.success&&result.data){\n      var r=result.data;\n      if(!r.dispatch){job.done=true;job.phase='already_claimed_unknown';return;}\n      if(!/^[a-f0-9]{32}$/.test(r.claim_owner||'')){job.done=true;job.phase='protocol_mismatch_unknown';return;}\n      job.unused=r;job.phase='claimed_unused';\n      if(!transmit(channel,r))return releaseUnused(channel);\n     }else if(result.data&&result.data.code==='receipt'){job.done=true;job.phase='expired';}\n    }).catch(function(){\n     // The server may have consumed the claim before the response disappeared. Never replay.\n     job.done=true;job.phase='claim_response_unknown';\n    });\n   }).finally(function(){job.busy=false;});\n  }\n  function reconcile(){dispatch('ga4');dispatch('ads');if(jobs.ga4.done&&jobs.ads.done&&timer){w.clearInterval(timer);timer=null;}}\n  d.addEventListener('click',function(e){var a=e.target&&e.target.closest&&e.target.closest('a[href]');if(!a)return;scrubLink(a);var name=contact(a.href);if(!name||typeof w.gtag!=='function')return;var state=consent(w,c);if(state.analytics_storage==='granted'&&/^G-[A-Z0-9]+$/.test(c.ga4_id))w.gtag('event',name,{send_to:c.ga4_id,contact_type:name==='email_click'?'email':'whatsapp'});var label=name==='email_click'?c.email_label:c.whatsapp_label;if(state.ad_storage==='granted'&&/^AW-[0-9]+$/.test(c.ads_id)&&/^[A-Za-z0-9_-]+$/.test(label||''))w.gtag('event','conversion',{send_to:c.ads_id+'/'+label});},true);\n  w.addEventListener('pagehide',function(){closed=true;});w.addEventListener('pageshow',function(){closed=false;reconcile();});d.addEventListener('DOMContentLoaded',reconcile);d.addEventListener('rcb-consent-changed',reconcile);reconcile();\n  timer=thank&&ticket?w.setInterval(reconcile,1000):null;\n  if(timer)w.setTimeout(function(){w.clearInterval(timer);timer=null;closed=true;ticket='';},30*60000);\n }\n return {start:start,consent:consent,contact:contact};\n});\n";
const KEY='xi_conversion_pending_v1',token='a'.repeat(64),original=`https://fixture.example.invalid/thank-you/?form=quick_quote&inquiry_id=FW-N7&xi_receipt=${token}`;
const clean='https://fixture.example.invalid/thank-you/?form=quick_quote&inquiry_id=FW-N7';
const flush=async()=>{for(let i=0;i<8;i++)await new Promise(resolve=>setImmediate(resolve));};

function fixture({script=source,url=original,store=new Map(),ledger={},clock={now:Date.UTC(2026,9,6,0)},expires=Math.floor(clock.now/1000)+1800,ready={ga4:true,ads:true},consent=true,cmpReady=true,loseClaim=false,holdClaim=false,storageBlocked=false,storageFailsAfterWrite=false,rejectClaim=null,unknownClaimResponse,failJson=false}={}){
 const w=new JSDOM('',{url,runScripts:'outside-only'}).window,calls=[],requests=[],ticks=[],timeouts=[],held=[];
 let nonce=0,failWrites=storageBlocked,failReads=storageBlocked,failRemove=storageBlocked;
 w.Date.now=()=>clock.now;
 Object.defineProperty(w,'sessionStorage',{value:{
  getItem(key){if(failReads)throw Error('storage blocked');return store.get(key)||null;},
  setItem(key,value){if(failWrites)throw Error('storage blocked');store.set(key,value);if(storageFailsAfterWrite){failReads=true;failRemove=true;storageFailsAfterWrite=false;}},
  removeItem(key){if(failRemove)throw Error('storage blocked');store.delete(key);}
 }});
 const hasOriginal=new URL(url).searchParams.get('xi_receipt')===token;
 w.XIConversionConfig={site_host:'fixture.example.invalid',number_prefix:'FW-N',ga4_id:'G-FIXTURE',ads_id:'AW-123',ads_label:'FORM',analytics_service:'analytics',ads_service:'ads',user_data_service:'ec',pending_storage_service:'analytics',receipt_protocol:'owned-v1',thank_you_routes:['/thank-you/','/es/thankyou/'],owned_endpoint:'https://fixture.example.invalid/api?action=xi_conversion_owned',...(hasOriginal?{receipt_expires_at:expires,receipt_pending_channels:{ga4:!ledger.ga4||ledger.ga4.state==='pending',ads:!ledger.ads||ledger.ads.state==='pending'}}:{})};
 w.consentApi={consentSync(name){if(!cmpReady)throw Error('CMP has not initialized');return {cookie:{id:name},consentGiven:true,cookieOptIn:consent};}};
 w.setInterval=fn=>{ticks.push(fn);return ticks.length;};w.clearInterval=()=>{};
 w.setTimeout=(fn,ms)=>{const timer={fn,ms,cancelled:false};timeouts.push(timer);return timer;};w.clearTimeout=timer=>{if(timer)timer.cancelled=true;};
 w.gtag=(...args)=>{calls.push(args);if(args[0]==='get'&&ready[args[1]==='G-FIXTURE'?'ga4':'ads'])args[3](args[2]==='client_id'?'fixture-client-id':undefined);};
 w.fetch=(_url,options)=>{
  const packet=JSON.parse(options.body);requests.push(packet);assert.equal(new URL(_url).searchParams.get('action'),'xi_conversion_owned');
  if(packet.op==='claim'){
   assert.equal(packet.receipt,token);assert.equal(packet.protocol,'owned-v1');
   // Mirrors the reviewed PHP contract: these explicit refusals happen BEFORE the state UPDATE.
   const rejection=typeof rejectClaim==='string'?rejectClaim:rejectClaim&&rejectClaim[packet.channel];
   if(rejection)return Promise.resolve({json:async()=>({success:false,data:{code:rejection}})});
   const row=ledger[packet.channel];let response;
   if(clock.now>=expires*1000)response={success:false,data:{code:'receipt'}};
   else if(row&&row.state!=='pending')response={success:true,data:{dispatch:false,reason:'already_claimed'}};
   else{const owner=(++nonce).toString(16).padStart(32,'0');ledger[packet.channel]={state:'claimed',owner};response={success:true,data:{dispatch:true,claim_owner:owner,send_to:packet.channel==='ga4'?'G-FIXTURE':'AW-123/FORM',inquiry_id:'FW-N7',form_type:'quick',is_test:true}};}
   if(loseClaim===true||loseClaim===packet.channel)return Promise.reject(Error('response lost after server committed claim'));
   if(failJson)return Promise.resolve({json:async()=>{throw Error('invalid or truncated JSON after the claim may have committed');}});
   if(unknownClaimResponse!==undefined)return Promise.resolve({json:async()=>unknownClaimResponse});
   if(holdClaim)return new Promise(resolve=>held.push(()=>resolve({json:async()=>response})));
   return Promise.resolve({json:async()=>response});
  }
  if(packet.op==='ack'){const row=ledger[packet.channel];assert.equal(packet.claim_owner,row.owner);row.state='callback';return Promise.resolve({json:async()=>({success:true,data:{ack:true}})});}
  if(packet.op==='release_unsent'){const row=ledger[packet.channel];assert.equal(packet.claim_owner,row.owner);row.state='pending';row.owner=null;return Promise.resolve({json:async()=>({success:true,data:{released:true}})});}
  throw Error('Unexpected operation');
 };
 w.eval(script);
 return {w,store,ledger,clock,expires,calls,requests,timeouts,tick:()=>ticks.forEach(fn=>fn()),change:()=>w.document.dispatchEvent(new w.Event('rcb-consent-changed')),setConsent:value=>{consent=value;},setCmpReady:value=>{cmpReady=value;},setReady:(channel,value)=>{ready[channel]=value;if(value)calls.filter(call=>call[0]==='get'&&(call[1]==='G-FIXTURE'?'ga4':'ads')===channel).forEach(call=>call[3](channel==='ga4'?'fixture-client-id':undefined));},setStorageFailures:(read,write,remove)=>{failReads=read;failWrites=write;failRemove=remove;},respond:()=>{holdClaim=false;held.splice(0).forEach(fn=>fn());},events:()=>calls.filter(call=>call[0]==='event'),close:()=>w.close()};
}

test('beta.8 baseline loses unclaimed measurement after a clean-URL refresh',async()=>{
 const first=fixture({script:baseline,ready:{ga4:false,ads:false}});await flush();assert.equal(first.store.size,0);assert.equal(first.requests.length,0);
 const next=fixture({script:baseline,url:first.w.location.href,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(next.events().length,0);first.close();next.close();
});
test('consented pending measurement survives refresh without re-exposing the receipt',async()=>{
 const first=fixture({ready:{ga4:false,ads:false}});await flush();assert.ok(!first.w.location.href.includes('xi_receipt'));assert.equal(first.requests.length,0);assert.ok(first.store.has(KEY));
 const next=fixture({url:first.w.location.href,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(next.events().length,2);assert.equal(next.requests.filter(p=>p.op==='claim').length,2);assert.equal(next.store.has(KEY),false);assert.ok(!next.w.location.href.includes('xi_receipt'));first.close();next.close();
});
test('only an unclaimed Ads channel resumes after GA4 was attempted',async()=>{
 const first=fixture({ready:{ga4:true,ads:false}});await flush();assert.equal(first.events().length,1);assert.equal(first.events()[0][1],'generate_lead');assert.deepEqual(JSON.parse(first.store.get(KEY)).channels,{ads:true});
 const next=fixture({url:clean,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(next.events().length,1);assert.equal(next.events()[0][1],'conversion');assert.equal(next.events()[0][2].transaction_id,'FW-N7');assert.equal(next.requests.some(p=>p.channel==='ga4'),false);first.close();next.close();
});
test('claim in flight removes recovery before its HTTP response arrives',async()=>{
 const first=fixture({holdClaim:true});await flush();assert.equal(first.store.has(KEY),false);assert.equal(first.events().length,0);
 const next=fixture({url:clean,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(next.requests.length,0);assert.equal(next.events().length,0);first.respond();await flush();assert.equal(first.events().length,2);first.close();next.close();
});
test('a lost claim response is unknown and never resumes, releases or replays',async()=>{
 const first=fixture({loseClaim:true});await flush();assert.equal(first.store.has(KEY),false);assert.equal(first.ledger.ga4.state,'claimed');
 const next=fixture({url:clean,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();next.tick();await flush();assert.equal(next.requests.length,0);assert.equal(next.events().length,0);assert.equal(first.requests.some(p=>p.op==='release_unsent'),false);first.close();next.close();
});
test('successful callback channels cannot resume after refresh',async()=>{
 const first=fixture();await flush();first.events().forEach(call=>call[2].event_callback());await flush();assert.equal(first.ledger.ga4.state,'callback');assert.equal(first.store.has(KEY),false);
 const next=fixture({url:clean,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(next.events().length,0);assert.equal(next.requests.length,0);first.close();next.close();
});
test('original URL reopened with server non-pending mask never claims either channel',async()=>{
 const first=fixture({ledger:{ga4:{state:'claimed',owner:'f'.repeat(32)},ads:{state:'callback',owner:'e'.repeat(32)}}});await flush();assert.equal(first.requests.length,0);assert.equal(first.events().length,0);assert.equal(first.store.has(KEY),false);first.close();
});
test('copied same-tab pending storage cannot send over another document claimed channels',async()=>{
 const first=fixture({ready:{ga4:false,ads:false}});await flush();const copy=new Map(first.store);
 first.setReady('ga4',true);first.setReady('ads',true);first.tick();await flush();assert.equal(first.events().length,2);
 const copied=fixture({url:clean,store:copy,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(copied.events().length,0);assert.equal(copied.requests.some(p=>p.op==='release_unsent'),false);first.close();copied.close();
});
test('explicit withdrawal clears the pointer and a refresh never reads or sends it',async()=>{
 const first=fixture({ready:{ga4:false,ads:false}});await flush();assert.ok(first.store.has(KEY));first.setConsent(false);first.change();await flush();assert.equal(first.store.has(KEY),false);
 const next=fixture({url:clean,store:first.store,consent:false,clock:first.clock,expires:first.expires});await flush();assert.equal(next.requests.length,0);assert.equal(next.events().length,0);assert.equal(next.store.has(KEY),false);first.close();next.close();
});
test('delayed CMP initialization defers storage reads without destroying pending recovery',async()=>{
 const first=fixture({ready:{ga4:false,ads:false}});await flush();
 const next=fixture({url:clean,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires,cmpReady:false});await flush();assert.ok(next.store.has(KEY));assert.equal(next.requests.length,0);next.setCmpReady(true);next.tick();await flush();assert.equal(next.events().length,2);first.close();next.close();
});
test('navigating to an ordinary page clears a pending pointer upon explicit consent withdrawal without claiming',async()=>{
 const first=fixture({ready:{ga4:false,ads:false}});await flush();const next=fixture({url:'https://fixture.example.invalid/products/',store:first.store,clock:first.clock,expires:first.expires});await flush();assert.ok(next.store.has(KEY));assert.equal(next.requests.length,0);assert.equal(next.events().length,0);
 next.setConsent(false);next.change();await flush();assert.equal(next.store.has(KEY),false);assert.equal(next.requests.length,0);assert.equal(next.events().length,0);first.close();next.close();
});
test('ordinary pages expire a pending pointer at its original deadline and do not perform lead measurement',async()=>{
 const first=fixture({ready:{ga4:false,ads:false}});await flush();first.clock.now+=10*60000;const next=fixture({url:'https://fixture.example.invalid/products/',store:first.store,clock:first.clock,expires:first.expires});await flush();assert.ok(next.timeouts.some(timer=>timer.ms===20*60000&&!timer.cancelled));
 next.clock.now=next.expires*1000;next.timeouts.filter(timer=>timer.ms===20*60000&&!timer.cancelled).forEach(timer=>timer.fn());await flush();assert.equal(next.store.has(KEY),false);assert.equal(next.requests.length,0);assert.equal(next.events().length,0);first.close();next.close();
});
test('an ordinary page defers pointer reads until CMP is ready, then only schedules expiry cleanup',async()=>{
 const first=fixture({ready:{ga4:false,ads:false}});await flush();const next=fixture({url:'https://fixture.example.invalid/products/',store:first.store,clock:first.clock,expires:first.expires,cmpReady:false});await flush();assert.ok(next.store.has(KEY));assert.equal(next.timeouts.length,0);
 next.setCmpReady(true);next.change();await flush();assert.ok(next.timeouts.some(timer=>timer.ms===30*60000));assert.equal(next.requests.length,0);assert.equal(next.events().length,0);first.close();next.close();
});
test('refresh retains the server original deadline rather than starting another 30 minutes',async()=>{
 const first=fixture({ready:{ga4:false,ads:false}});await flush();first.clock.now+=10*60000;
 const next=fixture({url:clean,store:first.store,clock:first.clock,expires:first.expires,ready:{ga4:false,ads:false}});await flush();assert.equal(JSON.parse(next.store.get(KEY)).expires_at,first.expires);assert.ok(next.timeouts.some(timer=>timer.ms===20*60000));
 first.clock.now=first.expires*1000;next.setReady('ga4',true);next.setReady('ads',true);next.tick();await flush();assert.equal(next.requests.length,0);assert.equal(next.events().length,0);assert.equal(next.store.has(KEY),false);first.close();next.close();
});
test('expired pointers and forged future deadlines cannot make a claim',async()=>{
 for(const offset of [-1,1801]){const clock={now:Date.UTC(2026,9,6,0)},store=new Map([[KEY,JSON.stringify({host:'fixture.example.invalid',inquiry_id:'FW-N7',receipt:token,expires_at:Math.floor(clock.now/1000)+offset,channels:{ga4:true,ads:true}})]]);const page=fixture({url:clean,store,clock});await flush();assert.equal(page.requests.length,0);assert.equal(page.events().length,0);assert.equal(store.has(KEY),false);page.close();}
});
test('foreign host, number, route and form flag never recover a historical pointer',async()=>{
 for(const url of ['https://fixture.example.invalid/products/?form=quick_quote&inquiry_id=FW-N7','https://fixture.example.invalid/thank-you/?form=detail&inquiry_id=FW-N7','https://fixture.example.invalid/thank-you/?form=quick_quote&inquiry_id=FW-N8','https://other.example.invalid/thank-you/?form=quick_quote&inquiry_id=FW-N7']){
  const clock={now:Date.UTC(2026,9,6,0)},store=new Map([[KEY,JSON.stringify({host:'fixture.example.invalid',inquiry_id:'FW-N7',receipt:token,expires_at:Math.floor(clock.now/1000)+1800,channels:{ga4:true,ads:true}})]]);const page=fixture({url,store,clock});await flush();assert.equal(page.requests.length,0);assert.equal(page.events().length,0);page.close();
 }
});
test('storage unavailable from the start retains beta.8 in-memory measurement without claiming recovery support',async()=>{
 const page=fixture({storageBlocked:true});await flush();assert.equal(page.events().length,2);assert.equal(page.store.size,0);page.close();
});
test('known persisted pointer must be invalidated before a claim; storage failure cannot leave a stale replay pointer',async()=>{
 const page=fixture({ready:{ga4:false,ads:false}});await flush();page.setStorageFailures(false,true,true);page.setReady('ga4',true);page.setReady('ads',true);page.tick();await flush();assert.equal(page.requests.length,0);assert.equal(page.events().length,0);assert.ok(page.store.has(KEY));
 page.setStorageFailures(false,false,false);page.tick();await flush();assert.equal(page.events().length,2);assert.equal(page.store.has(KEY),false);page.close();
});
test('a pointer write followed by failed readback is unknown and cannot claim over the possibly saved mask',async()=>{
 const page=fixture({storageFailsAfterWrite:true});await flush();assert.ok(page.store.has(KEY));assert.equal(page.requests.length,0);assert.equal(page.events().length,0);
 page.setStorageFailures(false,false,false);page.tick();await flush();assert.equal(page.events().length,2);assert.equal(page.store.has(KEY),false);page.close();
});
test('known-unused owner released within the same document may become pending, but no owner or customer data is persisted',async()=>{
 const page=fixture({holdClaim:true});await flush();page.setConsent(false);page.respond();await flush();assert.equal(page.ledger.ga4.state,'pending');assert.equal(page.ledger.ads.state,'pending');assert.equal(page.events().length,0);
 page.setConsent(true);page.setReady('ga4',false);page.setReady('ads',false);page.tick();await flush();const record=JSON.parse(page.store.get(KEY));assert.deepEqual(Object.keys(record).sort(),['channels','expires_at','host','inquiry_id','receipt']);assert.equal(JSON.stringify(record).includes('owner'),false);assert.equal(JSON.stringify(record).includes('email'),false);page.close();
});
test('an allowed translated thank-you route can resume matching pending channels',async()=>{
 const first=fixture({ready:{ga4:false,ads:false}});await flush();const next=fixture({url:'https://fixture.example.invalid/es/thankyou/?form=quick_quote&inquiry_id=FW-N7',store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(next.events().length,2);first.close();next.close();
});

for(const code of ['consent','config','protocol'])test(`explicit pre-UPDATE ${code} rejection preserves the original pending pointer across refresh`,async()=>{
 const first=fixture({rejectClaim:code});await flush();
 assert.equal(first.requests.filter(p=>p.op==='claim').length,2);assert.equal(first.events().length,0);assert.deepEqual(first.ledger,{});
 const saved=JSON.parse(first.store.get(KEY));assert.deepEqual(saved.channels,{ga4:true,ads:true});assert.equal(saved.expires_at,first.expires);assert.equal(saved.receipt,token);
 const next=fixture({url:first.w.location.href,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();
 assert.equal(next.events().length,2);assert.equal(next.events().filter(p=>p[1]==='generate_lead').length,1);assert.equal(next.events().filter(p=>p[1]==='conversion').length,1);assert.equal(next.store.has(KEY),false);
 next.tick();await flush();assert.equal(next.events().length,2);first.close();next.close();
});

test('an explicit rejection restores only the unclaimed channel, never an already attempted channel',async()=>{
 const first=fixture({rejectClaim:{ads:'consent'}});await flush();assert.equal(first.events().length,1);assert.equal(first.events()[0][1],'generate_lead');assert.equal(first.ledger.ga4.state,'claimed');assert.equal(first.ledger.ads,undefined);
 assert.deepEqual(JSON.parse(first.store.get(KEY)).channels,{ads:true});
 const next=fixture({url:clean,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(next.requests.filter(p=>p.op==='claim').length,1);assert.equal(next.requests[0].channel,'ads');assert.equal(next.events().length,1);assert.equal(next.events()[0][1],'conversion');first.close();next.close();
});

test('a recoverable explicit rejection does not revive the other channel with a lost response',async()=>{
 const first=fixture({rejectClaim:{ga4:'config'},loseClaim:'ads'});await flush();assert.equal(first.events().length,0);assert.equal(first.ledger.ga4,undefined);assert.equal(first.ledger.ads.state,'claimed');assert.deepEqual(JSON.parse(first.store.get(KEY)).channels,{ga4:true});
 const next=fixture({url:clean,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(next.requests.filter(p=>p.op==='claim').length,1);assert.equal(next.requests[0].channel,'ga4');assert.equal(next.events().length,1);assert.equal(next.events()[0][1],'generate_lead');assert.equal(next.requests.some(p=>p.channel==='ads'),false);first.close();next.close();
});

test('explicit rejection recovery still clears on current withdrawal and keeps the original deadline on later consent',async()=>{
 const page=fixture({rejectClaim:'consent'});await flush();assert.ok(page.store.has(KEY));page.setConsent(false);page.change();await flush();assert.equal(page.store.has(KEY),false);
 page.setReady('ga4',false);page.setReady('ads',false);page.setConsent(true);page.change();await flush();assert.deepEqual(JSON.parse(page.store.get(KEY)).channels,{ga4:true,ads:true});assert.equal(JSON.parse(page.store.get(KEY)).expires_at,page.expires);assert.equal(page.events().length,0);page.close();
});

for(const [label,response] of [
 ['unknown refusal',{success:false,data:{code:'storage'}}],
 ['missing fields',{}],
 ['null JSON',null],
 ['untyped success flag',{success:'false',data:{code:'consent'}}],
 ['contradictory refusal with a claim owner',{success:false,data:{code:'consent',dispatch:true,claim_owner:'f'.repeat(32)}}]
])test(`${label} never restores a potentially consumed claim or retries it in the same document`,async()=>{
 const first=fixture({unknownClaimResponse:response});await flush();assert.equal(first.requests.length,2);assert.equal(first.events().length,0);assert.equal(first.store.has(KEY),false);assert.equal(first.ledger.ga4.state,'claimed');assert.equal(first.ledger.ads.state,'claimed');
 first.tick();first.change();await flush();assert.equal(first.requests.length,2);assert.equal(first.requests.some(p=>p.op==='release_unsent'),false);
 const next=fixture({url:clean,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(next.requests.length,0);assert.equal(next.events().length,0);first.close();next.close();
});

test('claim JSON parsing failure cannot restore, release or replay either channel',async()=>{
 const first=fixture({failJson:true});await flush();assert.equal(first.requests.length,2);assert.equal(first.store.has(KEY),false);assert.equal(first.events().length,0);first.tick();first.change();await flush();assert.equal(first.requests.length,2);assert.equal(first.requests.some(p=>p.op==='release_unsent'),false);
 const next=fixture({url:clean,store:first.store,ledger:first.ledger,clock:first.clock,expires:first.expires});await flush();assert.equal(next.requests.length,0);assert.equal(next.events().length,0);first.close();next.close();
});
