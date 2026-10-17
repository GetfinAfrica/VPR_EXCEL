<?php
require_once __DIR__ . '/paynow_profiles.php';

function vpr_checkout_schema(PDO $pdo): void {
    $columns = array_column($pdo->query('PRAGMA table_info(payments)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $add = [
        'expected_amount' => 'TEXT', 'provider_amount' => 'TEXT', 'provider_currency' => 'TEXT',
        'integration_profile' => 'TEXT', 'integration_id' => 'TEXT', 'integration_environment' => 'TEXT',
        'provider_status' => 'TEXT', 'verified_at' => 'TEXT', 'browserurl' => 'TEXT',
        'request_key' => 'TEXT', 'request_fingerprint' => 'TEXT', 'request_ip_hash' => 'TEXT',
        'last_polled_at' => 'INTEGER', 'poll_claim_until' => 'INTEGER', 'reconciliation_note' => 'TEXT'
    ];
    foreach ($add as $name => $type) {
        if (!in_array($name, $columns, true)) $pdo->exec("ALTER TABLE payments ADD COLUMN $name $type");
    }
    // Legacy amounts may already have been overwritten by callbacks. Do not invent expected values.
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_payment_request_key ON payments(request_key) WHERE request_key IS NOT NULL');
    $pdo->exec('CREATE TABLE IF NOT EXISTS payment_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT, merchant_reference TEXT NOT NULL,
        source TEXT NOT NULL, outcome TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)');
}

function vpr_payment(PDO $pdo, string $reference): ?array {
    $q = $pdo->prepare('SELECT * FROM payments WHERE merchant_reference=? LIMIT 1');
    $q->execute([$reference]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}

function vpr_payment_event(PDO $pdo, string $reference, string $source, string $outcome): void {
    $pdo->prepare('INSERT INTO payment_events(merchant_reference,source,outcome) VALUES(?,?,?)')
        ->execute([$reference, $source, $outcome]);
}

function vpr_payment_error(PDO $pdo, string $reference, string $code): void {
    // Only application-owned codes, never raw provider bodies, tokens, URLs or exception messages.
    $pdo->prepare('UPDATE payments SET last_error=?,updated_at=CURRENT_TIMESTAMP WHERE merchant_reference=?')
        ->execute([$code, $reference]);
    vpr_payment_event($pdo, $reference, 'verification', $code);
}

function vpr_verification_error_code(Throwable $e): string {
    $known = ['SignatureMismatch','ReferenceMismatch','AmountMismatch','AmountMalformed',
        'CurrencyMismatch','IntegrationMismatch','MissingProviderFields','ProviderReferenceMismatch'];
    return in_array($e->getMessage(), $known, true) ? $e->getMessage() : 'VerificationUnavailable';
}

function paynow_create_hash(array $values, string $key): string {
    $text = '';
    foreach ($values as $name => $value) {
        if (!is_scalar($value)) throw new InvalidArgumentException('Invalid Paynow field.');
        if (strtolower((string)$name) !== 'hash') $text .= (string)$value;
    }
    return strtoupper(hash('sha512', $text . $key));
}

function paynow_validate_hash(array $values, string $key): bool {
    $hash = paynow_get($values, 'hash', '');
    return is_string($hash) && preg_match('/^[a-fA-F0-9]{128}$/D', $hash) &&
        hash_equals(paynow_create_hash($values, $key), strtoupper($hash));
}

function paynow_get(array $values, string $wanted, $default = null) {
    foreach ($values as $name => $value) {
        if (strtolower((string)$name) === strtolower($wanted)) return $value;
    }
    return $default;
}

function paynow_parse_response(string $response): array {
    if (strlen($response) > 65536 || $response === '') throw new RuntimeException('Invalid Paynow response.');
    $values = [];
    foreach (explode('&', $response) as $pair) {
        $parts = explode('=', $pair, 2);
        if (count($parts) !== 2) throw new RuntimeException('Invalid Paynow response.');
        $name = strtolower(urldecode($parts[0]));
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $name) || array_key_exists($name, $values)) {
            throw new RuntimeException('Ambiguous Paynow fields.');
        }
        $values[$name] = urldecode($parts[1]);
    }
    return $values;
}

function vpr_paynow_url(string $url, string $purpose): string {
    $p = parse_url($url);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || strtolower($p['host'] ?? '') !== 'www.paynow.co.zw' ||
        isset($p['user']) || isset($p['pass']) || isset($p['fragment']) || (isset($p['port']) && $p['port'] !== 443)) {
        throw new RuntimeException('Unapproved Paynow URL.');
    }
    $path = strtolower($p['path'] ?? '');
    $prefix = $purpose === 'poll' ? '/interface/checkpayment/' : '/payment/';
    if ($purpose === 'init') {
        if ($path !== '/interface/initiatetransaction') throw new RuntimeException('Unapproved Paynow endpoint.');
    } elseif (strpos($path, $prefix) !== 0 || strpos($path, '..') !== false || strpos($path, '%') !== false) {
        throw new RuntimeException('Unapproved Paynow endpoint.');
    }
    return $url;
}

function paynow_http_post(string $url, array $fields = [], string $purpose = 'poll'): string {
    vpr_paynow_url($url, $purpose);
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required for checkout.');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: text/plain']]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $status < 200 || $status >= 300) throw new RuntimeException('Paynow is temporarily unavailable.');
    return $body;
}

function paynow_is_paid($status): bool {
    return in_array(strtolower(trim((string)$status)), ['paid', 'awaiting delivery', 'delivered'], true);
}

function vpr_provider_state(string $status): string {
    $status = strtolower(trim($status));
    if (paynow_is_paid($status)) return 'Paid';
    return ['created' => 'Pending', 'sent' => 'Pending', 'cancelled' => 'Cancelled',
        'disputed' => 'Review', 'refunded' => 'Review'][$status] ?? 'Review';
}

function vpr_validate_payment_data(array $payment, array $data, array $profile): array {
    if (!paynow_validate_hash($data, $profile['key'])) throw new RuntimeException('SignatureMismatch');
    if (!hash_equals($payment['merchant_reference'], (string)paynow_get($data, 'reference', ''))) throw new RuntimeException('ReferenceMismatch');
    $reported = paynow_get($data, 'amount', '');
    try {
        if (empty($payment['expected_amount']) || vpr_money_minor($reported) !== vpr_money_minor($payment['expected_amount'])) {
            throw new RuntimeException('AmountMismatch');
        }
    } catch (InvalidArgumentException $e) { throw new RuntimeException('AmountMalformed'); }
    $currency = paynow_get($data, 'currency');
    // The classic API does not document a response currency field. Its signature binds to
    // this confirmed, currency-specific integration. Validate any field that IS supplied.
    if ($currency !== null && $currency !== $payment['currency']) throw new RuntimeException('CurrencyMismatch');
    $id = paynow_get($data, 'id');
    if ($id !== null && $id !== $payment['integration_id']) throw new RuntimeException('IntegrationMismatch');
    $reference = (string)paynow_get($data, 'paynowreference', '');
    $status = (string)paynow_get($data, 'status', '');
    if ($reference === '' || strlen($reference) > 100 || $status === '') throw new RuntimeException('MissingProviderFields');
    if (!empty($payment['paynow_reference']) && !hash_equals($payment['paynow_reference'], $reference)) throw new RuntimeException('ProviderReferenceMismatch');
    return [$reported, $currency, $reference, $status];
}

function vpr_apply_payment_data(PDO $pdo, string $reference, array $data, string $source): void {
    // A write transaction is acquired BEFORE reading/issuing. PDO deferred transactions
    // cannot safely upgrade concurrent WAL readers into writers.
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $payment = vpr_payment($pdo, $reference);
        if (!$payment) throw new RuntimeException('PaymentNotFound');
        $profile = vpr_payment_profile($payment);
        [$amount, $currency, $payref, $providerStatus] = vpr_validate_payment_data($payment, $data, $profile);
        $next = vpr_provider_state($providerStatus);
        $previous = $payment['status'];
        $note = null;
        if ($previous === 'Paid') {
            if ($next !== 'Paid') $note = 'PostPaymentStatus:' . $next;
            $next = 'Paid'; // Never silently revoke or reissue a certificate.
        } elseif (in_array($previous, ['Cancelled', 'Failed', 'Expired'], true)) {
            if ($next !== $previous) $note = 'TerminalStatusConflict:' . $next;
            $next = $previous; // Requires manual reconciliation; late updates cannot authorize.
        }
        $paid = $next === 'Paid';
        $pdo->prepare('UPDATE payments SET provider_amount=?,provider_currency=?,paynow_reference=?,
            provider_status=?,status=?,verified_at=CURRENT_TIMESTAMP,paid_at=CASE WHEN ? THEN COALESCE(paid_at,CURRENT_TIMESTAMP) ELSE paid_at END,
            reconciliation_note=COALESCE(?,reconciliation_note),last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE merchant_reference=?')
            ->execute([$amount, $currency, $payref, $providerStatus, $next, (int)$paid, $note, $reference]);
        if ($paid && empty($payment['search_id'])) {
            $id = vpr_create_search($pdo, $payment, $payment['request_ip_hash'] ?: protected_ip_value());
            $pdo->prepare('UPDATE payments SET search_id=? WHERE merchant_reference=?')->execute([$id, $reference]);
        }
        vpr_payment_event($pdo, $reference, $source, $note ?: $next);
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

function vpr_poll_payment(PDO $pdo, string $reference, bool $force = false): void {
    $payment = vpr_payment($pdo, $reference);
    if (!$payment || empty($payment['pollurl']) || empty($payment['expected_amount'])) return;
    if (!$force && (in_array($payment['status'], ['Paid','Cancelled','Failed','Expired'], true))) return;
    $now = time();
    $claim = $pdo->prepare('UPDATE payments SET poll_claim_until=?,last_polled_at=? WHERE merchant_reference=?
        AND COALESCE(poll_claim_until,0) < ? AND COALESCE(last_polled_at,0) <= ?');
    $claim->execute([$now + 30, $now, $reference, $now, $now - 10]);
    if (!$claim->rowCount()) return;
    try {
        vpr_payment_profile($payment);
        $data = paynow_parse_response(paynow_http_post($payment['pollurl']));
        vpr_apply_payment_data($pdo, $reference, $data, 'poll');
    } catch (Throwable $e) {
        vpr_payment_error($pdo, $reference, vpr_verification_error_code($e));
        throw $e;
    } finally {
        $pdo->prepare('UPDATE payments SET poll_claim_until=0 WHERE merchant_reference=?')->execute([$reference]);
    }
}

function vpr_vehicle_request(array $input): array {
    foreach (['registration_number','vehicle_brand','vehicle_model','chassis_number'] as $name) {
        if (isset($input[$name]) && !is_string($input[$name])) throw new InvalidArgumentException('Invalid vehicle details.');
    }
    $details = ['registration_number' => normalise_reg($input['registration_number'] ?? ''),
        'vehicle_brand' => clean($input['vehicle_brand'] ?? ''), 'vehicle_model' => clean($input['vehicle_model'] ?? ''),
        'chassis_number' => normalise_chassis($input['chassis_number'] ?? '')];
    if (!valid_registration($details['registration_number']) || $details['vehicle_brand'] === '' || $details['vehicle_model'] === '' ||
        strlen($details['vehicle_brand']) > 100 || strlen($details['vehicle_model']) > 150 || strlen($details['chassis_number'] ?? '') > 100) {
        throw new InvalidArgumentException('Enter a valid registration (AAA1111), brand and model.');
    }
    return $details;
}

// Internal persistence helper. Call only while holding a transaction and after payment/free authorization.
function vpr_create_search(PDO $pdo, array $details, string $ipHash): int {
    $reg = $details['registration_number'];
    $brand = $details['vehicle_brand'];
    $model = $details['vehicle_model'];
    $chassis = $details['chassis_number'];
    $vehicle = find_vehicle_by_reg($pdo, $reg);
    if (!$vehicle) {
        $pdo->prepare('INSERT INTO vehicles(chassis_number,vehicle_brand,vehicle_model,registration_number) VALUES(?,?,?,?)')
            ->execute([$chassis,$brand,$model,$reg]);
        $vehicle = ['id' => (int)$pdo->lastInsertId()];
    } else {
        $pdo->prepare('UPDATE vehicles SET chassis_number=?,vehicle_brand=?,vehicle_model=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
            ->execute([$vehicle['chassis_number'] ?: $chassis,
                in_array($vehicle['vehicle_brand'], ['', 'Other'], true) ? $brand : $vehicle['vehicle_brand'],
                mb_strlen($model) > mb_strlen($vehicle['vehicle_model']) ? $model : $vehicle['vehicle_model'], $vehicle['id']]);
    }
    $q = $pdo->prepare("SELECT i.*, (SELECT COUNT(*) FROM interests i2 WHERE i2.vehicle_id=i.vehicle_id
        AND (datetime(i2.created_at)<datetime(i.created_at) OR (i2.created_at=i.created_at AND i2.id<=i.id))) registration_order
        FROM interests i WHERE i.vehicle_id=? AND i.status='ACTIVE' ORDER BY datetime(i.created_at),i.id");
    $q->execute([$vehicle['id']]);
    $interests = $q->fetchAll(PDO::FETCH_ASSOC);
    $pdo->prepare('INSERT INTO searches(vehicle_id,chassis_number,vehicle_brand,vehicle_model,registration_number,result,ip_address) VALUES(?,?,?,?,?,?,?)')
        ->execute([$vehicle['id'],$chassis,$brand,$model,$reg,$interests ? 'INTEREST RECORDED ON VPR' : 'NO INTEREST RECORDED ON VPR',$ipHash]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE searches SET certificate_number=?,access_token=? WHERE id=?')
        ->execute([certificate_ref($id,date('Y-m-d H:i:s')),generate_cert_token(),$id]);
    $snapshot = $pdo->prepare('INSERT INTO search_interests(search_id,priority_rank,interested_party,reference_note,interest_registered_at) VALUES(?,?,?,?,?)');
    foreach ($interests as $interest) {
        $snapshot->execute([$id,$interest['registration_order'],$interest['interested_party'],$interest['reference_note'],$interest['created_at']]);
    }
    return $id;
}

function vpr_issue_free_search(PDO $pdo, string $currency, array $details): int {
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        if (vpr_money_minor(vpr_price_amount($pdo, $currency)) !== 0) throw new RuntimeException('Verified payment is required for this search.');
        $id = vpr_create_search($pdo, $details, protected_ip_value());
        $pdo->exec('COMMIT');
        return $id;
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
}

function vpr_start_checkout(PDO $pdo, string $currency, array $details, string $requestKey, ?callable $transport = null, ?string $quotedAmount = null): array {
    $currency = vpr_currency($currency);
    $profile = vpr_paynow_profile($currency);
    $fingerprint = hash('sha256', json_encode([$currency, $details]));
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $q = $pdo->prepare('SELECT * FROM payments WHERE request_key=?');
        $q->execute([$requestKey]);
        $existing = $q->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if (!hash_equals($existing['request_fingerprint'], $fingerprint)) throw new RuntimeException('Checkout request changed. Start a new search.');
            $pdo->exec('COMMIT');
            return $existing;
        }
        $amount = vpr_price_amount($pdo, $currency);
        if ($quotedAmount !== null && vpr_money_minor($quotedAmount) !== vpr_money_minor($amount)) {
            throw new InvalidArgumentException('The search fee changed. Reload the search page to confirm the current price.');
        }
        if (vpr_money_minor($amount) === 0) throw new RuntimeException('This search is free. Return to the search form.');
        $reference = 'VPR-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(8)));
        $token = bin2hex(random_bytes(24));
        $pdo->prepare("INSERT INTO payments(merchant_reference,amount,expected_amount,currency,integration_profile,
            integration_id,integration_environment,status,return_token,request_key,request_fingerprint,request_ip_hash,
            registration_number,vehicle_brand,vehicle_model,chassis_number,created_at,updated_at)
            VALUES(?,?,?,?,?,?,?,'Created',?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
            ->execute([$reference,$amount,$amount,$currency,$profile['name'],$profile['id'],$profile['environment'],
                $token,$requestKey,$fingerprint,protected_ip_value(),$details['registration_number'],$details['vehicle_brand'],
                $details['vehicle_model'],$details['chassis_number']]);
        vpr_payment_event($pdo, $reference, 'init', 'Created');
        $pdo->exec('COMMIT');
    } catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }

    $base = vpr_public_base_url();
    $fields = ['id' => $profile['id'], 'reference' => $reference, 'amount' => $amount,
        'additionalinfo' => 'VPR Vehicle Interest Search (' . $currency . ')',
        'returnurl' => $base . '/paynow_return.php?ref=' . urlencode($reference) . '&token=' . urlencode($token),
        'resulturl' => $base . '/paynow_result.php?ref=' . urlencode($reference), 'status' => 'Message'];
    $fields['hash'] = paynow_create_hash($fields, $profile['key']);
    try {
        $raw = $transport ? $transport($profile['init_url'], $fields, 'init') : paynow_http_post($profile['init_url'], $fields, 'init');
        $data = paynow_parse_response($raw);
        if (strtolower((string)paynow_get($data, 'status', '')) === 'error') {
            // An unsigned error is not authoritative evidence that a transaction does not exist.
            throw new RuntimeException('InitiationUnconfirmed');
        }
        if (!paynow_validate_hash($data, $profile['key']) || strtolower((string)paynow_get($data, 'status', '')) !== 'ok') {
            throw new RuntimeException('InitiationUnconfirmed');
        }
        $browser = vpr_paynow_url((string)paynow_get($data, 'browserurl', ''), 'browser');
        $poll = vpr_paynow_url((string)paynow_get($data, 'pollurl', ''), 'poll');
        // A callback can arrive before initiation finishes. Never downgrade its state.
        $pdo->prepare("UPDATE payments SET browserurl=?,pollurl=?,status=CASE WHEN status='Created' THEN 'Initiated' ELSE status END,
            updated_at=CURRENT_TIMESTAMP WHERE merchant_reference=?") ->execute([$browser,$poll,$reference]);
        vpr_payment_event($pdo, $reference, 'init', 'Initiated');
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE payments SET status=CASE WHEN status='Created' THEN 'Review' ELSE status END,
            last_error='InitiationUnconfirmed',updated_at=CURRENT_TIMESTAMP WHERE merchant_reference=?") ->execute([$reference]);
        vpr_payment_event($pdo, $reference, 'init', 'InitiationUnconfirmed');
    }
    return vpr_payment($pdo, $reference);
}

function vpr_checkout_response(array $payment): array {
    return ['ok' => true, 'reference' => $payment['merchant_reference'], 'token' => $payment['return_token'],
        'currency' => $payment['currency'], 'amount' => $payment['expected_amount'], 'status' => $payment['status'],
        'browserurl' => $payment['browserurl'],
        'returnurl' => 'paynow_return.php?ref=' . urlencode($payment['merchant_reference']) . '&token=' . urlencode($payment['return_token'])];
}
