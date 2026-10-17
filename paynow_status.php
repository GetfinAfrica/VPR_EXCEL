<?php
require_once __DIR__ . '/db.php';
security_headers();
header('Content-Type: application/json; charset=UTF-8');

$ref=clean($_GET['ref']??'');
$token=clean($_GET['token']??'');
if(!$ref || !$token){ echo json_encode(['ok'=>false,'message'=>'Missing payment reference.']); exit; }

$pdo=db();
$stmt=$pdo->prepare("SELECT * FROM payments WHERE merchant_reference=? LIMIT 1");
$stmt->execute([$ref]);
$payment=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$payment || !hash_equals((string)$payment['return_token'],$token)){
    echo json_encode(['ok'=>false,'message'=>'Payment reference not found.']); exit;
}

try{
    if(!empty($payment['pollurl']) && !paynow_is_paid($payment['status'])){
        $confirmed=paynow_poll_status($payment['pollurl']);
        $confirmedRef=clean(paynow_get($confirmed,'reference',''));
        if($confirmedRef && hash_equals($ref,$confirmedRef)){
            $status=(string)paynow_get($confirmed,'status',$payment['status']);
            $amount=(string)paynow_get($confirmed,'amount',$payment['amount']);
            $payref=(string)paynow_get($confirmed,'paynowreference',$payment['paynow_reference']);
            if(!payment_amount_ok($amount)) $status='AmountMismatch';
            $paidAt=paynow_is_paid($status)?date('Y-m-d H:i:s'):null;

            $pdo->prepare("UPDATE payments SET paynow_reference=?,amount=?,status=?,paid_at=COALESCE(paid_at,?),last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE merchant_reference=?")
                ->execute([$payref,$amount,$status,$paidAt,$ref]);

            if(paynow_is_paid($status)) finalize_vpr_search_from_payment($ref);
        }
    }
}catch(Throwable $e){
    $pdo->prepare("UPDATE payments SET last_error=?,updated_at=CURRENT_TIMESTAMP WHERE merchant_reference=?")
        ->execute([mb_substr($e->getMessage(),0,1000),$ref]);
}

$stmt=$pdo->prepare("SELECT p.*,s.access_token FROM payments p LEFT JOIN searches s ON s.id=p.search_id WHERE p.merchant_reference=? LIMIT 1");
$stmt->execute([$ref]);
$payment=$stmt->fetch(PDO::FETCH_ASSOC);

$paid=paynow_is_paid($payment['status']) && !empty($payment['search_id']);
$url=null;
if($paid){
    $url='certificate.php?id='.(int)$payment['search_id'].'&token='.urlencode((string)$payment['access_token']);
}
echo json_encode([
    'ok'=>true,
    'status'=>$payment['status'],
    'paid'=>$paid,
    'certificate_url'=>$url,
    'message'=>$payment['last_error']?:null
]);
?>