// LOCAL GTM Custom Template candidate; not imported/published.
// Required CHECKBOX fields default false:
// defaultStatesVerified, alwaysFireDisabledVerified.
// Verify in native GTM that denied is actually read as false. Do not infer an
// always-fire override merely from the NOT_NEEDED export enum: Help/API wording
// does not document that equivalence. Prefer Not set for this read-only helper.
// Triggers: Initialization - All Pages and xi_purpose_bridge_ready; actual CMP
// defaults must be applied before either (Consent Initialization or earlier).
// Only read access_consent for four Google purposes, read_container_data,
// and execute access_globals: XIPurposeConsent.install / .changed.
// No consent write permission, network, Cookie, browser storage or PII permission.
const addConsentListener=require('addConsentListener');
const isConsentGranted=require('isConsentGranted');
const getContainerVersion=require('getContainerVersion');
const callInWindow=require('callInWindow');
const templateStorage=require('templateStorage');

if(data.defaultStatesVerified!==true||data.alwaysFireDisabledVerified!==true){
 data.gtmOnFailure();return;
}
const container=getContainerVersion().containerId;
const protocol='gtm-purpose-v1';
const purposes=['analytics_storage','ad_storage','ad_user_data','ad_personalization'];
function readCurrent(){
 const states={};
 purposes.forEach(function(purpose){states[purpose]=isConsentGranted(purpose)===true?'granted':'denied';});
 return {protocol:protocol,container:container,defaultStatesVerified:true,alwaysFireDisabledVerified:true,states:states};
}
// readCurrent is deliberately invoked again by the plugin on EVERY check,
// including after the async receipt claim and immediately before user_data.
const installed=callInWindow('XIPurposeConsent.install',container,protocol,readCurrent);
const listenerKey='xi_purpose_listener_v1:'+container;
if(!templateStorage.getItem(listenerKey)){
 purposes.forEach(function(purpose){
  addConsentListener(purpose,function(){callInWindow('XIPurposeConsent.changed',container);});
 });
 templateStorage.setItem(listenerKey,true);
}
// No missing-receiver retries or polls: the internal ready event runs this tag
// once more if the plugin adapter is loaded after GTM.
if(installed===true)data.gtmOnSuccess();else data.gtmOnFailure();
