(function () {
  'use strict';
  const form = document.getElementById('vprSearchForm');
  const trigger = document.getElementById('vprPayButton');
  const modal = document.getElementById('vprPayModal');
  const open = document.getElementById('vprOpenPaynow');
  const close = document.getElementById('vprClosePay');
  const status = document.getElementById('vprPayStatus');
  const currency = document.getElementById('payment_currency');
  const method = document.getElementById('payment_method');
  let stage=1;
  const vehicleStage=document.getElementById('vehicleStage');
  const paymentStage=document.getElementById('paymentStage');
  function showStage(next) {
    stage=next;vehicleStage.hidden=next!==1;paymentStage.hidden=next!==2;paymentStage.disabled=next!==2;
    document.getElementById('stageTitle').textContent=next===1?'Vehicle details':'Payment details';
    document.getElementById('stageProgress').textContent=next===1?'Step 1 of 2 — Vehicle':'Step 2 of 2 — Payment';
  }
  function goToPayment(){if(form.reportValidity()){showStage(2);updateDetails();}}
  document.getElementById('vprContinue').addEventListener('click',goToPayment);
  document.getElementById('vprBack').addEventListener('click',()=>showStage(1));
  document.getElementById('test_scenario').addEventListener('change',function(){
    const value=this.value;
    if(testMode()&&['ecocash','onemoney'].includes(method.value)&&['0771111111','0772222222','0773333333','0774444444'].includes(value))document.getElementById('payer_phone').value=value;
  });
  function testMode(){return form.dataset[currency.value==='ZWG'?'zwgMode':'usdMode']==='test';}
  const isMobile = value => ['ecocash','onemoney','innbucks'].includes(value);
  function showAuthorization(data) {
    const link=document.getElementById('innbucksOpen');
    const valid=data.payment_method==='innbucks' && typeof data.authorization_code==='string' && /^[A-Za-z0-9-]{1,100}$/.test(data.authorization_code);
    link.hidden=!valid;if(valid)link.href='com.innbucks.customer://purchase?paymentToken='+encodeURIComponent(data.authorization_code);
  }
  function updateDetails() {
    const free=form.dataset[currency.value === 'ZWG' ? 'zwgPrice' : 'usdPrice']==='0.00';
    document.getElementById('checkoutDetails').disabled=free;
    document.getElementById('payer_phone').required=!free && isMobile(method.value);
    document.getElementById('phoneHint').textContent=isMobile(method.value)?'(required)':'(optional)';
    document.getElementById('testScenarioBox').hidden=!(testMode()&&['ecocash','onemoney'].includes(method.value));
    document.getElementById('methodHint').textContent=isMobile(method.value)?'Approve the request on your phone. Never enter your wallet PIN here.':'Enter card details only on Paynow. Never enter a PIN or card number here.';
  }
  method.addEventListener('change',updateDetails); currency.addEventListener('change',updateDetails);
  let payment = null, popup = null, busy = false, timer = null, attempts = 0;
  const storageKey = 'vpr.pendingCheckout';
  const requestStorageKey = 'vpr.checkoutRequest';
  let savedRequest = null;
  const label = c => c === 'ZWG' ? 'ZiG (ZWG)' : 'USD';
  function store(p) { try { sessionStorage.setItem(storageKey, JSON.stringify(p)); } catch (_) {} }
  function forget() { try { sessionStorage.removeItem(storageKey); sessionStorage.removeItem(requestStorageKey); } catch (_) {} }
  function returnUrl(p) {
    return 'paynow_return.php?ref=' + encodeURIComponent(p.reference) + '&token=' + encodeURIComponent(p.token);
  }
  function setBusy(on) {
    busy = on; open.disabled = on; trigger.disabled = on; close.disabled = on;
    document.getElementById('vprSpinner').style.display = on ? 'block' : 'none';
  }
  try {
    const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    if (saved && typeof saved.reference === 'string' && typeof saved.token === 'string') payment = saved;
    savedRequest = JSON.parse(sessionStorage.getItem(requestStorageKey) || 'null');
    if (savedRequest && typeof savedRequest.checkout_key === 'string') {
      for (const [name, value] of Object.entries(savedRequest.fields || {})) {
        const field = form.elements.namedItem(name);
        if (field && typeof value === 'string' && name !== 'csrf_token') field.value = value;
      }
      form.elements.namedItem('checkout_key').value = savedRequest.checkout_key;
    }
  } catch (_) {}
  updateDetails();
  showStage(payment||savedRequest?2:1);
  if (payment || savedRequest) {
    const recovery = document.createElement('p');
    const resume = document.createElement('a'); resume.href = payment ? returnUrl(payment) : '#vprSearchForm';
    resume.textContent = payment ? 'Resume payment ' + payment.reference : 'Retry the previous checkout using this form';
    const reset = document.createElement('button'); reset.type = 'button'; reset.textContent = 'Start a different vehicle search';
    reset.addEventListener('click', function () { forget(); window.location.reload(); });
    recovery.append(resume, reset); form.before(recovery);
  }
  trigger.addEventListener('click', function () {
    if (busy) return;
    if(stage===1){goToPayment();return;}
    if (payment) { window.location.href = returnUrl(payment); return; }
    if (!form.reportValidity()) return;
    const amount = form.dataset[currency.value === 'ZWG' ? 'zwgPrice' : 'usdPrice'];
    if (amount === '0.00') { form.submit(); return; }
    modal.style.display = 'flex';
    document.getElementById('vprPayPrice').textContent = label(currency.value) + ' ' + amount;
    document.getElementById('vprOpenPaynowText').textContent = isMobile(method.value) ? 'Request '+method.options[method.selectedIndex].text+' payment' : 'Continue to Paynow';
    document.getElementById('vprPayCopy').textContent = 'Your certificate is released only after VPR verifies payment.';
    status.textContent = 'Continue to secure checkout. The server confirms the current fee.';
  });
  close.addEventListener('click', function () { modal.style.display = 'none'; clearTimeout(timer); });
  form.addEventListener('submit', function (event) { event.preventDefault(); if(stage===1)goToPayment();else trigger.click(); });
  function terminal(data){
    if(!['Cancelled','Failed','Expired'].includes(data.status))return false;
    status.textContent=data.message||data.instructions||('Payment '+data.status.toLowerCase()+'. No certificate issued.');
    clearTimeout(timer);forget();payment=null;
    form.elements.namedItem('checkout_key').value=window.crypto.randomUUID().replace(/-/g,'');
    document.getElementById('vprOpenPaynowText').textContent='Try another payment';
    if(popup&&!popup.closed)popup.close();return true;
  }
  async function poll(manual=false) {
    if (!payment) return;
    try {
      if(manual)setBusy(true);
      const response = await fetch('paynow_status.php?ref=' + encodeURIComponent(payment.reference) + '&token=' + encodeURIComponent(payment.token), {cache: 'no-store',method:manual?'POST':'GET'});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Status temporarily unavailable.');
      if(terminal(data))return;
      status.textContent = 'Reference ' + payment.reference + ': ' + data.status + '. ' + (data.instructions || '')+' '+(data.message || '');
      showAuthorization(data);
      if (data.paid && data.certificate_url) {
        forget(); if (popup && !popup.closed) popup.close();
        window.location.href = data.certificate_url; return;
      }
      clearTimeout(timer); if (++attempts < 60) timer = setTimeout(poll, 10000);
    } catch (e) { status.textContent = e.message + ' Resume this payment to check again.'; }
    finally {if(manual)setBusy(false);}
  }
  open.addEventListener('click', async function () {
    if (busy) return;
    if (payment) { if(isMobile(payment.payment_method)){poll(true);return;} window.location.href = returnUrl(payment); return; }
    if (!form.reportValidity()) return;
    // Open before the first await, while still inside the user's click gesture.
    popup = isMobile(method.value) ? null : window.open('about:blank', 'VPRPaynow', 'popup=yes,width=650,height=780,resizable=yes,scrollbars=yes');
    setBusy(true); status.textContent = 'Creating your payment reference...';
    try {
      const fields = new FormData(form);
      fields.set('quoted_amount', form.dataset[currency.value === 'ZWG' ? 'zwgPrice' : 'usdPrice']);
      const recoverable = {};
      for (const [name, value] of fields.entries()) if (name !== 'csrf_token') recoverable[name] = value;
      try { sessionStorage.setItem(requestStorageKey, JSON.stringify({checkout_key: fields.get('checkout_key'), fields: recoverable})); } catch (_) {}
      const response = await fetch('paynow_create.php', {method: 'POST', body: fields, cache: 'no-store'});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Checkout unavailable.');
      payment = data; store(data);
      if(terminal(data))return;
      showAuthorization(data);
      document.getElementById('vprPayPrice').textContent = label(data.currency) + ' ' + data.amount;
      if(isMobile(data.payment_method)) {
        document.getElementById('vprOpenPaynowText').textContent='Check payment status';
        status.textContent='Reference '+data.reference+'. '+(data.instructions || 'Waiting for payment initiation confirmation. Keep this reference.');
        attempts=0;timer=setTimeout(poll,10000);return;
      }
      if (!data.browserurl) {
        if (popup && !popup.closed) popup.close();
        window.location.href = returnUrl(data); return;
      }
      if (popup && !popup.closed) popup.location.replace(data.browserurl);
      else { window.location.href = data.browserurl; return; }
      document.getElementById('vprOpenPaynowText').textContent = 'Check existing payment';
      poll();
    } catch (e) {
      if (popup && !popup.closed) popup.close();
      status.textContent = e.message + ' Retry uses the same checkout request.';
    } finally { setBusy(false); }
  });
  window.addEventListener('message', function (event) {
    if (event.origin === window.location.origin && event.source === popup && event.data && event.data.type === 'VPR_PAYNOW_RETURN') {
      clearTimeout(timer); poll();
    }
  });
})();
