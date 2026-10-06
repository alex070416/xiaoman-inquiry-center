/* Owned receipt dispatch: only definitely unclaimed channels may recover; unknown outcomes never replay. */
(function(root,factory){'use strict';var api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else api.start(root);})(typeof window!=='undefined'?window:null,function(){
 'use strict';
 function granted(w,name){try{var v=name&&w.consentApi.consentSync(name);return !!(v&&v.cookie&&v.consentGiven&&v.cookieOptIn);}catch(_){return false;}}
 function consent(w,c){return {analytics_storage:granted(w,c.analytics_service)?'granted':'denied',ad_storage:granted(w,c.ads_service)?'granted':'denied',ad_user_data:granted(w,c.user_data_service)?'granted':'denied',ad_personalization:'denied'};}
 function contact(href){try{var u=new URL(href);if(u.protocol==='mailto:')return 'email_click';if(u.protocol==='https:'&&((u.hostname==='wa.me'&&/^\/[0-9]+\/?$/.test(u.pathname))||(u.hostname==='api.whatsapp.com'&&/^\/send\/?$/.test(u.pathname))))return 'whatsapp_click';}catch(_){}return '';}
 function start(w){
  if(!w||!w.XIConversionConfig)return;var c=w.XIConversionConfig;
  if(w.location.hostname!==c.site_host||/[?&](bricks|brickspreview)=/.test(w.location.search))return;
  // Separate from the public consent API: repeated asset/start calls must not bind contact listeners again.
  if(w.__XI_CONVERSIONS_INITIALIZED__)return;w.__XI_CONVERSIONS_INITIALIZED__=true;
  var d=w.document,p=new URLSearchParams(w.location.search),number=p.get('inquiry_id')||'',ticket=p.get('xi_receipt')||'',closed=false,timer=null;
  var jobs={ga4:{busy:false,done:false,unused:null,recoverable:true},ads:{busy:false,done:false,unused:null,recoverable:true}};
  var thank=Array.isArray(c.thank_you_routes)&&c.thank_you_routes.indexOf(w.location.pathname.replace(/\/+$/,'')+'/')!==-1&&p.get('form')==='quick_quote';
  // One same-tab pointer, not a history queue. Storage belongs to the declared analytics service.
  var recoveryKey='xi_conversion_pending_v1',pointerPresent=false,recovery=null,cleanupTimer=null,cleanupExpiry=0;
  function storageState(){
   if(!c.pending_storage_service)return 'disabled';
   try{var value=w.consentApi.consentSync(c.pending_storage_service);if(!value||!value.cookie||!value.consentGiven)return 'pending';return value.cookieOptIn?'granted':'denied';}catch(_){return 'pending';}
  }
  function storageAllowed(){return storageState()==='granted';}
  function validNumber(){return c.number_prefix&&number.indexOf(c.number_prefix)===0&&/^[1-9][0-9]*$/.test(number.slice(c.number_prefix.length));}
  function validExpiry(value){return Number.isInteger(value)&&value*1000>Date.now()&&value*1000<=Date.now()+30*60000;}
  function clearRecovery(){
   if(cleanupTimer)w.clearTimeout(cleanupTimer);cleanupTimer=null;cleanupExpiry=0;
   try{w.sessionStorage.removeItem(recoveryKey);pointerPresent=false;return true;}catch(_){return !pointerPresent;}
  }
  function scheduleCleanup(expires){
   if(cleanupExpiry===expires)return;if(cleanupTimer)w.clearTimeout(cleanupTimer);cleanupExpiry=expires;
   cleanupTimer=w.setTimeout(clearRecovery,Math.max(0,expires*1000-Date.now()));
  }
  function cleanStoredRecovery(){
   // Consent withdrawal and original expiry apply on ordinary pages too. This never restores or claims.
   var state=storageState();if(state==='pending')return;if(state!=='granted'){clearRecovery();return;}
   try{
    var raw=w.sessionStorage.getItem(recoveryKey);if(!raw){clearRecovery();return;}pointerPresent=true;
    var saved=raw.length<2000?JSON.parse(raw):null;
    if(!saved||saved.host!==c.site_host||!validExpiry(saved.expires_at)){clearRecovery();return;}
    scheduleCleanup(saved.expires_at);
   }catch(_){clearRecovery();}
  }
  function pendingMask(exclude){var mask={};['ga4','ads'].forEach(function(channel){if(channel!==exclude&&jobs[channel].recoverable&&!jobs[channel].done)mask[channel]=true;});return mask;}
  function saveRecovery(exclude){
   var state=storageState();if(state==='pending')return !pointerPresent;if(state!=='granted')return clearRecovery();
   if(!recovery||!validExpiry(recovery.expires_at))return clearRecovery();
   var channels=pendingMask(exclude);if(!channels.ga4&&!channels.ads)return clearRecovery();
   var value=JSON.stringify({host:c.site_host,inquiry_id:number,receipt:ticket,expires_at:recovery.expires_at,channels:channels});
   try{
    var previous=w.sessionStorage.getItem(recoveryKey);if(previous)pointerPresent=true;
    // A write may succeed before its acknowledgement/readback fails. Treat that outcome as persisted.
    if(previous!==value){pointerPresent=true;w.sessionStorage.setItem(recoveryKey,value);}
    if(w.sessionStorage.getItem(recoveryKey)!==value)return false;
    pointerPresent=true;scheduleCleanup(recovery.expires_at);return true;
   }catch(_){return !pointerPresent;}
  }
  function forgetRecovery(channel){
   // Persist the no-replay boundary BEFORE any claim HTTP request can be sent.
   if(!saveRecovery(channel))return false;jobs[channel].recoverable=false;return true;
  }
  function initRecovery(){
   if(!thank||!validNumber()||c.receipt_protocol!=='owned-v1')return;
   if(/^[a-f0-9]{64}$/.test(ticket)){
    if(c.receipt_pending_channels&&typeof c.receipt_pending_channels==='object')['ga4','ads'].forEach(function(channel){
     if(c.receipt_pending_channels[channel]!==true){jobs[channel].done=true;jobs[channel].recoverable=false;}
    });
    if(validExpiry(c.receipt_expires_at))recovery={expires_at:c.receipt_expires_at};
    saveRecovery();return;
   }
   if(!storageAllowed()){if(storageState()!=='pending')clearRecovery();return;}
   try{
    var raw=w.sessionStorage.getItem(recoveryKey),saved=raw&&raw.length<2000?JSON.parse(raw):null;pointerPresent=!!raw;
    if(!saved||saved.host!==c.site_host||saved.inquiry_id!==number||!validExpiry(saved.expires_at)||!/^[a-f0-9]{64}$/.test(saved.receipt||'')||!saved.channels||(!saved.channels.ga4&&!saved.channels.ads)){clearRecovery();return;}
    ticket=saved.receipt;recovery={expires_at:saved.expires_at};
    ['ga4','ads'].forEach(function(channel){if(saved.channels[channel]!==true){jobs[channel].done=true;jobs[channel].recoverable=false;}});
   }catch(_){clearRecovery();}
  }
  initRecovery();
  // Keep bearer credentials out of the address bar and translated links.
  if(p.has('xi_receipt')){p.delete('xi_receipt');w.history.replaceState(w.history.state,'',w.location.pathname+(p.toString()?'?'+p:'')+w.location.hash);}
  function scrubLink(a){try{var u=new URL(a.href,w.location.href);if(u.searchParams.has('xi_receipt')){u.searchParams.delete('xi_receipt');a.href=u.href;}}catch(_){} }
  function scrubLinks(){d.querySelectorAll('a[href*="xi_receipt="]').forEach(scrubLink);}
  scrubLinks();d.addEventListener('DOMContentLoaded',scrubLinks);
  w.XIConversions={consents:function(){return consent(w,c);}};
  function allowed(channel){return !closed&&typeof w.gtag==='function'&&consent(w,c)[channel==='ga4'?'analytics_storage':'ad_storage']==='granted';}
  function request(data,keepalive){return w.fetch(c.owned_endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',keepalive:!!keepalive,headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}).then(function(r){return r.json();});}
  function ack(channel,owner){request({op:'ack',receipt:ticket,inquiry_id:number,channel:channel,claim_owner:owner},true).catch(function(){});}
  function tagReady(channel){
   // Official target-specific get callback is stronger than merely finding the queue function.
   // It proves handler responsiveness, not network delivery or report attribution.
   return new Promise(function(resolve){
    if(!allowed(channel)){resolve(false);return;}
    var settled=false,expiry=w.setTimeout(function(){finish(false);},3000);
    function finish(ok){if(settled)return;settled=true;w.clearTimeout(expiry);resolve(ok&&allowed(channel));}
    try{w.gtag('get',channel==='ga4'?c.ga4_id:c.ads_id,channel==='ga4'?'client_id':'gclid',function(value){
     // Ads get may validly return undefined for a visitor without an ad click.
     finish(channel==='ads'||(typeof value==='string'&&value!==''));
    });}catch(_){finish(false);}
   });
  }
  function releaseUnused(channel){
   var job=jobs[channel],r=job.unused;if(!r)return Promise.resolve();
   return request({op:'release_unsent',receipt:ticket,inquiry_id:number,channel:channel,claim_owner:r.claim_owner},true).then(function(result){
    if(result.success&&result.data&&result.data.released){job.unused=null;job.phase='pending';job.recoverable=true;saveRecovery();}
    else if(result.data&&result.data.code==='receipt'){job.done=true;job.phase='expired';}
    // A lost release response retains the known-unused owner; only release may be retried.
   }).catch(function(){job.phase='release_response_unknown';});
  }
  function transmit(channel,r){
   var job=jobs[channel];if(!allowed(channel))return false;
   var params={send_to:r.send_to,inquiry_id:r.inquiry_id,form_type:r.form_type,test_lead:r.is_test,event_callback:function(){ack(channel,r.claim_owner);},event_timeout:2000};
   var userSet=false;
   try{
    if(channel==='ads'){
     params.transaction_id=r.inquiry_id;
     if(r.user_data&&consent(w,c).ad_user_data==='granted'){w.gtag('set','user_data',r.user_data);userSet=true;}
    }
    // From this point onward, even a thrown error may occur after queueing. Do not release.
    job.done=true;job.unused=null;job.phase='event_attempted';
    w.gtag('event',channel==='ads'?'conversion':'generate_lead',params);
   }catch(_){job.phase=job.done?'event_outcome_unknown':'not_attempted';}
   finally{if(userSet)try{w.gtag('set','user_data',null);}catch(_){} }
   return job.done;
  }
  function dispatch(channel){
   var job=jobs[channel],suffix=number.slice((c.number_prefix||'').length);
   // Old cached HTML/PHP must not consume a receipt before revealing an incompatible response.
   if(c.receipt_protocol!=='owned-v1'||!c.owned_endpoint)return;
   if(!thank||!c.number_prefix||number.indexOf(c.number_prefix)!==0||!/^[1-9][0-9]*$/.test(suffix)||!/^[a-f0-9]{64}$/.test(ticket)||job.busy||job.done)return;
   // Release a definitely-unused owned claim even after consent withdrawal; never send Google data.
   if(job.unused){job.busy=true;releaseUnused(channel).finally(function(){job.busy=false;});return;}
   if(!allowed(channel))return;job.busy=true;
   tagReady(channel).then(function(ready){
    if(!ready||!allowed(channel)||!forgetRecovery(channel))return;job.phase='claim_inflight';
    return request({op:'claim',receipt:ticket,inquiry_id:number,channel:channel,consent:consent(w,c),protocol:c.receipt_protocol}).then(function(result){
     if(result.success&&result.data){
      var r=result.data;
      if(!r.dispatch){job.done=true;job.phase='already_claimed_unknown';return;}
      if(!/^[a-f0-9]{32}$/.test(r.claim_owner||'')){job.done=true;job.phase='protocol_mismatch_unknown';return;}
      job.unused=r;job.phase='claimed_unused';
      if(!transmit(channel,r))return releaseUnused(channel);
     }else if(result.success===false&&result.data&&Object.keys(result.data).length===1&&['consent','config','protocol'].indexOf(result.data.code)!==-1){
      // These explicit server checks return before any receipt UPDATE. No other failure may regain recovery.
      job.recoverable=true;job.phase='pending_rejected';saveRecovery();
     }else if(result.success===false&&result.data&&result.data.code==='receipt'){job.done=true;job.phase='expired';}
     else{job.done=true;job.phase='claim_response_unknown';}
    }).catch(function(){
     // The server may have consumed the claim before the response disappeared. Never replay.
     job.done=true;job.phase='claim_response_unknown';
    });
   }).finally(function(){job.busy=false;});
  }
  function reconcile(){
   cleanStoredRecovery();
   if(thank&&!ticket)initRecovery();
   if(recovery&&!validExpiry(recovery.expires_at)){clearRecovery();closed=true;ticket='';jobs.ga4.done=true;jobs.ads.done=true;}
   if(thank&&ticket)saveRecovery();dispatch('ga4');dispatch('ads');
   if(jobs.ga4.done&&jobs.ads.done&&timer){w.clearInterval(timer);timer=null;}
  }
  d.addEventListener('click',function(e){var a=e.target&&e.target.closest&&e.target.closest('a[href]');if(!a)return;scrubLink(a);var name=contact(a.href);if(!name||typeof w.gtag!=='function')return;var state=consent(w,c);if(state.analytics_storage==='granted'&&/^G-[A-Z0-9]+$/.test(c.ga4_id))w.gtag('event',name,{send_to:c.ga4_id,contact_type:name==='email_click'?'email':'whatsapp'});var label=name==='email_click'?c.email_label:c.whatsapp_label;if(state.ad_storage==='granted'&&/^AW-[0-9]+$/.test(c.ads_id)&&/^[A-Za-z0-9_-]+$/.test(label||''))w.gtag('event','conversion',{send_to:c.ads_id+'/'+label});},true);
  w.addEventListener('pagehide',function(){closed=true;});w.addEventListener('pageshow',function(){closed=false;reconcile();});d.addEventListener('DOMContentLoaded',reconcile);d.addEventListener('rcb-consent-changed',reconcile);reconcile();
  timer=thank&&(ticket||c.pending_storage_service)?w.setInterval(reconcile,1000):null;
  if(timer)w.setTimeout(function(){w.clearInterval(timer);timer=null;closed=true;ticket='';clearRecovery();},recovery?Math.max(0,recovery.expires_at*1000-Date.now()):30*60000);
 }
 return {start:start,consent:consent,contact:contact};
});
