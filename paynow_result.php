<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain; charset=UTF-8'); header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
$ref = $_GET['ref'] ?? '';
if (!is_string($ref) || !preg_match('/^VPR-[A-Za-z0-9-]{1,80}$/D', $ref)) { http_response_code(400); exit; }
try {
    $pdo = db(); $payment = vpr_payment($pdo, $ref);
    if (!$payment) { http_response_code(404); exit; }
    $data = paynow_parse_response(file_get_contents('php://input'));
    $profile = vpr_payment_profile($payment);
    if (!paynow_validate_hash($data, $profile['key'])) { http_response_code(403); exit; }
    // Use the signed notification. Never request a callback-supplied pollurl.
    vpr_apply_payment_data($pdo, $ref, $data, 'callback');
    http_response_code(200); echo 'OK';
} catch (Throwable $e) {
    if (isset($pdo)) vpr_payment_error($pdo, $ref, vpr_verification_error_code($e));
    // Retry timing is not documented; CLI reconciliation is the independent recovery path.
    http_response_code(503); echo 'Verification deferred';
}
