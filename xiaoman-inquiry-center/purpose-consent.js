/* Current-page, read-only Google-purpose adapter.
 * No browser cookie/storage access, Google consent updates or measurement calls.
 * The controlled GTM template supplies a LIVE reader, not a remembered grant.
 */
(function(root,factory){'use strict';var api=factory();if(typeof module==='object'&&module.exports)module.exports=api;else api.start(root);})(typeof window!=='undefined'?window:null,function(){
 'use strict';
 var protocol='gtm-purpose-v1';
 var purposes=['analytics_storage','ad_storage','ad_user_data','ad_personalization'];
 function create(w){
  var reader=null,container='';
  function read(expected){
   if(!reader||!/^GTM-[A-Z0-9]+$/.test(expected||'')||expected!==container)return null;
   try{
    var value=reader();
    if(!value||value.protocol!==protocol||value.container!==container||value.defaultStatesVerified!==true||value.alwaysFireDisabledVerified!==true||!value.states)return null;
    var states={};
    for(var i=0;i<purposes.length;i++){
     var purpose=purposes[i],state=value.states[purpose];
     if(state!=='granted'&&state!=='denied')return null;
     states[purpose]=state;
    }
    return states;
   }catch(_){return null;}
  }
  function changed(source){
   if(source!==container)return false;
   try{if(w&&w.document&&typeof w.CustomEvent==='function')w.document.dispatchEvent(new w.CustomEvent('xi-purpose-consent-changed'));}catch(_){}
   return true;
  }
  return {
   protocol:protocol,
   install:function(source,version,supplier){
    // An invalid replacement clears the previous reader; no stale grant survives.
    reader=null;container='';
    if(!/^GTM-[A-Z0-9]+$/.test(source||'')||version!==protocol||typeof supplier!=='function')return false;
    container=source;reader=supplier;
    if(!read(source)){reader=null;container='';return false;}
    changed(source);return true;
   },
   read:read,
   changed:changed,
   reset:function(){reader=null;container='';}
  };
 }
 function start(w){
  if(!w)return null;
  if(w.XIPurposeConsent)return null; // Do not replace an unknown existing global.
  var bridge=create(w);w.XIPurposeConsent=bridge;
  w.dataLayer=w.dataLayer||[];
  // Internal handshake only: no identifier, contacts, receipt or Google event.
  if(typeof w.dataLayer.push==='function')w.dataLayer.push({event:'xi_purpose_bridge_ready'});
  // RCB can finish applying an existing decision after DOM Ready without a
  // Google-purpose transition. Wake the read-only helpers from actual CMP
  // lifecycle events; never infer consent from the event or send measurement.
  var d=w.document;
  if(d&&typeof d.addEventListener==='function'&&typeof w.addEventListener==='function'&&typeof w.setTimeout==='function'){
   var installed=false,scheduled=false;
   function wake(){
    if(scheduled)return;scheduled=true;
    w.setTimeout(function(){
     scheduled=false;
     if(d.readyState==='loading'||w.XIPurposeConsent!==bridge)return;
     w.dataLayer=w.dataLayer||[];
     if(typeof w.dataLayer.push==='function')w.dataLayer.push({event:'xi_cmp_ready'});
    },0);
   }
   function watch(){
    if(installed)return;installed=true;
    w.addEventListener('consentApi',wake);
    ['RCB/Apply/Interactive','RCB/OptIn/All','RCB/OptOut/All'].forEach(function(name){d.addEventListener(name,wake);});
    wake();
   }
   if(d.readyState==='loading')d.addEventListener('DOMContentLoaded',watch,{once:true});else watch();
  }
  return bridge;
 }
 return {protocol:protocol,create:create,start:start};
});
