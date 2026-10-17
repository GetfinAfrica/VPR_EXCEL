<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/db.php';
// Safe to schedule independently of browser sessions. No credentials/tokens in output.
// --reference=VPR-... explicitly reconciles one transaction, including final outcomes.
$options = getopt('', ['reference:', 'limit:']);
$pdo = db();
$limit = max(1, min(100, (int)($options['limit'] ?? 25)));
if (isset($options['reference'])) {
    $payment = vpr_payment($pdo, $options['reference']);
    $payments = $payment ? [$payment] : [];
    if (!$payment) { fwrite(STDERR, "Reference not found.\n"); exit(1); }
} else {
    $payments = $pdo->query("SELECT * FROM payments WHERE expected_amount IS NOT NULL
        AND (status IN ('Created','Initiated','Pending','Review') OR reconciliation_note IS NOT NULL)
        ORDER BY COALESCE(last_polled_at,0),id LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
}
$failed = false;
foreach ($payments as $payment) {
    $ref = $payment['merchant_reference'];
    if (!$payment['pollurl']) {
        echo $ref . ": manual provider reconciliation required (no verified poll URL).\n";
        $failed = true; continue;
    }
    try {
        vpr_poll_payment($pdo, $ref, true);
        $current = vpr_payment($pdo, $ref);
        echo $ref . ': ' . $current['status'] . ($current['reconciliation_note'] ? ' / review required' : '') . "\n";
        if ($current['reconciliation_note']) $failed = true;
    } catch (Throwable $e) {
        fwrite(STDERR, $ref . ": verification unavailable; retry later.\n"); $failed = true;
    }
}
exit($failed ? 1 : 0);
