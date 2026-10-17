<?php
require_once __DIR__ . '/db.php';
security_headers();
secure_session_start();
header('Cache-Control: no-store');
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
[hidden]{display:none!important}*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--ink)}
.wrap{max-width:820px;margin:0 auto;padding:30px 20px 54px}.brand{text-align:center;margin-bottom:20px}
.logoimg{width:min(560px,92%);height:auto;display:block;margin:0 auto 8px}.sub{font-size:14px;color:var(--muted);letter-spacing:.02em}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:28px;box-shadow:0 10px 32px rgba(6,44,82,.08)}
h1{font-size:27px;color:var(--navy);margin:0 0 8px}p{line-height:1.55}
label{display:block;font-weight:700;margin:16px 0 6px}.optional{font-weight:400;color:var(--muted)}
input,select{width:100%;padding:13px 14px;border:1px solid #cbd5df;border-radius:9px;font-size:16px;background:#fff}
input:focus,select:focus{outline:2px solid rgba(184,138,38,.25);border-color:var(--gold)}
.currency-picker{border:0;padding:0;margin:0}.currency-picker legend{font-weight:700;margin-bottom:10px}.currency-options{display:grid;width:min(100%,300px);grid-template-columns:1fr 1fr;gap:4px;padding:4px;background:#eef2f6;border:1px solid var(--line);border-radius:13px}.currency-option{position:relative;margin:0;cursor:pointer}.currency-option input{position:absolute;opacity:0;width:1px;height:1px;padding:0}.currency-option span{display:flex;flex-direction:column;gap:3px;padding:7px 10px;border:2px solid transparent;border-radius:9px;color:var(--muted);transition:background .15s,color .15s}.currency-option strong{font-size:15px}.currency-option small{font-size:11px;font-weight:400}.currency-option input:checked+span{background:var(--navy);border-color:var(--navy);color:#fff;box-shadow:0 3px 9px rgba(6,44,82,.16)}.currency-option input:checked+span strong:after{content:' ✓';color:var(--gold2)}.currency-option input:focus-visible+span{outline:3px solid var(--gold);outline-offset:2px}.currency-summary{margin:12px 0 0;padding:11px 14px;border-left:3px solid var(--gold);background:#f5f8fb;color:var(--navy);font-size:14px}
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
<?php require_once __DIR__.'/ui.php'; vpr_ui_assets(); ?></head>
<body>
<div class="wrap">
  <?php vpr_page_heading('Vehicle check','Vehicle Pledge Registry'); ?>
  <div class="brand">
    <div style="display:inline-block;margin-top:8px;padding:5px 9px;border-radius:999px;background:#e9f5ef;color:#1f6b47;font-size:11px;font-weight:700"><?=request_is_https() ? '🔒 HTTPS Secure Connection' : 'Local development connection'?></div>
  </div>

  <div class="card">
    <div class="vpr-tabs" role="tablist" aria-label="Vehicle check steps"><button type="button" id="vehicleTab" role="tab" aria-selected="true" aria-controls="vehicleStage">1. Vehicle</button><button type="button" id="paymentTab" role="tab" aria-selected="false" aria-controls="paymentStage" tabindex="-1">2. Payment</button></div>
    <p id="stageProgress" aria-live="polite">Step 1 of 2 — Vehicle</p>
    <h2 id="stageTitle" style="color:#062c52;margin:0">Vehicle details</h2>
    <p id="stageIntro">Enter the vehicle details below. Registration number is the unique VPR reference and is required for every search.</p>
<form method="post" action="search_process.php" autocomplete="off" id="vprSearchForm" data-usd-mode="<?=htmlspecialchars(vpr_paynow_profile('USD',false)['environment'])?>" data-zwg-mode="<?=htmlspecialchars(vpr_paynow_profile('ZWG',false)['environment'])?>" data-usd-price="<?=htmlspecialchars(vpr_price_amount($pdo,'USD'))?>" data-zwg-price="<?=htmlspecialchars(vpr_price_amount($pdo,'ZWG'))?>">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
<input type="hidden" name="checkout_key" value="<?=bin2hex(random_bytes(16))?>">
<fieldset id="vehicleStage" role="tabpanel" aria-labelledby="vehicleTab" style="border:0;padding:0;margin:0"><div class="vpr-form-grid">
<div class="vpr-col-6"><label for="registration_number">Registration Number</label>
      <input id="registration_number" name="registration_number" required maxlength="7" pattern="[A-Za-z]{3}[0-9]{4}" title="Enter exactly 3 letters followed by 4 numbers, e.g. AAA1111" placeholder="e.g. AAA1111" value="<?= htmlspecialchars($_POST['registration_number'] ?? '') ?>"></div>
<div class="vpr-col-6"><label for="chassis_number">Chassis Number <span class="optional">(optional)</span></label>
      <input id="chassis_number" name="chassis_number" placeholder="Enter if available" value="<?= htmlspecialchars($_POST['chassis_number'] ?? '') ?>"></div>
<div class="vpr-col-6"><label for="vehicle_brand">Vehicle Brand</label>
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
      </select></div>
<div class="vpr-col-6"><label for="vehicle_model">Vehicle Model</label>
      <input id="vehicle_model" name="vehicle_model" required placeholder="e.g. Fortuner, Hilux, Prado, NV350" value="<?= htmlspecialchars($_POST['vehicle_model'] ?? '') ?>">
      <div class="hint">Enter the model as fully as possible, e.g. “NV350” rather than “NV”.</div></div>
</div><div class="vpr-actions"><button type="button" id="vprContinue">Continue to payment</button></div></fieldset>
<fieldset role="tabpanel" aria-labelledby="paymentTab" id="paymentStage" style="border:0;padding:0;margin:0" hidden disabled>
      <div class="field" style="margin-top:14px">
<fieldset class="currency-picker">
<legend>Payment currency</legend>
<input type="hidden" id="payment_currency" name="payment_currency" value="USD">
<div class="currency-options">
<label class="currency-option"><input type="radio" id="currencyUSD" name="currency_choice" value="USD" checked aria-describedby="currencySummary"><span><strong>USD</strong><small>US dollars · <?=htmlspecialchars(vpr_price_amount($pdo,'USD'))?></small></span></label>
<label class="currency-option"><input type="radio" id="currencyZWG" name="currency_choice" value="ZWG" aria-describedby="currencySummary"><span><strong>ZWG</strong><small>ZiG · <?=htmlspecialchars(vpr_price_amount($pdo,'ZWG'))?></small></span></label>
</div>
<p id="currencySummary" class="currency-summary" role="status" aria-live="polite">You will pay USD <?=htmlspecialchars(vpr_price_amount($pdo,'USD'))?>.</p>
</fieldset>
</div>
<fieldset id="checkoutDetails" style="border:0;padding:0;margin:12px 0"><legend style="font-weight:bold">Payer details</legend><div class="vpr-form-grid">
<div class="vpr-col-6"><label for="payer_name">Full name</label><input id="payer_name" name="payer_name" maxlength="100" autocomplete="name" required></div>
<div class="vpr-col-6"><label for="payer_email">Email address</label><input type="email" id="payer_email" name="payer_email" maxlength="254" autocomplete="email" required></div>
<div class="vpr-col-6"><label for="payment_method">Payment method</label>
<select id="payment_method" name="payment_method">
<option value="hosted">Choose on Paynow</option><option value="card">Visa / Mastercard — secure Paynow checkout</option>
<option value="ecocash">EcoCash</option><option value="onemoney">OneMoney</option><option value="innbucks">InnBucks</option></select></div>
<div class="vpr-col-6"><label for="payer_phone">Mobile number <span id="phoneHint" class="optional">(optional)</span></label>
<input type="tel" id="payer_phone" name="payer_phone" maxlength="20" autocomplete="tel" placeholder="Enter mobile number"></div>
<div class="vpr-col-6" id="testScenarioBox" hidden>
<label for="test_scenario">Paynow test scenario</label>
<select id="test_scenario" name="test_scenario"><option value="manual">Enter a number manually</option><option value="0771111111">Success — 0771111111</option><option value="0772222222">Delayed success — 0772222222</option><option value="0773333333">Customer cancels — 0773333333</option><option value="0774444444">Insufficient funds — 0774444444</option></select>
<p class="hint">Test mode: use the integration owner's email. The selected scenario fills the mobile number below; no real money moves.</p></div>
<p class="hint vpr-col-12" id="methodHint">Enter card details only on Paynow. Never enter a PIN or card number here.</p>
</div></fieldset>
<div class="vpr-actions"><button type="button" id="vprBack" class="payclose">Back to vehicle</button>
<button type="button" id="vprPayButton">Review payment</button></div><div class="hint" style="text-align:center;margin-top:7px" id="priceHint">Current search fees are set by VPR administration. If the selected fee is 0.00, the check proceeds immediately with no payment step.</div>
    </fieldset>
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
const currentBrand = <?= json_encode($_POST['vehicle_brand'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
if (currentBrand) document.getElementById('vehicle_brand').value = currentBrand;
</script>
<div style="max-width:900px;margin:24px auto;padding:14px 18px;border-top:1px solid #dfe5ea;color:#66727e;font-size:12px;line-height:1.55"><strong>Important:</strong> VPR is an independent private vehicle-interest information platform. A VPR search reports information recorded on VPR at the time of search. It does not establish vehicle ownership, statutory registration or legal priority of a security interest. Where appropriate, users should also make enquiries with relevant statutory authorities.<br><a href="terms.php">Terms of Use</a> &nbsp;|&nbsp; <a href="privacy.php">Privacy Policy</a> &nbsp;|&nbsp; <a href="corrections.php">Correction / Dispute</a></div>

<div class="paymodal" id="vprPayModal">
  <div class="paycard" role="dialog" aria-modal="true" aria-labelledby="vprPayTitle">
    <?php vpr_page_heading('Complete vehicle check','Secure payment',2,'vprPayTitle'); ?>
    <div class="payprice" id="vprPayPrice">US$0.00</div>
    <p style="margin-top:0" id="vprPayCopy">Continue to Paynow. Your VPR report is released automatically only after Paynow confirms the selected payment.</p>
    <div id="vprPayOutcome" class="vpr-success" hidden role="status">✓ Payment confirmed by Paynow</div>
    <div id="vprSpinner" class="spinner" style="display:none" aria-hidden="true"></div>
    <div id="vprProgress" hidden role="status" aria-live="polite"><strong id="vprProgressTitle">Waiting for payment approval</strong><p class="hint">Keep this page open. We check Paynow every two seconds. You can complete the checkout after payment is verified.</p></div>
    <div id="vprPayStatus" class="paystatus" role="status" aria-live="polite">Click the payment button to continue securely to Paynow.</div>
    <a id="innbucksOpen" hidden>Open InnBucks app</a>
    <div class="vpr-actions"><button type="button" id="vprOpenPaynow"><span id="vprOpenPaynowText">Pay US$0.00 with Paynow</span></button>
    <button type="button" id="vprClosePay" class="payclose">Back to payment details</button></div>
  </div>
</div>

<script src="assets/checkout.js?v=<?=filemtime(__DIR__.'/assets/checkout.js')?>" defer></script>


</body>
</html>
