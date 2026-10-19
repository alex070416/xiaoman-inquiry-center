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

 w.XIConversionConfig.purpose_consent_enabled=true;w.XIConversionConfig.gtm_id='GTM-HJF1X';
 w.XIPurposeConsent={protocol:'gtm-purpose-v1',read:container=>container==='GTM-HJF1X'?{analytics_storage:'granted',ad_storage:'granted',ad_user_data:'granted',ad_personalization:'denied'}:null};
 w.eval(source);
 function success({id=7,kind='quick',route='/thank-you/',data={},resSuccess=true}={}){
  const res={success:resSuccess,data:{type:'success',xiTracking:'receipt_issued',redirectTo:target(id,kind,route),inquiryNumber:'FW-N'+id,xiReceiptProtocol:'owned-v1',xiReceiptExpiresAt:expires,xiReceiptPendingChannels:{ga4:true,ads:true},...data}};
  formKinds[id]=kind;
  w.document.dispatchEvent(new w.CustomEvent('bricks/form/success',{detail:{elementId:'abc',res}}));
  return res;
 }
 return {w,clock,states,ready,store,ledger,calls,requests,timers,navigations,success,events:()=>calls.filter(c=>c[0]==='event'),callbacks:()=>calls.filter(c=>c[0]==='event').forEach(c=>c[2].event_callback()),fire:ms=>timers.filter(t=>t.ms===ms&&!t.cancelled&&!t.fired).forEach(t=>{t.fired=true;t.fn();}),respond:()=>{holdClaim=false;held.splice(0).forEach(fn=>fn());},respondRelease:()=>{holdRelease=false;heldReleases.splice(0).forEach(fn=>fn());},tick:()=>ticks.forEach(fn=>fn()),change:()=>w.document.dispatchEvent(new w.Event('rcb-consent-changed')),close:()=>w.close()};
}

module.exports=page;
