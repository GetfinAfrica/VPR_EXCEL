<?php
require_once __DIR__ . '/db.php';

$ref=clean($_GET['ref']??'');
if(!$ref){ http_response_code(400); exit; }

$data=$_POST;
if(!$data || !paynow_validate_hash($data)){ http_response_code(403); exit; }

$signedRef=clean(paynow_get($data,'reference',''));
if(!$signedRef || !hash_equals($ref,$signedRef)){ http_response_code(403); exit; }

$pdo=db();
$stmt=$pdo->prepare("SELECT * FROM payments WHERE merchant_reference=? LIMIT 1");
$stmt->execute([$ref]);
$payment=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$payment){ http_response_code(404); exit; }

try{
    $pollurl=(string)paynow_get($data,'pollurl',$payment['pollurl']);
    $confirmed=paynow_poll_status($pollurl);
    $confirmedRef=clean(paynow_get($confirmed,'reference',''));
    if(!$confirmedRef || !hash_equals($ref,$confirmedRef)) throw new Exception('Paynow reference mismatch.');

    $status=(string)paynow_get($confirmed,'status','Unknown');
    $amount=(string)paynow_get($confirmed,'amount',$payment['amount']);
    $payref=(string)paynow_get($confirmed,'paynowreference','');

    if(!payment_amount_ok($amount)) $status='AmountMismatch';
    $paidAt=paynow_is_paid($status)?date('Y-m-d H:i:s'):null;

    $pdo->prepare("UPDATE payments SET paynow_reference=?,amount=?,status=?,pollurl=?,paid_at=COALESCE(paid_at,?),last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE merchant_reference=?")
        ->execute([$payref,$amount,$status,$pollurl,$paidAt,$ref]);

    if(paynow_is_paid($status)) finalize_vpr_search_from_payment($ref);
}catch(Throwable $e){
    $pdo->prepare("UPDATE payments SET last_error=?,updated_at=CURRENT_TIMESTAMP WHERE merchant_reference=?")
        ->execute([mb_substr($e->getMessage(),0,1000),$ref]);
}
http_response_code(200);
?>