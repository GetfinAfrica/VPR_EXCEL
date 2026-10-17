const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/checkout.js'), 'utf8');
let checks = 0;
function fixture({saved = null, request = null, blocked = false, fail = false, statusReplies=[]} = {}) {
  const events = [], nodes = new Map(), store = new Map(), timers=new Map(), requests=[], delays=[];
  let nextTimer=0;
  if (saved) store.set('vpr.pendingCheckout', JSON.stringify(saved));
  if (request) store.set('vpr.checkoutRequest', JSON.stringify(request));
  function node() { return {style: {}, handlers: {}, attributes:{},value: '',setAttribute(k,v){this.attributes[k]=v;},focus(){}, addEventListener(k, fn) {this.handlers[k] = fn;}, click(){return this.handlers.click?.();}, append() {}, remove() {}}; }
  const names = ['csrf_token', 'checkout_key', 'registration_number', 'vehicle_brand', 'vehicle_model', 'payment_currency','payment_method','payer_phone'];
  const fields = Object.fromEntries(names.map(n => [n, {value: n === 'payment_currency' ? 'USD' : (n === 'checkout_key' ? 'a'.repeat(32) : 'fixture')}]));
  const form = node();
  form.dataset = {usdPrice: '1.00', zwgPrice: '2.00',usdMode:'test',zwgMode:'test'};
  form.elements = {namedItem: n => fields[n]}; form.reportValidity = () => true;
  form.submit = () => events.push('submit'); form.before = () => {};
  nodes.set('vprSearchForm', form); nodes.set('payment_currency', fields.payment_currency);
  fields.payment_method=node();fields.payment_method.value='hosted';fields.payment_method.options=[{text:'EcoCash'}];fields.payment_method.selectedIndex=0;
  nodes.set('payment_method',fields.payment_method);fields.payment_currency.addEventListener=()=>{};
  nodes.set('payer_phone',fields.payer_phone);
  const popup = {closed: false, close() {this.closed = true;}, location: {replace(url) {events.push('navigate:' + url);}}};
  const window = {crypto:{randomUUID:()=> 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'},location: {origin: 'http://localhost', href: '', reload() {}},
    addEventListener() {}, open() {events.push('open'); return blocked ? null : popup;}};
  const context = {window, document: {getElementById(id) {if (!nodes.has(id)) nodes.set(id, node()); return nodes.get(id);}, createElement: node},
    sessionStorage: {getItem: k => store.get(k), setItem(k, v) {events.push('persist:' + k); store.set(k, v);}, removeItem: k => store.delete(k)},
    FormData: class {constructor() {this.values = Object.entries(fields).map(([k,v]) => [k,v.value]);} entries() {return this.values;} set(k,v) {const found=this.values.find(([n])=>n===k); if(found)found[1]=v;else this.values.push([k,v]);} get(k) {return this.values.find(([n]) => n === k)[1];}},
    setTimeout: (fn,delay) => {delays.push(delay);timers.set(++nextTimer,fn);return nextTimer;}, clearTimeout(id) {timers.delete(id);}, console,
    async fetch(url, options) {
      requests.push({url,method:options.method});
      events.push('fetch:' + url);
      if (fail) throw new Error('Network interruption');
      if(url!=='paynow_create.php'&&statusReplies.length){const reply=statusReplies.shift();if(reply instanceof Error)throw reply;return {ok:true,json:async()=>reply};}
      return {ok: true, async json() {return url === 'paynow_create.php' ?
        {ok:true,reference:'VPR-test',token:'fixture-token',currency:fields.payment_currency.value,payment_method:fields.payment_method.value,instructions:'Approve on phone',amount:'2.00',browserurl:fields.payment_method.value==='ecocash'?null:'https://www.paynow.co.zw/Payment/ConfirmPayment/123'} :
        {ok:true,status:'Pending',paid:false};}};
    }};
  vm.runInNewContext(source, context);
  return {events, nodes, fields, window, store, requests, timers, delays, popup, async tick(){const first=timers.entries().next().value;if(!first)throw new Error('No automatic check scheduled');timers.delete(first[0]);await first[1]();},click: id => nodes.get(id).handlers.click()};
}
(async function () {
  const stages=fixture();
  assert.equal(stages.nodes.get('paymentStage').hidden,true);assert.equal(stages.nodes.get('paymentStage').disabled,true);++checks;
  await stages.click('vprContinue');assert.equal(stages.nodes.get('vehicleStage').hidden,true);assert.equal(stages.nodes.get('paymentStage').disabled,false);++checks;
  await stages.click('vprBack');assert.equal(stages.nodes.get('vehicleStage').hidden,false);++checks;
  stages.fields.payment_method.value='ecocash';stages.fields.payment_method.handlers.change();
  const selector=stages.nodes.get('test_scenario');
  for(const number of ['0771111111','0772222222','0773333333','0774444444']){
    selector.value=number;selector.handlers.change.call(selector);assert.equal(stages.fields.payer_phone.value,number);++checks;
  }
  stages.nodes.get('vprSearchForm').dataset.usdMode='live';stages.fields.payment_method.handlers.change();assert.equal(stages.nodes.get('testScenarioBox').hidden,true);++checks;
  const f = fixture();
  await f.click('vprContinue'); await f.click('vprPayButton'); await f.click('vprOpenPaynow');
  assert(f.events.indexOf('open') < f.events.indexOf('fetch:paynow_create.php')); ++checks;
  assert(f.events.indexOf('persist:vpr.checkoutRequest') < f.events.indexOf('fetch:paynow_create.php')); ++checks;
  assert(f.events.some(e => e.startsWith('navigate:https://www.paynow.co.zw/'))); ++checks;
  const z = fixture(); z.fields.payment_currency.value = 'ZWG';
  await z.click('vprContinue'); await z.click('vprPayButton');
  assert.equal(z.nodes.get('vprPayPrice').textContent, 'ZiG (ZWG) 2.00'); ++checks;
  await z.click('vprOpenPaynow'); assert(z.events.includes('fetch:paynow_create.php')); ++checks;
  const blocked = fixture({blocked:true}); await blocked.click('vprContinue'); await blocked.click('vprPayButton'); await blocked.click('vprOpenPaynow');
  assert(blocked.window.location.href.startsWith('https://www.paynow.co.zw/')); ++checks;
  const recovered = fixture({saved: {reference:'VPR-old', token:'old-token'}});
  assert(!recovered.events.some(e => e.startsWith('fetch:'))); ++checks;
  assert.equal(recovered.nodes.get('vehicleTab').attributes['aria-selected'],'true');await recovered.click('paymentTab');await recovered.click('vprPayButton'); assert.equal(recovered.nodes.get('vprPayModal').style.display,'flex');assert(recovered.nodes.get('vprOpenPaynow').hidden);assert(!recovered.events.includes('fetch:paynow_create.php')); ++checks;
  const lost = fixture({request: {checkout_key:'b'.repeat(32), fields:{payment_currency:'ZWG',registration_number:'TST1234'}}});
  assert.equal(lost.fields.checkout_key.value, 'b'.repeat(32)); assert.equal(lost.fields.payment_currency.value,'ZWG'); ++checks;
  const failure = fixture({fail:true}); await failure.click('vprContinue'); await failure.click('vprPayButton'); await failure.click('vprOpenPaynow');
  assert(failure.store.has('vpr.checkoutRequest')); assert(!failure.nodes.get('vprOpenPaynow').disabled); ++checks;
  const free = fixture(); free.nodes.get('vprSearchForm').dataset.usdPrice = '0.00'; await free.click('vprContinue'); await free.click('vprPayButton');
  assert.deepEqual(free.events,['submit']); ++checks;
  const mobile=fixture();mobile.fields.payment_method.value='ecocash';await mobile.click('vprContinue'); await mobile.click('vprPayButton');await mobile.click('vprOpenPaynow');
  assert(!mobile.events.includes('open'));assert.equal(mobile.window.location.href,'');++checks;
  assert(mobile.nodes.get('vprPayStatus').textContent.includes('Approve on phone'));++checks;
  await mobile.click('vprOpenPaynow');assert.equal(mobile.events.filter(x=>x==='fetch:paynow_create.php').length,1);++checks;
  const automatic=fixture({statusReplies:[{ok:true,status:'Pending',paid:false},{ok:true,status:'Paid',paid:true,certificate_url:'certificate.php?id=123'}]});
  automatic.fields.payment_method.value='ecocash';await automatic.click('vprContinue');await automatic.click('vprPayButton');await automatic.click('vprOpenPaynow');
  assert.equal(automatic.nodes.get('vprSpinner').style.display,'block');assert.equal(automatic.nodes.get('vprOpenPaynow').hidden,true);++checks;
  await automatic.tick();assert.equal(automatic.requests.at(-1).method,'POST');assert(automatic.timers.size>0);++checks;
  await automatic.tick();assert.equal(automatic.window.location.href,'');assert.equal(automatic.nodes.get('vprSpinner').style.display,'none');assert.equal(automatic.timers.size,0);assert.equal(automatic.nodes.get('vprOpenPaynowText').textContent,'Complete');++checks;
  assert(automatic.delays.every(delay=>delay===2000));assert.equal(automatic.popup.closed,false);++checks;
  await automatic.click('vprOpenPaynow');assert(automatic.window.location.href.startsWith('payment_success.php?ref=VPR-test&token='));assert(!automatic.window.location.href.includes('certificate.php'));++checks;
  const hostedSuccess=fixture({statusReplies:[{ok:true,status:'Paid',paid:true,certificate_url:'certificate.php?id=456'}]});
  await hostedSuccess.click('vprContinue');await hostedSuccess.click('vprPayButton');await hostedSuccess.click('vprOpenPaynow');await new Promise(setImmediate);
  assert.equal(hostedSuccess.popup.closed,false);assert.equal(hostedSuccess.window.location.href,'');assert.equal(hostedSuccess.nodes.get('vprOpenPaynowText').textContent,'Complete');++checks;
  const longPending=fixture();longPending.fields.payment_method.value='ecocash';await longPending.click('vprContinue');await longPending.click('vprPayButton');await longPending.click('vprOpenPaynow');
  for(let i=0;i<65;i++)await longPending.tick();assert.equal(longPending.timers.size,1);assert.equal(longPending.events.filter(x=>x==='fetch:paynow_create.php').length,1);++checks;
  const retrying=fixture({statusReplies:[new Error('Offline'),{ok:true,status:'Cancelled',paid:false}]});
  retrying.fields.payment_method.value='ecocash';await retrying.click('vprContinue');await retrying.click('vprPayButton');await retrying.click('vprOpenPaynow');
  await retrying.tick();assert(retrying.nodes.get('vprPayStatus').textContent.includes('Retrying automatically'));assert(retrying.timers.size>0);++checks;
  await retrying.tick();assert.equal(retrying.nodes.get('vprSpinner').style.display,'none');assert.equal(retrying.nodes.get('vprOpenPaynow').hidden,false);assert.equal(retrying.timers.size,0);++checks;
  console.log(`PASS: ${checks} browser-flow checks (popup timing, ZWG, recovery, retries, free search).`);
})().catch(e => {console.error(e); process.exitCode = 1;});
