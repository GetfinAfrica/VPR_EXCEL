<?php
require __DIR__ . '/bootstrap.php';
$payment = vpr_payment($pdo, $argv[1]);
$data = ['reference'=>$argv[1],'paynowreference'=>'PN-CONCURRENT','amount'=>$payment['expected_amount'],'status'=>'Paid'];
$data['hash'] = paynow_create_hash($data, vpr_payment_profile($payment)['key']);
try { vpr_apply_payment_data($pdo, $argv[1], $data, 'worker'); echo "ok\n"; }
catch (Throwable $e) { fwrite(STDERR, get_class($e) . ': ' . $e->getMessage()); exit(1); }
