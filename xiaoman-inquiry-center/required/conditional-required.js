(function () {
  'use strict';
  var config = window.XIFRConfig || {};
  var defaultFieldName = String(config.fieldName || 'Message').replace(/[^A-Za-z0-9_-]/g, '');
  var defaultMinLength = Math.max(1, parseInt(config.minLength || 1, 10));
  var defaultCampaignCodes = Array.isArray(config.campaignCodes) ? config.campaignCodes.map(function (v) { return String(v || '').trim().toLowerCase(); }).filter(Boolean) : [];
  var FORM = 'form.brxe-form[data-xifr-form="1"]';

  function clean(value) { return typeof value === 'string' ? value.replace(/[\u0000-\u001f\u007f]/g, '').trim().slice(0, 100) : ''; }
  function formConfig(form) {
    var codes = defaultCampaignCodes;
    try {
      var raw = form.getAttribute('data-xifr-campaign-codes');var parsed = raw === null ? null : JSON.parse(raw);
      if (Array.isArray(parsed)) codes = parsed.map(function (v) { return clean(String(v || '')).toLowerCase(); }).filter(Boolean);
    } catch (_) {}
    return {
      fieldName: String(form.getAttribute('data-xifr-field') || defaultFieldName || 'Message').replace(/[^A-Za-z0-9_-]/g, ''),
      minLength: Math.max(1, parseInt(form.getAttribute('data-xifr-min-length') || defaultMinLength || 1, 10)),
      campaignCodes: codes
    };
  }
  function matches(value, campaignCodes) {
    value = clean(value).toLowerCase();
    if (!value) return false;
    return campaignCodes.some(function (code) { return value.indexOf(code) === 0; });
  }
  function campaignFromUrl(value) {
    try {
      var params = new URL(value, window.location.href).searchParams;
      var keys = ['campaign_name', 'campaign', 'utm_campaign', 'campaign_id', 'campaignid', 'utm_id'];
      for (var i = 0; i < keys.length; i++) { var found = clean(params.get(keys[i])); if (found) return found; }
    } catch (_) {}
    return '';
  }
  function addCampaign(values, value) { value = clean(value); if (value && values.indexOf(value) === -1) values.push(value); }
  function campaigns(form) {
    var values = [];
    addCampaign(values, campaignFromUrl(window.location.href));
    try {
      var packet = JSON.parse((form.querySelector('input[name="xi_context"]') || {}).value || '{}');
      addCampaign(values, packet.A1);
      addCampaign(values, campaignFromUrl(packet._request && packet._request.landing_ad_url || ''));
    } catch (_) {}

    return values;
  }
  function hidden(form, name, value) {
    var input = form.querySelector('input[name="' + name + '"]');
    if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.setAttribute('data-xifr-owned', '1'); form.appendChild(input); }
    input.value = value || '';
  }
  function field(form, name) { return form.querySelector('textarea[name="' + name + '"],input[name="' + name + '"]'); }
  function apply(form) {
    var settings = formConfig(form);
    var target = field(form, settings.fieldName); if (!target) return;
    if(!target.hasAttribute('data-xi-required-saved')){target.setAttribute('data-xi-required-saved','1');if(target.required)target.setAttribute('data-xi-original-required','1');if(target.hasAttribute('minlength'))target.setAttribute('data-xi-original-minlength',target.getAttribute('minlength'));}var values = campaigns(form);
    var isCampaign = function (value) { return matches(value, settings.campaignCodes); };
    var required = target.hasAttribute('data-xi-original-required') || form.getAttribute('data-xifr-page-required') === 'true' || values.some(isCampaign);
    var base = target.getAttribute('data-xifr-placeholder');
    if (base === null) { base = String(target.placeholder || '').replace(/\s*\*\s*$/, ''); target.setAttribute('data-xifr-placeholder', base); }
    if(target.required!==required)target.required=required;
    if(target.getAttribute('aria-required')!==(required?'true':'false'))target.setAttribute('aria-required',required?'true':'false');
    var original=target.getAttribute('data-xi-original-minlength');var min=required?String(Math.max(settings.minLength,parseInt(original||'0',10))):original;
    if(min!==null){if(target.getAttribute('minlength')!==min)target.setAttribute('minlength',min);}else if(target.hasAttribute('minlength'))target.removeAttribute('minlength');
    var placeholder=required ? (base ? base + ' *' : '*') : base;if(target.placeholder!==placeholder)target.placeholder=placeholder;
    hidden(form, 'xifr_context', form.getAttribute('data-xifr-token') || '');
    hidden(form, 'xifr_campaign', values.find(isCampaign) || values[0] || '');
  }
  function scan() { document.querySelectorAll(FORM).forEach(apply); }
  function ready() {
    scan();
    [100, 500, 1500].forEach(function (delay) { window.setTimeout(scan, delay); });
    if (window.MutationObserver) new MutationObserver(function (records) {
      if (records.some(function (record) { return record.addedNodes && record.addedNodes.length; })) scan();
    }).observe(document.documentElement, {childList:true, subtree:true});
  }
  document.addEventListener('focusin', function (event) { var form = event.target.closest && event.target.closest(FORM); if (form) apply(form); }, true);
  document.addEventListener('change', function (event) { var form = event.target.closest && event.target.closest(FORM); if (form) apply(form); }, true);
  document.addEventListener('submit', function (event) { if (event.target.matches && event.target.matches(FORM)) apply(event.target); }, true);
  document.addEventListener('bricks/popup/open', scan);
  window.addEventListener('pageshow', scan);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
})();
