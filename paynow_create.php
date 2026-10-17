<?php
ob_start();
header('Content-Type: application/json; charset=UTF-8');

function vpr_json_exit($ok, $message='', array $extra=[], $http=200) {
    if (!headers_sent()) {
        http_response_code($http);
        header('Content-Type: application/json; charset=UTF-8');
    }
    while (ob_get_level() > 0) { @ob_end_clean(); }
    echo json_encode(array_merge([
        'ok' => (bool)$ok,
        'message' => (string)$message
    ], $extra), JSON_UNESCAPED_SLASHES);
    exit;
}

register_shutdown_function(function() {
    $e = error_get_last();
    if (!$e) return;
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($e['type'], $fatalTypes, true)) return;

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }
    while (ob_get_level() > 0) { @ob_end_clean(); }
    echo json_encode([
        'ok' => false,
        'message' => 'VPR payment service encountered a server error.',
        'technical' => basename($e['file']).' line '.$e['line'].': '.$e['message']
    ], JSON_UNESCAPED_SLASHES);
});

try {
    require_once __DIR__ . '/db.php';
    security_headers();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        vpr_json_exit(false, 'Invalid request method.', [], 405);
    }
    if (strtoupper((string)($_POST['payment_currency'] ?? 'USD')) !== 'USD') {
        vpr_json_exit(false, 'This integration endpoint accepts USD payments only.', [], 400);
    }

    $reg=normalise_reg($_POST['registration_number']??'');
    $brand=clean($_POST['vehicle_brand']??'');
    $model=clean($_POST['vehicle_model']??'');
    $chassis=normalise_chassis($_POST['chassis_number']??'');

    if(!$reg || !$brand || !$model || !valid_registration($reg)){
        vpr_json_exit(false, 'Registration must be exactly 3 letters followed by 4 numbers, e.g. AAA1111.', [], 400);
    }

    $reference='VPR-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(8)));
    $returnToken=bin2hex(random_bytes(24));
    $baseurl=vpr_public_base_url();
    $returnurl=$baseurl.'/paynow_return.php?ref='.urlencode($reference).'&token='.urlencode($returnToken);
    $resulturl=$baseurl.'/paynow_result.php?ref='.urlencode($reference);

    $pdo=db();

    // Confirm the payments table is available on upgraded/live databases.
    $exists=$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='payments'")->fetchColumn();
    if(!$exists){
        throw new Exception('Payments database table is not available. Database upgrade did not complete.');
    }

    $stmt=$pdo->prepare("INSERT INTO payments(
        merchant_reference,amount,currency,status,return_token,
        registration_number,vehicle_brand,vehicle_model,chassis_number,
        created_at,updated_at
    ) VALUES(?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    $stmt->execute([
        $reference,
        number_format(vpr_price($pdo,'USD'),2,'.',''),
        VPR_SEARCH_CURRENCY,
        'Created',
        $returnToken,
        $reg,$brand,$model,$chassis
    ]);

    $fields=[
        'id'=>VPR_PAYNOW_INTEGRATION_ID,
        'reference'=>$reference,
        'amount'=>number_format(vpr_price($pdo,'USD'),2,'.',''),
        'additionalinfo'=>'VPR Vehicle Interest Search',
        'returnurl'=>$returnurl,
        'resulturl'=>$resulturl,
        'status'=>'Message'
    ];
    $fields['hash']=paynow_create_hash($fields);

    try{
        $raw=paynow_http_post(VPR_PAYNOW_INIT_URL,$fields);
        if(trim((string)$raw)===''){
            throw new Exception('Paynow returned an empty response.');
        }

        $data=paynow_parse_response($raw);

        if(strtolower((string)paynow_get($data,'status',''))!=='ok'){
            $error=(string)paynow_get($data,'error','Paynow did not accept the transaction.');
            throw new Exception($error);
        }

        if(!paynow_validate_hash($data)){
            throw new Exception('Paynow response signature could not be verified.');
        }

        $browserurl=(string)paynow_get($data,'browserurl','');
        $pollurl=(string)paynow_get($data,'pollurl','');
        if(!$browserurl || !$pollurl){
            throw new Exception('Paynow did not return the required payment URL.');
        }

        $pdo->prepare("UPDATE payments
            SET pollurl=?,status='Initiated',last_error=NULL,updated_at=CURRENT_TIMESTAMP
            WHERE merchant_reference=?")
            ->execute([$pollurl,$reference]);

        vpr_json_exit(true,'Payment created.',[
            'reference'=>$reference,
            'token'=>$returnToken,
            'browserurl'=>$browserurl
        ]);
    } catch(Throwable $gatewayError){
        try {
            $pdo->prepare("UPDATE payments
                SET status='InitiationError',last_error=?,updated_at=CURRENT_TIMESTAMP
                WHERE merchant_reference=?")
                ->execute([mb_substr($gatewayError->getMessage(),0,1000),$reference]);
        } catch(Throwable $ignore) {}

        vpr_json_exit(false,$gatewayError->getMessage(),[
            'reference'=>$reference
        ],502);
    }
} catch(Throwable $e) {
    vpr_json_exit(false, $e->getMessage(), [
        'technical' => get_class($e)
    ], 500);
}
?>