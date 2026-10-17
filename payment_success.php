<?php
require_once __DIR__.'/db.php';
require_once __DIR__.'/ui.php';
security_headers();header('Cache-Control: no-store');
$ref=is_string($_GET['ref']??null)?$_GET['ref']:'';
$token=is_string($_GET['token']??null)?$_GET['token']:'';
$pdo=db();$payment=$ref!==''?vpr_payment($pdo,$ref):null;
$authorized=$payment && $token!=='' && hash_equals((string)$payment['return_token'],$token);
$paid=$authorized && $payment['status']==='Paid' && !empty($payment['verified_at']) && !empty($payment['search_id']);
$certificate=null;
if($paid){
    $q=$pdo->prepare('SELECT access_token FROM searches WHERE id=?');$q->execute([$payment['search_id']]);$access=$q->fetchColumn();
    if($access)$certificate='certificate.php?id='.(int)$payment['search_id'].'&token='.urlencode($access);
}
if(!$authorized)http_response_code(403);
elseif(!$certificate)http_response_code(409);
$methodNames=['ecocash'=>'EcoCash','onemoney'=>'OneMoney','innbucks'=>'InnBucks','card'=>'Visa / Mastercard','hosted'=>'Paynow'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VPR Payment Acknowledgement</title><?php vpr_ui_assets(); ?></head>
<body class="vpr-payment-page"><main class="vpr-payment-box">
<?php vpr_page_heading($certificate?'Payment successful':'Payment acknowledgement','Vehicle Pledge Registry'); ?>
<?php if($certificate): ?>
<div class="vpr-success" role="status">✓ VPR acknowledges your successful payment.</div>
<p>Thank you. Your payment has been verified and your vehicle certificate is ready to view.</p>
<dl class="vpr-receipt">
<div><dt>Amount paid</dt><dd><?=htmlspecialchars($payment['currency'].' '.$payment['expected_amount'])?></dd></div>
<div><dt>Payment method</dt><dd><?=htmlspecialchars($methodNames[$payment['payment_method']]??'Paynow')?></dd></div>
<div><dt>Vehicle registration</dt><dd><?=htmlspecialchars($payment['registration_number'])?></dd></div>
<div><dt>Paynow reference</dt><dd><?=htmlspecialchars((string)$payment['paynow_reference'])?></dd></div>
<div class="vpr-wide"><dt>VPR payment reference</dt><dd><?=htmlspecialchars($payment['merchant_reference'])?></dd></div>
</dl>
<div class="vpr-actions"><a id="viewCertificate" href="<?=htmlspecialchars($certificate)?>" target="_blank" rel="noopener" aria-label="View certificate (opens in a new tab)">View certificate ↗</a><a class="vpr-secondary" href="index.php">New vehicle check</a></div>
<p style="font-size:12px;color:#687481">The certificate opens in a new tab. This acknowledgement stays open here.</p>
<script>try{sessionStorage.removeItem('vpr.pendingCheckout');sessionStorage.removeItem('vpr.checkoutRequest');}catch(e){}</script>
<?php elseif($authorized): ?>
<p>VPR has not yet verified a successful payment for this reference. Your certificate is available only after verification.</p>
<div class="vpr-actions"><a href="paynow_return.php?ref=<?=urlencode($ref)?>&amp;token=<?=urlencode($token)?>">Return to payment status</a><a class="vpr-secondary" href="index.php">Vehicle check</a></div>
<?php else: ?>
<p>Payment access denied. Use the private link from your checkout to view your payment acknowledgement.</p>
<div class="vpr-actions"><a href="index.php">Vehicle check</a></div>
<?php endif; ?>
</main></body></html>
