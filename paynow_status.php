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
        // Explicit customer reconciliation, using the same reference rather than a new charge.
        try { vpr_poll_payment($pdo, $ref, true); } catch (Throwable $ignored) {}
        $payment = vpr_payment($pdo, $ref);
    }
    // Browser reads persisted state. Callbacks and CLI reconciliation verify with Paynow.
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
        'needs_review'=>!empty($payment['last_error']) || !empty($payment['reconciliation_note']),
        'message'=>!empty($payment['last_error']) ? 'Verification is pending. Keep this reference; contact VPR if the delay continues.' : null]);
} catch (Throwable $e) {
    http_response_code(503); echo json_encode(['ok'=>false,'message'=>'Payment status is temporarily unavailable. Check again.']);
}
