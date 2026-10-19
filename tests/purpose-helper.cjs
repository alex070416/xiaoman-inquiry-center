// Local tests: mocked Google APIs and services; no browser/network/storage.
const assert=require('assert/strict'),fs=require('fs'),path=require('path'),vm=require('vm');
const bridgeApi=require('../xiaoman-inquiry-center/purpose-consent.js'),conversion=require('../xiaoman-inquiry-center/conversions.js');
const purposes=['analytics_storage','ad_storage','ad_user_data','ad_personalization'];
const c={purpose_consent_enabled:true,gtm_id:'GTM-5DTR4ZFG',analytics_service:'ga',ads_service:'ads',user_data_service:'ec'};
const all=state=>Object.fromEntries(purposes.map(p=>[p,state]));
const denied=all('denied');
const passed=[];
function test(name,fn){fn();passed.push(name);}
function windowWith(services={ga:true,ads:true,ec:true}){return {consentApi:{consentSync:name=>({cookie:{uniqueName:name},consentGiven:true,cookieOptIn:services[name]===true})}};}
function packet(states,override={}){return {protocol:'gtm-purpose-v1',container:c.gtm_id,defaultStatesVerified:true,alwaysFireDisabledVerified:true,states,...override};}
function install(w,states){w.XIPurposeConsent=bridgeApi.create(w);assert.equal(w.XIPurposeConsent.install(c.gtm_id,'gtm-purpose-v1',()=>packet(states)),true);}
test('missing bridge denies despite all services granted',()=>assert.deepEqual(conversion.consent(windowWith(),c),denied));
test('bridge without reader denies',()=>{const w=windowWith();w.XIPurposeConsent=bridgeApi.create(w);assert.deepEqual(conversion.consent(w,c),denied);});
test('container mismatch denies',()=>{const w=windowWith();install(w,all('granted'));assert.deepEqual(conversion.consent(w,{...c,gtm_id:'GTM-OTHER'}),denied);});
test('missing configured container denies',()=>{const w=windowWith();install(w,all('granted'));assert.deepEqual(conversion.consent(w,{...c,gtm_id:''}),denied);});
test('unverified defaults reject reader',()=>{const b=bridgeApi.create({});assert.equal(b.install(c.gtm_id,'gtm-purpose-v1',()=>packet(all('granted'),{defaultStatesVerified:false})),false);});
test('unverified always-fire setting rejects reader',()=>{const b=bridgeApi.create({});assert.equal(b.install(c.gtm_id,'gtm-purpose-v1',()=>packet(all('granted'),{alwaysFireDisabledVerified:false})),false);});
test('wrong protocol rejects reader',()=>assert.equal(bridgeApi.create({}).install(c.gtm_id,'unknown',()=>packet(all('granted'))),false));
test('supplier exception denies',()=>{const b=bridgeApi.create({});assert.equal(b.install(c.gtm_id,'gtm-purpose-v1',()=>{throw Error('mock API failure');}),false);});
test('purpose missing rejects reader',()=>{const b=bridgeApi.create({});assert.equal(b.install(c.gtm_id,'gtm-purpose-v1',()=>packet({analytics_storage:'granted'})),false);});
test('unknown purpose does not become granted',()=>{const b=bridgeApi.create({});assert.equal(b.install(c.gtm_id,'gtm-purpose-v1',()=>packet({...all('denied'),ad_user_data:'unknown'})),false);});
test('analytics-only purpose is independent of Ads and EC',()=>{const w=windowWith();install(w,{...all('denied'),analytics_storage:'granted'});assert.deepEqual(conversion.consent(w,c),{...denied,analytics_storage:'granted'});});
test('service denial blocks a granted Google purpose',()=>{const w=windowWith({ga:false,ads:true,ec:false});install(w,all('granted'));assert.deepEqual(conversion.consent(w,c),{...denied,ad_storage:'granted'});});
test('purpose denial blocks granted service',()=>{const w=windowWith();install(w,all('denied'));assert.deepEqual(conversion.consent(w,c),denied);});
test('no service object blocks cookieOptIn=true',()=>{const w=windowWith();w.consentApi.consentSync=()=>({cookie:null,consentGiven:true,cookieOptIn:true});install(w,all('granted'));assert.deepEqual(conversion.consent(w,c),denied);});
test('consentGiven=false blocks opted-in value',()=>{const w=windowWith();w.consentApi.consentSync=()=>({cookie:{},consentGiven:false,cookieOptIn:true});install(w,all('granted'));assert.deepEqual(conversion.consent(w,c),denied);});
test('live withdrawal changes every read without a remembered grant',()=>{const w=windowWith();let states=all('granted');w.XIPurposeConsent=bridgeApi.create(w);w.XIPurposeConsent.install(c.gtm_id,'gtm-purpose-v1',()=>packet(states));assert.equal(conversion.consent(w,c).ad_user_data,'granted');states={...states,ad_user_data:'denied'};assert.equal(conversion.consent(w,c).ad_user_data,'denied');});
test('submission and send snapshots are independent',()=>{const w=windowWith();let states=all('granted');w.XIPurposeConsent=bridgeApi.create(w);w.XIPurposeConsent.install(c.gtm_id,'gtm-purpose-v1',()=>packet(states));const submitted=conversion.consent(w,c);states={...states,ad_user_data:'denied'};const sending=conversion.consent(w,c);assert.equal(submitted.ad_user_data,'granted');assert.equal(sending.ad_user_data,'denied');});
test('late EC grant does not change previously denied submission',()=>{const w=windowWith();let states=all('denied');w.XIPurposeConsent=bridgeApi.create(w);w.XIPurposeConsent.install(c.gtm_id,'gtm-purpose-v1',()=>packet(states));const submitted=conversion.consent(w,c);states=all('granted');assert.equal(submitted.ad_user_data,'denied');assert.equal(conversion.consent(w,c).ad_user_data,'granted');});
test('supplier failure after installation immediately denies',()=>{const w=windowWith();let fail=false;w.XIPurposeConsent=bridgeApi.create(w);w.XIPurposeConsent.install(c.gtm_id,'gtm-purpose-v1',()=>{if(fail)throw Error('mock');return packet(all('granted'));});fail=true;assert.deepEqual(conversion.consent(w,c),denied);});
test('invalid replacement clears prior live grant',()=>{const w=windowWith();install(w,all('granted'));assert.equal(w.XIPurposeConsent.install('bad','gtm-purpose-v1',()=>packet(all('granted'))),false);assert.deepEqual(conversion.consent(w,c),denied);});
test('reset clears prior reader',()=>{const w=windowWith();install(w,all('granted'));w.XIPurposeConsent.reset();assert.deepEqual(conversion.consent(w,c),denied);});
test('adapter creates only an identifier-free internal handshake',()=>{const w={dataLayer:[]};bridgeApi.start(w);assert.deepEqual(w.dataLayer,[{event:'xi_purpose_bridge_ready'}]);});
test('adapter does not replace an existing unknown global',()=>{const existing={unknown:true},w={XIPurposeConsent:existing};assert.equal(bridgeApi.start(w),null);assert.equal(w.XIPurposeConsent,existing);});

const templateSource=fs.readFileSync(path.join(__dirname,'../required-gtm-templates/gtm-purpose-reader.template.js'),'utf8');
function templateHarness(checked=true){
 const state=all('denied'),listeners={},store={},calls=[],requested=[];
 const w=windowWith();let success=0,failure=0;
 const apis={
  addConsentListener:(p,fn)=>{(listeners[p]||(listeners[p]=[])).push(fn);},
  isConsentGranted:p=>state[p]==='granted',
  getContainerVersion:()=>({containerId:c.gtm_id}),
  callInWindow:(key,...args)=>{calls.push(key);const fn=key.split('.').reduce((o,k)=>o&&o[k],w);return typeof fn==='function'?fn(...args):undefined;},
  templateStorage:{getItem:k=>store[k],setItem:(k,v)=>store[k]=v}
 };
 function run(flags={}){vm.runInNewContext('(function(){'+templateSource+'\n})()',{require:k=>{requested.push(k);if(!(k in apis))throw Error('Unexpected permission API '+k);return apis[k];},data:{defaultStatesVerified:checked,alwaysFireDisabledVerified:checked,gtmOnSuccess:()=>success++,gtmOnFailure:()=>failure++,...flags}});}
 return {w,state,listeners,calls,requested,run,counts:()=>({success,failure})};
}
test('GTM default guard makes no state reads or installation',()=>{const h=templateHarness(false);h.run();assert.equal(h.calls.length,0);assert.deepEqual(h.counts(),{success:0,failure:1});});
test('GTM always-fire guard makes no installation',()=>{const h=templateHarness();h.run({alwaysFireDisabledVerified:false});assert.equal(h.calls.length,0);});
test('missing receiver later handshake installs without duplicate listeners',()=>{const h=templateHarness();h.run();assert.equal(h.counts().failure,1);h.w.XIPurposeConsent=bridgeApi.create(h.w);h.run();assert.equal(h.counts().success,1);purposes.forEach(p=>assert.equal(h.listeners[p].length,1));});
test('GTM installed reader reads current purposes on demand',()=>{const h=templateHarness();h.w.XIPurposeConsent=bridgeApi.create(h.w);h.run();assert.deepEqual(conversion.consent(h.w,c),denied);h.state.analytics_storage='granted';assert.equal(conversion.consent(h.w,c).analytics_storage,'granted');h.state.analytics_storage='denied';assert.equal(conversion.consent(h.w,c).analytics_storage,'denied');});
test('GTM purpose listeners call only local bridge notification',()=>{const h=templateHarness();h.w.XIPurposeConsent=bridgeApi.create(h.w);h.run();const before=h.calls.length;h.listeners.ad_user_data[0]('ad_user_data',false);assert.deepEqual(h.calls.slice(before),['XIPurposeConsent.changed']);});
test('GTM template has no network/consent write/cookie/storage APIs',()=>{const h=templateHarness();h.w.XIPurposeConsent=bridgeApi.create(h.w);h.run();assert.deepEqual([...new Set(h.requested)].sort(),['addConsentListener','callInWindow','getContainerVersion','isConsentGranted','templateStorage'].sort());});
test('Ad personalization remains denied under all grants',()=>{const w=windowWith();install(w,all('granted'));assert.equal(conversion.consent(w,c).ad_personalization,'denied');});

const report={date:'2026-10-15',tests:passed.length,passed:passed.length,failed:0,names:passed,scope:'local mocks only; not native GTM/browser/Google delivery acceptance',productionWrites:0,realSubmissions:0,externalRequests:0};
// Results are emitted to stdout by the integration runner.
console.log(JSON.stringify({passed:passed.length,failed:0,productionWrites:0}));
