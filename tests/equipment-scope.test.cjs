const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
const root=path.join(__dirname,'../xiaoman-inquiry-center');
const formHTML='<style>.form-group{display:flex}</style><form class="brxe-form" data-xi-inquiry="detail"><div class="form-group"><select name="Equipment"></select></div><div class="form-group"><select name="ProductID"></select></div><input name="Product"><input name="CategoryID"><input name="CategorySignature"></form>';
const categories=[{id:3,name:'Electric Forklifts',signature:'sig-3',products:[{id:9,model:'CPD15'},{id:10,model:'CPD20'}]},{id:4,name:'Diesel Forklifts',signature:'sig-4',products:[{id:20,model:'CPCD30'}]}];
function page(catalog){
 const d=new JSDOM(formHTML,{runScripts:'outside-only'});const w=d.window,f=w.document.querySelector('form');
 f.setAttribute('data-xi-catalog',JSON.stringify(catalog));
 w.eval(fs.readFileSync(path.join(root,'native-product-context.js'),'utf8'));
 const q=n=>f.querySelector('[name="'+n+'"]');
 return {d,w,f,q,options:n=>Array.from(q(n).options).map(o=>o.textContent)};
}
function choose(p,value){p.q('Equipment').value=value;p.q('Equipment').dispatchEvent(new p.w.Event('change',{bubbles:true}));}

test('catalog scope starts on the page category and product but lists every category',()=>{
 const p=page({categories,empty_signature:'sig-0',default_category_id:3,selected_product_id:10});
 assert.deepEqual(p.options('Equipment'),['Select Your Equipment','Electric Forklifts','Diesel Forklifts']);
 assert.equal(p.q('Equipment').value,'Electric Forklifts');
 assert.deepEqual(p.options('ProductID'),['Select Your Product','CPD15','CPD20']);
 assert.equal(p.q('ProductID').value,'10');
 assert.equal(p.q('CategoryID').value,'3');assert.equal(p.q('CategorySignature').value,'sig-3');
 const g=p.q('Equipment').closest('.form-group');assert.equal(g.hidden,false);assert.equal(p.w.getComputedStyle(g).display,'flex');
 p.d.window.close();
});

test('choosing another category swaps products and the signed category, and returning restores the page product',()=>{
 const p=page({categories,empty_signature:'sig-0',default_category_id:3,selected_product_id:10});
 choose(p,'Diesel Forklifts');
 assert.deepEqual(p.options('ProductID'),['Select Your Product','CPCD30']);assert.equal(p.q('ProductID').value,'');
 assert.equal(p.q('CategoryID').value,'4');assert.equal(p.q('CategorySignature').value,'sig-4');
 choose(p,'Electric Forklifts');
 assert.equal(p.q('ProductID').value,'10');assert.equal(p.q('CategoryID').value,'3');
 p.d.window.close();
});

test('form reset returns to the page category and product',async()=>{
 const p=page({categories,empty_signature:'sig-0',default_category_id:3,selected_product_id:10});
 choose(p,'Diesel Forklifts');p.f.reset();await new Promise(r=>p.w.setTimeout(r,5));
 assert.equal(p.q('Equipment').value,'Electric Forklifts');assert.equal(p.q('ProductID').value,'10');assert.equal(p.q('CategoryID').value,'3');
 p.d.window.close();
});

test('category page without a product starts on the category with no product chosen',()=>{
 const p=page({categories,empty_signature:'sig-0',default_category_id:4});
 assert.equal(p.q('Equipment').value,'Diesel Forklifts');assert.equal(p.q('ProductID').value,'');assert.equal(p.q('ProductID').disabled,false);
 assert.equal(p.q('CategoryID').value,'4');
 p.d.window.close();
});

test('plain catalog keeps the original placeholder start',()=>{
 const p=page({categories,empty_signature:'sig-0'});
 assert.equal(p.q('Equipment').value,'');assert.equal(p.q('ProductID').disabled,true);
 assert.equal(p.q('CategoryID').value,'0');assert.equal(p.q('CategorySignature').value,'sig-0');
 p.d.window.close();
});

test('unknown default category falls back to the placeholder',()=>{
 const p=page({categories,empty_signature:'sig-0',default_category_id:99,selected_product_id:10});
 assert.equal(p.q('Equipment').value,'');assert.equal(p.q('ProductID').disabled,true);
 p.d.window.close();
});
