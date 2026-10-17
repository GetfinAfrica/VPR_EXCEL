<?php
require_once __DIR__ . '/db.php';
security_headers();
secure_session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
    if (!verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(403); exit; }
    $currency = vpr_currency($_POST['payment_currency'] ?? '');
    $details = vpr_vehicle_request($_POST);
    $key = $_POST['checkout_key'] ?? '';
    if (!is_string($key) || !preg_match('/^[a-f0-9]{32}$/D', $key)) throw new InvalidArgumentException('Invalid checkout request.');
    $requestKey = hash_hmac('sha256', $key, csrf_token());
    $quote = $_POST['quoted_amount'] ?? '';
    if (!is_string($quote)) throw new InvalidArgumentException('Invalid quoted fee.');
    vpr_money_minor($quote);
    session_write_close();
    echo json_encode(vpr_checkout_response(vpr_start_checkout(db(), $currency, $details, $requestKey, null, $quote)), JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok'=>false,'message'=>'Checkout is unavailable. Check the currency configuration or try again later.']);
}
