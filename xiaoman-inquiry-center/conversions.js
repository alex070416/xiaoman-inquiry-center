/* One saved receipt, one browser dispatch per destination. No form-field PII in GA4. */
(function(root,factory){'use strict';var api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else api.start(root);})(typeof window!=='undefined'?window:null,function(){
 'use strict';
 function granted(w,name){try{var c=name&&w.consentApi.consentSync(name);return !!(c&&c.cookie&&c.consentGiven&&c.cookieOptIn);}catch(_){return false;}}
 function consent(w,c){return {analytics_storage:granted(w,c.analytics_service)?'granted':'denied',ad_storage:granted(w,c.ads_service)?'granted':'denied',ad_user_data:granted(w,c.user_data_service)?'granted':'denied',ad_personalization:'denied'};}
 function contact(href){try{var u=new URL(href);if(u.protocol==='mailto:')return 'email_click';if(u.protocol==='https:'&&((u.hostname==='wa.me'&&/^\/[0-9]+\/?$/.test(u.pathname))||(u.hostname==='api.whatsapp.com'&&/^\/send\/?$/.test(u.pathname))))return 'whatsapp_click';}catch(_){}return '';}
 function start(w){
  if(!w||!w.XIConversionConfig)return;var c=w.XIConversionConfig;if(w.location.hostname!==c.site_host||/[?&](bricks|brickspreview)=/.test(w.location.search))return;
  var d=w.document,p=new URLSearchParams(w.location.search),number=p.get('inquiry_id')||'',ticket=p.get('xi_receipt')||'',busy={},done={},closed=false;
  var thank=Array.isArray(c.thank_you_routes)&&c.thank_you_routes.indexOf(w.location.pathname.replace(/\/+$/,'')+'/')!==-1&&p.get('form')==='quick_quote';
  // Strip the bearer token before base tags/pageviews read the URL. Never persist it in browser storage.
  if(p.has('xi_receipt')){p.delete('xi_receipt');w.history.replaceState(w.history.state,'',w.location.pathname+(p.toString()?'?'+p:'')+w.location.hash);}
  function scrubLink(a){try{var u=new URL(a.href,w.location.href);if(u.searchParams.has('xi_receipt')){u.searchParams.delete('xi_receipt');a.href=u.href;}}catch(_){} }
  function scrubLinks(){d.querySelectorAll('a[href*="xi_receipt="]').forEach(scrubLink);}
  scrubLinks();d.addEventListener('DOMContentLoaded',scrubLinks);
  w.XIConversions={consents:function(){return consent(w,c);}};
  function request(data){return w.fetch(c.endpoint,{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)}).then(function(r){return r.json();});}
  function ack(channel){request({op:'ack',receipt:ticket,inquiry_id:number,channel:channel}).catch(function(){});}
  function transmit(channel,r){
   if(closed||typeof w.gtag!=='function'||consent(w,c)[channel==='ga4'?'analytics_storage':'ad_storage']!=='granted')return;
   var params={send_to:r.send_to,inquiry_id:r.inquiry_id,form_type:r.form_type,test_lead:r.is_test,event_callback:function(){ack(channel);},event_timeout:2000};
   if(channel==='ads'){params.transaction_id=r.inquiry_id;if(r.user_data&&consent(w,c).ad_user_data==='granted')w.gtag('set','user_data',r.user_data);w.gtag('event','conversion',params);w.gtag('set','user_data',null);}
   else w.gtag('event','generate_lead',params);
  }
  function dispatch(channel){
   var state=consent(w,c),suffix=number.slice((c.number_prefix||'').length);
   if(closed||!thank||!c.number_prefix||number.indexOf(c.number_prefix)!==0||!/^[1-9][0-9]*$/.test(suffix)||!/^[a-f0-9]{64}$/.test(ticket)||busy[channel]||done[channel]||state[channel==='ga4'?'analytics_storage':'ad_storage']!=='granted'||typeof w.gtag!=='function')return;
   busy[channel]=true;request({op:'claim',receipt:ticket,inquiry_id:number,channel:channel,consent:state}).then(function(result){if(result.success&&result.data){done[channel]=true;if(result.data.dispatch)transmit(channel,result.data);}else if(result.data&&result.data.code==='receipt')done[channel]=true;}).catch(function(){}).finally(function(){busy[channel]=false;});
  }
  function reconcile(){dispatch('ga4');dispatch('ads');if(done.ga4&&done.ads&&timer){w.clearInterval(timer);timer=null;}}
  d.addEventListener('click',function(e){var a=e.target&&e.target.closest&&e.target.closest('a[href]');if(!a)return;scrubLink(a);var name=contact(a.href);if(!name||typeof w.gtag!=='function')return;var state=consent(w,c);if(state.analytics_storage==='granted'&&/^G-[A-Z0-9]+$/.test(c.ga4_id))w.gtag('event',name,{send_to:c.ga4_id,contact_type:name==='email_click'?'email':'whatsapp'});var label=name==='email_click'?c.email_label:c.whatsapp_label;if(state.ad_storage==='granted'&&/^AW-[0-9]+$/.test(c.ads_id)&&/^[A-Za-z0-9_-]+$/.test(label||''))w.gtag('event','conversion',{send_to:c.ads_id+'/'+label});},true);
  w.addEventListener('pagehide',function(){closed=true;});w.addEventListener('pageshow',function(){closed=false;reconcile();});d.addEventListener('DOMContentLoaded',reconcile);d.addEventListener('rcb-consent-changed',reconcile);reconcile();
  var timer=thank&&ticket?w.setInterval(reconcile,1000):null;if(timer)w.setTimeout(function(){w.clearInterval(timer);closed=true;ticket='';},30*60000);
 }
 return {start:start,consent:consent,contact:contact};
});
