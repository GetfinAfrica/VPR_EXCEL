const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/checkout.js'), 'utf8');
let checks = 0;
function fixture({saved = null, request = null, blocked = false, fail = false} = {}) {
  const events = [], nodes = new Map(), store = new Map();
  if (saved) store.set('vpr.pendingCheckout', JSON.stringify(saved));
  if (request) store.set('vpr.checkoutRequest', JSON.stringify(request));
  function node() { return {style: {}, handlers: {}, value: '', addEventListener(k, fn) {this.handlers[k] = fn;}, append() {}, remove() {}}; }
  const names = ['csrf_token', 'checkout_key', 'registration_number', 'vehicle_brand', 'vehicle_model', 'payment_currency','payment_method'];
  const fields = Object.fromEntries(names.map(n => [n, {value: n === 'payment_currency' ? 'USD' : (n === 'checkout_key' ? 'a'.repeat(32) : 'fixture')}]));
  const form = node();
  form.dataset = {usdPrice: '1.00', zwgPrice: '2.00'};
  form.elements = {namedItem: n => fields[n]}; form.reportValidity = () => true;
  form.submit = () => events.push('submit'); form.before = () => {};
  nodes.set('vprSearchForm', form); nodes.set('payment_currency', fields.payment_currency);
  fields.payment_method=node();fields.payment_method.value='hosted';fields.payment_method.options=[{text:'EcoCash'}];fields.payment_method.selectedIndex=0;
  nodes.set('payment_method',fields.payment_method);fields.payment_currency.addEventListener=()=>{};
  const popup = {closed: false, close() {this.closed = true;}, location: {replace(url) {events.push('navigate:' + url);}}};
  const window = {location: {origin: 'http://localhost', href: '', reload() {}},
    addEventListener() {}, open() {events.push('open'); return blocked ? null : popup;}};
  const context = {window, document: {getElementById(id) {if (!nodes.has(id)) nodes.set(id, node()); return nodes.get(id);}, createElement: node},
    sessionStorage: {getItem: k => store.get(k), setItem(k, v) {events.push('persist:' + k); store.set(k, v);}, removeItem: k => store.delete(k)},
    FormData: class {constructor() {this.values = Object.entries(fields).map(([k,v]) => [k,v.value]);} entries() {return this.values;} set(k,v) {const found=this.values.find(([n])=>n===k); if(found)found[1]=v;else this.values.push([k,v]);} get(k) {return this.values.find(([n]) => n === k)[1];}},
    setTimeout: () => 1, clearTimeout() {}, console,
    async fetch(url) {
      events.push('fetch:' + url);
      if (fail) throw new Error('Network interruption');
      return {ok: true, async json() {return url === 'paynow_create.php' ?
        {ok:true,reference:'VPR-test',token:'fixture-token',currency:fields.payment_currency.value,payment_method:fields.payment_method.value,instructions:'Approve on phone',amount:'2.00',browserurl:fields.payment_method.value==='ecocash'?null:'https://www.paynow.co.zw/Payment/ConfirmPayment/123'} :
        {ok:true,status:'Pending',paid:false};}};
    }};
  vm.runInNewContext(source, context);
  return {events, nodes, fields, window, store, click: id => nodes.get(id).handlers.click()};
}
(async function () {
  const f = fixture();
  await f.click('vprPayButton'); await f.click('vprOpenPaynow');
  assert(f.events.indexOf('open') < f.events.indexOf('fetch:paynow_create.php')); ++checks;
  assert(f.events.indexOf('persist:vpr.checkoutRequest') < f.events.indexOf('fetch:paynow_create.php')); ++checks;
  assert(f.events.some(e => e.startsWith('navigate:https://www.paynow.co.zw/'))); ++checks;
  const z = fixture(); z.fields.payment_currency.value = 'ZWG';
  await z.click('vprPayButton');
  assert.equal(z.nodes.get('vprPayPrice').textContent, 'ZiG (ZWG) 2.00'); ++checks;
  await z.click('vprOpenPaynow'); assert(z.events.includes('fetch:paynow_create.php')); ++checks;
  const blocked = fixture({blocked:true}); await blocked.click('vprPayButton'); await blocked.click('vprOpenPaynow');
  assert(blocked.window.location.href.startsWith('https://www.paynow.co.zw/')); ++checks;
  const recovered = fixture({saved: {reference:'VPR-old', token:'old-token'}});
  assert(!recovered.events.some(e => e.startsWith('fetch:'))); ++checks;
  await recovered.click('vprPayButton'); assert(recovered.window.location.href.includes('ref=VPR-old')); ++checks;
  const lost = fixture({request: {checkout_key:'b'.repeat(32), fields:{payment_currency:'ZWG',registration_number:'TST1234'}}});
  assert.equal(lost.fields.checkout_key.value, 'b'.repeat(32)); assert.equal(lost.fields.payment_currency.value,'ZWG'); ++checks;
  const failure = fixture({fail:true}); await failure.click('vprPayButton'); await failure.click('vprOpenPaynow');
  assert(failure.store.has('vpr.checkoutRequest')); assert(!failure.nodes.get('vprOpenPaynow').disabled); ++checks;
  const free = fixture(); free.nodes.get('vprSearchForm').dataset.usdPrice = '0.00'; await free.click('vprPayButton');
  assert.deepEqual(free.events,['submit']); ++checks;
  const mobile=fixture();mobile.fields.payment_method.value='ecocash';await mobile.click('vprPayButton');await mobile.click('vprOpenPaynow');
  assert(!mobile.events.includes('open'));assert.equal(mobile.window.location.href,'');++checks;
  assert(mobile.nodes.get('vprPayStatus').textContent.includes('Approve on phone'));++checks;
  await mobile.click('vprOpenPaynow');assert.equal(mobile.events.filter(x=>x==='fetch:paynow_create.php').length,1);++checks;
  console.log(`PASS: ${checks} browser-flow checks (popup timing, ZWG, recovery, retries, free search).`);
})().catch(e => {console.error(e); process.exitCode = 1;});
