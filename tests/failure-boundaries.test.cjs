const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
const sourceDir=path.join(__dirname,'../xiaoman-inquiry-center');
const candidate=fs.readFileSync(path.join(sourceDir,'conversions.js'),'utf8');
// The original-defect baseline uses the frozen beta.7 copy only; all candidate assertions use conversions.js.
const beta7=fs.readFileSync(path.join(sourceDir,'conversions.legacy.js'),'utf8');
const token='a'.repeat(64);
const flush=async()=>{for(let i=0;i<6;i++)await new Promise(r=>setImmediate(r));};
function fixture({source=candidate,channels=['ga4'],ready=true,holdClaim=false,loseClaim=false,loseRelease=false,throwEvent=false,ledger={},protocol='owned-v1',oldServer=false}={}){
 const w=new JSDOM('',{url:`https://fixture.example.invalid/thank-you/?form=quick_quote&inquiry_id=FW-N7&xi_receipt=${token}`,runScripts:'outside-only'}).window;
 const calls=[],requests=[],ticks=[],timeouts=[],claims=[];let allow=true,nonce=0,releasedLost=false;
 w.XIConversionConfig={site_host:'fixture.example.invalid',number_prefix:'FW-N',ga4_id:'G-FIXTURE',ads_id:'AW-123',ads_label:'FORM',analytics_service:channels.includes('ga4')?'analytics':'',ads_service:channels.includes('ads')?'ads':'',user_data_service:'ec',receipt_protocol:protocol,thank_you_routes:['/thank-you/'],endpoint:'https://fixture.example.invalid/api?action=xi_conversion',owned_endpoint:protocol?'https://fixture.example.invalid/api?action=xi_conversion_owned':''};
 w.consentApi={consentSync(service){return service?{cookie:{},consentGiven:true,cookieOptIn:allow}:null;}};
 w.setInterval=f=>{ticks.push(f);return ticks.length;};w.clearInterval=()=>{};
 w.setTimeout=(f,ms)=>{const t={f,ms,cancelled:false};timeouts.push(t);return t;};w.clearTimeout=t=>{if(t)t.cancelled=true;};
 w.gtag=(...args)=>{calls.push(args);if(args[0]==='get'&&ready)args[3](args[2]==='client_id'?'fixture-client-id':undefined);if(args[0]==='event'&&throwEvent)throw Error('outcome unknown after event call');};
 w.fetch=(_url,o)=>{const p=JSON.parse(o.body);requests.push(p);let data;
  if(oldServer&&new URL(_url).searchParams.get('action')==='xi_conversion_owned')return Promise.resolve({json:async()=>0});
  if(p.op==='claim'){
   if(ledger[p.channel]&&ledger[p.channel].state!=='pending')data={dispatch:false,reason:'already_claimed'};
   else{const owner=(++nonce).toString(16).padStart(32,'0');ledger[p.channel]={state:'claimed',owner};data={dispatch:true,claim_owner:owner,send_to:p.channel==='ga4'?'G-FIXTURE':'AW-123/FORM',inquiry_id:'FW-N7',form_type:'quick',is_test:1};}
   if(loseClaim)return Promise.reject(new Error('response lost after server committed claim'));
   if(holdClaim)return new Promise(resolve=>claims.push(()=>resolve({json:async()=>({success:true,data})})));
  }else if(p.op==='release_unsent'){
   const row=ledger[p.channel];const ok=!!row&&(row.state==='pending'||row.state==='claimed'&&row.owner===p.claim_owner);
   if(ok)ledger[p.channel]={state:'pending',owner:null};data={released:ok};
   if(loseRelease&&!releasedLost){releasedLost=true;return Promise.reject(new Error('response lost after release'));}
  }else if(p.op==='ack'){ledger[p.channel].state='callback';data={ack:true};}
  return Promise.resolve({json:async()=>({success:true,data})});
 };
 w.eval(source);
 return {w,calls,requests,ledger,setAllow:v=>{allow=v;},setReady:v=>{ready=v;},respond:()=>{holdClaim=false;claims.splice(0).forEach(f=>f());},tick:()=>ticks.forEach(f=>f()),expireReadiness:()=>timeouts.filter(t=>t.ms===3000&&!t.cancelled).forEach(t=>t.f()),eventCount:()=>calls.filter(a=>a[0]==='event').length,change:()=>w.document.dispatchEvent(new w.Event('rcb-consent-changed')),close:()=>w.close()};
}
test('beta.7 reproduction: withdrawing during claim consumes the claim without a Google event',async()=>{
 const p=fixture({source:beta7,holdClaim:true});await flush();p.setAllow(false);p.respond();await flush();p.setAllow(true);p.change();p.tick();await flush();assert.equal(p.ledger.ga4.state,'claimed');assert.equal(p.eventCount(),0);p.close();
});
test('candidate: withdrawal during claim releases known-unused owner and later consent sends once',async()=>{
 const p=fixture({holdClaim:true});await flush();p.setAllow(false);p.respond();await flush();assert.equal(p.ledger.ga4.state,'pending');assert.equal(p.eventCount(),0);p.setAllow(true);p.change();await flush();assert.equal(p.eventCount(),1);p.tick();p.change();await flush();assert.equal(p.eventCount(),1);p.close();
});
test('candidate: pagehide during claim releases, then pageshow may safely send once',async()=>{
 const p=fixture({holdClaim:true});await flush();p.w.dispatchEvent(new p.w.Event('pagehide'));p.respond();await flush();assert.equal(p.ledger.ga4.state,'pending');assert.equal(p.eventCount(),0);p.w.dispatchEvent(new p.w.Event('pageshow'));await flush();assert.equal(p.eventCount(),1);p.close();
});
test('candidate: gtag queue alone does not consume receipt; responsive destination can recover',async()=>{
 const p=fixture({ready:false});await flush();assert.equal(p.requests.length,0);p.expireReadiness();await flush();assert.equal(p.requests.length,0);p.setReady(true);p.change();await flush();assert.equal(p.eventCount(),1);assert.equal(p.calls.filter(a=>a[0]==='config').length,0);p.close();
});
test('candidate: consent removed during readiness causes no claim',async()=>{
 const p=fixture({ready:false});await flush();p.setAllow(false);p.calls.find(a=>a[0]==='get')[3]('fixture-client-id');await flush();assert.equal(p.requests.length,0);p.close();
});
test('candidate: Ads undefined gclid callback is valid readiness without pretending an ad click exists',async()=>{
 const p=fixture({channels:['ads']});await flush();assert.equal(p.eventCount(),1);const e=p.calls.find(a=>a[0]==='event');assert.equal(e[2].transaction_id,'FW-N7');assert.equal(e[2].gclid,undefined);p.close();
});
test('candidate: lost claim response is unknown and is never released or replayed',async()=>{
 const p=fixture({loseClaim:true});await flush();p.tick();p.change();await flush();assert.equal(p.ledger.ga4.state,'claimed');assert.equal(p.requests.filter(a=>a.op==='claim').length,1);assert.equal(p.requests.filter(a=>a.op==='release_unsent').length,0);assert.equal(p.eventCount(),0);p.close();
});
test('candidate: lost known-unused release response retries release, not Google send, until acknowledged',async()=>{
 const p=fixture({holdClaim:true,loseRelease:true});await flush();p.setAllow(false);p.respond();await flush();p.setAllow(true);p.tick();await flush();assert.equal(p.eventCount(),0);assert.equal(p.requests.filter(a=>a.op==='release_unsent').length,2);p.tick();await flush();assert.equal(p.eventCount(),1);p.close();
});
test('candidate: thrown event call is unknown, so claimed is retained and no second event is attempted',async()=>{
 const p=fixture({throwEvent:true});await flush();p.tick();p.change();await flush();assert.equal(p.eventCount(),1);assert.equal(p.ledger.ga4.state,'claimed');assert.equal(p.requests.filter(a=>a.op==='release_unsent').length,0);p.close();
});
test('candidate: callback acknowledgement has matching owner and is not a Google delivery proof',async()=>{
 const p=fixture();await flush();assert.equal(p.ledger.ga4.state,'claimed');const e=p.calls.find(a=>a[0]==='event');e[2].event_callback();await flush();assert.equal(p.ledger.ga4.state,'callback');const ack=p.requests.find(a=>a.op==='ack');assert.equal(ack.claim_owner,p.ledger.ga4.owner);assert.equal(ack.inquiry_id,'FW-N7');p.close();
});
test('candidate: another tab seeing already claimed never sends or releases it',async()=>{
 const ledger={ga4:{state:'claimed',owner:'f'.repeat(32)}};const p=fixture({ledger});await flush();assert.equal(p.eventCount(),0);assert.equal(p.requests.filter(a=>a.op==='release_unsent').length,0);p.close();
});
test('candidate: refresh of scrubbed URL cannot recover this version; no storage or historical resend',async()=>{
 const p=fixture({ready:false});await flush();assert.ok(!p.w.location.href.includes('xi_receipt'));assert.equal(p.w.localStorage.length,0);assert.equal(p.w.sessionStorage.length,0);p.close();
});
test('candidate: repeated start binds one WhatsApp listener and preserves the public consent API',async()=>{
 const p=fixture({channels:['ga4','ads']});await flush();p.w.XIConversionConfig.whatsapp_label='WA';
 const api=require(path.join(sourceDir,'conversions.js')),publicApi=p.w.XIConversions;api.start(p.w);api.start(p.w);
 assert.equal(p.w.XIConversions,publicApi);assert.equal(typeof publicApi.consents,'function');
 const a=p.w.document.createElement('a');a.href='https://api.whatsapp.com/send?phone=123';a.addEventListener('click',e=>e.preventDefault());p.w.document.body.append(a);a.click();await flush();
 assert.equal(p.calls.filter(c=>c[0]==='event'&&c[1]==='whatsapp_click').length,1);
 assert.equal(p.calls.filter(c=>c[0]==='event'&&c[1]==='conversion'&&c[2].send_to==='AW-123/WA').length,1);p.close();
});
test('candidate: old cached HTML without owned protocol never consumes a receipt',async()=>{
 const p=fixture({protocol:''});await flush();p.tick();await flush();assert.equal(p.requests.length,0);assert.equal(p.eventCount(),0);assert.equal(p.ledger.ga4,undefined);p.close();
});
test('candidate: new HTML with rolled-back beta7 PHP calls only unregistered action and cannot consume legacy claim',async()=>{
 const p=fixture({oldServer:true});await flush();p.tick();await flush();assert.equal(p.ledger.ga4,undefined);assert.equal(p.eventCount(),0);assert.ok(p.requests.length>0);p.close();
});
