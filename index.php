<?php
require_once __DIR__ . '/db.php';
security_headers();
$pdo=db();
$usdPrice=vpr_price($pdo,'USD');
$zigPrice=vpr_price($pdo,'ZIG');

$result = null;
$vehicle = null;
$activeInterests = [];
$searchId = null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>VPR — Vehicle Pledge Registry</title>
<style>
:root{--navy:#062c52;--navy2:#0c4d86;--gold:#b88a26;--gold2:#dfbf68;--ink:#14202b;--muted:#687481;--line:#dfe5ea;--bg:#f5f7f9;--ok:#1f6b47;--okbg:#e9f5ef;--warn:#8f2d2d;--warnbg:#fbecec}
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--ink)}
.wrap{max-width:820px;margin:0 auto;padding:30px 20px 54px}.brand{text-align:center;margin-bottom:20px}
.logoimg{width:min(560px,92%);height:auto;display:block;margin:0 auto 8px}.sub{font-size:14px;color:var(--muted);letter-spacing:.02em}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:28px;box-shadow:0 10px 32px rgba(6,44,82,.08)}
h1{font-size:27px;color:var(--navy);margin:0 0 8px}p{line-height:1.55}
label{display:block;font-weight:700;margin:16px 0 6px}.optional{font-weight:400;color:var(--muted)}
input,select{width:100%;padding:13px 14px;border:1px solid #cbd5df;border-radius:9px;font-size:16px;background:#fff}
input:focus,select:focus{outline:2px solid rgba(184,138,38,.25);border-color:var(--gold)}
button,.btn{display:inline-block;width:100%;margin-top:20px;padding:14px 16px;border:0;border-radius:9px;background:linear-gradient(135deg,var(--navy),var(--navy2));color:#fff;font-weight:700;font-size:16px;cursor:pointer;text-decoration:none;text-align:center}
.result{margin-top:24px;padding:20px;border-radius:12px}.success{background:var(--okbg);color:var(--ok);border:1px solid #bedfce}.warning{background:var(--warnbg);color:var(--warn);border:1px solid #efc9c9}.error{background:#fff6e8;color:#8a5515;border:1px solid #efd8ad}
.kv{margin-top:14px;padding-top:12px;border-top:1px solid rgba(0,0,0,.08)}.kv div{margin:7px 0}
.interest{background:#fff;border:1px solid rgba(0,0,0,.12);padding:13px;border-radius:10px;margin-top:10px;color:#111827}
.priority{display:inline-block;font-size:12px;font-weight:800;color:#fff;background:var(--navy);padding:4px 8px;border-radius:999px;margin-right:6px}
.notice{font-size:12px;color:var(--muted);margin-top:22px}.hint{font-size:12px;color:var(--muted);margin-top:5px}
.actions{display:grid;grid-template-columns:1fr;gap:10px;margin-top:14px}
.contactbox{margin-top:18px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;text-align:center;box-shadow:0 8px 24px rgba(6,44,82,.05)}.contactbox h3{margin:0 0 10px;color:var(--navy);font-size:17px}.contactgrid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px 18px;font-size:13px;color:var(--muted)}.contactgrid a{color:var(--navy);font-weight:700;text-decoration:none}.contactaddr{grid-column:1/-1}.footer{text-align:center;font-size:12px;color:var(--muted);margin-top:14px}.footer a{color:var(--navy);font-weight:700;text-decoration:none}
@media(max-width:600px){.card{padding:20px}.contactgrid{grid-template-columns:1fr}.contactaddr{grid-column:auto}}

.paymodal{display:none;position:fixed;inset:0;background:rgba(8,20,34,.70);z-index:9999;align-items:center;justify-content:center;padding:18px}
.paycard{background:#fff;border-radius:16px;max-width:460px;width:100%;padding:26px;text-align:center;box-shadow:0 25px 70px rgba(0,0,0,.30)}
.payprice{font-size:34px;font-weight:800;color:var(--navy);margin:8px 0}.paystatus{font-size:13px;line-height:1.5;color:var(--muted);margin-top:12px}
.spinner{width:34px;height:34px;border:4px solid #e4e9ee;border-top-color:var(--navy);border-radius:50%;margin:16px auto;animation:vprspin .9s linear infinite}
@keyframes vprspin{to{transform:rotate(360deg)}} .payclose{background:#e8edf2;color:var(--ink);margin-top:12px}

</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <img class="logoimg" src="assets/vpr-logo.png" alt="VPR — Vehicle Pledge Registry">
    <div class="sub">Public vehicle interest verification</div>
    <div style="display:inline-block;margin-top:8px;padding:5px 9px;border-radius:999px;background:#e9f5ef;color:#1f6b47;font-size:11px;font-weight:700">🔒 HTTPS Secure Connection</div>
  </div>

  <div class="card">
    <h1>Check a Vehicle</h1>
    <p>Enter the vehicle details below. Registration number is the unique VPR reference and is required for every search.</p>
<form method="post" action="#" autocomplete="off" id="vprSearchForm">
      <label for="registration_number">Registration Number</label>
      <input id="registration_number" name="registration_number" required maxlength="7" pattern="[A-Za-z]{3}[0-9]{4}" title="Enter exactly 3 letters followed by 4 numbers, e.g. AAA1111" placeholder="e.g. AAA1111" value="<?= htmlspecialchars($_POST['registration_number'] ?? '') ?>">

      <label for="vehicle_brand">Vehicle Brand</label>
      <select id="vehicle_brand" name="vehicle_brand" required>
        <option value="">Select vehicle brand</option>
        <option value="Toyota">Toyota</option>
        <option value="Nissan">Nissan</option>
        <option value="Honda">Honda</option>
        <option value="Mazda">Mazda</option>
        <option value="Mitsubishi">Mitsubishi</option>
        <option value="Suzuki">Suzuki</option>
        <option value="Subaru">Subaru</option>
        <option value="Isuzu">Isuzu</option>
        <option value="Ford">Ford</option>
        <option value="Chevrolet">Chevrolet</option>
        <option value="Volkswagen">Volkswagen</option>
        <option value="Mercedes-Benz">Mercedes-Benz</option>
        <option value="BMW">BMW</option>
        <option value="Audi">Audi</option>
        <option value="Land Rover">Land Rover</option>
        <option value="Jeep">Jeep</option>
        <option value="Hyundai">Hyundai</option>
        <option value="Kia">Kia</option>
        <option value="Renault">Renault</option>
        <option value="Peugeot">Peugeot</option>
        <option value="Volvo">Volvo</option>
        <option value="Fiat">Fiat</option>
        <option value="Citroën">Citroën</option>
        <option value="Opel">Opel</option>
        <option value="Lexus">Lexus</option>
        <option value="Infiniti">Infiniti</option>
        <option value="Daihatsu">Daihatsu</option>
        <option value="Hino">Hino</option>
        <option value="UD Trucks">UD Trucks</option>
        <option value="Scania">Scania</option>
        <option value="MAN">MAN</option>
        <option value="Foton">Foton</option>
        <option value="BAW">BAW</option>
        <option value="GWM">GWM</option>
        <option value="Haval">Haval</option>
        <option value="FAW">FAW</option>
        <option value="JAC">JAC</option>
        <option value="Chery">Chery</option>
        <option value="Geely">Geely</option>
        <option value="BYD">BYD</option>
        <option value="Changan">Changan</option>
        <option value="Dongfeng">Dongfeng</option>
        <option value="DFSK">DFSK</option>
        <option value="JMC">JMC</option>
        <option value="MG">MG</option>
        <option value="Maxus">Maxus</option>
        <option value="GAC">GAC</option>
        <option value="Jetour">Jetour</option>
        <option value="Omoda">Omoda</option>
        <option value="Jaecoo">Jaecoo</option>
        <option value="Hongqi">Hongqi</option>
        <option value="Great Wall">Great Wall</option>
        <option value="Tata">Tata</option>
        <option value="Mahindra">Mahindra</option>
        <option value="Ashok Leyland">Ashok Leyland</option>
        <option value="Other">Other</option>
      </select>

      <label for="vehicle_model">Vehicle Model</label>
      <input id="vehicle_model" name="vehicle_model" required placeholder="e.g. Fortuner, Hilux, Prado, NV350" value="<?= htmlspecialchars($_POST['vehicle_model'] ?? '') ?>">
      <div class="hint">Enter the model as fully as possible, e.g. “NV350” rather than “NV”.</div>

      <label for="chassis_number">Chassis Number <span class="optional">(optional)</span></label>
      <input id="chassis_number" name="chassis_number" placeholder="Enter if available" value="<?= htmlspecialchars($_POST['chassis_number'] ?? '') ?>">

      <div class="field" style="margin-top:14px">
<label for="payment_currency">Payment Currency</label>
<select id="payment_currency" name="payment_currency" required>
<option value="USD">USD — US$<?=number_format($usdPrice,2) ?></option>
<option value="ZIG">ZiG — ZiG <?=number_format($zigPrice,2) ?></option>
</select>
</div>
<button type="button" id="vprPayButton">Check Vehicle</button><div class="hint" style="text-align:center;margin-top:7px" id="priceHint">Current search fees are set by VPR administration. If the selected fee is 0.00, the check proceeds immediately with no payment step.</div>
    </form>

    <div class="notice"><strong>Important:</strong> A “No Interest Recorded on VPR” result means only that no applicable active interest is recorded in the VPR database at the time of the check. VPR does not replace official ownership, title, or statutory encumbrance checks.</div>
  </div>

  <div class="contactbox">
    <h3>Contact VPR</h3>
    <div class="contactgrid">
      <div><strong>Secure / Interest Registrations</strong><br><a href="mailto:secure@vpr.co.zw">secure@vpr.co.zw</a></div>
      <div><strong>General Enquiries</strong><br><a href="mailto:info@vpr.co.zw">info@vpr.co.zw</a></div>
      <div><strong>Telephone</strong><br><a href="tel:+263788444409">+263 788 444 409</a></div>
      <div class="contactaddr"><strong>Office</strong><br>10 Shepperton Road, Graniteside, Harare</div>
    </div>
  </div>

  <div style="text-align:center;font-size:11px;color:#84909c;margin-top:10px">VPR v<?=htmlspecialchars(VPR_VERSION)?> · Secure Vehicle Search</div><div class="footer">A member of <a href="https://diranor.com" target="_blank" rel="noopener">Diranor Group</a><br><a href="admin2000.php" rel="nofollow" style="display:inline-block;margin-top:7px">Admin Login</a></div>
</div>

<script>
const currentBrand = <?= json_encode($_POST['vehicle_brand'] ?? '') ?>;
if (currentBrand) document.getElementById('vehicle_brand').value = currentBrand;
</script>
<div style="max-width:900px;margin:24px auto;padding:14px 18px;border-top:1px solid #dfe5ea;color:#66727e;font-size:12px;line-height:1.55"><strong>Important:</strong> VPR is an independent private vehicle-interest information platform. A VPR search reports information recorded on VPR at the time of search. It does not establish vehicle ownership, statutory registration or legal priority of a security interest. Where appropriate, users should also make enquiries with relevant statutory authorities.<br><a href="terms.php">Terms of Use</a> &nbsp;|&nbsp; <a href="privacy.php">Privacy Policy</a> &nbsp;|&nbsp; <a href="corrections.php">Correction / Dispute</a></div>

<div class="paymodal" id="vprPayModal">
  <div class="paycard">
    <img src="assets/vpr-logo.png" alt="VPR" style="max-width:260px;width:80%;height:auto">
    <h2 style="color:#062c52;margin-bottom:4px">Complete Vehicle Check</h2>
    <div class="payprice" id="vprPayPrice">US$0.00</div>
    <p style="margin-top:0" id="vprPayCopy">Continue to Paynow. Your VPR report is released automatically only after Paynow confirms the USD payment.</p>
    <div id="vprSpinner" class="spinner" style="display:none"></div>
    <div id="vprPayStatus" class="paystatus">Click the payment button to continue securely to Paynow.</div>
    <button type="button" id="vprOpenPaynow"><span id="vprOpenPaynowText">Pay US$0.00 with Paynow</span></button>
    <button type="button" id="vprClosePay" class="payclose">Cancel</button>
  </div>
</div>

<script>
(function(){
  const form=document.getElementById('vprSearchForm');
  const trigger=document.getElementById('vprPayButton');
  const modal=document.getElementById('vprPayModal');
  const openBtn=document.getElementById('vprOpenPaynow');
  const closeBtn=document.getElementById('vprClosePay');
  const status=document.getElementById('vprPayStatus');
  const spinner=document.getElementById('vprSpinner');
  const currency=document.getElementById('payment_currency');
  const price=document.getElementById('vprPayPrice');
  const payText=document.getElementById('vprOpenPaynowText');
  const payCopy=document.getElementById('vprPayCopy');
  const usdPrice=<?=json_encode(number_format($usdPrice,2,'.',''))?>;
  const zigPrice=<?=json_encode(number_format($zigPrice,2,'.',''))?>;
  let payment=null, popup=null, pollTimer=null;

  function setBusy(on){
    spinner.style.display=on?'block':'none';
    openBtn.disabled=on;
    closeBtn.disabled=on;
  }

  trigger.addEventListener('click',function(){
    if(!form.reportValidity()) return;
    const zig=currency.value==='ZIG';
    const amount=parseFloat(zig?zigPrice:usdPrice);
    if(amount<=0){
      // Zero-fee searches never invoke or mention Paynow.
      form.action='search_process.php';
      form.method='post';
      form.submit();
      return;
    }
    modal.style.display='flex';
    const formatted=amount.toFixed(2);
    price.textContent=zig?'ZiG '+formatted:'US$'+formatted;
    payText.textContent=zig?'Pay ZiG '+formatted+' with Paynow':'Pay US$'+formatted+' with Paynow';
    payCopy.textContent='Continue to Paynow. Your VPR report is released automatically only after Paynow confirms the USD payment.';
    status.textContent='Click the payment button to continue securely to Paynow.';
  });

  closeBtn.addEventListener('click',function(){
    modal.style.display='none';
    if(pollTimer) clearInterval(pollTimer);
  });

  async function pollPayment(){
    if(!payment) return;
    try{
      const r=await fetch('paynow_status.php?ref='+encodeURIComponent(payment.reference)+'&token='+encodeURIComponent(payment.token),{cache:'no-store'});
      const data=await r.json();
      if(!data.ok){
        if(data.message) status.textContent=data.message;
        return;
      }
      status.textContent='Payment status: '+data.status;
      if(data.paid && data.certificate_url){
        if(pollTimer) clearInterval(pollTimer);
        if(popup && !popup.closed) popup.close();
        status.innerHTML='<strong>Payment successful and verified.</strong><br>Generating your VPR Search Report Certificate...';
        window.location.href=data.certificate_url;
      }
    }catch(e){
      status.textContent='Checking payment status...';
    }
  }

  openBtn.addEventListener('click',async function(){
    if(!form.reportValidity()) return;
    if(currency.value==='ZIG'){
      const zigUrl=<?=json_encode(VPR_ZIG_PAYNOW_LINK)?>;
      popup=window.open(zigUrl,'VPRPaynowZiG','popup=yes,width=650,height=780,resizable=yes,scrollbars=yes');
      if(!popup){ status.textContent='Your browser blocked the Paynow popup. Please allow popups for VPR.'; return; }
      status.innerHTML='<strong>ZiG payment window opened.</strong><br>Complete the ZiG payment in Paynow.';
      const watcher=setInterval(function(){
        if(popup.closed){
          clearInterval(watcher);
          status.innerHTML='<strong>Paynow window closed.</strong><br>VPR is waiting for a verifiable payment confirmation. The search cannot safely be released from popup closure alone.';
        }
      },700);
      return;
    }

    setBusy(true);
    status.textContent='Preparing your secure Paynow payment...';
    try{
      const fd=new FormData(form); fd.set('payment_currency','USD');
      const r=await fetch('paynow_create.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store'});
      const raw=await r.text();
      if(!raw || !raw.trim()) throw new Error('VPR payment service returned an empty response.');
      let data; try{data=JSON.parse(raw)}catch(e){console.error(raw);throw new Error('VPR received an invalid payment response from the server.')}
      if(!r.ok || !data.ok) throw new Error(data.message || ('Payment service error '+r.status+'.'));
      payment=data;
      popup=window.open(data.browserurl,'VPRPaynow','popup=yes,width=650,height=780,resizable=yes,scrollbars=yes');
      if(!popup) throw new Error('Your browser blocked the Paynow popup. Please allow popups for VPR.');
      status.innerHTML='<strong>Paynow payment opened.</strong><br>Complete payment. Once Paynow confirms SUCCESS, VPR will automatically generate and open your report certificate.';
      if(pollTimer) clearInterval(pollTimer);
      pollTimer=setInterval(pollPayment,2000); setBusy(false); pollPayment();
    }catch(e){
      status.innerHTML='<strong>Payment could not be started.</strong><br>'+e.message;
      setBusy(false);
    }
  });

  window.addEventListener('message',function(ev){
    if(ev.origin!==window.location.origin) return;
    if(ev.data && ev.data.type==='VPR_PAYNOW_RETURN') pollPayment();
  });
})();
</script>


</body>
</html>
