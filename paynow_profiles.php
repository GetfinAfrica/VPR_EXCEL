<?php
// Hosting/process environment only. Never embed integration keys in source or HTML.
function vpr_currency($value): string {
    if (!is_string($value)) throw new InvalidArgumentException('Select USD or ZiG (ZWG).');
    $value = strtoupper(trim($value));
    if ($value === 'ZIG') $value = 'ZWG'; // Compatibility with older forms/settings.
    if (!in_array($value, ['USD', 'ZWG'], true)) throw new InvalidArgumentException('Select USD or ZiG (ZWG).');
    return $value;
}

function vpr_paynow_profile(string $currency, bool $requireEnabled = true): array {
    $currency = vpr_currency($currency);
    $prefix = 'VPR_PAYNOW_' . $currency . '_';
    $profile = [
        'name' => $currency,
        'currency' => $currency,
        'id' => trim((string)getenv($prefix . 'ID')),
        'key' => trim((string)getenv($prefix . 'KEY')),
        'environment' => strtolower(trim((string)getenv($prefix . 'ENVIRONMENT'))),
        'enabled' => getenv($prefix . 'ENABLED') === '1',
        // This classic shopping-cart API binds currency to the integration profile.
        // Explicit confirmation is required, especially for ZWG. No invented currency field.
        'confirmed' => getenv($prefix . 'CURRENCY_CONFIRMED') === '1',
        'init_url' => 'https://www.paynow.co.zw/interface/initiatetransaction'
    ];
    if ($requireEnabled && (!$profile['enabled'] || !$profile['confirmed'] ||
        !preg_match('/^[1-9][0-9]*$/D', $profile['id']) || $profile['key'] === '' ||
        !in_array($profile['environment'], ['test', 'live'], true))) {
        throw new RuntimeException('This currency checkout is not configured and confirmed yet.');
    }
    return $profile;
}

function vpr_payment_profile(array $payment): array {
    $profile = vpr_paynow_profile((string)$payment['currency'], false);
    if ($payment['integration_profile'] !== $profile['name'] ||
        $payment['integration_id'] !== $profile['id'] ||
        $payment['integration_environment'] !== $profile['environment'] || !$profile['confirmed'] || $profile['key'] === '') {
        throw new RuntimeException('Payment integration configuration changed; reconciliation is required.');
    }
    return $profile;
}

// Integer minor units: reject exponents, signs, excess precision and overflows.
function vpr_money_minor($value): int {
    if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?$/D', $value, $m)) {
        throw new InvalidArgumentException('Amount must be a non-negative decimal with at most two decimal places.');
    }
    return (int)$m[1] * 100 + (int)str_pad($m[2] ?? '', 2, '0');
}

function vpr_money_format(int $minor): string {
    return intdiv($minor, 100) . '.' . str_pad((string)($minor % 100), 2, '0', STR_PAD_LEFT);
}

function vpr_price_amount(PDO $pdo, string $currency): string {
    $currency = vpr_currency($currency);
    $fallback = $currency === 'ZWG' ? (string)VPR_ZIG_SEARCH_FEE : (string)VPR_SEARCH_FEE;
    // Authorization must fail closed on a database error, rather than falling back
    // through vpr_setting(), which intentionally suppresses errors for UI settings.
    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)');
    $read = function (string $key, string $default) use ($pdo): string {
        $q=$pdo->prepare('SELECT setting_value FROM settings WHERE setting_key=?'); $q->execute([$key]);
        $value=$q->fetchColumn(); return $value===false ? $default : (string)$value;
    };
    $legacy = $currency === 'ZWG' ? $read('price_ZIG', $fallback) : $fallback;
    return vpr_money_format(vpr_money_minor($read('price_' . $currency, $legacy)));
}
