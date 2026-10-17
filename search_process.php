<?php
require_once __DIR__ . '/db.php';
security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$reg = normalise_reg($_POST['registration_number'] ?? '');
$brand = clean($_POST['vehicle_brand'] ?? '');
$model = clean($_POST['vehicle_model'] ?? '');
$chassis = normalise_chassis($_POST['chassis_number'] ?? '');
if (!$reg || !$brand || !$model || !valid_registration($reg)) {
    http_response_code(400);
    exit('Invalid vehicle details. Registration must be 3 letters followed by 4 digits, e.g. AAA1111.');
}

$pdo = db();
$vehicle = find_vehicle_by_reg($pdo, $reg);

if (!$vehicle) {
    $stmt = $pdo->prepare("INSERT INTO vehicles (chassis_number,vehicle_brand,vehicle_model,registration_number) VALUES (?,?,?,?)");
    $stmt->execute([$chassis,$brand,$model,$reg]);
    $vehicleId=(int)$pdo->lastInsertId();
    $stmt=$pdo->prepare("SELECT * FROM vehicles WHERE id=?");
    $stmt->execute([$vehicleId]);
    $vehicle=$stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $bestModel = mb_strlen($model) > mb_strlen((string)$vehicle['vehicle_model']) ? $model : $vehicle['vehicle_model'];
    $bestBrand = ($vehicle['vehicle_brand']==='' || $vehicle['vehicle_brand']==='Other') ? $brand : $vehicle['vehicle_brand'];
    $bestChassis = $vehicle['chassis_number'] ?: $chassis;
    $pdo->prepare("UPDATE vehicles SET chassis_number=?,vehicle_brand=?,vehicle_model=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
        ->execute([$bestChassis,$bestBrand,$bestModel,$vehicle['id']]);
    $stmt=$pdo->prepare("SELECT * FROM vehicles WHERE id=?");
    $stmt->execute([$vehicle['id']]);
    $vehicle=$stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt=$pdo->prepare("
    SELECT i.*,
      (SELECT COUNT(*) FROM interests i2
       WHERE i2.vehicle_id=i.vehicle_id
         AND (datetime(i2.created_at)<datetime(i.created_at)
              OR (i2.created_at=i.created_at AND i2.id<=i.id))) AS registration_order
    FROM interests i
    WHERE i.vehicle_id=? AND i.status='ACTIVE'
    ORDER BY datetime(i.created_at),i.id
");
$stmt->execute([$vehicle['id']]);
$activeInterests=$stmt->fetchAll(PDO::FETCH_ASSOC);

$resultCode = count($activeInterests) ? 'INTEREST RECORDED ON VPR' : 'NO INTEREST RECORDED ON VPR';

$stmt=$pdo->prepare("INSERT INTO searches (vehicle_id,chassis_number,vehicle_brand,vehicle_model,registration_number,result,ip_address) VALUES (?,?,?,?,?,?,?)");
$stmt->execute([$vehicle['id'],$chassis,$brand,$model,$reg,$resultCode,protected_ip_value()]);
$searchId=(int)$pdo->lastInsertId();

$certificateNumber=certificate_ref($searchId,date('Y-m-d H:i:s'));
$certificateAccessToken=generate_cert_token();
$pdo->prepare("UPDATE searches SET certificate_number=?,access_token=? WHERE id=?")
    ->execute([$certificateNumber,$certificateAccessToken,$searchId]);

if ($activeInterests) {
    $snap=$pdo->prepare("INSERT INTO search_interests (search_id,priority_rank,interested_party,reference_note,interest_registered_at) VALUES (?,?,?,?,?)");
    foreach($activeInterests as $interest) {
        $snap->execute([
            $searchId,
            (int)$interest['registration_order'],
            $interest['interested_party'],
            $interest['reference_note'] ?? null,
            $interest['created_at'] ?? null
        ]);
    }
}

header('Location: certificate.php?id='.urlencode((string)$searchId).'&token='.urlencode($certificateAccessToken));
exit;
?>