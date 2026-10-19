const {test}=require('node:test'),assert=require('node:assert/strict'),fs=require('fs'),path=require('path'),vm=require('vm');
const source=fs.readFileSync(path.join(__dirname,'../required-gtm-templates/gtm-service-ready.template.js'),'utf8');
function harness({service=true,purpose=false,alwaysVerified=true,defaultsVerified=true}={}){
 const listeners={},store={},tasks=[],events=[];let baseLoaded=false,baseLoads=0,failures=0;const state={service,purpose};
 const apis={addConsentListener:(name,fn)=>(listeners[name]||(listeners[name]=[])).push(fn),isConsentGranted:name=>name==='ads'?state.service:state.purpose,callInWindow:()=>({cookie:{},consentGiven:true,cookieOptIn:state.service}),createQueue:()=>event=>events.push(event),callLater:fn=>tasks.push(fn),templateStorage:{getItem:key=>store[key],setItem:(key,value)=>store[key]=value}};
 function run(){vm.runInNewContext('(function(){'+source+'\n})()',{require:name=>apis[name],data:{serviceConsent:'ads',purposeConsent:'ad_storage',readyEvent:'xi_ads_base_ready',defaultStatesVerified:defaultsVerified,alwaysFireDisabledVerified:alwaysVerified,gtmOnSuccess(){},gtmOnFailure(){failures++;}}});}
 function flushTasks(){tasks.splice(0).forEach(fn=>fn());}
 function consumeEvents(){events.splice(0).forEach(()=>{if(state.service&&state.purpose&&!baseLoaded){baseLoaded=true;baseLoads++;}});}
 function change(which,value){state[which]=value;(listeners[which==='service'?'ads':'ad_storage']||[]).forEach(fn=>fn());}
 return {run,flushTasks,consumeEvents,change,events,tasks,listeners,stats:()=>({baseLoads,failures})};
}
test('unverified actual defaults or always-fire setting prevents helper listener/queue setup',()=>{for(const flags of [{defaultsVerified:false},{alwaysVerified:false}]){const h=harness(flags);h.run();h.flushTasks();assert.equal(h.events.length,0);assert.equal(h.tasks.length,0);assert.equal(h.stats().failures,1);}});
test('service plus purpose denied never emits base initialization',()=>{const h=harness({service:false});h.run();h.flushTasks();assert.equal(h.events.length,0);});
test('denied then granted initializes actual base once under native ONCE_PER_LOAD model',()=>{const h=harness();h.run();h.flushTasks();h.change('purpose',true);h.flushTasks();assert.equal(h.events.length,1);h.consumeEvents();h.change('purpose',false);h.flushTasks();h.change('purpose',true);h.flushTasks();h.consumeEvents();assert.equal(h.stats().baseLoads,1);});
test('withdrawal after ready queued blocks base and regrant requeues without false initialized flag',()=>{const h=harness({purpose:true});h.run();h.flushTasks();assert.equal(h.events.length,1);h.change('purpose',false);h.consumeEvents();assert.equal(h.stats().baseLoads,0);h.flushTasks();h.change('purpose',true);h.flushTasks();assert.equal(h.events.length,1);h.consumeEvents();assert.equal(h.stats().baseLoads,1);});
test('repeated helper tag executions do not add listeners or immediate queues twice',()=>{const h=harness({purpose:true});h.run();h.run();assert.equal(h.listeners.ads.length,1);assert.equal(h.listeners.ad_storage.length,1);assert.equal(h.tasks.length,1);h.flushTasks();assert.equal(h.events.length,1);});
