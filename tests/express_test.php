<?php
require __DIR__.'/bootstrap.php';
$checks=0;
function verify(bool $ok,string $label):void {global $checks;++$checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);echo "PASS: $label\n";}
function refuses(callable $call,string $label):void {try{$call();}catch(Throwable $e){verify(true,$label);return;}verify(false,$label);}
$vehicle=vpr_vehicle_request(['registration_number'=>'EXP1234','vehicle_brand'=>'Toyota','vehicle_model'=>'Hilux']);
$contact=['payer_name'=>'Test Buyer','payer_email'=>'buyer@example.test','payer_phone'=>'0771111111','payment_method'=>'ecocash'];
verify(vpr_checkout_details($contact)['payer_phone']==='263771111111','local mobile number normalized');
foreach([['payer_email'=>'bad'],['payer_email'=>"a@b.com\r\nBcc: c@d.com"],['payer_name'=>"Test\nBuyer"],['payer_phone'=>''],['payer_phone'=>'1234'],['payment_method'=>'unsupported'],['payer_email'=>[]]]as$bad){refuses(fn()=>vpr_checkout_details(array_replace($contact,$bad)),'invalid payer details rejected');}
foreach(['USD','ZWG']as$currency){foreach(['ecocash','onemoney','innbucks','card','hosted']as$method){
    $input=array_replace($contact,['payment_method'=>$method]);$calls=0;
    $transport=function($url,$fields)use($currency,$method,&$calls){
        ++$calls;$profile=vpr_paynow_profile($currency);$mobile=vpr_is_express($method);
        verify($url==='https://www.paynow.co.zw/interface/'.($mobile?'remotetransaction':'initiatetransaction'),'correct endpoint for '.$currency.' '.$method);
        verify($fields['id']===$profile['id']&&paynow_validate_hash($fields,$profile['key']),'correct profile and signature');
        verify($fields['authemail']==='buyer@example.test'&&$fields['authname']==='Test Buyer','payer details sent before checkout');
        if($mobile)verify($fields['method']===$method&&$fields['phone']==='0771111111','mobile method and number sent');
        else verify(!isset($fields['method'])&&!isset($fields['phone']),'hosted checkout receives no invented method fields');
        $response=['status'=>'Ok','pollurl'=>'https://www.paynow.co.zw/Interface/CheckPayment/?guid=express-fixture'];
        if(!$mobile)$response['browserurl']='https://www.paynow.co.zw/Payment/ConfirmPayment/12345';
        if($method==='innbucks'){$response['authorizationcode']='123456';$response['authorizationexpires']='17-Oct-2026 23:59';}
        $response['hash']=paynow_create_hash($response,$profile['key']);return http_build_query($response);
    };
    $key=bin2hex(random_bytes(32));$p=vpr_start_checkout($pdo,$currency,$vehicle,$key,$transport,null,$input);
    verify($p['status']==='Initiated'&&$p['payment_method']===$method,'method persisted for '.$method);
    verify(vpr_is_express($method)?!$p['browserurl']&&$p['payment_instructions']:$p['browserurl']!==null,'mobile instructions or hosted redirect returned');
    if($method==='innbucks')verify(strpos($p['payment_instructions'],'123456')!==false,'InnBucks authorization instructions available');
    vpr_start_checkout($pdo,$currency,$vehicle,$key,$transport,null,$input);verify($calls===1,'retry never duplicates initiation');
    refuses(fn()=>vpr_start_checkout($pdo,$currency,$vehicle,$key,$transport,null,array_replace($input,['payer_email'=>'different@example.test'])),'changed payer cannot reuse request key');
    $json=json_encode(vpr_checkout_response($p));verify(strpos($json,'buyer@example.test')===false&&strpos($json,'263771111111')===false,'response omits private payer data');
    $data=['reference'=>$p['merchant_reference'],'amount'=>$p['expected_amount'],'paynowreference'=>'EXP-'.$p['id'],'status'=>'Paid'];
    $data['hash']=paynow_create_hash($data,vpr_paynow_profile($currency)['key']);
    vpr_apply_payment_data($pdo,$p['merchant_reference'],$data,'express-test');
    verify(!empty(vpr_payment($pdo,$p['merchant_reference'])['search_id']),'verified express or hosted payment issues certificate');
}}
echo "Completed $checks express checks.\n";
