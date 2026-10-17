<?php
require __DIR__ . '/bootstrap.php';
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks; ++$checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $message);
    echo 'PASS: ' . $message . "\n";
}
function rejected(callable $operation, string $message): void {
    try { $operation(); } catch (Throwable $e) { check(true, $message); return; }
    check(false, $message);
}
function details(string $reg = 'TST1234'): array {
    return vpr_vehicle_request(['registration_number'=>$reg,'vehicle_brand'=>'Toyota','vehicle_model'=>'Hilux']);
}
function mock_init(string $currency, array &$captured): callable {
    return function ($url, $fields) use ($currency, &$captured) {
        $captured[] = $fields;
        $profile = vpr_paynow_profile($currency);
        check($fields['id'] === $profile['id'], $currency . ' uses its own integration ID');
        check(paynow_validate_hash($fields, $profile['key']), $currency . ' initiation signed with its own key');
        $response = ['status'=>'Ok','browserurl'=>'https://www.paynow.co.zw/Payment/ConfirmPayment/12345',
            'pollurl'=>'https://www.paynow.co.zw/Interface/CheckPayment/?guid=fixture'];
        $response['hash'] = paynow_create_hash($response, $profile['key']);
        return http_build_query($response);
    };
}
function notification(array $payment, array $replace = [], ?string $key = null): array {
    $data = array_replace(['reference'=>$payment['merchant_reference'],'paynowreference'=>'PN-' . $payment['id'],
        'amount'=>$payment['expected_amount'],'status'=>'Paid'], $replace);
    $data['hash'] = paynow_create_hash($data, $key ?? vpr_payment_profile($payment)['key']);
    return $data;
}

check(vpr_money_minor('123.45') === 12345, 'decimal-safe money conversion');
foreach (['-1.00','1e2','1.001','NaN','1,00','9999999999','01.00',''] as $bad) {
    rejected(fn()=>vpr_money_minor($bad), 'reject malformed amount ' . $bad);
}
rejected(fn()=>vpr_currency('EUR'), 'unsupported currency rejected');
rejected(fn()=>vpr_currency([]), 'array currency rejected');
foreach (['http://www.paynow.co.zw/Interface/CheckPayment/?guid=x','https://www.paynow.co.zw.evil.test/Interface/CheckPayment/',
    'https://user@www.paynow.co.zw/Interface/CheckPayment/','https://127.0.0.1/Interface/CheckPayment/',
    'https://www.paynow.co.zw:444/Interface/CheckPayment/','https://www.paynow.co.zw/Interface/CheckPayment/../trace'] as $bad) {
    rejected(fn()=>vpr_paynow_url($bad,'poll'), 'SSRF URL rejected');
}
rejected(fn()=>paynow_parse_response('status=Paid&STATUS=Cancelled'), 'duplicate response keys rejected');
$official = 'status=Ok&browserurl=https%3a%2f%2fstaging.paynow.co.zw%2fPayment%2fConfirmPayment%2f9510&pollurl=https%3a%2f%2fstaging.paynow.co.zw%2fInterface%2fCheckPayment%2f%3fguid%3dc7ed41da-0159-46da-b428-69549f770413&paynowreference=9510&hash=750DD0B0DF374678707BB5AF915AF81C228B9058AD57BB7120569EC68BBB9C2EFC1B26C6375D2BC562AC909B3CD6B2AF1D42E1A5E479FFAC8F4FB3FDCE71DF4D';
check(paynow_validate_hash(paynow_parse_response($official),'3e9fed89-60e1-4ce5-ab6e-6b1eb2d4f977'), 'official Paynow hash test vector');

$captured = [];
foreach (['USD','ZWG'] as $currency) {
    $payment = vpr_start_checkout($pdo,$currency,details(),bin2hex(random_bytes(32)),mock_init($currency,$captured));
    check($payment['status'] === 'Initiated', $currency . ' creates transaction-specific checkout');
    $initial = $payment['expected_amount'];
    vpr_set_setting($pdo,'price_'.$currency,'9.99');
    rejected(fn()=>vpr_issue_free_search($pdo,$currency,details()), $currency . ' direct paid-search bypass blocked');
    foreach ([['amount'=>'0.01'],['amount'=>''],['amount'=>'1e2'],['reference'=>'VPR-wrong'],['currency'=>'EUR'],['id'=>'wrong']] as $bad) {
        rejected(fn()=>vpr_apply_payment_data($pdo,$payment['merchant_reference'],notification($payment,$bad),'test'), $currency . ' mismatched payment rejected');
        check(empty(vpr_payment($pdo,$payment['merchant_reference'])['search_id']), 'invalid notification issues no search');
    }
    rejected(fn()=>vpr_apply_payment_data($pdo,$payment['merchant_reference'],notification($payment,[],'other-key'),'test'), $currency . ' wrong integration signature rejected');
    $missing = notification($payment); unset($missing['amount']);
    $missing['hash']=paynow_create_hash($missing,vpr_payment_profile($payment)['key']);
    rejected(fn()=>vpr_apply_payment_data($pdo,$payment['merchant_reference'],$missing,'test'), $currency . ' missing amount rejected');
    vpr_apply_payment_data($pdo,$payment['merchant_reference'],notification($payment,['status'=>'Sent']),'test');
    check(vpr_payment($pdo,$payment['merchant_reference'])['status']==='Pending', $currency . ' pending status has no certificate');
    vpr_apply_payment_data($pdo,$payment['merchant_reference'],notification($payment,['status'=>'Awaiting Delivery']),'test');
    $paid = vpr_payment($pdo,$payment['merchant_reference']);
    check($paid['status']==='Paid' && $paid['search_id'] && $paid['expected_amount']===$initial && $paid['amount']===$initial, $currency . ' original price retained after admin price change');
    vpr_apply_payment_data($pdo,$payment['merchant_reference'],notification($payment),'duplicate');
    check(vpr_payment($pdo,$payment['merchant_reference'])['search_id']===$paid['search_id'], $currency . ' duplicate callback idempotent');
    vpr_apply_payment_data($pdo,$payment['merchant_reference'],notification($payment,['status'=>'Created']),'stale');
    check(vpr_payment($pdo,$payment['merchant_reference'])['status']==='Paid', $currency . ' stale update cannot reverse paid state');
    $again = vpr_start_checkout($pdo,$currency,details(),$payment['request_key'],mock_init($currency,$captured));
    check($again['id']===$payment['id'], $currency . ' checkout retry reuses transaction');
    rejected(fn()=>vpr_start_checkout($pdo,$currency,details('TST9999'),$payment['request_key'],mock_init($currency,$captured)), 'same request key cannot change vehicle');
    $cancel = vpr_start_checkout($pdo,$currency,details(),bin2hex(random_bytes(32)),mock_init($currency,$captured));
    vpr_apply_payment_data($pdo,$cancel['merchant_reference'],notification($cancel,['status'=>'Cancelled']),'test');
    vpr_apply_payment_data($pdo,$cancel['merchant_reference'],notification($cancel),'late');
    $cancelled=vpr_payment($pdo,$cancel['merchant_reference']);
    check($cancelled['status']==='Cancelled' && !$cancelled['search_id'] && $cancelled['reconciliation_note'], 'late success on cancelled transaction flagged without issuance');
    $unknown=vpr_start_checkout($pdo,$currency,details(),bin2hex(random_bytes(32)),mock_init($currency,$captured));
    vpr_apply_payment_data($pdo,$unknown['merchant_reference'],notification($unknown,['status'=>'Mystery']),'test');
    check(!vpr_payment($pdo,$unknown['merchant_reference'])['search_id'], 'unknown provider state never authorizes search');
    vpr_set_setting($pdo,'price_'.$currency,'0.00');
    check(vpr_issue_free_search($pdo,$currency,details()) > 0, $currency . ' server-authorized free search works');
    vpr_set_setting($pdo,'price_'.$currency,'1.00');
}
check(count($captured)===6, 'duplicate checkout attempts never repeat provider initiation');
$uncertain = vpr_start_checkout($pdo,'USD',details(),bin2hex(random_bytes(32)),function(){throw new RuntimeException('Timeout');});
check($uncertain['status']==='Review' && !$uncertain['search_id'], 'initiation timeout retained for reconciliation');
vpr_start_checkout($pdo,'USD',details(),$uncertain['request_key'],function(){throw new RuntimeException('Must not be called');});
check(true,'ambiguous initiation retry never creates a second provider charge');

$concurrent=vpr_start_checkout($pdo,'USD',details(),bin2hex(random_bytes(32)),mock_init('USD',$captured));
$searchesBefore=(int)$pdo->query('SELECT COUNT(*) FROM searches')->fetchColumn();
$workers=[];
for($i=0;$i<4;$i++) {
    $pipes=[];
    $proc=proc_open([PHP_BINARY,__DIR__.'/payment_worker.php',$concurrent['merchant_reference']], [1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $workers[]=[$proc,$pipes];
}
foreach($workers as [$proc,$pipes]) {
    $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($proc)===0,'concurrent callback completed: '.trim($out.$err));
}
$q=$pdo->prepare('SELECT COUNT(*) FROM payment_events WHERE merchant_reference=? AND source=?');
$q->execute([$concurrent['merchant_reference'],'worker']);
check((int)$q->fetchColumn()===4 && vpr_payment($pdo,$concurrent['merchant_reference'])['search_id'], 'concurrent notifications converge on one search');
check((int)$pdo->query('SELECT COUNT(*) FROM searches')->fetchColumn()===$searchesBefore+1, 'concurrent notifications create exactly one search row');
rejected(fn()=>vpr_start_checkout($pdo,'USD',details(),bin2hex(random_bytes(32)),mock_init('USD',$captured),'0.01'), 'stale or client-modified price quote cannot initiate payment');
putenv('VPR_PAYNOW_USD_ENABLED=0');
rejected(fn()=>vpr_start_checkout($pdo,'USD',details(),bin2hex(random_bytes(32))), 'disabled profile prevents new checkout');
$p=vpr_payment($pdo,$concurrent['merchant_reference']);
vpr_apply_payment_data($pdo,$p['merchant_reference'],notification($p,['paynowreference'=>'PN-CONCURRENT']),'disabled-profile-recovery');
check(vpr_payment($pdo,$p['merchant_reference'])['search_id']===$p['search_id'], 'disabled new checkout does not block pending reconciliation');
putenv('VPR_PAYNOW_USD_ENABLED=1');
echo "Completed $checks checks. Fixture: $fixture\n";
