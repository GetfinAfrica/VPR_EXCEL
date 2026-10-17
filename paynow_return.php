<?php
require_once __DIR__ . '/db.php';
security_headers();
$ref=clean($_GET['ref']??'');
$token=clean($_GET['token']??'');
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VPR Payment</title>
<style>body{font-family:Arial;background:#f5f7f9;padding:30px;color:#17212b}.box{max-width:520px;margin:auto;background:#fff;border:1px solid #dfe5ea;border-radius:14px;padding:26px;text-align:center}.spin{width:34px;height:34px;border:4px solid #e4e9ee;border-top-color:#062c52;border-radius:50%;margin:18px auto;animation:s .9s linear infinite}@keyframes s{to{transform:rotate(360deg)}}</style>
</head><body><div class="box"><img src="assets/vpr-logo.png" alt="VPR" style="max-width:280px;width:80%"><h2>Returning to VPR</h2><div class="spin"></div><p id="msg">Confirming your Paynow payment...</p></div>
<script>
(async function(){
 const ref=<?=json_encode($ref)?>, token=<?=json_encode($token)?>;
 try{
   const r=await fetch('paynow_status.php?ref='+encodeURIComponent(ref)+'&token='+encodeURIComponent(token),{cache:'no-store'});
   const d=await r.json();
   if(window.opener){ window.opener.postMessage({type:'VPR_PAYNOW_RETURN'},window.location.origin); }
   if(d.paid && d.certificate_url){
      if(window.opener && !window.opener.closed){ window.opener.location.href=d.certificate_url; window.close(); return; }
      window.location.href=d.certificate_url; return;
   }
   document.getElementById('msg').textContent='Payment received by Paynow. VPR is waiting for final confirmation; you may close this window.';
   setTimeout(function(){ if(window.opener) window.close(); },1800);
 }catch(e){
   document.getElementById('msg').textContent='VPR is checking your payment. You may return to the main VPR window.';
 }
})();
</script></body></html>