// Task-local GTM Custom Template; not imported/published.
// Required TEXT: serviceConsent, purposeConsent, readyEvent.
// Required CHECKBOX (default false): defaultStatesVerified,
// alwaysFireDisabledVerified. They attest configuration; they cannot detect unset.
// Read-only access_consent for the configured real RCB Unique Name and Google
// purpose. Execute access_globals consentApi.consentSync; write_data_layer event.
// Configure helper as Not set (no extra gate) and verify denied reads in native
// GTM. Never give the reader/helper an always-fire override.
// The three Google base tags MUST require their service+purpose and use native
// ONCE_PER_LOAD. This helper intentionally does NOT mark a queued base as loaded.
const addConsentListener=require('addConsentListener');
const isConsentGranted=require('isConsentGranted');
const callInWindow=require('callInWindow');
const createQueue=require('createQueue');
const callLater=require('callLater');
const templateStorage=require('templateStorage');
if(data.defaultStatesVerified!==true||data.alwaysFireDisabledVerified!==true||
 !data.serviceConsent||!data.purposeConsent||!data.readyEvent){data.gtmOnFailure();return;}
const queueKey='xi_ready_scheduled_v2:'+data.readyEvent;
function queueWhenReady(){
 const decision=callInWindow('consentApi.consentSync',data.serviceConsent);
 if(!decision||!decision.cookie||decision.consentGiven!==true||decision.cookieOptIn!==true)return;
 if(!isConsentGranted(data.serviceConsent)||!isConsentGranted(data.purposeConsent))return;
 const push=createQueue('dataLayer');push({event:data.readyEvent});
}
function afterConsentChange(){
 if(templateStorage.getItem(queueKey))return;
 templateStorage.setItem(queueKey,true);
 callLater(function(){templateStorage.setItem(queueKey,false);queueWhenReady();});
}
// Listener registration is once per template instance/configuration. Retrying a
// blocked base event after withdrawal/regrant is safe; ONCE_PER_LOAD is owned by
// the actual Google base tag, after its additional-consent checks pass.
const listenerKey='xi_ready_listener_v2:'+data.readyEvent+':'+data.serviceConsent+':'+data.purposeConsent;
if(!templateStorage.getItem(listenerKey)){
 addConsentListener(data.serviceConsent,afterConsentChange);
 addConsentListener(data.purposeConsent,afterConsentChange);
 templateStorage.setItem(listenerKey,true);
}
afterConsentChange();data.gtmOnSuccess();
