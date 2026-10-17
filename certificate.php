<?php
require_once __DIR__ . '/db.php';
security_headers();
secure_session_start();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Certificate not found.'); }

$pdo = db();
$stmt = $pdo->prepare("
    SELECT s.*, v.vehicle_brand, v.vehicle_model AS stored_model, v.registration_number AS stored_reg, v.chassis_number AS stored_chassis
    FROM searches s
    LEFT JOIN vehicles v ON v.id=s.vehicle_id
    WHERE s.id=?
");
$stmt->execute([$id]);
$s = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$s) { http_response_code(404); exit('Certificate not found.'); }

$isAdminViewer = admin_session_valid();
$token = (string)($_GET['token'] ?? '');
if (!$isAdminViewer) {
    if ($token === '' || empty($s['access_token']) || !hash_equals((string)$s['access_token'], $token)) {
        http_response_code(403);
        exit('Certificate access denied.');
    }
}

$interests = [];
$stmt = $pdo->prepare("SELECT interested_party,reference_note,interest_registered_at AS created_at,priority_rank FROM search_interests WHERE search_id=? ORDER BY priority_rank ASC, id ASC");
$stmt->execute([$s['id']]);
$interests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Legacy searches created before snapshot support fall back to the current active list.
if (!$interests && $s['result'] === 'INTEREST RECORDED ON VPR' && !empty($s['vehicle_id'])) {
    $stmt = $pdo->prepare("SELECT interested_party,reference_note,created_at,NULL AS priority_rank FROM interests WHERE vehicle_id=? AND status='ACTIVE' ORDER BY datetime(created_at) ASC, id ASC");
    $stmt->execute([$s['vehicle_id']]);
    $interests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$ref = certificate_number_for_row($s);
$isClear = in_array($s['result'], ['NO INTEREST RECORDED ON VPR','NO INTEREST RECORDED'], true);
$certificateParents=[];
$receiptQuery=$pdo->prepare("SELECT merchant_reference,return_token FROM payments WHERE search_id=? AND status='Paid' AND verified_at IS NOT NULL ORDER BY id DESC LIMIT 1");
$receiptQuery->execute([$s['id']]);$receipt=$receiptQuery->fetch(PDO::FETCH_ASSOC);
if($receipt)$certificateParents[]=['label'=>'Payment acknowledgement','href'=>'payment_success.php?ref='.urlencode($receipt['merchant_reference']).'&token='.urlencode($receipt['return_token'])];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($ref) ?> — VPR Certificate</title>
<style>
@page{size:A4 portrait;margin:0}
:root{--navy:#062c52;--gold:#b88a26;--ink:#17212b;--muted:#66727e;--line:#cfd7df;--ok:#1f6b47;--warn:#8f2d2d}
*{box-sizing:border-box}body{margin:0;background:#eef2f5;font-family:Georgia,"Times New Roman",serif;color:var(--ink)}
.page{width:210mm;height:297mm;overflow:hidden;margin:16px auto;background:#fff;padding:13mm 15mm 10mm;position:relative;box-shadow:0 10px 35px rgba(0,0,0,.12);border:1px solid #cfd6dd}
.page:before{content:"";position:absolute;inset:7mm;border:2px solid var(--navy);pointer-events:none}
.page:after{content:"";position:absolute;inset:9mm;border:1px solid var(--gold);pointer-events:none}
.watermark{position:absolute;left:50%;top:48%;transform:translate(-50%,-50%) rotate(-24deg);font:700 68px Arial,sans-serif;color:rgba(6,44,82,.035);white-space:nowrap;letter-spacing:.08em}
.content{position:relative;z-index:2}.logo{display:block;width:110mm;max-width:80%;height:auto;margin:0 auto 4mm}
.title{text-align:center;color:var(--navy);font:700 25px Arial,sans-serif;letter-spacing:.05em;text-transform:uppercase;margin-top:2mm}
.subtitle{text-align:center;font:13px Arial,sans-serif;color:var(--muted);letter-spacing:.12em;text-transform:uppercase;margin:2mm 0 5mm}
.rule{height:2px;background:linear-gradient(90deg,transparent,var(--gold),var(--navy),var(--gold),transparent);margin:4mm 0 7mm}
.refbox{display:flex;justify-content:space-between;gap:8mm;font:12px Arial,sans-serif;border:1px solid var(--line);padding:4mm;background:#fafbfc}
.status{margin:7mm 0 6mm;padding:5mm;text-align:center;border:2px solid <?= $isClear ? 'var(--ok)' : 'var(--warn)' ?>}
.status .big{font:800 22px Arial,sans-serif;color:<?= $isClear ? 'var(--ok)' : 'var(--warn)' ?>;letter-spacing:.04em}
.status .small{font:12px Arial,sans-serif;margin-top:2mm;color:#4f5a64}
.section{margin-top:6mm}.section h3{font:700 14px Arial,sans-serif;color:var(--navy);text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid var(--gold);padding-bottom:2mm;margin:0 0 3mm}
table{width:100%;border-collapse:collapse;font:13px Arial,sans-serif}td{padding:2.2mm 1mm;border-bottom:1px solid #e8ecef}td:first-child{width:36%;font-weight:700;color:#33424f}
.interest{border:1px solid var(--line);padding:3.5mm;margin-top:2.5mm;font:12.5px Arial,sans-serif;background:#fcfcfd}
.rank{display:inline-block;background:var(--navy);color:#fff;padding:1.5mm 2.5mm;border-radius:10px;font-weight:700;margin-right:2mm}
.note{font:11px Arial,sans-serif;line-height:1.5;color:#58636d;margin-top:7mm;padding:4mm;border:1px solid #dfe4e9;background:#fbfcfd}
.auth{display:grid;grid-template-columns:1fr 1fr;gap:8mm;margin-top:9mm;font:11px Arial,sans-serif}.sig{padding-top:8mm;border-top:1px solid #8e99a3;text-align:center}
.footer{margin-top:8mm;text-align:center;font:10.5px Arial,sans-serif;color:#66727e}.footer a{color:var(--navy);font-weight:700;text-decoration:none}
.print{position:fixed;right:20px;top:20px;background:var(--navy);color:#fff;border:0;border-radius:7px;padding:12px 16px;font:700 14px Arial,sans-serif;cursor:pointer}
@media print{
html,body{width:210mm;height:297mm;margin:0!important;padding:0!important;background:#fff}
.page{margin:0!important;box-shadow:none!important;border:0!important;width:210mm!important;height:297mm!important;min-height:297mm!important;max-height:297mm!important;overflow:hidden!important;page-break-after:avoid!important;break-after:avoid!important}
.print{display:none!important}
.title{font-size:21px}.subtitle{margin-bottom:3mm}.rule{margin:3mm 0 4mm}
.refbox{padding:3mm}.status{margin:4mm 0;padding:3.5mm}.status .big{font-size:18px}
.section{margin-top:4mm}.section h3{margin-bottom:2mm}td{padding:1.5mm 1mm}
.interest{padding:2.5mm;margin-top:1.5mm;font-size:11.5px}
.note{font-size:9.5px;line-height:1.35;margin-top:4mm;padding:2.5mm}
.auth{margin-top:5mm}.footer{margin-top:4mm;font-size:9px}
}
</style>
<?php require_once __DIR__.'/ui.php'; vpr_ui_assets(); ?></head>
<body>
<div class="vpr-certificate-nav"><?php vpr_breadcrumbs('Certificate',$certificateParents); ?></div>
<div class="vpr-actions vpr-print-actions"><button class="print" onclick="window.print()">Print / Save PDF</button><a class="vpr-secondary" href="index.php">New vehicle check</a></div>
<div class="page">
<div class="watermark">VPR VERIFIED RECORD</div>
<div class="content">
<header class="vpr-page-heading vpr-report-heading"><div><h1 class="title">Vehicle interest search report</h1><div class="subtitle">Vehicle Pledge Registry</div></div><img class="vpr-brand-logo" src="assets/vpr-logo.png" alt="VPR"></header>
<div class="rule"></div>

<div class="refbox">
  <div><strong>Certificate Reference:</strong><br><?= htmlspecialchars($ref) ?></div>
  <div><strong>Date & Time Issued:</strong><br><?= date('d F Y, H:i', strtotime($s['searched_at'])) ?></div>
</div>

<div class="status">
  <div class="big"><?= $isClear ? 'NO REGISTERED INTEREST RECORDED ON VPR' : 'REGISTERED INTEREST RECORDED ON VPR' ?></div>
  <div class="small">Result reflects active interests recorded in the VPR database at the time shown above.</div>
</div>

<div class="section">
<h3>Vehicle Details</h3>
<table>
<tr><td>Registration Number</td><td><?= htmlspecialchars($s['stored_reg'] ?: $s['registration_number']) ?></td></tr>
<tr><td>Vehicle Brand</td><td><?= htmlspecialchars($s['vehicle_brand']) ?></td></tr>
<tr><td>Vehicle Model</td><td><?= htmlspecialchars($s['stored_model'] ?: $s['vehicle_model']) ?></td></tr>
<?php if (!empty($s['stored_chassis']) || !empty($s['chassis_number'])): ?>
<tr><td>Chassis Number</td><td><?= htmlspecialchars($s['stored_chassis'] ?: $s['chassis_number']) ?></td></tr>
<?php endif; ?>
</table>
</div>

<div class="section">
<h3>Registered Interests</h3>
<?php if (count($interests) === 0): ?>
<div class="interest">No active interest was recorded against this vehicle on VPR at the time of this check.</div>
<?php else: ?>
<?php foreach ($interests as $idx => $i): ?>
<div class="interest">
  <span class="rank">Order of VPR Registration #<?= intval($i['priority_rank'] ?? ($idx+1)) ?></span>
  <strong><?= htmlspecialchars($i['interested_party']) ?></strong><br>
  Registered on VPR: <?= date('d F Y, H:i', strtotime($i['created_at'])) ?>
  <?php if (!empty($i['reference_note'])): ?><br>Reference: <?= htmlspecialchars($i['reference_note']) ?><?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<div class="note">
<strong>Important notice:</strong> This certificate is a record of the VPR database at the stated date and time. “No registered interest found” means that VPR had no active interest recorded against the stated registration number at that moment. It is not proof of legal title and does not replace statutory, ownership, police, licensing, or other official checks. Any “VPR Priority” shown reflects the order in which interests were registered on VPR and does not itself determine legal priority between creditors.
</div>

<div class="auth">
  <div class="sig">VPR System Generated Record</div>
  <div class="sig">Reference: <?= htmlspecialchars($ref) ?></div>
</div>

<div class="footer">
  Issued by <strong>Vehicle Pledge Registry (VPR)</strong><br>
  Secure / Interest Registrations: <strong>secure@vpr.co.zw</strong> &nbsp; | &nbsp; General Enquiries: <strong>info@vpr.co.zw</strong><br>
  Tel: <strong>+263 788 444 409</strong> &nbsp; | &nbsp; 10 Shepperton Road, Graniteside, Harare<br>
  A member of <a href="https://diranor.com" target="_blank" rel="noopener">Diranor Group</a><br>
  KNOW. VERIFY. LEND WITH CONFIDENCE.
</div>
</div>
</div>
</body>
</html>
