'use strict';
const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {JSDOM,VirtualConsole}=require('jsdom');
const source=fs.readFileSync(path.join(__dirname,'../xiaoman-inquiry-center/conversions.js'),'utf8');
const HOST='fixture.example.invalid',KEY='xi_conversion_pending_v1',clockDefault=Date.UTC(2026,9,11,7);
const token=id=>'a'.repeat(60)+Number(id).toString(16).padStart(4,'0');
const target=(id=7,kind='quick',route='/thank-you/')=>`https://${HOST}${route}?form=quick_quote&inquiry_id=FW-N${id}&form_type=${kind}&xi_receipt=${token(id)}`;
const flush=async()=>{for(let i=0;i<12;i++)await new Promise(resolve=>setImmediate(resolve));};

function page({url=`https://${HOST}/products/`,ledger={},store=new Map(),clock={now:clockDefault},expires=Math.floor(clock.now/1000)+1800,states={ga:true,ads:true,ec:true},adsStorage=false,ready={ga4:true,ads:true},holdClaim=false,loseClaim=false,holdRelease=false,commitRelease=true,abortController=true,rejections={},throwEvent=false,failUserSet=false,replyOverride=null}={}){
 const vc=new VirtualConsole(),navigations=[],calls=[],requests=[],timers=[],held=[],heldReleases=[],ticks=[],formKinds={};
 vc.on('jsdomError',err=>{if(/navigation/i.test(err.message))navigations.push(err.message);});
 const w=new JSDOM('<form data-element-id="abc"><button type="submit">Send</button></form>',{url,runScripts:'outside-only',virtualConsole:vc}).window;
 if(!abortController)w.AbortController=undefined;
 w.Date.now=()=>clock.now;
 const query=new URL(url).searchParams,id=Number((query.get('inquiry_id')||'FW-N7').slice(4)),row=ledger['FW-N'+id]||{};
 w.XIConversionConfig={site_host:HOST,number_prefix:'FW-N',ga4_id:'G-FIXTURE',ads_id:'AW-123',ads_label:'FORM',analytics_service:'ga',ads_service:'ads',user_data_service:'ec',pending_storage_service:'ga',pending_ads_storage_service:adsStorage?'ads':'',receipt_protocol:'owned-v1',thank_you_routes:['/thank-you/','/es/thankyou/','/fr/merci/'],owned_endpoint:`https://${HOST}/wp-admin/admin-ajax.php?action=xi_conversion_owned`,...(query.has('xi_receipt')?{receipt_expires_at:expires,receipt_pending_channels:{ga4:!row.ga4||row.ga4.state==='pending',ads:!row.ads||row.ads.state==='pending'}}:{})};
 w.consentApi={consentSync(name){if(states[name]===null)throw Error('CMP pending');return {cookie:{id:name},consentGiven:true,cookieOptIn:states[name]===true};}};
 Object.defineProperty(w,'sessionStorage',{value:{getItem:key=>store.get(key)||null,setItem:(key,value)=>store.set(key,value),removeItem:key=>store.delete(key)}});
 w.setInterval=fn=>{ticks.push(fn);return ticks.length;};w.clearInterval=()=>{};
 w.setTimeout=(fn,ms)=>{const timer={fn,ms,cancelled:false};timers.push(timer);return timer;};w.clearTimeout=t=>{if(t)t.cancelled=true;};
 w.gtag=(...args)=>{calls.push(args);if(args[0]==='get'&&ready[args[1]==='G-FIXTURE'?'ga4':'ads'])args[3](args[2]==='client_id'?'fixture-cid':undefined);if(args[0]==='set'&&args[2]&&failUserSet)throw Error('user set failed before event');if(args[0]==='event'&&throwEvent)throw Error('possibly queued before failure');};
 let nonce=0;
 w.fetch=(url,options)=>{
  const p=JSON.parse(options.body);requests.push({...p,keepalive:!!options.keepalive,signal:options.signal});
  assert.equal(new URL(url).searchParams.get('action'),'xi_conversion_owned');
  const id=Number(p.inquiry_id.slice(4));assert.equal(p.receipt,token(id));
  const records=ledger[p.inquiry_id]||(ledger[p.inquiry_id]={});
  if(p.op==='claim'){
   if(rejections[p.channel])return Promise.resolve({json:async()=>({success:false,data:{code:rejections[p.channel]}})});
   const old=records[p.channel];let data;
   if(old&&old.state!=='pending')data={dispatch:false,reason:'already_claimed'};
   else{const owner=(++nonce).toString(16).padStart(32,'0');records[p.channel]={state:'claimed',owner};data={dispatch:true,claim_owner:owner,channel:p.channel,send_to:p.channel==='ga4'?'G-FIXTURE':'AW-123/FORM',inquiry_id:p.inquiry_id,form_type:formKinds[id]||query.get('form_type')||'quick',is_test:1,...(p.channel==='ads'?{user_data:{sha256_email_address:'e'.repeat(64)}}:{})};}
   if(loseClaim)return Promise.reject(Error('claim response lost after commit'));
   const response={json:async()=>replyOverride||{success:true,data}};
   if(holdClaim)return new Promise((resolve,reject)=>{if(options.signal)options.signal.addEventListener('abort',()=>reject(new w.DOMException('Aborted','AbortError')));held.push(()=>resolve(response));});
   return Promise.resolve(response);
  }
  if(p.op==='release_unsent'){
   const row=records[p.channel];assert.equal(row.owner,p.claim_owner);const release=()=>{row.state='pending';row.owner=null;};
   if(commitRelease)release();
   const response={json:async()=>({success:true,data:{released:true}})};
   if(holdRelease)return new Promise((resolve,reject)=>{if(options.signal)options.signal.addEventListener('abort',()=>reject(new w.DOMException('Aborted','AbortError')));heldReleases.push(()=>{if(!commitRelease)release();resolve(response);});});
   if(!commitRelease)release();return Promise.resolve(response);
  }
  if(p.op==='ack'){const row=records[p.channel];assert.equal(row.owner,p.claim_owner);row.state='callback';return Promise.resolve({json:async()=>({success:true,data:{ack:true}})});}
  throw Error('Unexpected op '+p.op);
 };
 w.eval(source);
 function success({id=7,kind='quick',route='/thank-you/',data={},resSuccess=true}={}){
  const res={success:resSuccess,data:{type:'success',xiTracking:'receipt_issued',redirectTo:target(id,kind,route),inquiryNumber:'FW-N'+id,xiReceiptProtocol:'owned-v1',xiReceiptExpiresAt:expires,xiReceiptPendingChannels:{ga4:true,ads:true},...data}};
  formKinds[id]=kind;
  w.document.dispatchEvent(new w.CustomEvent('bricks/form/success',{detail:{elementId:'abc',res}}));
  return res;
 }
 return {w,clock,states,ready,store,ledger,calls,requests,timers,navigations,success,events:()=>calls.filter(c=>c[0]==='event'),callbacks:()=>calls.filter(c=>c[0]==='event').forEach(c=>c[2].event_callback()),fire:ms=>timers.filter(t=>t.ms===ms&&!t.cancelled&&!t.fired).forEach(t=>{t.fired=true;t.fn();}),respond:()=>{holdClaim=false;held.splice(0).forEach(fn=>fn());},respondRelease:()=>{holdRelease=false;heldReleases.splice(0).forEach(fn=>fn());},tick:()=>ticks.forEach(fn=>fn()),change:()=>w.document.dispatchEvent(new w.Event('rcb-consent-changed')),close:()=>w.close()};
}

test('verified quick/detail forms use the same dispatcher and take native navigation only after event processing',async()=>{
 for(const kind of ['quick','detail']){const p=page();const res=p.success({kind});await flush();
  assert.equal(res.data.redirectTo,undefined);assert.equal(res.data.redirectTimeout,undefined);assert.equal(p.w.document.querySelector('button').disabled,true);
  assert.equal(p.events().length,2);assert.equal(p.events().find(c=>c[1]==='conversion')[2].transaction_id,'FW-N7');assert.ok(p.events().every(c=>c[2].form_type===kind));
  assert.equal(p.navigations.length,0);p.callbacks();await flush();assert.equal(p.navigations.length,1);
  p.callbacks();p.tick();await flush();assert.equal(p.events().length,2);assert.equal(p.navigations.length,1);p.close();
 }
});

test('no independent 3-second or native redirect ends an in-flight claim; delayed responses can still send',async()=>{
 const p=page({holdClaim:true});const res=p.success();await flush();
 assert.equal(res.data.redirectTo,undefined);assert.equal(p.events().length,0);assert.equal(p.ledger['FW-N7'].ga4.state,'claimed');
 p.fire(3000);p.fire(4500);await flush();assert.equal(p.navigations.length,0);
 p.clock.now+=8000;p.respond();await flush();assert.equal(p.events().length,2);assert.equal(p.navigations.length,0);
 p.callbacks();await flush();assert.equal(p.navigations.length,1);p.close();
});

test('explicit HTTP abort marks claim unknown before navigating and never restores or replays the potentially committed claim',async()=>{
 const p=page({holdClaim:true});p.success();await flush();p.fire(12000);await flush();
 assert.equal(p.navigations.length,1);assert.equal(p.events().length,0);assert.equal(p.store.has(KEY),false);
 assert.ok(p.w.XIConversions.status().every(row=>row.ga4==='claim_response_unknown'&&row.ads==='claim_response_unknown'));
 p.respond();p.tick();p.change();await flush();assert.equal(p.events().length,0);assert.equal(p.requests.filter(r=>r.op==='claim').length,2);
 const t=page({url:target(),ledger:p.ledger,store:p.store});await flush();assert.equal(t.events().length,0);assert.equal(t.requests.length,0);
 p.close();t.close();
});

test('lack of callback uses only the post-event local wait, does not falsely ack, and does not retry',async()=>{
 const p=page();p.success();await flush();assert.equal(p.events().length,2);p.fire(2250);await flush();
 assert.equal(p.navigations.length,1);assert.equal(p.requests.filter(r=>r.op==='ack').length,0);
 p.tick();p.change();await flush();assert.equal(p.events().length,2);p.close();
});

test('unready form-page destinations remain pending and thank-you fallback sends once',async()=>{
 const p=page({ready:{ga4:false,ads:false}});p.success();await flush();assert.equal(p.requests.length,0);
 assert.equal(JSON.parse(p.store.get(KEY)).expires_at,Math.floor(clockDefault/1000)+1800);
 p.fire(600);await flush();assert.equal(p.navigations.length,1);assert.equal(p.requests.length,0);
 const t=page({url:target(),ledger:p.ledger,store:p.store,clock:p.clock});await flush();assert.equal(t.events().length,2);p.close();t.close();
});

test('server pre-UPDATE refusals retain original expiry and fallback eligibility',async()=>{
 const p=page({rejections:{ga4:'config',ads:'consent'},expires:Math.floor(clockDefault/1000)+900});p.success();await flush();
 assert.equal(p.events().length,0);assert.equal(p.navigations.length,1);assert.deepEqual(JSON.parse(p.store.get(KEY)).channels,{ga4:true,ads:true});
 assert.equal(JSON.parse(p.store.get(KEY)).expires_at,Math.floor(clockDefault/1000)+900);
 const t=page({url:target(),ledger:p.ledger,store:p.store,clock:p.clock,expires:Math.floor(clockDefault/1000)+900});await flush();assert.equal(t.events().length,2);p.close();t.close();
});

test('claimed/callback channels on the thank-you page never resend form-page events',async()=>{
 const p=page();p.success();await flush();p.callbacks();await flush();
 const t=page({url:target(),ledger:p.ledger,store:p.store,clock:p.clock});await flush();assert.equal(t.requests.length,0);assert.equal(t.events().length,0);p.close();t.close();
});

test('withdrawal during claim releases only the known-unused owner without sending any Google data',async()=>{
 const p=page({holdClaim:true});p.success();await flush();p.states.ga=false;p.states.ads=false;p.states.ec=false;p.respond();await flush();
 assert.equal(p.events().length,0);assert.equal(p.requests.filter(r=>r.op==='release_unsent').length,2);assert.equal(p.ledger['FW-N7'].ga4.state,'pending');assert.equal(p.store.has(KEY),false);assert.equal(p.navigations.length,1);p.close();
});

test('a stalled known-unused release reaches an UNKNOWN deadline and permits navigation without pretending release succeeded',async()=>{
 for(const abortController of [true,false]){
  const p=page({holdClaim:true,holdRelease:true,commitRelease:false,abortController});p.success();await flush();p.states.ga=false;p.states.ads=false;p.states.ec=false;p.respond();await flush();
  assert.equal(p.navigations.length,0);assert.equal(p.requests.filter(r=>r.op==='release_unsent').length,2);assert.equal(p.ledger['FW-N7'].ga4.state,'claimed');
  p.fire(4000);await flush();assert.equal(p.navigations.length,1);assert.equal(p.events().length,0);assert.equal(p.store.has(KEY),false);
  assert.ok(p.w.XIConversions.status().every(row=>row.ga4==='release_response_unknown'&&row.ads==='release_response_unknown'));
  const t=page({url:target(),ledger:p.ledger,store:p.store});await flush();assert.equal(t.events().length,0);assert.equal(t.requests.length,0);
  // A late completion cannot make the timed-out client operation assume release or replay a send.
  p.respondRelease();await flush();assert.equal(p.events().length,0);assert.ok(p.w.XIConversions.status().every(row=>row.ga4==='release_response_unknown'&&row.ads==='release_response_unknown'));p.close();t.close();
 }
});

test('a lost release response after server commit also remains UNKNOWN until an explicit owner release acknowledgement',async()=>{
 const p=page({holdClaim:true,holdRelease:true});p.success();await flush();p.states.ga=false;p.states.ads=false;p.states.ec=false;p.respond();await flush();
 assert.equal(p.ledger['FW-N7'].ga4.state,'pending');p.fire(4000);await flush();assert.equal(p.navigations.length,1);assert.equal(p.events().length,0);assert.equal(p.store.has(KEY),false);
 assert.ok(p.w.XIConversions.status().every(row=>row.ga4==='release_response_unknown'&&row.ads==='release_response_unknown'));p.respondRelease();await flush();assert.equal(p.store.has(KEY),false);assert.equal(p.events().length,0);p.close();
});

test('pagehide during claim does not send; BFCache restore safely resumes and completes navigation',async()=>{
 const p=page({holdClaim:true});p.success();await flush();p.w.dispatchEvent(new p.w.Event('pagehide'));p.respond();await flush();
 assert.equal(p.events().length,0);assert.equal(p.ledger['FW-N7'].ga4.state,'pending');assert.equal(p.navigations.length,0);
 p.w.dispatchEvent(new p.w.Event('pageshow'));await flush();assert.equal(p.events().length,2);p.callbacks();await flush();assert.equal(p.navigations.length,1);p.close();
});

test('duplicate success events for the same receipt do not make another claim or accelerate navigation',async()=>{
 const p=page({holdClaim:true});p.success();p.success();await flush();assert.equal(p.requests.filter(r=>r.op==='claim').length,2);assert.equal(p.navigations.length,0);
 p.respond();await flush();p.callbacks();await flush();assert.equal(p.events().length,2);assert.equal(p.navigations.length,1);p.close();
});

test('two different saved IDs in one document are not mistaken for a duplicate inquiry',async()=>{
 const p=page();p.success();await flush();p.callbacks();await flush();p.w.dispatchEvent(new p.w.Event('pagehide'));p.w.dispatchEvent(new p.w.Event('pageshow'));
 p.success({id:8});await flush();p.callbacks();await flush();
 assert.equal(p.events().filter(c=>c[1]==='generate_lead').length,2);assert.deepEqual(p.events().filter(c=>c[1]==='generate_lead').map(c=>c[2].inquiry_id),['FW-N7','FW-N8']);p.close();
});

test('Ads-only granted can restore the declared Ads pointer without reading or sending Analytics',async()=>{
 const states={ga:false,ads:true,ec:false},p=page({url:target(),states,adsStorage:true,ready:{ga4:false,ads:false}});await flush();
 assert.deepEqual(JSON.parse(p.store.get(KEY)).channels,{ads:true});
 const t=page({url:p.w.location.href,states,adsStorage:true,store:p.store,ledger:p.ledger,clock:p.clock});await flush();
 assert.equal(t.events().length,1);assert.equal(t.events()[0][1],'conversion');assert.equal(t.calls.some(c=>c[0]==='get'&&c[1]==='G-FIXTURE'),false);assert.equal(t.calls.some(c=>c[0]==='set'&&c[2]),false);p.close();t.close();
});

test('Analytics-only granted restores Analytics and never sends Ads',async()=>{
 const states={ga:true,ads:false,ec:false},p=page({url:target(),states,adsStorage:true,ready:{ga4:false,ads:false}});await flush();
 // The declared analytics service owns the pointer, including deferred Ads eligibility; actual Ads transmission stays denied.
 const t=page({url:p.w.location.href,states,adsStorage:true,store:p.store,ledger:p.ledger,clock:p.clock});await flush();
 assert.equal(t.events().length,1);assert.equal(t.events()[0][1],'generate_lead');assert.equal(t.requests.some(r=>r.channel==='ads'),false);p.close();t.close();
});

test('undeclared Ads storage is never automatically substituted for rejected Analytics storage',async()=>{
 const p=page({url:target(),states:{ga:false,ads:true,ec:false},adsStorage:false,ready:{ga4:false,ads:false}});await flush();assert.equal(p.store.size,0);
 const t=page({url:p.w.location.href,states:{ga:false,ads:true,ec:false},store:p.store,ledger:p.ledger,clock:p.clock});await flush();assert.equal(t.events().length,0);p.close();t.close();
});

test('Ads withdrawal preserves consented Analytics recovery and never attempts Ads again',async()=>{
 const p=page({url:target(),adsStorage:true,ready:{ga4:false,ads:false}});await flush();p.states.ads=false;p.change();await flush();
 const t=page({url:p.w.location.href,states:p.states,adsStorage:true,store:p.store,ledger:p.ledger,clock:p.clock});await flush();assert.equal(t.events().length,1);assert.equal(t.events()[0][1],'generate_lead');p.close();t.close();
});

test('no-consent success keeps the native redirect and makes no tracking request',async()=>{
 const p=page({states:{ga:false,ads:false,ec:false}});const res=p.success();await flush();assert.equal(res.data.redirectTo,target());assert.equal(p.requests.length,0);assert.equal(p.store.size,0);p.close();
});

test('old cached config cannot claim and does not take over the native redirect',async()=>{
 const p=page();p.w.XIConversionConfig.receipt_protocol='';p.w.XIConversionConfig.owned_endpoint='';const res=p.success();await flush();assert.equal(res.data.redirectTo,target());assert.equal(p.requests.length,0);p.close();
});

test('enhanced data denied at send is omitted despite a fixture payload containing hashes',async()=>{
 const p=page({states:{ga:true,ads:true,ec:false}});p.success();await flush();assert.equal(p.events().length,2);assert.equal(p.calls.some(c=>c[0]==='set'&&c[2]),false);assert.equal(Object.keys(p.events().find(c=>c[1]==='generate_lead')[2]).some(k=>/email|phone|name|user_data/.test(k)),false);p.callbacks();await flush();p.close();
});

test('event-call failure is unknown and is neither released nor replayed',async()=>{
 const p=page({throwEvent:true});p.success();await flush();assert.equal(p.events().length,2);assert.equal(p.navigations.length,1);assert.equal(p.requests.some(r=>r.op==='release_unsent'),false);p.change();p.tick();await flush();assert.equal(p.events().length,2);p.close();
});

test('failure before event call releases unused Ads owner',async()=>{
 const p=page({failUserSet:true});p.success();await flush();assert.equal(p.events().filter(c=>c[1]==='conversion').length,0);assert.equal(p.requests.filter(r=>r.op==='release_unsent').length,1);assert.equal(p.ledger['FW-N7'].ads.state,'pending');p.callbacks();await flush();p.close();
});

test('translated routes and legacy redirectUrl are accepted only with paired valid receipt metadata',async()=>{
 for(const route of ['/es/thankyou/','/fr/merci/']){const p=page();const res=p.success({route,data:{redirectTo:undefined,redirectUrl:target(7,'detail',route)}});await flush();assert.equal(res.data.redirectUrl,undefined);assert.equal(p.events().length,2);p.callbacks();await flush();assert.equal(p.navigations.length,1);p.close();}
});

test('failed, unverified, malformed, cross-origin, expired and protocol-mismatched responses are never intercepted',async()=>{
 const variants=[{resSuccess:false},{data:{type:'danger'}},{data:{xiTracking:'unavailable'}},{data:{xiReceiptProtocol:'other'}},{data:{xiReceiptExpiresAt:Math.floor(clockDefault/1000)-1}},{data:{xiReceiptExpiresAt:Math.floor(clockDefault/1000)+1801}},{data:{redirectTo:target().replace('https:','http:')}},{data:{redirectTo:target().replace(HOST,HOST+':444')}},{data:{redirectTo:target().replace(HOST,'evil.example.invalid')}},{data:{redirectTo:target().replace('/thank-you/','/products/')}},{data:{redirectTo:target().replace('form=quick_quote','form=other')}},{data:{redirectTo:target().replace('FW-N7','AX-N7')}},{data:{redirectTo:target().replace(token(7),'short')}}];
 for(const variant of variants){const p=page();const res=p.success(variant);await flush();assert.ok(res.data.redirectTo,'native response is retained');assert.equal(p.requests.length,0);assert.equal(p.events().length,0);p.close();}
});

test('malformed claim dispatch identity is unknown and cannot send another inquiry ID',async()=>{
 const p=page({replyOverride:{success:true,data:{dispatch:true,claim_owner:'b'.repeat(32),send_to:'G-FIXTURE',inquiry_id:'FW-N8',form_type:'quick'}}});p.success();await flush();assert.equal(p.events().length,0);assert.equal(p.store.has(KEY),false);assert.equal(p.navigations.length,1);p.change();await flush();assert.equal(p.requests.filter(r=>r.op==='claim').length,2);p.close();
});

test('the actual public Bricks callsite reads redirect fields after the synchronous success hook',async()=>{
 const callsite=fs.readFileSync(path.join(__dirname,'fixtures/bricks-form-response-callsite.js'),'utf8');
 assert.ok(callsite.includes('bricks/form/success')&&callsite.includes('refreshPage'));
 for(const granted of [true,false]){
  const p=page({holdClaim:true,states:{ga:granted,ads:granted,ec:granted}});
  p.w.__formResponse={success:true,data:{type:'success',xiTracking:'receipt_issued',redirectTo:target(),redirectTimeout:1000,refreshPage:true,xiReceiptProtocol:'owned-v1',xiReceiptExpiresAt:Math.floor(clockDefault/1000)+1800,xiReceiptPendingChannels:{ga4:true,ads:true}}};
  p.w.eval('var a,s,l,u,m="bricks/form/success",e="abc",d=new FormData(),f=window.__formResponse;'+callsite);
  await flush();
  if(granted){assert.equal(p.w.__formResponse.data.redirectTo,undefined);assert.equal(p.w.__formResponse.data.refreshPage,undefined);p.fire(1000);assert.equal(p.navigations.length,0);p.respond();await flush();p.callbacks();await flush();assert.equal(p.navigations.length,1);}
  else{assert.equal(p.w.__formResponse.data.redirectTo,target());assert.equal(p.w.__formResponse.data.refreshPage,true);assert.equal(p.requests.length,0);p.fire(1000);assert.equal(p.navigations.length,1);}
  p.close();
 }
});
