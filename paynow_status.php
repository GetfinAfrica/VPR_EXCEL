<?php
require_once __DIR__ . '/db.php'; security_headers();
header('Content-Type: application/json; charset=UTF-8'); header('Cache-Control: no-store');
try {
    $ref = $_GET['ref'] ?? ''; $token = $_GET['token'] ?? '';
    if (!is_string($ref) || !is_string($token) || $ref === '' || $token === '') {
        http_response_code(400); echo json_encode(['ok'=>false,'message'=>'Missing payment access details.']); exit;
    }
    $pdo = db(); $payment = vpr_payment($pdo, $ref);
    if (!$payment || !hash_equals((string)$payment['return_token'], $token)) {
        http_response_code(403); echo json_encode(['ok'=>false,'message'=>'Payment access denied.']); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Authenticated browser reconciliation (automatic or manual). The provider
        // poll has a shared ten-second rate limit and never initiates another charge.
        try { vpr_poll_payment($pdo, $ref); } catch (Throwable $ignored) {}
        $payment = vpr_payment($pdo, $ref);
    }
    // GET reads persisted state; POST above reconciles with Paynow before responding.
    $paid = $payment['status'] === 'Paid' && !empty($payment['verified_at']) && !empty($payment['search_id']);
    $url = null;
    if ($paid) {
        $q = $pdo->prepare('SELECT access_token FROM searches WHERE id=?'); $q->execute([$payment['search_id']]);
        $access = $q->fetchColumn();
        if ($access) $url = 'certificate.php?id='.(int)$payment['search_id'].'&token='.urlencode($access);
    }
    echo json_encode(['ok'=>true,'paid'=>$paid && $url !== null,'status'=>$payment['status'],
        'currency'=>$payment['currency'],'amount'=>$payment['expected_amount'],
        'payment_method'=>$payment['payment_method'],'instructions'=>$payment['payment_instructions'],'certificate_url'=>$url,
        'authorization_code'=>$payment['authorization_code'],'authorization_expires'=>$payment['authorization_expires'],
        'needs_review'=>($payment['status']!=='Failed' && !empty($payment['last_error'])) || !empty($payment['reconciliation_note']),
        'error_code'=>$payment['last_error'],'message'=>vpr_payment_message($payment)]);
} catch (Throwable $e) {
    http_response_code(503); echo json_encode(['ok'=>false,'message'=>'Payment status is temporarily unavailable. Check again.']);
}
