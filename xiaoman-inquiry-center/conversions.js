/* Paired PHP required. Unknown outcomes never replay; callbacks are local tag processing evidence. */
(function(root,factory){'use strict';var api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else api.start(root);})(typeof window!=='undefined'?window:null,function(){
 'use strict';
 function granted(w,name){try{var v=name&&w.consentApi.consentSync(name);return !!(v&&v.cookie&&v.consentGiven&&v.cookieOptIn);}catch(_){return false;}}
 function purposeSnapshot(w,c){try{var bridge=w.XIPurposeConsent;return bridge&&bridge.protocol==='gtm-purpose-v1'&&typeof bridge.read==='function'?bridge.read(c.gtm_id):null;}catch(_){return null;}}
 function consent(w,c){var p=c.purpose_consent_enabled===true?(purposeSnapshot(w,c)||{}):{analytics_storage:'granted',ad_storage:'granted',ad_user_data:'granted'};return {analytics_storage:p.analytics_storage==='granted'&&granted(w,c.analytics_service)?'granted':'denied',ad_storage:p.ad_storage==='granted'&&granted(w,c.ads_service)?'granted':'denied',ad_user_data:p.ad_user_data==='granted'&&granted(w,c.user_data_service)?'granted':'denied',ad_personalization:'denied'};}
 function contact(href){try{var u=new URL(href);if(u.protocol==='mailto:')return 'email_click';if(u.protocol==='https:'&&((u.hostname==='wa.me'&&/^\/[0-9]+\/?$/.test(u.pathname))||(u.hostname==='api.whatsapp.com'&&/^\/send\/?$/.test(u.pathname))))return 'whatsapp_click';}catch(_){}return '';}
 function start(w){
  if(!w||!w.XIConversionConfig)return;var c=w.XIConversionConfig;
  if(w.location.hostname!==c.site_host||/[?&](bricks|brickspreview)=/.test(w.location.search))return;
  if(w.__XI_CONVERSIONS_INITIALIZED__)return;w.__XI_CONVERSIONS_INITIALIZED__=true;
  var d=w.document,closed=false,contexts=Object.create(null),formLocks=[],timer=null;
  var recoveryKey='xi_conversion_pending_v1',pointerPresent=false,cleanupTimer=null,cleanupExpiry=0;
  var CLAIM_TIMEOUT=12000,RELEASE_TIMEOUT=4000,TAG_TIMEOUT=3000,FORM_TAG_TIMEOUT=600,EVENT_TIMEOUT=2000;
  function storageNames(channel){
   // New Ads mapping must explicitly identify a published service declaring this storage use.
   // The existing analytics declaration remains the backwards-compatible owner for both channels.
   var names=[c.pending_storage_service];if(channel==='ads')names.push(c.pending_ads_storage_service);
   return names.filter(function(name,index){return typeof name==='string'&&name&&names.indexOf(name)===index;});
  }
  function serviceState(name){try{var v=w.consentApi.consentSync(name);if(!v||!v.cookie||!v.consentGiven)return 'pending';if(!v.cookieOptIn)return 'denied';if(c.purpose_consent_enabled!==true)return 'granted';var purpose=name===c.pending_storage_service?'analytics_storage':name===c.pending_ads_storage_service?'ad_storage':'';if(!purpose)return 'disabled';var p=purposeSnapshot(w,c);return !p?'pending':p[purpose]==='granted'?'granted':'denied';}catch(_){return 'pending';}}
  function storageState(channel){var names=storageNames(channel);if(!names.length)return 'disabled';var states=names.map(serviceState);return states.indexOf('granted')!==-1?'granted':states.indexOf('pending')!==-1?'pending':'denied';}
  function canReadStorage(){return storageState('ga4')==='granted'||storageState('ads')==='granted';}
  function validNumber(number){return c.number_prefix&&number.indexOf(c.number_prefix)===0&&/^[1-9][0-9]*$/.test(number.slice(c.number_prefix.length));}
  function validExpiry(value){return Number.isInteger(value)&&value*1000>Date.now()&&value*1000<=Date.now()+30*60000;}
  function validTarget(url){
   try{var u=new URL(url,w.location.href),q=u.searchParams;
    if(u.origin!==w.location.origin||u.hostname!==c.site_host||u.protocol!=='https:'||!Array.isArray(c.thank_you_routes)||c.thank_you_routes.indexOf(u.pathname.replace(/\/+$/,'')+'/')===-1||q.get('form')!=='quick_quote'||!validNumber(q.get('inquiry_id')||'')||!/^[a-f0-9]{64}$/.test(q.get('xi_receipt')||''))return null;
    return {url:u.href,number:q.get('inquiry_id'),ticket:q.get('xi_receipt')};
   }catch(_){return null;}
  }
  function clearRecovery(){if(cleanupTimer)w.clearTimeout(cleanupTimer);cleanupTimer=null;cleanupExpiry=0;try{w.sessionStorage.removeItem(recoveryKey);pointerPresent=false;return true;}catch(_){return !pointerPresent;}}
  function scheduleCleanup(expires){if(cleanupExpiry===expires)return;if(cleanupTimer)w.clearTimeout(cleanupTimer);cleanupExpiry=expires;cleanupTimer=w.setTimeout(clearRecovery,Math.max(0,expires*1000-Date.now()));}
  function readRecovery(){
   if(!canReadStorage())return null;
   try{var raw=w.sessionStorage.getItem(recoveryKey);pointerPresent=!!raw;var saved=raw&&raw.length<2000?JSON.parse(raw):null;
    if(!saved||saved.host!==c.site_host||!validNumber(saved.inquiry_id||'')||!/^[a-f0-9]{64}$/.test(saved.receipt||'')||!validExpiry(saved.expires_at)||!saved.channels){clearRecovery();return null;}
    return saved;
   }catch(_){clearRecovery();return null;}
  }
  function cleanStoredRecovery(){
   var ga=storageState('ga4'),ads=storageState('ads');
   if(!canReadStorage()){if(ga!=='pending'&&ads!=='pending')clearRecovery();return;}
   var saved=readRecovery();if(!saved)return;
   ['ga4','ads'].forEach(function(channel){if(storageState(channel)==='denied'||storageState(channel)==='disabled')delete saved.channels[channel];});
   if(!saved.channels.ga4&&!saved.channels.ads){clearRecovery();return;}
   try{w.sessionStorage.setItem(recoveryKey,JSON.stringify(saved));}catch(_){}scheduleCleanup(saved.expires_at);
  }
  function saveRecovery(ctx,exclude){
   if(!canReadStorage()){var ga=storageState('ga4'),ads=storageState('ads');return ga==='pending'||ads==='pending'?!pointerPresent:clearRecovery();}
   if(!validExpiry(ctx.expires))return clearRecovery();
   try{
    var previous=w.sessionStorage.getItem(recoveryKey),saved=previous&&previous.length<2000?JSON.parse(previous):null;if(previous)pointerPresent=true;
    var same=saved&&saved.host===c.site_host&&saved.inquiry_id===ctx.number&&saved.receipt===ctx.ticket&&saved.expires_at===ctx.expires;
    var channels={};['ga4','ads'].forEach(function(channel){var job=ctx.jobs[channel],state=storageState(channel);if(channel!==exclude&&job.recoverable&&!job.done&&(state==='granted'||state==='pending'&&same&&saved.channels&&saved.channels[channel]===true))channels[channel]=true;});
    if(!channels.ga4&&!channels.ads)return clearRecovery();
    var value=JSON.stringify({host:c.site_host,inquiry_id:ctx.number,receipt:ctx.ticket,expires_at:ctx.expires,channels:channels});
    if(previous!==value){pointerPresent=true;w.sessionStorage.setItem(recoveryKey,value);}if(w.sessionStorage.getItem(recoveryKey)!==value)return false;pointerPresent=true;scheduleCleanup(ctx.expires);return true;
   }catch(_){return !pointerPresent;}
  }
  function forgetRecovery(ctx,channel){if(!saveRecovery(ctx,channel))return false;ctx.jobs[channel].recoverable=false;return true;}
  function allowed(channel){return !closed&&typeof w.gtag==='function'&&consent(w,c)[channel==='ga4'?'analytics_storage':'ad_storage']==='granted';}
  function request(ctx,data,keepalive,timeout){
   var controller=timeout&&typeof w.AbortController==='function'?new w.AbortController():null;
   var packet=Object.assign({receipt:ctx.ticket,inquiry_id:ctx.number},data),options={method:'POST',credentials:'same-origin',cache:'no-store',keepalive:!!keepalive,headers:{'Content-Type':'application/json'},body:JSON.stringify(packet)};
   if(controller)options.signal=controller.signal;
   // The waiting deadline also works when AbortController is absent or the response body stalls.
   // Timeout is UNKNOWN: it never proves that the server did not commit this operation.
   return new Promise(function(resolve,reject){
    var settled=false,expiry=timeout?w.setTimeout(function(){
     if(settled)return;settled=true;if(controller)try{controller.abort();}catch(_){}
     reject(new Error('XI operation response timeout'));
    },timeout):null;
    function finish(ok,value){if(settled)return;settled=true;if(expiry)w.clearTimeout(expiry);(ok?resolve:reject)(value);}
    try{Promise.resolve(w.fetch(c.owned_endpoint,options)).then(function(r){return r.json();}).then(function(result){finish(true,result);},function(err){finish(false,err);});}
    catch(err){finish(false,err);}
   });
  }
  function tagReady(channel,timeout){return new Promise(function(resolve){
   if(!allowed(channel)){resolve(false);return;}var settled=false,expiry=w.setTimeout(function(){finish(false);},timeout||TAG_TIMEOUT);
   function finish(ok){if(settled)return;settled=true;w.clearTimeout(expiry);resolve(ok&&allowed(channel));}
   try{w.gtag('get',channel==='ga4'?c.ga4_id:c.ads_id,channel==='ga4'?'client_id':'gclid',function(value){finish(channel==='ads'||(typeof value==='string'&&value!==''));});}catch(_){finish(false);}
  });}
  function releaseUnused(ctx,channel){
   var job=ctx.jobs[channel],r=job.unused;if(!r)return Promise.resolve();
   return request(ctx,{op:'release_unsent',channel:channel,claim_owner:r.claim_owner},true,RELEASE_TIMEOUT).then(function(result){
    if(result&&result.success===true&&result.data&&result.data.released===true){job.unused=null;job.phase='pending';job.recoverable=true;saveRecovery(ctx);}
    else if(result&&result.success===false&&result.data&&result.data.code==='receipt'){job.done=true;job.phase='expired';}
    else job.phase='release_response_unknown';
    // Only a repeat of this known-unused owner release is safe after an unknown response.
    // It cannot authorize a Google send until the server explicitly acknowledges release.
   }).catch(function(){job.phase='release_response_unknown';});
  }
  function transmit(ctx,channel,r){
   var job=ctx.jobs[channel];if(!allowed(channel)||ctx.expires&&!validExpiry(ctx.expires))return null;
   var finish,localExpiry,wait=new Promise(function(resolve){finish=resolve;});
   // This timer only bounds waiting AFTER the Google event is attempted. It never ends an in-flight claim.
   localExpiry=w.setTimeout(function(){finish();},EVENT_TIMEOUT+250);
   var params={send_to:r.send_to,inquiry_id:r.inquiry_id,form_type:r.form_type,test_lead:r.is_test,event_timeout:EVENT_TIMEOUT,event_callback:function(){
    request(ctx,{op:'ack',channel:channel,claim_owner:r.claim_owner},true).catch(function(){});
    w.clearTimeout(localExpiry);finish();
   }},userSet=false;
   try{
    if(channel==='ads'){params.transaction_id=r.inquiry_id;if(r.user_data&&consent(w,c).ad_user_data==='granted'){w.gtag('set','user_data',r.user_data);userSet=true;}}
    job.done=true;job.unused=null;job.phase='event_attempted';w.gtag('event',channel==='ads'?'conversion':'generate_lead',params);
   }catch(_){job.phase=job.done?'event_outcome_unknown':'not_attempted';w.clearTimeout(localExpiry);finish();}
   finally{if(userSet)try{w.gtag('set','user_data',null);}catch(_){} }
   if(!job.done){w.clearTimeout(localExpiry);finish();return null;}return wait;
  }
  function dispatch(ctx,channel,readinessTimeout){
   var job=ctx.jobs[channel];
   if(c.receipt_protocol!=='owned-v1'||!c.owned_endpoint||!validNumber(ctx.number)||!/^[a-f0-9]{64}$/.test(ctx.ticket)||job.done||ctx.expires&&!validExpiry(ctx.expires))return Promise.resolve();
   if(job.busy)return job.promise;
   if(job.unused){job.busy=true;job.promise=releaseUnused(ctx,channel).finally(function(){job.busy=false;});return job.promise;}
   if(!allowed(channel))return Promise.resolve();job.busy=true;
   job.promise=tagReady(channel,readinessTimeout).then(function(ready){
    if(!ready||!allowed(channel)||ctx.expires&&!validExpiry(ctx.expires)||!forgetRecovery(ctx,channel))return;job.phase='claim_inflight';
    // A claim fetch failure or abort is UNKNOWN: the server may already have committed it.
    return request(ctx,{op:'claim',channel:channel,consent:consent(w,c),protocol:c.receipt_protocol},false,CLAIM_TIMEOUT).then(function(result){
     if(result&&result.success===true&&result.data){var r=result.data;
      if(r.dispatch!==true){job.done=true;job.phase='already_claimed_unknown';return;}
      if(!/^[a-f0-9]{32}$/.test(r.claim_owner||'')||r.inquiry_id!==ctx.number||r.send_to!==(channel==='ga4'?c.ga4_id:c.ads_id+'/'+c.ads_label)||['quick','detail'].indexOf(r.form_type)===-1){job.done=true;job.phase='protocol_mismatch_unknown';return;}
      job.unused=r;job.phase='claimed_unused';var waiting=transmit(ctx,channel,r);if(waiting)return waiting;return releaseUnused(ctx,channel);
     }else if(result&&result.success===false&&result.data&&Object.keys(result.data).length===1&&['consent','config','protocol'].indexOf(result.data.code)!==-1){job.recoverable=true;job.phase='pending_rejected';saveRecovery(ctx);}
     else if(result&&result.success===false&&result.data&&result.data.code==='receipt'){job.done=true;job.phase='expired';}
     else{job.done=true;job.phase='claim_response_unknown';}
    }).catch(function(){job.done=true;job.phase='claim_response_unknown';});
   }).finally(function(){job.busy=false;});return job.promise;
  }
  function createContext(info){
   var existing=contexts[info.number];if(existing)return existing.ticket===info.ticket?existing:null;
   var ctx={number:info.number,ticket:info.ticket,expires:info.expires||0,jobs:{ga4:{busy:false,done:false,unused:null,recoverable:true},ads:{busy:false,done:false,unused:null,recoverable:true}}};
   if(info.channels)['ga4','ads'].forEach(function(channel){if(info.channels[channel]!==true){ctx.jobs[channel].done=true;ctx.jobs[channel].recoverable=false;}});
   contexts[info.number]=ctx;saveRecovery(ctx);return ctx;
  }
  function currentContext(){
   var p=new URLSearchParams(w.location.search),number=p.get('inquiry_id')||'',ticket=p.get('xi_receipt')||'';
   if(!Array.isArray(c.thank_you_routes)||c.thank_you_routes.indexOf(w.location.pathname.replace(/\/+$/,'')+'/')===-1||p.get('form')!=='quick_quote'||!validNumber(number)||c.receipt_protocol!=='owned-v1')return null;
   if(contexts[number])return contexts[number];
   if(/^[a-f0-9]{64}$/.test(ticket))return createContext({number:number,ticket:ticket,expires:c.receipt_expires_at,channels:c.receipt_pending_channels});
   var saved=readRecovery();if(!saved)return null;if(saved.inquiry_id!==number){clearRecovery();return null;}
   ['ga4','ads'].forEach(function(channel){var state=storageState(channel);if(state==='denied'||state==='disabled')delete saved.channels[channel];});
   return saved.channels.ga4||saved.channels.ads?createContext({number:number,ticket:saved.receipt,expires:saved.expires_at,channels:saved.channels}):null;
  }
  function reconcile(){
   cleanStoredRecovery();currentContext();Object.keys(contexts).forEach(function(number){var ctx=contexts[number];
    if(ctx.expires&&!validExpiry(ctx.expires)){clearRecovery();ctx.jobs.ga4.done=true;ctx.jobs.ads.done=true;return;}
    saveRecovery(ctx);dispatch(ctx,'ga4');dispatch(ctx,'ads');
   });
  }
  currentContext();
  // The credential is retained only in memory and consented pending storage.
  var initialParams=new URLSearchParams(w.location.search);if(initialParams.has('xi_receipt')){initialParams.delete('xi_receipt');w.history.replaceState(w.history.state,'',w.location.pathname+(initialParams.toString()?'?'+initialParams:'')+w.location.hash);}
  function scrubLink(a){try{var u=new URL(a.href,w.location.href);if(u.searchParams.has('xi_receipt')){u.searchParams.delete('xi_receipt');a.href=u.href;}}catch(_){} }
  function scrubLinks(){d.querySelectorAll('a[href*="xi_receipt="]').forEach(scrubLink);}scrubLinks();d.addEventListener('DOMContentLoaded',scrubLinks);
  function formSend(e){
   var res=e&&e.detail&&e.detail.res,data=res&&res.success===true?res.data:null;
   if(!data||data.type!=='success'||data.xiTracking!=='receipt_issued'||c.receipt_protocol!=='owned-v1'||!c.owned_endpoint||data.xiReceiptProtocol!==c.receipt_protocol||!validExpiry(data.xiReceiptExpiresAt))return;
   var target=validTarget(typeof data.redirectTo==='string'?data.redirectTo:data.redirectUrl);if(!target)return;
   var channels=['ga4','ads'].filter(allowed);if(!channels.length)return;
   var ctx=createContext({number:target.number,ticket:target.ticket,expires:data.xiReceiptExpiresAt,channels:data.xiReceiptPendingChannels});if(!ctx)return;
   // Bricks synchronously reads these fields after this event. Own navigation only for this verified response.
   delete data.redirectTo;delete data.redirectUrl;delete data.redirectTimeout;delete data.refreshPage;
   if(ctx.formPromise){Promise.resolve().then(function(){formLocks.forEach(function(item){if(item.ctx===ctx&&!ctx.navigated){item.buttons.forEach(function(button){button.disabled=true;});if(item.form)item.form.dataset.submitting='true';}});});return;}
   var form=e.detail.elementId&&d.querySelector('form[data-element-id="'+String(e.detail.elementId).replace(/[^a-zA-Z0-9_-]/g,'')+'"]'),buttons=form?form.querySelectorAll('button[type="submit"],input[type="submit"]'):[];
   function lock(){if(ctx.navigated)return;buttons.forEach(function(button){button.disabled=true;});if(form)form.dataset.submitting='true';}
   Promise.resolve().then(lock);formLocks.push({ctx:ctx,form:form,buttons:buttons});
   function go(){if(ctx.navigated||closed)return;ctx.navigated=true;w.location.href=target.url;}
   // No independent redirect deadline: wait for readiness, each claim response/explicit abort, then event processing wait.
   ctx.resume=function(){return Promise.all(channels.map(function(channel){return dispatch(ctx,channel,FORM_TAG_TIMEOUT);})).then(go,go);};
   ctx.formPromise=ctx.resume();
  }
  w.XIConversions={consents:function(){return consent(w,c);},status:function(){return Object.keys(contexts).map(function(number){var ctx=contexts[number];return {inquiry_id:number,ga4:ctx.jobs.ga4.phase||'pending',ads:ctx.jobs.ads.phase||'pending'};});}};
  d.addEventListener('click',function(e){var a=e.target&&e.target.closest&&e.target.closest('a[href]');if(!a)return;scrubLink(a);var name=contact(a.href);if(!name||typeof w.gtag!=='function')return;var state=consent(w,c);if(state.analytics_storage==='granted'&&/^G-[A-Z0-9]+$/.test(c.ga4_id))w.gtag('event',name,{send_to:c.ga4_id,contact_type:name==='email_click'?'email':'whatsapp'});var label=name==='email_click'?c.email_label:c.whatsapp_label;if(state.ad_storage==='granted'&&/^AW-[0-9]+$/.test(c.ads_id)&&/^[A-Za-z0-9_-]+$/.test(label||''))w.gtag('event','conversion',{send_to:c.ads_id+'/'+label});},true);
  w.addEventListener('pagehide',function(){closed=true;});w.addEventListener('pageshow',function(){closed=false;formLocks.forEach(function(item){if(item.ctx.navigated){item.buttons.forEach(function(button){button.disabled=false;});if(item.form)item.form.dataset.submitting='false';}else if(item.ctx.resume)item.ctx.resume();});reconcile();});
  d.addEventListener('DOMContentLoaded',reconcile);d.addEventListener('rcb-consent-changed',reconcile);d.addEventListener('xi-purpose-consent-changed',reconcile);
  // Real RCB 5.x service changes can leave shared Google purposes unchanged.
  // Coalesce its three native events and re-read after vendor state updates.
  var consentReconcileScheduled=false;function scheduleConsentReconcile(){if(consentReconcileScheduled)return;consentReconcileScheduled=true;w.setTimeout(function(){consentReconcileScheduled=false;reconcile();},0);}
  ['RCB/Apply/Interactive','RCB/OptIn','RCB/OptOut'].forEach(function(name){d.addEventListener(name,scheduleConsentReconcile);});d.addEventListener('bricks/form/success',formSend);reconcile();
  var p=new URLSearchParams(w.location.search),thank=Array.isArray(c.thank_you_routes)&&c.thank_you_routes.indexOf(w.location.pathname.replace(/\/+$/,'')+'/')!==-1&&p.get('form')==='quick_quote';
  if(thank){timer=w.setInterval(reconcile,1000);w.setTimeout(function(){w.clearInterval(timer);timer=null;closed=true;clearRecovery();},30*60000);}
 }
 return {start:start,consent:consent,contact:contact};
});
