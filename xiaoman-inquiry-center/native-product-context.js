/* Native Bricks product/category context. No cookies, tracking or network requests. */
(function () {
  'use strict';
  var initialized = new WeakSet();
  function findField(form,name) {
    var aliases={Equipment:'F1',Product:'F2',ProductID:'product_id',CategoryID:'category_id',CategorySignature:'category_signature'};
    var map={};try{map=JSON.parse(form.getAttribute('data-xi-field-map')||'{}');}catch(_){}
    var wanted=map[aliases[name]||name]||name;
    return Array.from(form.elements).find(function(el){return el.name===wanted;})||null;
  }
  function setValue(form, name, value) {
    var field = findField(form,name);
    if (!field) return;
    field.value = value == null ? '' : String(value);
    if (field.tagName === 'INPUT') field.defaultValue = field.value;
  }
  function option(select, value, label, selected) {
    var node = select.ownerDocument.createElement('option');
    node.value = String(value); node.textContent = label;
    node.defaultSelected = !!selected; node.selected = !!selected; select.appendChild(node);
  }
  function syncContext(form) {
    var category, catalog;
    try {
      category = JSON.parse(form.getAttribute('data-xi-category') || 'null');
      catalog = JSON.parse(form.getAttribute('data-xi-catalog') || 'null');
    } catch (_) { return; }
    if (catalog) {
      var equipment = findField(form,'Equipment');
      category = catalog.categories.find(function (c) { return equipment && c.name === equipment.value; });
      if (!category) category = {id:0, signature:catalog.empty_signature};
    }
    if (!category) return;
    setValue(form, 'CategoryID', category.id);
    setValue(form, 'CategorySignature', category.signature);
  }
  function initialize(form) {
    syncContext(form);
    if (initialized.has(form)) return;
    var category, product, catalog;
    try {
      category = JSON.parse(form.getAttribute('data-xi-category') || 'null');
      product = JSON.parse(form.getAttribute('data-xi-product') || 'null');
      catalog = JSON.parse(form.getAttribute('data-xi-catalog') || 'null');
    } catch (_) { return; }
    if (!category && !product && !catalog) return;
    var equipment = findField(form,'Equipment');
    var model = findField(form,'ProductID');
    if (!equipment || !model || equipment.tagName!=='SELECT' || model.tagName!=='SELECT') return;
    initialized.add(form);
    equipment.replaceChildren();
    if (catalog) {
      option(equipment, '', 'Select Your Equipment', true);
      catalog.categories.forEach(function (c) { option(equipment, c.name, c.name, false); });
      function updateProducts() {
        var current = catalog.categories.find(function (c) { return c.name === equipment.value; });
        model.replaceChildren();
        option(model, '', 'Select Your Product', true);
        (current ? current.products : []).forEach(function (p) { option(model, p.id, p.model, false); });
        model.disabled = !current;
        setValue(form, 'Product', '');
        syncContext(form);
      }
      equipment.addEventListener('change', updateProducts);
      form.addEventListener('reset', function () { window.setTimeout(updateProducts, 0); });
      updateProducts();
      return;
    }
    option(equipment, category ? category.name : product.equipment, category ? category.name : product.equipment, true);
    model.replaceChildren();
    if (category) {
      var selectedId = String(category.selected_product_id || '');
      option(model, '', model.getAttribute('data-placeholder') || 'Select Your Product', !selectedId);
      (category.products || []).forEach(function (p) { option(model, p.id, p.model, String(p.id) === selectedId); });
      if (form.hasAttribute('data-xi-product-page')) {
        var equipmentGroup = equipment.closest('.form-group');
        if (equipmentGroup) {
          equipmentGroup.hidden = true;
          // Bricks' author CSS can override the browser's default [hidden] rule.
          if (equipmentGroup.style.display !== 'none') equipmentGroup.style.display = 'none';
        }
      }
      setValue(form, 'CategoryID', category.id);
      setValue(form, 'CategorySignature', category.signature);
      setValue(form, 'Product', '');
      model.addEventListener('change', function () {
        var chosen = (category.products || []).find(function (p) { return String(p.id) === model.value; });
        setValue(form, 'Product', chosen ? chosen.model : '');
      });
    } else {
      option(model, product.id, product.model, true);
      setValue(form, 'Product', product.model);
    }
    equipment.dispatchEvent(new Event('change', {bubbles:true}));
    model.dispatchEvent(new Event('change', {bubbles:true}));
  }
  function quick(form) {
    if(initialized.has(form))return;
    var field=findField(form,'Equipment'), value=form.getAttribute('data-xi-equipment');
    if(!field || field.tagName!=='SELECT' || !value)return;
    if(!Array.from(field.options).some(function(o){return o.value===value;}))option(field,value,value,false);
    field.value=value;Array.from(field.options).forEach(function(o){o.defaultSelected=o.value===value;});
    initialized.add(form);field.dispatchEvent(new Event('change',{bubbles:true}));
  }
  function scan() { document.querySelectorAll('form.brxe-form[data-xi-inquiry="quick"]').forEach(quick); document.querySelectorAll('form.brxe-form[data-xi-inquiry="detail"]').forEach(initialize); }
  scan();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan, {once:true});
  window.addEventListener('load', scan, {once:true});
  document.addEventListener('bricks/popup/open', scan);
  // Bricks may initialize/reset hidden fields after the first render. Restore the
  // server-signed category immediately before its submit handler serializes them.
  document.addEventListener('submit', function (event) {
    if (event.target.matches('form.brxe-form[data-xi-inquiry="detail"]')) syncContext(event.target);
  }, true);
})();
