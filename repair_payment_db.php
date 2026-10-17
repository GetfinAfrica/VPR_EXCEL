<?php
require_once __DIR__ . '/db.php';
security_headers(); secure_session_start();
if (!admin_session_valid()) { http_response_code(403); exit('Admin login required.'); }
try {
    $pdo=db(); // db() runs the V12.9 schema migration
    $names=array_column($pdo->query("PRAGMA table_info(payments)")->fetchAll(PDO::FETCH_ASSOC),'name');
    $required=['merchant_reference','paynow_reference','amount','currency','status','pollurl','return_token','registration_number','vehicle_brand','vehicle_model','chassis_number','search_id','last_error','created_at','updated_at','paid_at'];
    $missing=array_diff($required,$names);
    $ok=!$missing; $msg=$ok?'Payment database repaired and verified. All V12.9 payment fields are present.':'Missing fields: '.implode(', ',$missing);
} catch(Throwable $e) { $ok=false; $msg=$e->getMessage(); }
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VPR Payment DB Repair</title>
<style>body{font-family:Arial;background:#f5f7f9;padding:30px}.b{max-width:700px;margin:auto;background:#fff;padding:28px;border-radius:14px}.ok{color:#166534}.bad{color:#991b1b}</style><?php require_once __DIR__.'/ui.php'; vpr_ui_assets(); ?></head>
<body><div class="b"><?php vpr_page_heading("VPR V12.9 Payment Database"); ?><h2 class="<?=$ok?'ok':'bad'?>"><?=$ok?'Repair successful':'Repair failed'?></h2><p><?=htmlspecialchars($msg)?></p><div class="vpr-actions"><a href="admin2000.php">Return to Admin</a></div></div></body></html>