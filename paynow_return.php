<?php
require_once __DIR__ . '/db.php'; security_headers(); header('Cache-Control: no-store');
$ref = is_string($_GET['ref'] ?? null) ? $_GET['ref'] : '';
$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VPR Payment Status</title>
<style>body{font:16px Arial;background:#f5f7f9;padding:30px;color:#17212b}.box{max-width:560px;margin:auto;background:white;border:1px solid #dfe5ea;border-radius:14px;padding:26px;text-align:center}button{padding:12px 20px;cursor:pointer}p{overflow-wrap:anywhere}</style>
</head><body><div class="box"><img src="assets/vpr-logo.png" alt="VPR" style="max-width:280px;width:80%">
<h2>Payment status</h2><p>Reference: <strong><?=htmlspecialchars($ref)?></strong></p>
<p id="amount"></p><div id="spinner" aria-hidden="true" style="width:30px;height:30px;border:4px solid #e4e9ee;border-top-color:#062c52;border-radius:50%;margin:16px auto;animation:spin 1s linear infinite"></div><style>@keyframes spin{to{transform:rotate(360deg)}}</style><p id="msg" role="status" aria-live="polite">Checking Paynow automatically. Keep this page open.</p><p><a id="innbucks-app" hidden>Open InnBucks app</a></p>
<button id="check" type="button" hidden>Resume automatic checks</button><p><a id="new-search" href="index.php">New vehicle search</a></p></div>
<script>
(function(){
 const ref=<?=json_encode($ref, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
 const token=<?=json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
 const msg=document.getElementById('msg'),button=document.getElementById('check');
 let timer=null,attempts=0,busy=false,watching=true;
 function stop(){watching=false;clearTimeout(timer);document.getElementById('spinner').hidden=true;}
 function forget(){try{sessionStorage.removeItem('vpr.pendingCheckout');sessionStorage.removeItem('vpr.checkoutRequest');}catch(e){}}
 document.getElementById('new-search').addEventListener('click',forget);
 async function check(manual=false){
   if(busy)return; busy=true;button.disabled=true;
   try{
     const r=await fetch('paynow_status.php?ref='+encodeURIComponent(ref)+'&token='+encodeURIComponent(token),{cache:'no-store',method:'POST'});
     const d=await r.json();
     if(!r.ok||!d.ok){if(r.status===400||r.status===403){stop();msg.textContent=d.message||'Payment access denied.';return;}throw new Error('Verification unavailable');}
     document.getElementById('amount').textContent=(d.currency==='ZWG'?'ZiG (ZWG)':d.currency)+' '+(d.amount||'');
     const app=document.getElementById('innbucks-app');
     app.hidden=!(d.payment_method==='innbucks'&&typeof d.authorization_code==='string'&&/^[A-Za-z0-9-]{1,100}$/.test(d.authorization_code));
     if(!app.hidden)app.href='com.innbucks.customer://purchase?paymentToken='+encodeURIComponent(d.authorization_code);
     if(d.paid&&d.certificate_url){
       forget();
       stop();msg.textContent='Payment verified. Opening your certificate…';
       if(window.opener&&!window.opener.closed)window.opener.postMessage({type:'VPR_PAYNOW_RETURN'},window.location.origin);
       window.location.replace(d.certificate_url);return;
     }
     const terminal=['Cancelled','Failed','Expired'].includes(d.status);
     if(terminal){forget();stop();}
     msg.textContent=terminal?(d.message||'Payment '+d.status.toLowerCase()+'. No certificate has been issued.'):
       ((d.message||d.instructions||'Waiting for Paynow confirmation.').replace('Keep this page open, then check status.','Keep this page open.')+' We are checking Paynow automatically.');
   }catch(e){msg.textContent='Verification is temporarily unavailable. Retrying automatically using this same payment reference.';}
   finally{
     busy=false;button.disabled=false;
     if(watching){if(++attempts<60){clearTimeout(timer);timer=setTimeout(check,10000);}else{stop();button.hidden=false;msg.textContent='Payment has not been confirmed yet. Keep this reference. You can resume automatic checks for this same payment.';}}
   }
 }
 button.addEventListener('click',function(){attempts=0;watching=true;button.hidden=true;document.getElementById('spinner').hidden=false;check();});check();
})();
</script></body></html>
