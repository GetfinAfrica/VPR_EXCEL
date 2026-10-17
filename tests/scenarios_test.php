<?php
require __DIR__.'/bootstrap.php';$checks=0;
function scenario_check(bool $ok,string $label):void {global $checks;++$checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);echo "PASS: $label\n";}
$vehicle=vpr_vehicle_request(['registration_number'=>'SIM1234','vehicle_brand'=>'Toyota','vehicle_model'=>'Scenario test']);
foreach(['USD','ZWG']as$ccy){foreach(['0771111111','0772222222','0773333333','0774444444']as$number){
 $contact=['payer_name'=>'Test Buyer','payer_email'=>'owner@example.test','payer_phone'=>$number,'payment_method'=>'ecocash'];$calls=0;
 $transport=function($url,$fields)use($ccy,$number,&$calls){
  ++$calls;scenario_check($fields['phone']===$number,'selected scenario reaches Paynow unchanged');
  if($number==='0774444444')return 'status=Error&error=Insufficient+balance';
  $data=['status'=>'Ok','pollurl'=>'https://www.paynow.co.zw/Interface/CheckPayment/?guid=scenario'];$data['hash']=paynow_create_hash($data,vpr_paynow_profile($ccy)['key']);return http_build_query($data);
 };
 $key=bin2hex(random_bytes(32));$p=vpr_start_checkout($pdo,$ccy,$vehicle,$key,$transport,null,$contact);
 if($number==='0774444444'){
  scenario_check($p['status']==='Failed'&&$p['last_error']==='InsufficientFunds'&&!$p['search_id'],'insufficient funds is a definite decline, no certificate');
  scenario_check(stripos(vpr_checkout_response($p)['message'],'insufficient')!==false,'decline explanation returned to the browser');
  vpr_start_checkout($pdo,$ccy,$vehicle,$key,$transport,null,$contact);scenario_check($calls===1,'retry of declined transaction does not create another request');continue;
 }
 $status=$number==='0773333333'?'Cancelled':($number==='0772222222'?'Created':'Paid');
 $data=['reference'=>$p['merchant_reference'],'amount'=>$p['expected_amount'],'paynowreference'=>'SIM-'.$p['id'],'status'=>$status];
 $data['hash']=paynow_create_hash($data,vpr_paynow_profile($ccy)['key']);vpr_apply_payment_data($pdo,$p['merchant_reference'],$data,'scenario-test');
 $current=vpr_payment($pdo,$p['merchant_reference']);
 if($status==='Paid')scenario_check(!empty($current['search_id']),'success issues a verified certificate');
 else scenario_check(empty($current['search_id']),'pending or cancellation issues no certificate');
 if($status==='Created'){
  scenario_check($current['status']==='Pending','delayed success remains pending before confirmation');
  $data['status']='Paid';$data['hash']=paynow_create_hash($data,vpr_paynow_profile($ccy)['key']);vpr_apply_payment_data($pdo,$p['merchant_reference'],$data,'delayed');
  scenario_check(!empty(vpr_payment($pdo,$p['merchant_reference'])['search_id']),'delayed signed success issues certificate later');
 }
 if($status==='Cancelled')scenario_check(vpr_payment_message($current)==='Payment cancelled. No certificate was issued.','cancellation has a clear customer message');
}}
$bad=function(){return 'status=Error&error=Insufficient+balance&hash='.str_repeat('0',128);};
$p=vpr_start_checkout($pdo,'USD',$vehicle,bin2hex(random_bytes(32)),$bad,null,$contact);
scenario_check($p['status']==='Review'&&!$p['search_id'],'invalid signed error remains unconfirmed rather than a definite decline');
$p=vpr_start_checkout($pdo,'USD',$vehicle,bin2hex(random_bytes(32)),function(){return 'status=Error';},null,$contact);
scenario_check($p['status']==='Review'&&!$p['search_id'],'malformed error remains unconfirmed');
[$code,$message]=vpr_init_decline(['error'=>'Unknown raw payload with sensitive details']);scenario_check($code==='PaymentRejected'&&strpos($message,'sensitive')===false,'raw provider errors never reach customers');
putenv('VPR_PUBLIC_URL=https://localhost/VPR_EXCEL');scenario_check(vpr_public_base_url()==='https://localhost/VPR_EXCEL','Apache subdirectory base URL can be configured without source secrets');putenv('VPR_PUBLIC_URL');
echo "Completed $checks scenario checks.\n";
