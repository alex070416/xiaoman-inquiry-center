/* REVIEW CANDIDATE ONLY. Requires matching owner/release PHP and schema migration. */
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
  var jobs={ga4:{busy:false,done:false,unused:null},ads:{busy:false,done:false,unused:null}};
  var thank=Array.isArray(c.thank_you_routes)&&c.thank_you_routes.indexOf(w.location.pathname.replace(/\/+$/,'')+'/')!==-1&&p.get('form')==='quick_quote';
  // Preserve beta.7 URL privacy. Recovery is only within this page lifecycle; no browser storage.
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
    if(result.success&&result.data&&result.data.released){job.unused=null;job.phase='pending';}
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
    if(!ready||!allowed(channel))return;job.phase='claim_inflight';
    return request({op:'claim',receipt:ticket,inquiry_id:number,channel:channel,consent:consent(w,c),protocol:c.receipt_protocol}).then(function(result){
     if(result.success&&result.data){
      var r=result.data;
      if(!r.dispatch){job.done=true;job.phase='already_claimed_unknown';return;}
      if(!/^[a-f0-9]{32}$/.test(r.claim_owner||'')){job.done=true;job.phase='protocol_mismatch_unknown';return;}
      job.unused=r;job.phase='claimed_unused';
      if(!transmit(channel,r))return releaseUnused(channel);
     }else if(result.data&&result.data.code==='receipt'){job.done=true;job.phase='expired';}
    }).catch(function(){
     // The server may have consumed the claim before the response disappeared. Never replay.
     job.done=true;job.phase='claim_response_unknown';
    });
   }).finally(function(){job.busy=false;});
  }
  function reconcile(){dispatch('ga4');dispatch('ads');if(jobs.ga4.done&&jobs.ads.done&&timer){w.clearInterval(timer);timer=null;}}
  d.addEventListener('click',function(e){var a=e.target&&e.target.closest&&e.target.closest('a[href]');if(!a)return;scrubLink(a);var name=contact(a.href);if(!name||typeof w.gtag!=='function')return;var state=consent(w,c);if(state.analytics_storage==='granted'&&/^G-[A-Z0-9]+$/.test(c.ga4_id))w.gtag('event',name,{send_to:c.ga4_id,contact_type:name==='email_click'?'email':'whatsapp'});var label=name==='email_click'?c.email_label:c.whatsapp_label;if(state.ad_storage==='granted'&&/^AW-[0-9]+$/.test(c.ads_id)&&/^[A-Za-z0-9_-]+$/.test(label||''))w.gtag('event','conversion',{send_to:c.ads_id+'/'+label});},true);
  w.addEventListener('pagehide',function(){closed=true;});w.addEventListener('pageshow',function(){closed=false;reconcile();});d.addEventListener('DOMContentLoaded',reconcile);d.addEventListener('rcb-consent-changed',reconcile);reconcile();
  timer=thank&&ticket?w.setInterval(reconcile,1000):null;
  if(timer)w.setTimeout(function(){w.clearInterval(timer);timer=null;closed=true;ticket='';},30*60000);
 }
 return {start:start,consent:consent,contact:contact};
});
