<?php
require_once __DIR__.'/db.php';require_once __DIR__.'/ui.php';
security_headers();header('Cache-Control: no-store');
$ref=is_string($_GET['ref']??null)?$_GET['ref']:'';
$token=is_string($_GET['token']??null)?$_GET['token']:'';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VPR Payment Status</title><?php vpr_ui_assets(); ?></head>
<body class="vpr-payment-page"><main class="vpr-payment-box">
<?php vpr_page_heading('Payment status','Vehicle Pledge Registry',1,'statusHeading'); ?>
<p>Reference: <strong><?=htmlspecialchars($ref)?></strong></p><p id="amount"></p>
<div id="spinner" class="vpr-spinner" aria-hidden="true"></div>
<div id="confirmed" class="vpr-success" hidden>✓ Payment confirmed by Paynow</div>
<p id="msg" role="status" aria-live="polite">Checking Paynow every two seconds. Keep this page open.</p>
<p><a id="innbucks-app" hidden>Open InnBucks app</a></p>
<div class="vpr-actions"><button id="complete" type="button" hidden>Complete</button><a id="new-search" class="vpr-secondary" href="index.php">New vehicle check</a></div>
</main><script>
(function(){
 const ref=<?=json_encode($ref,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
 const token=<?=json_encode($token,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
 const msg=document.getElementById('msg'),complete=document.getElementById('complete');
 let timer=null,busy=false,watching=true,paid=false;
 function stop(){watching=false;clearTimeout(timer);document.getElementById('spinner').hidden=true;}
 function forget(){try{sessionStorage.removeItem('vpr.pendingCheckout');sessionStorage.removeItem('vpr.checkoutRequest');}catch(e){}}
 document.getElementById('new-search').addEventListener('click',forget);
 complete.addEventListener('click',function(){if(paid){forget();window.location.href='payment_success.php?ref='+encodeURIComponent(ref)+'&token='+encodeURIComponent(token);}});
 async function check(){
   if(busy||!watching)return;busy=true;
   try{
     const r=await fetch('paynow_status.php?ref='+encodeURIComponent(ref)+'&token='+encodeURIComponent(token),{cache:'no-store',method:'POST'});
     const d=await r.json();
     if(!r.ok||!d.ok){if(r.status===400||r.status===403){stop();msg.textContent=d.message||'Payment access denied.';return;}throw new Error('Verification unavailable');}
     document.getElementById('amount').textContent=(d.currency==='ZWG'?'ZiG (ZWG)':d.currency)+' '+(d.amount||'');
     const app=document.getElementById('innbucks-app');
     app.hidden=!(d.payment_method==='innbucks'&&typeof d.authorization_code==='string'&&/^[A-Za-z0-9-]{1,100}$/.test(d.authorization_code));
     if(!app.hidden)app.href='com.innbucks.customer://purchase?paymentToken='+encodeURIComponent(d.authorization_code);
     if(d.paid&&d.certificate_url){
       paid=true;stop();app.hidden=true;complete.hidden=false;document.getElementById('confirmed').hidden=false;
       document.getElementById('statusHeading').textContent='Payment confirmed';
       msg.textContent='Your payment is verified. Select Complete to receive your VPR acknowledgement.';
       if(window.opener&&!window.opener.closed)window.opener.postMessage({type:'VPR_PAYNOW_RETURN'},window.location.origin);
       return;
     }
     if(['Cancelled','Failed','Expired'].includes(d.status)){stop();forget();app.hidden=true;msg.textContent=d.message||'Payment '+d.status.toLowerCase()+'. No certificate has been issued.';return;}
     msg.textContent=(d.message||d.instructions||'Waiting for Paynow confirmation.').replace('Keep this page open, then check status.','Keep this page open.')+' We are checking Paynow automatically.';
   }catch(e){msg.textContent='Verification is temporarily unavailable. Retrying automatically using this same payment reference.';}
   finally{busy=false;if(watching){clearTimeout(timer);timer=setTimeout(check,2000);}}
 }
 check();
})();
</script></body></html>
