<?php
function vpr_checkout_details(array $input): array {
    $result=[];
    foreach (['payer_name','payer_email','payer_phone','payment_method'] as $field) {
        $value=$input[$field] ?? '';
        if (!is_string($value)) throw new InvalidArgumentException('Invalid payment details.');
        $result[$field]=trim($value);
    }
    if ($result['payer_name']==='' || strlen($result['payer_name'])>100 || preg_match('/[\x00-\x1f]/',$result['payer_name'])) throw new InvalidArgumentException('Enter your full name.');
    if (strlen($result['payer_email'])>254 || !filter_var($result['payer_email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
    if (!in_array($result['payment_method'],['hosted','card','ecocash','onemoney','innbucks'],true)) throw new InvalidArgumentException('Choose a supported payment method.');
    $phone=preg_replace('/[\s()-]/','',$result['payer_phone']);
    if (preg_match('/^0(7[0-9]{8})$/D',$phone,$m)) $phone='263'.$m[1];
    $phone=ltrim($phone,'+');
    $mobile=in_array($result['payment_method'],['ecocash','onemoney','innbucks'],true);
    if (($mobile || $phone!=='') && !preg_match('/^2637[0-9]{8}$/D',$phone)) throw new InvalidArgumentException('Enter a Zimbabwe mobile number, for example 0771111111.');
    $result['payer_phone']=$phone;
    return $result;
}

function vpr_is_express(string $method): bool {
    return in_array($method,['ecocash','onemoney','innbucks'],true);
}

function vpr_init_decline(array $data): array {
    $error=strtolower((string)paynow_get($data,'error',''));
    if (strpos($error,'insufficient')!==false || strpos($error,'balance')!==false) return ['InsufficientFunds','Insufficient funds. No certificate was issued. Check your balance or choose another payment method.'];
    if (strpos($error,'email')!==false) return ['PayerEmailRejected','Paynow rejected the payer email. In test mode, use the integration owner’s email. No certificate was issued.'];
    if (strpos($error,'method')!==false) return ['MethodUnavailable','This payment method is not available for this integration. No certificate was issued.'];
    if (strpos($error,'phone')!==false || strpos($error,'msisdn')!==false) return ['PhoneRejected','Paynow rejected this mobile number. Check the number and selected payment method.'];
    return ['PaymentRejected','Paynow rejected this payment request. Check the payment details and integration settings. No certificate was issued.'];
}

function vpr_payment_message(array $payment): ?string {
    if ($payment['status']==='Failed') return $payment['payment_instructions'] ?: 'Payment failed. No certificate was issued.';
    if ($payment['status']==='Cancelled') return 'Payment cancelled. No certificate was issued.';
    if ($payment['status']==='Expired') return 'Payment expired. No certificate was issued.';
    if (!empty($payment['last_error'])) return $payment['payment_instructions'] ?: 'Verification is temporarily unavailable. Keep this reference and check again.';
    return null;
}

function vpr_express_instructions(array $data,string $method): string {
    if ($method==='innbucks') {
        $code=(string)paynow_get($data,'authorizationcode','');
        $expiry=(string)paynow_get($data,'authorizationexpires','');
        if (!preg_match('/^[A-Za-z0-9-]{1,100}$/D',$code) || $expiry==='' || strlen($expiry)>80 || preg_match('/[\x00-\x1f]/',$expiry)) throw new RuntimeException('Missing InnBucks authorization details.');
        return 'Approve in InnBucks using authorization code '.$code.'. Expires '.$expiry.'.';
    }
    return 'Approve the '.$method.' payment request on your phone. Keep this page open, then check status.';
}
