<?php
require_once __DIR__ . '/db.php';
security_headers(); secure_session_start(); header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required.'); }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Invalid security token.'); }
try {
    $currency = vpr_currency($_POST['payment_currency'] ?? '');
    $details = vpr_vehicle_request($_POST);
    $pdo = db();
    // Paid searches issue only through verified callback/poll processing.
    $id = vpr_issue_free_search($pdo, $currency, $details);
    $q = $pdo->prepare('SELECT access_token FROM searches WHERE id=?'); $q->execute([$id]);
    header('Location: certificate.php?id='.$id.'&token='.urlencode($q->fetchColumn()), true, 303);
} catch (InvalidArgumentException $e) {
    http_response_code(400); echo htmlspecialchars($e->getMessage());
} catch (Throwable $e) {
    http_response_code(403); echo 'Search not authorized. Return to the search page and complete verified checkout if a fee applies.';
}
