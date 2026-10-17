<?php
require_once __DIR__ . '/db.php';
security_headers();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
secure_session_start();

$error = '';
$message = isset($_GET['imported']) ? 'Excel backup imported successfully.' : '';

if (isset($_GET['logout'])) {
    audit_admin('logout');
    session_destroy();
    header('Location: admin2000.php');
    exit;
}

if (!admin_session_valid()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        $pdoLogin = db();
        $ipHash = protected_ip_value();
        $now = time();

        $stmt = $pdoLogin->prepare("SELECT attempts,first_attempt,last_attempt FROM login_attempts WHERE ip_hash=?");
        $stmt->execute([$ipHash]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        $blocked = $attempt && (int)$attempt['attempts'] >= VPR_MAX_LOGIN_ATTEMPTS &&
                   ($now - (int)$attempt['last_attempt']) < VPR_LOGIN_BLOCK_SECONDS;

        if ($blocked) {
            $error = 'Too many failed login attempts. Please try again later.';
            audit_admin('blocked_login');
            send_security_email(
                'VPR Admin Security Alert - Login Blocked',
                "VPR blocked repeated admin login attempts.\n\nTime: " . date('c') . "\nProtected IP fingerprint: " . $ipHash . "\n\nIf this was not you, review the admin audit log."
            );
        } elseif (verify_admin_password((string)$_POST['password'])) {
            $pdoLogin->prepare("DELETE FROM login_attempts WHERE ip_hash=?")->execute([$ipHash]);
            session_regenerate_id(true);
            $_SESSION['vpr_admin'] = true;
            $_SESSION['vpr_admin_last'] = time();
            csrf_token();
            audit_admin('login_success');
            header('Location: admin2000.php');
            exit;
        } else {
            if (!$attempt || ($now - (int)$attempt['last_attempt']) >= VPR_LOGIN_BLOCK_SECONDS) {
                $pdoLogin->prepare("INSERT OR REPLACE INTO login_attempts(ip_hash,attempts,first_attempt,last_attempt) VALUES(?,?,?,?)")
                    ->execute([$ipHash,1,$now,$now]);
            } else {
                $pdoLogin->prepare("UPDATE login_attempts SET attempts=attempts+1,last_attempt=? WHERE ip_hash=?")
                    ->execute([$now,$ipHash]);
            }
            $error = 'Incorrect password.';
            audit_admin('login_failed');

            $check = $pdoLogin->prepare("SELECT attempts FROM login_attempts WHERE ip_hash=?");
            $check->execute([$ipHash]);
            $countNow = (int)$check->fetchColumn();
            if ($countNow >= 3) {
                send_security_email(
                    'VPR Admin Security Alert - Failed Login Attempts',
                    "VPR recorded " . $countNow . " failed admin login attempts.\n\nTime: " . date('c') . "\nProtected IP fingerprint: " . $ipHash . "\n\nThe site will automatically block repeated attempts."
                );
            }
        }
    }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VPR Admin</title>
<style>body{font-family:Arial;background:#f5f7f9;padding:40px;color:#14202b}.box{max-width:420px;margin:auto;background:#fff;padding:24px;border:1px solid #dfe5ea;border-radius:14px;box-shadow:0 10px 30px rgba(6,44,82,.08)}.logo{width:100%;max-width:320px;display:block;margin:0 auto 18px}input,button{width:100%;padding:12px;margin-top:12px;box-sizing:border-box}button{background:#062c52;color:#fff;border:0;border-radius:8px;font-weight:700}.err{color:#b91c1c}
:root{--vpr-navy:#062c52;--vpr-blue:#0c4d86;--vpr-gold:#b88a26;--vpr-bg:#f3f6f9;--vpr-line:#dfe6ed;--vpr-text:#17212b;--vpr-muted:#66727e;--vpr-ok:#1f6b47}
body{background:var(--vpr-bg)!important;color:var(--vpr-text)}
.wrap{max-width:1220px!important;margin:0 auto!important;padding:24px!important}
.topbar{background:linear-gradient(135deg,var(--vpr-navy),var(--vpr-blue));color:#fff;border-radius:16px;padding:18px 22px;margin-bottom:18px;box-shadow:0 10px 28px rgba(6,44,82,.16)}
.topbar h1,.topbar h2{color:#fff!important;margin:0}
.vpr-admin-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.vpr-admin-head img{width:190px;max-width:42vw;background:#fff;border-radius:10px;padding:6px}
.vpr-admin-title{flex:1}.vpr-admin-title h1{margin:0;color:#fff;font-size:25px}.vpr-admin-title div{opacity:.82;font-size:12px;margin-top:5px}
.vpr-secure{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.24);padding:7px 10px;border-radius:999px;font-size:11px;font-weight:700}
.panel,.card,.box{background:#fff!important;border:1px solid var(--vpr-line)!important;border-radius:14px!important;box-shadow:0 6px 22px rgba(6,44,82,.05)!important;padding:20px!important;margin-bottom:18px}
.panel h2,.card h2,.box h2{color:var(--vpr-navy)!important;margin-top:0}
.grid{gap:14px!important}
.stat,.metric{border:1px solid var(--vpr-line)!important;border-radius:12px!important;background:#fff!important}
.vpr-price-panel{border-top:4px solid var(--vpr-gold)!important}
.vpr-price-head{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.vpr-price-badge{background:#fff8e6;color:#795c00;border:1px solid #ead59c;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:800}
.vpr-price-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:14px 0}
.vpr-price-card{border:1px solid var(--vpr-line);border-radius:12px;padding:16px;background:#fafcfe}
.vpr-price-card label{display:block;font-weight:800;color:var(--vpr-navy);margin-bottom:7px}
.vpr-price-card input{font-size:22px;font-weight:800;padding:12px;width:100%}
.vpr-price-note{font-size:12px;color:var(--vpr-muted);line-height:1.5;margin-top:8px}
.vpr-price-panel button{background:linear-gradient(135deg,var(--vpr-navy),var(--vpr-blue))!important}
table{background:#fff}th{background:#eef3f7!important;color:var(--vpr-navy)!important}
@media(max-width:700px){.vpr-price-grid{grid-template-columns:1fr}.wrap{padding:12px!important}}

</style>
</head><body>
<div class="box"><img class="logo" src="assets/vpr-logo.png" alt="VPR"><h2>Administration</h2><p>Private VPR administration access.</p>
<?php if($error): ?><p class="err"><?=htmlspecialchars($error)?></p><?php endif;?>
<form method="post"><input type="password" name="password" placeholder="Admin password" required><button>Login</button></form>
<p style="text-align:center;margin-top:16px"><a href="recover_admin.php">Forgot / blocked admin password?</a></p>
</div></body></html>
<?php exit;
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid security token.');
    }
    if ($_POST['action'] === 'save_pricing') {
        $usd=trim((string)($_POST['price_usd'] ?? ''));
        $zig=trim((string)($_POST['price_zig'] ?? ''));
        if (!preg_match('/^(0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/D', $usd) || !preg_match('/^(0|[1-9][0-9]{0,8})(?:\.[0-9]{1,2})?$/D', $zig)) {
            $error='Prices must be valid numbers equal to or greater than zero.';
        } else {
            $pdo->beginTransaction();
            vpr_set_setting($pdo,'price_USD',vpr_money_format(vpr_money_minor($usd)));
            vpr_set_setting($pdo,'price_ZWG',vpr_money_format(vpr_money_minor($zig)));
            audit_admin('pricing_updated','USD '.$usd.' | ZWG '.$zig);
            $pdo->commit();
            $message='Public search prices updated successfully.';

        }
    }

    if ($_POST['action'] === 'add_interest') {
        $chassis = normalise_chassis($_POST['chassis_number'] ?? '');
        $brand = clean($_POST['vehicle_brand'] ?? '');
        $model = clean($_POST['vehicle_model'] ?? '');
        $reg = normalise_reg($_POST['registration_number'] ?? '');
        $party = clean($_POST['interested_party'] ?? '');
        $note = clean($_POST['reference_note'] ?? '');

        if (!$reg || !$brand || !$model || !$party) {
            $error = 'Registration number, brand, model and interested party are required.';
        } elseif (!valid_registration($reg)) {
            $error = 'Registration number must be exactly 3 letters followed by 4 numbers, e.g. AAA1111.';
        } else {
            $vehicle = find_vehicle_by_reg($pdo, $reg);
            if (!$vehicle) {
                $stmt = $pdo->prepare("INSERT INTO vehicles (chassis_number, vehicle_brand, vehicle_model, registration_number) VALUES (?, ?, ?, ?)");
                $stmt->execute([$chassis, $brand, $model, $reg]);
                $vehicleId = (int)$pdo->lastInsertId();
            } else {
                $vehicleId = (int)$vehicle['id'];
                $bestModel = mb_strlen($model) > mb_strlen((string)$vehicle['vehicle_model']) ? $model : $vehicle['vehicle_model'];
                $bestBrand = ($vehicle['vehicle_brand']==='' || $vehicle['vehicle_brand']==='Other') ? $brand : $vehicle['vehicle_brand'];
                $bestChassis = $vehicle['chassis_number'] ?: $chassis;
                $stmt = $pdo->prepare("UPDATE vehicles SET chassis_number=?, vehicle_brand=?, vehicle_model=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
                $stmt->execute([$bestChassis,$bestBrand,$bestModel,$vehicleId]);
            }
            $stmt = $pdo->prepare("INSERT INTO interests (vehicle_id, interested_party, reference_note, status) VALUES (?, ?, ?, 'ACTIVE')");
            $stmt->execute([$vehicleId,$party,$note ?: null]);
            $message = 'Interest added successfully. Order of VPR Registration is based on the time each interest is recorded on VPR.';
            audit_admin('interest_added', $reg . ' | ' . $party);
        }
    }

    if ($_POST['action'] === 'clear_interest') {
        $interestId=(int)($_POST['interest_id'] ?? 0);
        if ($interestId>0) {
            $pdo->prepare("UPDATE interests SET status='CLEARED', cleared_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$interestId]);
            $message='Interest cleared.';
            audit_admin('interest_cleared', 'Interest ID ' . $interestId);
        }
    }
}

$usdPrice=vpr_price($pdo,'USD');
$zigPrice=vpr_price($pdo,'ZIG');
$searchesToday=(int)$pdo->query("SELECT COUNT(*) FROM searches WHERE date(searched_at)=date('now','localtime')")->fetchColumn();
$totalSearches=(int)$pdo->query("SELECT COUNT(*) FROM searches")->fetchColumn();
$totalRecords=(int)$pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$activeCount=(int)$pdo->query("SELECT COUNT(*) FROM interests WHERE status='ACTIVE'")->fetchColumn();
$paidSearches=(int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status='Paid' AND verified_at IS NOT NULL AND search_id IS NOT NULL")->fetchColumn();
$revenue=['USD'=>0,'ZWG'=>0];
foreach ($pdo->query("SELECT currency,expected_amount FROM payments WHERE status='Paid' AND verified_at IS NOT NULL AND search_id IS NOT NULL AND reconciliation_note IS NULL")->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($revenue[$row['currency']])) $revenue[$row['currency']] += vpr_money_minor($row['expected_amount']);
}
$recentPayments=$pdo->query("SELECT merchant_reference,paynow_reference,registration_number,payment_method,amount,expected_amount,provider_amount,currency,provider_currency,integration_id,integration_environment,status,provider_status,reconciliation_note,created_at,paid_at,last_error FROM payments ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$searchesWeek=(int)$pdo->query("SELECT COUNT(*) FROM searches WHERE date(searched_at) >= date('now','localtime','-6 days')")->fetchColumn();
$searchesMonth=(int)$pdo->query("SELECT COUNT(*) FROM searches WHERE strftime('%Y-%m',searched_at)=strftime('%Y-%m','now','localtime')")->fetchColumn();

$statsClass = $_GET['stats_class'] ?? 'day';
if (!in_array($statsClass,['day','week','month'],true)) $statsClass='day';
$statsPeriod = trim((string)($_GET['stats_period'] ?? ''));
$selectedStats = 0;
$statsLabel = '';

if ($statsClass === 'day') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$statsPeriod)) $statsPeriod=date('Y-m-d');
    $q=$pdo->prepare("SELECT COUNT(*) FROM searches WHERE date(searched_at)=?");
    $q->execute([$statsPeriod]);
    $selectedStats=(int)$q->fetchColumn();
    $statsLabel=date('d M Y',strtotime($statsPeriod));
} elseif ($statsClass === 'week') {
    if (!preg_match('/^(\d{4})-W(\d{2})$/',$statsPeriod,$m)) {
        $statsPeriod=date('o-\WW');
        preg_match('/^(\d{4})-W(\d{2})$/',$statsPeriod,$m);
    }
    $d=new DateTime(); $d->setISODate((int)$m[1],(int)$m[2],1);
    $weekStart=$d->format('Y-m-d'); $d->modify('+6 days'); $weekEnd=$d->format('Y-m-d');
    $q=$pdo->prepare("SELECT COUNT(*) FROM searches WHERE date(searched_at) BETWEEN ? AND ?");
    $q->execute([$weekStart,$weekEnd]);
    $selectedStats=(int)$q->fetchColumn();
    $statsLabel='Week '.$m[2].' ('.date('d M',strtotime($weekStart)).' - '.date('d M Y',strtotime($weekEnd)).')';
} else {
    if (!preg_match('/^\d{4}-\d{2}$/',$statsPeriod)) $statsPeriod=date('Y-m');
    $q=$pdo->prepare("SELECT COUNT(*) FROM searches WHERE strftime('%Y-%m',searched_at)=?");
    $q->execute([$statsPeriod]);
    $selectedStats=(int)$q->fetchColumn();
    $statsLabel=date('F Y',strtotime($statsPeriod.'-01'));
}

$dailyStats=$pdo->query("SELECT date(searched_at) period,COUNT(*) searches FROM searches GROUP BY date(searched_at) ORDER BY period DESC LIMIT 31")->fetchAll(PDO::FETCH_ASSOC);
$weeklyStats=$pdo->query("SELECT strftime('%Y-W%W',searched_at) period,COUNT(*) searches FROM searches GROUP BY strftime('%Y-W%W',searched_at) ORDER BY period DESC LIMIT 16")->fetchAll(PDO::FETCH_ASSOC);
$monthlyStats=$pdo->query("SELECT strftime('%Y-%m',searched_at) period,COUNT(*) searches FROM searches GROUP BY strftime('%Y-%m',searched_at) ORDER BY period DESC LIMIT 24")->fetchAll(PDO::FETCH_ASSOC);

$daily=$pdo->query("SELECT date(searched_at) day,COUNT(*) searches FROM searches GROUP BY date(searched_at) ORDER BY day DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
$active=$pdo->query("
SELECT i.*,v.chassis_number,v.vehicle_brand,v.vehicle_model,v.registration_number,
(SELECT COUNT(*) FROM interests i2 WHERE i2.vehicle_id=i.vehicle_id AND (datetime(i2.created_at)<datetime(i.created_at) OR (i2.created_at=i.created_at AND i2.id<=i.id))) AS priority_rank
FROM interests i JOIN vehicles v ON v.id=i.vehicle_id
WHERE i.status='ACTIVE'
ORDER BY v.registration_number, datetime(i.created_at), i.id
")->fetchAll(PDO::FETCH_ASSOC);

$historyReg = normalise_reg($_GET['history_reg'] ?? '');
$historyRows = [];

if ($historyReg) {
    $stmt = $pdo->prepare("
        SELECT s.*
        FROM searches s
        WHERE s.registration_number = ?
        ORDER BY datetime(s.searched_at) DESC, s.id DESC
    ");
    $stmt->execute([$historyReg]);
    $historyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$brands = ["Toyota","Nissan","Honda","Mazda","Mitsubishi","Suzuki","Subaru","Isuzu","Ford","Chevrolet","Volkswagen","Mercedes-Benz","BMW","Audi","Land Rover","Jeep","Hyundai","Kia","Renault","Peugeot","Volvo","Fiat","Citroën","Opel","Lexus","Infiniti","Daihatsu","Hino","UD Trucks","Scania","MAN","Foton","BAW","GWM","Haval","FAW","JAC","Chery","Geely","BYD","Changan","Dongfeng","DFSK","JMC","MG","Maxus","GAC","Jetour","Omoda","Jaecoo","Hongqi","Great Wall","Tata","Mahindra","Ashok Leyland","Other"];
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VPR Admin</title>
<style>
:root{--navy:#062c52;--gold:#b88a26}body{font-family:Arial;background:#f5f7f9;color:#14202b;margin:0}.wrap{max-width:1120px;margin:auto;padding:28px 18px}.logo{max-width:360px;width:100%}.top{display:flex;justify-content:space-between;align-items:center}.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:18px 0}.card,.panel{background:#fff;border:1px solid #dfe5ea;border-radius:12px;padding:18px}.num{font-size:28px;font-weight:800;color:var(--navy)}.muted{color:#6b7280;font-size:13px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.panel{margin-top:16px}input,textarea,select,button{width:100%;padding:10px;margin-top:8px;box-sizing:border-box}button{background:var(--navy);color:#fff;border:0;border-radius:8px;font-weight:700}table{width:100%;border-collapse:collapse;font-size:14px}th,td{text-align:left;padding:8px;border-bottom:1px solid #eee}.ok{color:#166534}.err{color:#b91c1c}.smallbtn{width:auto;padding:8px 10px;background:#991b1b}.viewbtn{display:inline-block;background:var(--navy);color:#fff;text-decoration:none;padding:8px 10px;border-radius:7px;font-size:12px;font-weight:700;white-space:nowrap}.historyform{display:flex;gap:8px;align-items:end}.historyform>div{flex:1}.historyform button{width:auto;min-width:130px}.historysummary{margin:10px 0 0;color:#58636d;font-size:13px}.certref{font-family:monospace;font-size:12px;color:#334155}.result-ok{color:#166534;font-weight:700}.result-warn{color:#991b1b;font-weight:700}.rank{font-weight:800;color:var(--gold)}.footer{text-align:center;margin-top:20px;font-size:12px}.footer a{color:var(--navy);font-weight:700;text-decoration:none}@media(max-width:800px){.cards,.grid{grid-template-columns:1fr}.top{flex-direction:column;align-items:flex-start;gap:10px}.historyform{flex-direction:column;align-items:stretch}.historyform button{width:100%}}
</style></head><body><div class="wrap">
<div class="top"><div><img class="logo" src="assets/vpr-logo.png" alt="VPR"><div class="muted">Private administration</div></div><a href="?logout=1">Logout</a></div>
<?php if($message): ?><p class="ok"><?=htmlspecialchars($message)?></p><?php endif;?>
<?php if($error): ?><p class="err"><?=htmlspecialchars($error)?></p><?php endif;?>
<div class="cards" style="grid-template-columns:repeat(3,1fr)">
<div class="card"><div class="num"><?=$searchesToday?></div><div class="muted">Searches today</div></div>
<div class="card"><div class="num"><?=$searchesWeek?></div><div class="muted">Searches last 7 days</div></div>
<div class="card"><div class="num"><?=$searchesMonth?></div><div class="muted">Searches this month</div></div>
<div class="card"><div class="num"><?=$totalSearches?></div><div class="muted">Total searches since launch</div></div>
<div class="card"><div class="num"><?=$totalRecords?></div><div class="muted">Vehicle records</div></div>
<div class="card"><div class="num"><?=$activeCount?></div><div class="muted">Active interests</div></div>
<div class="card"><div class="num"><?=$paidSearches?></div><div class="muted">Paid searches</div></div>
<div class="card"><div class="num">USD <?=htmlspecialchars(vpr_money_format($revenue['USD']))?><br>ZWG <?=htmlspecialchars(vpr_money_format($revenue['ZWG']))?></div><div class="muted">Paid search revenue</div></div>
</div>
<div class="grid">

<div class="panel vpr-price-panel" id="price-management">
  <div class="vpr-price-head">
    <div>
      <h2 style="margin:0 0 5px">Search Price Management</h2>
      <div class="vpr-price-note">Set the live public vehicle-search fees. Changes take effect immediately.</div>
    </div>
    <span class="vpr-price-badge">LIVE PRICING CONTROL</span>
  </div>
  <form method="post">
    <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>">
    <input type="hidden" name="action" value="save_pricing">
    <div class="vpr-price-grid">
      <div class="vpr-price-card">
        <label>USD Price per Search</label>
        <input type="number" name="price_usd" min="0" step="0.01" required value="<?=htmlspecialchars(number_format($usdPrice,2,'.',''))?>">
        <div class="vpr-price-note"><strong>0.00 = FREE.</strong> VPR bypasses Paynow and immediately issues the search certificate.</div>
      </div>
      <div class="vpr-price-card">
        <label>ZiG (ZWG) Price per Search</label>
        <input type="number" name="price_zig" min="0" step="0.01" required value="<?=htmlspecialchars(number_format($zigPrice,2,'.',''))?>">
        <div class="vpr-price-note"><strong>0.00 = FREE.</strong> VPR bypasses Paynow and immediately issues the search certificate.</div>
      </div>
    </div>
    <button type="submit">Save & Publish Search Prices</button>
  </form>
</div>

<div class="panel"><h2>Add Vehicle Interest</h2><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="action" value="add_interest"><label>Registration Number</label><input name="registration_number" required maxlength="7" pattern="[A-Za-z]{3}[0-9]{4}" title="Exactly 3 letters followed by 4 numbers, e.g. AAA1111" placeholder="AAA1111"><label>Vehicle Brand</label><select name="vehicle_brand" required><option value="">Select vehicle brand</option><?php foreach($brands as $b): ?><option value="<?=htmlspecialchars($b)?>"><?=htmlspecialchars($b)?></option><?php endforeach;?></select><label>Vehicle Model</label><input name="vehicle_model" required placeholder="e.g. NV350"><label>Chassis Number (optional)</label><input name="chassis_number"><label>Interested Party</label><input name="interested_party" required placeholder="Bank, lender or secured party"><label>Reference / Note</label><textarea name="reference_note" rows="3"></textarea><button>Add Interest</button></form></div>
<div class="panel"><h2>Searches per day</h2><table><tr><th>Date</th><th>Searches</th></tr><?php foreach($daily as $d): ?><tr><td><?=htmlspecialchars($d['day'])?></td><td><?=intval($d['searches'])?></td></tr><?php endforeach;?><?php if(!$daily): ?><tr><td colspan="2">No searches yet.</td></tr><?php endif;?></table></div>
</div>


<div class="panel">
<h2>Search Traffic Analytics</h2>
<p class="muted">Choose a day, week or month to review site usage. Reports can be downloaded in Excel or PDF.</p>

<form method="get" class="historyform">
<div>
<label>Period Class</label>
<select name="stats_class" id="stats_class" onchange="vprPeriodType()">
<option value="day" <?=$statsClass==='day'?'selected':''?>>Day</option>
<option value="week" <?=$statsClass==='week'?'selected':''?>>Week</option>
<option value="month" <?=$statsClass==='month'?'selected':''?>>Month</option>
</select>
</div>
<div>
<label>Period</label>
<input name="stats_period" id="stats_period" value="<?=htmlspecialchars($statsPeriod)?>" required>
</div>
<button type="submit">Show Statistics</button>
</form>

<div class="card" style="margin-top:14px;background:#f8fafc">
<div class="muted"><?=htmlspecialchars($statsLabel)?></div>
<div class="num"><?=$selectedStats?></div>
<div class="muted">Searches in selected period</div>
</div>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
<a class="viewbtn" href="traffic_report.php?format=xlsx&amp;stats_class=<?=urlencode($statsClass)?>&amp;stats_period=<?=urlencode($statsPeriod)?>">Download Excel Report</a>
<a class="viewbtn" href="traffic_report.php?format=pdf&amp;stats_class=<?=urlencode($statsClass)?>&amp;stats_period=<?=urlencode($statsPeriod)?>">Download PDF Report</a>
</div>

<div class="grid" style="margin-top:18px">
<div><table><tr><th>Recent Days</th><th>Searches</th></tr>
<?php foreach($dailyStats as $r): ?><tr><td><?=htmlspecialchars($r['period'])?></td><td><?=intval($r['searches'])?></td></tr><?php endforeach;?>
</table></div>
<div><table><tr><th>Recent Weeks</th><th>Searches</th></tr>
<?php foreach($weeklyStats as $r): ?><tr><td><?=htmlspecialchars($r['period'])?></td><td><?=intval($r['searches'])?></td></tr><?php endforeach;?>
</table></div>
</div>

<div style="overflow-x:auto;margin-top:14px"><table><tr><th>Recent Months</th><th>Searches</th></tr>
<?php foreach($monthlyStats as $r): ?><tr><td><?=htmlspecialchars($r['period'])?></td><td><?=intval($r['searches'])?></td></tr><?php endforeach;?>
</table></div>
</div>

<script>
function vprPeriodType(){
 const c=document.getElementById('stats_class').value;
 const i=document.getElementById('stats_period');
 if(c==='day'){i.type='date';i.value='<?=date('Y-m-d')?>';}
 else if(c==='week'){i.type='week';i.value='<?=date('o-\WW')?>';}
 else {i.type='month';i.value='<?=date('Y-m')?>';}
}
document.addEventListener('DOMContentLoaded',function(){
 const c=document.getElementById('stats_class').value;
 document.getElementById('stats_period').type=(c==='day'?'date':(c==='week'?'week':'month'));
});
</script>

<div class="panel">
  <h2>Data Backup & Migration</h2>
  <p class="muted">Download a complete VPR Excel backup for safekeeping or future system upgrades. The same Excel file can later be uploaded into an upgraded VPR version.</p>
  <div class="grid" style="grid-template-columns:1fr 1fr">
    <div>
      <h3 style="margin-top:0">Download Backup</h3>
      <p class="muted">Includes vehicles, interests, searches, certificate numbers and historical interest snapshots.</p>
      <a class="viewbtn" style="font-size:14px;padding:11px 14px" href="data_exchange.php?action=export">Download Excel Backup (.xlsx)</a>
    </div>
    <div>
      <h3 style="margin-top:0">Upload / Restore Backup</h3>
      <p class="muted">Imports and merges a VPR Excel backup. Existing matching records are preserved or updated rather than blindly duplicated.</p>
      <form method="post" action="data_exchange.php?action=import" enctype="multipart/form-data" onsubmit="return confirm('Import this VPR Excel backup? Please make sure you have a current backup first.');">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>"><input type="file" name="backup_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
        <button type="submit">Upload & Merge Excel Backup</button>
      </form>
    </div>
  </div>
</div>

<div class="panel">
  <h2>Vehicle Search History</h2>
  <p class="muted">Enter a registration number to retrieve every VPR search recorded for that vehicle.</p>
  <form class="historyform" method="get">
    <div>
      <label>Registration Number</label>
      <input name="history_reg" value="<?=htmlspecialchars($historyReg ?? '')?>" placeholder="e.g. AAA1111" required>
    </div>
    <button type="submit">View History</button>
  </form>

  <?php if ($historyReg): ?>
    <div class="historysummary">
      <strong><?=count($historyRows)?></strong> recorded search<?=count($historyRows)==1?'':'es'?> for
      <strong><?=htmlspecialchars($historyReg)?></strong>.
    </div>

    <div style="overflow-x:auto;margin-top:12px">
      <table>
        <tr>
          <th>Date / Time</th>
          <th>Details Stored for This Search</th>
          <th>Result</th>
          <th>Certificate No.</th>
          <th></th>
        </tr>
        <?php foreach($historyRows as $h): ?>
        <tr>
          <td><?=date('d M Y, H:i', strtotime($h['searched_at']))?></td>
          <td>
            <strong><?=htmlspecialchars($h['vehicle_brand'])?> <?=htmlspecialchars($h['vehicle_model'])?></strong><br>
            Reg: <?=htmlspecialchars($h['registration_number'])?>
            <?php if(!empty($h['chassis_number'])): ?><br>Chassis: <?=htmlspecialchars($h['chassis_number'])?><?php endif;?>
          </td>
          <td class="<?=($h['result']==='NO INTEREST RECORDED')?'result-ok':'result-warn'?>">
            <?=htmlspecialchars($h['result'])?>
          </td>
          <td class="certref"><?=htmlspecialchars(certificate_number_for_row($h))?></td>
          <td><a class="viewbtn" href="certificate.php?id=<?=intval($h['id'])?>" target="_blank">View Certificate</a></td>
        </tr>
        <?php endforeach;?>
        <?php if(!$historyRows): ?><tr><td colspan="5">No search history found for this registration number.</td></tr><?php endif;?>
      </table>
    </div>
  <?php endif;?>
</div>

<div class="panel"><h2>Active Interests — Order of VPR Registration</h2><table><tr><th>Order of VPR Registration</th><th>Vehicle</th><th>Interested Party</th><th>Registered</th><th></th></tr>
<?php foreach($active as $i): ?><tr><td class="rank">#<?=intval($i['priority_rank'])?></td><td><?=htmlspecialchars($i['registration_number'])?><br><?=htmlspecialchars($i['vehicle_brand'].' '.$i['vehicle_model'])?></td><td><?=htmlspecialchars($i['interested_party'])?></td><td><?=htmlspecialchars($i['created_at'])?></td><td><form method="post" onsubmit="return confirm('Clear this interest?');"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(csrf_token())?>"><input type="hidden" name="action" value="clear_interest"><input type="hidden" name="interest_id" value="<?=intval($i['id'])?>"><button class="smallbtn">Clear</button></form></td></tr><?php endforeach;?>
<?php if(!$active): ?><tr><td colspan="5">No active interests.</td></tr><?php endif;?></table></div>


<div class="panel">
<h2>Recent Paynow Transactions</h2>
<p class="muted">Current fees: USD <?=htmlspecialchars(vpr_price_amount($pdo,'USD'))?> / ZiG (ZWG) <?=htmlspecialchars(vpr_price_amount($pdo,'ZWG'))?>. Pending transactions retain their original expected fee. Revenue above includes verified transactions without reconciliation flags; legacy payments require separate reconciliation.</p>
<div style="overflow-x:auto"><table>
<tr><th>Date</th><th>VPR Ref</th><th>Paynow Ref</th><th>Vehicle</th><th>Expected / Reported</th><th>Integration</th><th>Status</th><th>Reconciliation</th></tr>
<?php foreach($recentPayments as $pmt): ?>
<tr>
<td><?=htmlspecialchars($pmt['created_at'])?></td>
<td><?=htmlspecialchars($pmt['merchant_reference'])?></td>
<td><?=htmlspecialchars($pmt['paynow_reference'] ?: '-')?></td>
<td><?=htmlspecialchars($pmt['registration_number'])?></td>
<td><?=htmlspecialchars($pmt['currency'])?> <?=htmlspecialchars($pmt['expected_amount'] ?? 'Legacy: unknown')?><br><?=htmlspecialchars($pmt['provider_amount'] ?? '-')?> <?=htmlspecialchars($pmt['provider_currency'] ?? 'profile-bound')?></td>
<td><?=htmlspecialchars($pmt['integration_id'] ?? 'legacy')?> / <?=htmlspecialchars($pmt['integration_environment'] ?? 'unknown')?><br><?=htmlspecialchars($pmt['payment_method'] ?? 'hosted')?></td>
<td><?=htmlspecialchars($pmt['status'])?></td>
<td><?=htmlspecialchars($pmt['reconciliation_note'] ?: ($pmt['last_error'] ?: '-'))?></td>
</tr>
<?php endforeach; ?>
<?php if(!$recentPayments): ?><tr><td colspan="8">No Paynow transactions yet.</td></tr><?php endif;?>
</table></div>
</div>

<div class="panel">
<h2>Payment System Health</h2><p><a class="viewbtn" href="repair_payment_db.php">Repair / Verify Payment Database</a></p>
<table>
<tr><td>VPR Version</td><td><strong><?=htmlspecialchars(VPR_VERSION)?></strong></td></tr>
<tr><td>PDO SQLite</td><td><?=in_array('sqlite',PDO::getAvailableDrivers(),true)?'Available':'MISSING'?></td></tr>
<tr><td>PHP cURL</td><td><?=function_exists('curl_init')?'Available':'Not available'?></td></tr>
<tr><td>allow_url_fopen</td><td><?=in_array(strtolower((string)ini_get('allow_url_fopen')),['1','on','yes','true'],true)?'Enabled':'Disabled'?></td></tr>
<tr><td>OpenSSL</td><td><?=extension_loaded('openssl')?'Available':'MISSING'?></td></tr>
<?php foreach (['USD','ZWG'] as $ccy): $profile=vpr_paynow_profile($ccy,false); ?><tr><td><?=htmlspecialchars($ccy)?> checkout</td><td><?=htmlspecialchars($profile['id'] ?: 'Not configured')?> / <?=htmlspecialchars($profile['environment'] ?: 'unknown')?> / <?=($profile['enabled'] && $profile['confirmed']) ? 'Enabled and currency confirmed' : 'Disabled or unconfirmed'?></td></tr><?php endforeach;?>
</table>
<p class="muted">At least one of PHP cURL or allow_url_fopen must be available for VPR to contact Paynow.</p>
</div>
<div class="footer">secure@vpr.co.zw &nbsp; | &nbsp; info@vpr.co.zw &nbsp; | &nbsp; +263 788 444 409<br>10 Shepperton Road, Graniteside, Harare<br>A member of <a href="https://diranor.com" target="_blank" rel="noopener">Diranor Group</a></div>
</div><div style="text-align:center;margin:16px 0 28px;font-size:12px"><a href="domain_check.php">Domain & HTTPS Readiness Check</a></div>
</body></html>
