<?php
require_once __DIR__ . '/db.php';
security_headers();
secure_session_start();
if (!admin_session_valid()) { header('Location: admin2000.php'); exit; }
header('Cache-Control: no-store');
$checks=[];
$checks[]=['HTTPS request', request_is_https(), request_is_https()?'Active':'Not detected'];
$checks[]=['Current domain', true, htmlspecialchars((string)($_SERVER['HTTP_HOST'] ?? 'Unknown'))];
$checks[]=['Database writable', is_writable(dirname(VPR_DB)), is_writable(dirname(VPR_DB))?'Writable':'Not writable'];
$checks[]=['SQLite database', file_exists(VPR_DB), file_exists(VPR_DB)?'Present':'Missing'];
$checks[]=['Data protection file', file_exists(__DIR__.'/data/.htaccess'), file_exists(__DIR__.'/data/.htaccess')?'Present':'Missing'];
$checks[]=['Application security file', file_exists(__DIR__.'/.htaccess'), file_exists(__DIR__.'/.htaccess')?'Present':'Missing'];
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>VPR Domain Readiness</title><style>body{font-family:Arial;background:#f3f6f9;margin:0;color:#17212b}.w{max-width:820px;margin:30px auto;padding:20px}.c{background:#fff;border:1px solid #dfe6ed;border-radius:14px;padding:22px}.ok{color:#1f6b47;font-weight:800}.bad{color:#8f2d2d;font-weight:800}table{width:100%;border-collapse:collapse}td{padding:12px;border-bottom:1px solid #e7ebef}a{color:#062c52;font-weight:700}</style><?php require_once __DIR__.'/ui.php'; vpr_ui_assets(); ?></head>
<body><div class="w"><div class="c"><?php vpr_page_heading("VPR Domain Readiness Check"); ?><p>This page checks the deployed VPR instance without sending information to any external security service.</p>
<table><?php foreach($checks as $x): ?><tr><td><strong><?=$x[0]?></strong></td><td class="<?=$x[1]?'ok':'bad'?>"><?=$x[2]?></td></tr><?php endforeach;?></table>
<p><strong>Detected secure base URL:</strong> <?=htmlspecialchars(vpr_public_base_url())?></p>
<p><a href="admin2000.php">← Back to Admin Dashboard</a></p></div></div></body></html>