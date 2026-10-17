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
  for(const code of ['USD','ZWG']){
    document.getElementById('currency'+code).addEventListener('change',function(){
      if(this.checked){currency.value=code;updateDetails();}
    });
  }
  let stage=1;
  const vehicleStage=document.getElementById('vehicleStage');
  const paymentStage=document.getElementById('paymentStage');
  const vehicleTab=document.getElementById('vehicleTab'),paymentTab=document.getElementById('paymentTab');
  function showStage(next) {
    stage=next;vehicleStage.hidden=next!==1;paymentStage.hidden=next!==2;paymentStage.disabled=next!==2;
    vehicleTab.setAttribute('aria-selected',String(next===1));paymentTab.setAttribute('aria-selected',String(next===2));
    vehicleTab.tabIndex=next===1?0:-1;paymentTab.tabIndex=next===2?0:-1;
    document.getElementById('stageTitle').textContent=next===1?'Vehicle details':'Payment details';
    document.getElementById('stageProgress').textContent=next===1?'Step 1 of 2 — Vehicle':'Step 2 of 2 — Payment';
    document.getElementById('stageIntro').textContent=next===1?'Enter your vehicle details to begin the check.':'Choose the currency and payment method for your vehicle check.';
  }
  function goToPayment(){if(stage===2)return;if(form.reportValidity()){showStage(2);updateDetails();paymentTab.focus();}}
  vehicleTab.addEventListener('click',()=>{if(!busy)showStage(1);});
  paymentTab.addEventListener('click',()=>{if(!busy)goToPayment();});
  for(const tab of [vehicleTab,paymentTab])tab.addEventListener('keydown',function(event){
    if(busy||!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
    event.preventDefault();const next=event.key==='Home'?1:(event.key==='End'?2:(stage===1?2:1));
    if(next===1){showStage(1);vehicleTab.focus();}else goToPayment();
  });
  document.getElementById('vprContinue').addEventListener('click',goToPayment);
  document.getElementById('vprBack').addEventListener('click',()=>{showStage(1);vehicleTab.focus();});
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
    for(const code of ['USD','ZWG'])document.getElementById('currency'+code).checked=currency.value===code;
    const total=label(currency.value)+' '+form.dataset[currency.value==='ZWG'?'zwgPrice':'usdPrice'];
    const paymentMethod=method.value==='hosted'?'Paynow':(method.value==='card'?'Visa / Mastercard':method.options[method.selectedIndex].text);
    document.getElementById('currencySummary').textContent=free?'This vehicle check is free. No payment is required.':'You will pay '+total+' via '+paymentMethod+'.';
    trigger.textContent=free?'Check vehicle — free':'Review '+total+' payment';
    document.getElementById('checkoutDetails').disabled=free;
    document.getElementById('payer_phone').required=!free && isMobile(method.value);
    document.getElementById('phoneHint').textContent=isMobile(method.value)?'(required)':'(optional)';
    document.getElementById('testScenarioBox').hidden=!(testMode()&&['ecocash','onemoney'].includes(method.value));
    document.getElementById('methodHint').textContent=isMobile(method.value)?'Approve the request on your phone. Never enter your wallet PIN here.':'Enter card details only on Paynow. Never enter a PIN or card number here.';
  }
  method.addEventListener('change',updateDetails); currency.addEventListener('change',updateDetails);
  let payment = null, popup = null, busy = false, timer = null, watching = false, polling = false, completed = false;
  const storageKey = 'vpr.pendingCheckout';
  const requestStorageKey = 'vpr.checkoutRequest';
  let savedRequest = null;
  const label = c => c === 'ZWG' ? 'ZiG (ZWG)' : 'USD';
  function store(p) { try { sessionStorage.setItem(storageKey, JSON.stringify(p)); } catch (_) {} }
  function forget() { try { sessionStorage.removeItem(storageKey); sessionStorage.removeItem(requestStorageKey); } catch (_) {} }
  function returnUrl(p) {
    return 'paynow_return.php?ref=' + encodeURIComponent(p.reference) + '&token=' + encodeURIComponent(p.token);
  }
  function acknowledgementUrl(p){
    return 'payment_success.php?ref='+encodeURIComponent(p.reference)+'&token='+encodeURIComponent(p.token);
  }
  function showCompleted(data){
    payment={...payment,...data};completed=true;store(payment);clearTimeout(timer);setWatching(false);
    document.getElementById('vprPayTitle').textContent='Payment confirmed';
    document.getElementById('vprPayOutcome').hidden=false;
    document.getElementById('innbucksOpen').hidden=true;
    document.getElementById('vprPayCopy').textContent='Your payment is verified. Select Complete to receive your VPR acknowledgement.';
    document.getElementById('vprOpenPaynowText').textContent='Complete';
    trigger.textContent='Complete payment';
    status.textContent='Reference '+payment.reference+'. Payment successful. Your certificate is ready after acknowledgement.';
  }
  function setBusy(on) {
    busy = on; open.disabled = on; trigger.disabled = on; close.disabled = on;
    vehicleTab.disabled=on;paymentTab.disabled=on;
    document.getElementById('vprSpinner').style.display = on || watching ? 'block' : 'none';
    open.hidden=watching;
  }
  function setWatching(on,title='Waiting for payment approval'){
    watching=on;open.hidden=on;document.getElementById('vprProgress').hidden=!on;
    document.getElementById('vprProgressTitle').textContent=title;
    document.getElementById('vprSpinner').style.display=on||busy?'block':'none';
  }
  function waitingMessage(data){
    const instruction=(data.message||data.instructions||'Complete your payment approval.').replace('Keep this page open, then check status.','Keep this page open.');
    status.textContent='Reference '+payment.reference+'. '+instruction;
  }
  function schedulePoll(){
    clearTimeout(timer);
    if(!watching||!payment)return;
    timer=setTimeout(poll,2000);
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
  showStage(1);
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
    if (payment) {
      modal.style.display='flex';document.getElementById('vprPayPrice').textContent=label(payment.currency||currency.value)+' '+(payment.amount||'');
      if(completed){showCompleted(payment);return;}
      setWatching(true);waitingMessage(payment);poll();return;
    }
    if (!form.reportValidity()) return;
    const amount = form.dataset[currency.value === 'ZWG' ? 'zwgPrice' : 'usdPrice'];
    if (amount === '0.00') { form.submit(); return; }
    modal.style.display = 'flex';
    document.getElementById('vprPayTitle').textContent='Complete vehicle check';
    document.getElementById('vprPayOutcome').hidden=true;
    document.getElementById('vprPayPrice').textContent = label(currency.value) + ' ' + amount;
    document.getElementById('vprOpenPaynowText').textContent = isMobile(method.value) ? 'Request '+label(currency.value)+' '+amount+' via '+method.options[method.selectedIndex].text : 'Pay '+label(currency.value)+' '+amount+' with '+(method.value==='card'?'Visa / Mastercard':'Paynow');
    document.getElementById('vprPayCopy').textContent = 'Your certificate is released only after VPR verifies payment.';
    status.textContent = 'Continue to secure checkout. The server confirms the current fee.';
  });
  close.addEventListener('click', function () { modal.style.display = 'none'; clearTimeout(timer);setWatching(false); });
  form.addEventListener('submit', function (event) { event.preventDefault(); if(stage===1)goToPayment();else trigger.click(); });
  function terminal(data){
    if(!['Cancelled','Failed','Expired'].includes(data.status))return false;
    status.textContent=data.message||data.instructions||('Payment '+data.status.toLowerCase()+'. No certificate issued.');
    clearTimeout(timer);setWatching(false);forget();payment=null;completed=false;
    document.getElementById('vprPayOutcome').hidden=true;
    document.getElementById('innbucksOpen').hidden=true;
    form.elements.namedItem('checkout_key').value=window.crypto.randomUUID().replace(/-/g,'');
    document.getElementById('vprOpenPaynowText').textContent='Try another payment';
    return true;
  }
  async function poll() {
    if (!payment || polling || !watching) return;
    polling=true;
    try {
      const response = await fetch('paynow_status.php?ref=' + encodeURIComponent(payment.reference) + '&token=' + encodeURIComponent(payment.token), {cache: 'no-store',method:'POST'});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || 'Status temporarily unavailable.');
      if(terminal(data))return;
      waitingMessage(data);
      document.getElementById('vprProgressTitle').textContent=data.needs_review?'Waiting for Paynow confirmation':'Waiting for payment approval';
      showAuthorization(data);
      if (data.paid && data.certificate_url) {
        showCompleted(data);return;
      }
      schedulePoll();
    } catch (e) {
      document.getElementById('vprProgressTitle').textContent='Reconnecting to payment verification';
      status.textContent='We could not check Paynow just now. Retrying automatically for reference '+payment.reference+'.';schedulePoll();
    } finally {polling=false;}
  }
  open.addEventListener('click', async function () {
    if (busy) return;
    if (payment) {
      if(completed){forget();window.location.href=acknowledgementUrl(payment);return;}
      setWatching(true);poll();return;
    }
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
      if(data.status==='Paid'){setWatching(true,'Confirming payment');poll();return;}
      if(isMobile(data.payment_method)) {
        setWatching(true);waitingMessage(data);
        schedulePoll();return;
      }
      if (!data.browserurl) {
        if (popup && !popup.closed) popup.close();
        window.location.href = returnUrl(data); return;
      }
      if (popup && !popup.closed) popup.location.replace(data.browserurl);
      else { window.location.href = data.browserurl; return; }
      setWatching(true,'Complete payment in the Paynow window');
      poll();
    } catch (e) {
      if (popup && !popup.closed) popup.close();
      status.textContent = e.message + ' Retry uses the same checkout request.';
    } finally { setBusy(false); }
  });
  window.addEventListener('message', function (event) {
    if (event.origin === window.location.origin && event.source === popup && event.data && event.data.type === 'VPR_PAYNOW_RETURN') {
      clearTimeout(timer);if(watching)poll();
    }
  });
})();
