const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
const root=path.join(__dirname,'../xiaoman-inquiry-center');
const html='<form class="brxe-form" data-xi-inquiry="quick" data-xifr-form="1" data-xifr-field="Message" data-xifr-page-required="false"><input name="FullName"><input name="Email"><input name="WhatsApp"><textarea name="Message" placeholder="Your requirements"></textarea><button type="submit">Submit</button></form>';
function page(query='',choice='missing',seed=null,formHTML=html){
 const dom=new JSDOM(formHTML,{url:'https://fixture.example.invalid/contact/'+query,runScripts:'outside-only'});const w=dom.window;
 w.XIContextConfig={host:'fixture.example.invalid',service:'xiaoman-inquiry-attribution'};w.XIFRConfig={fieldName:'Message',minLength:1,campaignCodes:['pm','fkxi']};
 if(choice!=='unavailable')w.consentApi={consentSync(name){assert.equal(name,'xiaoman-inquiry-attribution');return {cookie:choice==='missing'?null:{id:1},consentGiven:true,cookieOptIn:choice==='accepted'};}};
 if(seed)w.localStorage.setItem('xi_marketing_v1',JSON.stringify(seed));
 for(const f of ['form-context.js','required/conditional-required.js'])w.eval(fs.readFileSync(path.join(root,f),'utf8'));
 w.document.dispatchEvent(new w.Event('DOMContentLoaded'));return dom;
}
function packet(dom){return JSON.parse(dom.window.document.querySelector('[name="xi_context"]').value);}
test('PM direct URL requires message when the consent service is missing',()=>{const d=page('?utm_campaign=pmfk-ou&gclid=click');assert.equal(packet(d).A1,'pmfk-ou');assert.equal(d.window.document.querySelector('textarea').required,true);assert.equal(d.window.localStorage.length,0);assert.equal(d.window.sessionStorage.length,0);d.window.close();});
test('case insensitive fkxi prefix works',()=>{const d=page('?utm_campaign=FKXI-italy');assert.equal(d.window.document.querySelector('textarea').required,true);d.window.close();});
test('click ID alone does not invent campaign or keyword',()=>{const d=page('?gclid=click');assert.equal(packet(d).A1,'');assert.equal(packet(d).A2,'');assert.equal(packet(d).A5,'click');assert.equal(d.window.document.querySelector('textarea').required,false);d.window.close();});
test('accepted history uses the declared canonical storage keys',()=>{const d=page('?utm_campaign=pm-test','accepted');assert.ok(d.window.localStorage.getItem('xi_marketing_v1'));assert.ok(d.window.localStorage.getItem('xi_first_v1'));assert.ok(d.window.sessionStorage.getItem('xi_visit_v1'));assert.ok(d.window.sessionStorage.getItem('xi_session_ad_v1'));assert.equal(packet(d)._request.consent_status,'accepted');d.window.close();});
test('rejection clears retained history and does not reuse it',()=>{const d=page('','rejected',{landing:'https://fixture.example.invalid/?utm_campaign=old-pm',at:Date.now()});assert.equal(packet(d).A1,'');assert.equal(d.window.localStorage.length,0);assert.equal(d.window.sessionStorage.length,0);d.window.close();});
test('expired history is ignored after consent',()=>{const d=page('','accepted',{landing:'https://fixture.example.invalid/?utm_campaign=old-pm',at:Date.now()-31*86400000});assert.equal(packet(d).A1,'');d.window.close();});
test('missing API never reads a historical campaign',()=>{const d=page('','unavailable',{landing:'https://fixture.example.invalid/?utm_campaign=old-pm',at:Date.now()});assert.equal(packet(d).A1,'');d.window.close();});
test('new ad clicks stay paired and do not borrow old campaign names',()=>{const d=page('?gclid=new-click','accepted',{landing:'https://fixture.example.invalid/?utm_campaign=old-pm&gclid=old-click',at:Date.now()-10000});assert.equal(packet(d).A1,'');assert.equal(packet(d).A5,'new-click');d.window.close();});
test('native required and minlength settings survive conditional scans',()=>{const d=page('','missing',null,html.replace('placeholder="Your requirements"','required minlength="8" placeholder="Your requirements"'));const el=d.window.document.querySelector('textarea');assert.equal(el.required,true);assert.equal(el.minLength,8);d.window.close();});
test('repeated focus does not keep writing field attributes',async()=>{const d=page('?utm_campaign=pm-test');const el=d.window.document.querySelector('textarea');let writes=0;const m=new d.window.MutationObserver(r=>writes+=r.length);m.observe(el,{attributes:true});for(let i=0;i<100;i++)el.dispatchEvent(new d.window.FocusEvent('focusin',{bubbles:true}));await new Promise(r=>setTimeout(r,5));assert.equal(writes,0);m.disconnect();d.window.close();});
test('popup forms are recognized and contain one context packet',()=>{const d=page('?utm_campaign=pm-test');d.window.document.body.insertAdjacentHTML('beforeend','<div class="brx-popup">'+html+'</div>');d.window.document.dispatchEvent(new d.window.CustomEvent('bricks/popup/open',{detail:{popupElement:d.window.document.querySelector('.brx-popup')}}));const forms=d.window.document.querySelectorAll('form');forms.forEach(f=>{assert.equal(f.querySelectorAll('[name="xi_context"]').length,1);assert.equal(f.querySelector('textarea').required,true);});d.window.close();});

test('fixed product context hides equipment even when Bricks defines flex display',()=>{
 const d=new JSDOM('<style>.form-group{display:flex}</style><form class="brxe-form" data-xi-inquiry="detail" data-xi-product-page="true"><div class="form-group"><select name="Equipment"></select></div><select name="ProductID"></select><input name="Product"><input name="CategoryID"><input name="CategorySignature"></form>',{url:'https://fixture.example.invalid/products/example/',runScripts:'outside-only'});
 const w=d.window,f=w.document.querySelector('form');f.setAttribute('data-xi-category',JSON.stringify({id:3,name:'Electric Forklift',signature:'fixture-signature',selected_product_id:9,products:[{id:9,model:'Fixed product'}]}));
 w.eval(fs.readFileSync(path.join(root,'native-product-context.js'),'utf8'));
 const g=f.querySelector('.form-group');assert.equal(g.hidden,true);assert.equal(w.getComputedStyle(g).display,'none');assert.equal(f.querySelector('[name="ProductID"]').value,'9');assert.equal(f.querySelector('[name="Product"]').value,'Fixed product');
 w.document.dispatchEvent(new w.CustomEvent('bricks/popup/open'));assert.equal(f.querySelector('[name="ProductID"]').value,'9');d.window.close();
});
