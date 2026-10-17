<?php
require __DIR__.'/bootstrap.php';
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
$address=stream_socket_get_name($socket,false);fclose($socket);
$base='http://'.$address;
$log=$fixture.'/http.log';
$pipes=[];
$server=proc_open([PHP_BINARY,'-S',$address,'-t',$fixture],[1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes);
if(!is_resource($server))throw new RuntimeException('Unable to start fixture server.');
$cookie=$fixture.'/cookies';$checks=0;
function expect_http(bool $ok,string $label):void{global $checks;++$checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);echo "PASS: $label\n";}
function request(string $path,?array $post=null,?string $raw=null):array{
    global $base,$cookie;
    $ch=curl_init($base.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_TIMEOUT=>10]);
    if($post!==null||$raw!==null){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$raw??http_build_query($post));curl_setopt($ch,CURLOPT_HTTPHEADER,['Content-Type: application/x-www-form-urlencoded']);}
    $response=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$headerSize=(int)curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
    if($response===false)throw new RuntimeException('Fixture HTTP failed.');
    return [$code,substr($response,$headerSize),substr($response,0,$headerSize)];
}
try{
    for($i=0;$i<40;$i++){if($c=@fsockopen('127.0.0.1',(int)explode(':',$address)[1])){fclose($c);break;}usleep(50000);}
    [$code,$body]=request('/index.php');
    expect_http($code===200,'public search renders with migrated database');
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/',$body,$m);$csrf=$m[1]??'';
    expect_http(strlen($csrf)===64,'search form has CSRF protection');
    expect_http(strpos($body,'value="ZWG"')!==false&&strpos($body,'assets/checkout.js')!==false,'both currency options use shared checkout script');
    $input=['csrf_token'=>$csrf,'registration_number'=>'TST1234','vehicle_brand'=>'Toyota','vehicle_model'=>'Hilux','payment_currency'=>'USD','quoted_amount'=>'1.00','checkout_key'=>str_repeat('a',32)];
    [$code]=request('/search_process.php',$input);expect_http($code===403,'direct paid issuance rejected over HTTP');
    $bad=$input;$bad['csrf_token']='wrong';[$code]=request('/search_process.php',$bad);expect_http($code===403,'invalid CSRF rejected');
    $bad=$input;$bad['payment_currency']='EUR';[$code]=request('/paynow_create.php',$bad);expect_http($code===400,'unsupported checkout currency rejected');
    foreach(['USD','ZWG'] as $ccy){
        $input['payment_currency']=$ccy;$input['checkout_key']=bin2hex(random_bytes(16));
        $requestKey=hash_hmac('sha256',$input['checkout_key'],$csrf);
        $captured=[];
        $mock=function($url,$fields)use($ccy,&$captured){
            $captured=$fields;
            $d=['status'=>'Ok','browserurl'=>'https://www.paynow.co.zw/Payment/ConfirmPayment/12345','pollurl'=>'https://www.paynow.co.zw/Interface/CheckPayment/?guid=fixture'];
            $d['hash']=paynow_create_hash($d,vpr_paynow_profile($ccy)['key']);return http_build_query($d);
        };
        $p=vpr_start_checkout($pdo,$ccy,vpr_vehicle_request($input),$requestKey,$mock);
        [$code,$body]=request('/paynow_create.php',$input);$result=json_decode($body,true);
        expect_http($code===200&&($result['reference']??'')===$p['merchant_reference'],$ccy.' HTTP retry resumes existing checkout');
        expect_http(strpos($body,'fixture-'.$ccy.'-key')===false&&strpos($body,'integration_key')===false,'checkout JSON never exposes integration key');
        $data=['reference'=>$p['merchant_reference'],'amount'=>$p['expected_amount'],'paynowreference'=>'PN-HTTP-'.$ccy,'status'=>'Paid'];
        $data['hash']=paynow_create_hash($data,'bad-key');
        [$code]=request('/paynow_result.php?ref='.$p['merchant_reference'],null,http_build_query($data));expect_http($code===403,'wrong callback signature rejected over HTTP');
        $data['hash']=paynow_create_hash($data,vpr_paynow_profile($ccy)['key']);
        [$code,$body]=request('/paynow_result.php?ref='.$p['merchant_reference'],null,http_build_query($data));expect_http($code===200&&$body==='OK',$ccy.' signed callback acknowledged');
        $q='?ref='.urlencode($p['merchant_reference']).'&token='.urlencode($p['return_token']);
        [$code,$body]=request('/paynow_status.php'.$q);$result=json_decode($body,true);
        expect_http($code===200&&($result['paid']??false)&&!empty($result['certificate_url']),$ccy.' verified status releases certificate URL');
        [$code,$cert]=request('/'.$result['certificate_url']);expect_http($code===200&&strpos($cert,'TST1234')!==false,$ccy.' authorized certificate renders');
        $id=vpr_payment($pdo,$p['merchant_reference'])['search_id'];
        [$code]=request('/certificate.php?id='.$id.'&token=wrong');expect_http($code===403,'certificate rejects wrong access token');
        [$code]=request('/paynow_status.php?ref='.$p['merchant_reference'].'&token=wrong');expect_http($code===403,'payment status rejects wrong return token');
    }
    vpr_set_setting($pdo,'price_USD','0.00');$input['payment_currency']='USD';
    [$code,$body,$headers]=request('/search_process.php',$input);expect_http($code===303&&strpos($headers,'certificate.php?id=')!==false,'free search redirects only after server price authorization');
    [$code]=request('/admin2000.php',['password'=>'fixture-admin-password']);expect_http($code===302,'admin login works');
    [$code,$body]=request('/admin2000.php');expect_http($code===200&&strpos($body,'Expected / Reported')!==false,'currency-aware admin reporting renders');
    expect_http(strpos($body,'Fatal error')===false&&strpos($body,'Warning:')===false,'admin renders without PHP diagnostics');
    echo "Completed $checks HTTP checks.\n";
}finally{proc_terminate($server);proc_close($server);}
