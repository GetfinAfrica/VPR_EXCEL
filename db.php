<?php
$vprRuntimeConfig = in_array(PHP_SAPI, ['cli','cli-server'], true) ? getenv('VPR_CONFIG_FILE') : false;
require_once ($vprRuntimeConfig ?: __DIR__ . '/config.php');
unset($vprRuntimeConfig);
require_once __DIR__ . '/payments.php';


function vpr_setting(PDO $pdo, string $key, string $default=''): string {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
        $q=$pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=?"); $q->execute([$key]);
        $v=$q->fetchColumn(); return $v===false ? $default : (string)$v;
    } catch (Throwable $e) { return $default; }
}
function vpr_set_setting(PDO $pdo, string $key, string $value): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (setting_key TEXT PRIMARY KEY, setting_value TEXT NOT NULL, updated_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $q=$pdo->prepare("INSERT INTO settings(setting_key,setting_value,updated_at) VALUES(?,?,CURRENT_TIMESTAMP)
        ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value,updated_at=CURRENT_TIMESTAMP");
    $q->execute([$key,$value]);
}
function vpr_price(PDO $pdo, string $currency): float {
    return (float)vpr_price_amount($pdo, $currency);
}

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . VPR_DB);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // VPR performance/resilience settings for concurrent searches.
        // WAL allows readers and writers to operate with far less blocking.
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA temp_store = MEMORY');

        // Serialize first-request migrations so concurrent callbacks cannot race ALTER TABLE.
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            ensure_v6_schema($pdo);
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            $pdo = null;
            throw $e;
        }
    }
    return $pdo;
}

function checkpoint_db($mode = 'PASSIVE') {
    $allowed = ['PASSIVE','FULL','RESTART','TRUNCATE'];
    if (!in_array($mode, $allowed, true)) $mode = 'PASSIVE';
    try {
        db()->exec("PRAGMA wal_checkpoint(" . $mode . ")");
    } catch (Throwable $e) {
        // Non-fatal: normal requests should continue even if checkpoint is unavailable.
    }
}



function vpr_public_base_url(): string {
    if (defined('VPR_PUBLIC_URL') && trim((string)VPR_PUBLIC_URL) !== '') {
        return rtrim((string)VPR_PUBLIC_URL, '/');
    }
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return 'https://' . $host;
}

function request_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    // Trust the standard proxy header only when explicitly supplied by the web server/reverse proxy.
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0])) === 'https') return true;
    return false;
}

function security_headers() {
    if (headers_sent()) return;

    // Redirect the public site to HTTPS. TLS itself must be enabled on the hosting/domain.
    if (defined('VPR_FORCE_HTTPS') && VPR_FORCE_HTTPS && !request_is_https()) {
        $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'vpr.co.zw'));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }

    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-sniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self)");
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; form-action 'self' https://www.paynow.co.zw; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');

    if (request_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function secure_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    $secure = request_is_https();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

function csrf_token() {
    if (empty($_SESSION['vpr_csrf'])) {
        $_SESSION['vpr_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['vpr_csrf'];
}

function verify_csrf($token) {
    return is_string($token) && !empty($_SESSION['vpr_csrf']) && hash_equals($_SESSION['vpr_csrf'], $token);
}

function client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function protected_ip_value() {
    return hash_hmac('sha256', client_ip(), VPR_IP_HASH_KEY);
}

function admin_session_valid() {
    if (empty($_SESSION['vpr_admin'])) return false;
    $last = (int)($_SESSION['vpr_admin_last'] ?? 0);
    if ($last && time() - $last > VPR_ADMIN_SESSION_TIMEOUT) {
        $_SESSION = [];
        return false;
    }
    $_SESSION['vpr_admin_last'] = time();
    return true;
}

function audit_admin($action, $details = '') {
    try {
        $stmt = db()->prepare("INSERT INTO admin_audit(action,details,ip_hash,created_at) VALUES(?,?,?,CURRENT_TIMESTAMP)");
        $stmt->execute([$action, mb_substr((string)$details,0,1000), protected_ip_value()]);
    } catch (Throwable $e) {}
}

function generate_cert_token() {
    return bin2hex(random_bytes(24));
}

function valid_registration($reg) {
    return is_string($reg) && preg_match('/^[A-Z]{3}[0-9]{4}$/', $reg);
}


function send_security_email($subject, $body) {
    $headers = [];
    $headers[] = 'From: VPR Security <' . VPR_MAIL_FROM . '>';
    $headers[] = 'Reply-To: ' . VPR_SECURITY_EMAIL;
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    return @mail(VPR_SECURITY_EMAIL, $subject, $body, implode("\r\n", $headers));
}

function base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'vpr.co.zw';
    $dir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($dir === '.' || $dir === '/') $dir = '';
    return $scheme . '://' . $host . $dir;
}

function create_recovery_token() {
    $raw = bin2hex(random_bytes(32));
    $hash = hash('sha256', $raw);
    $expires = time() + VPR_RECOVERY_TOKEN_TTL;
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_recovery (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token_hash TEXT NOT NULL UNIQUE,
        expires_at INTEGER NOT NULL,
        used_at INTEGER,
        requested_ip_hash TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
$stmt = $pdo->prepare("INSERT INTO admin_recovery(token_hash,expires_at,requested_ip_hash) VALUES(?,?,?)");
    $stmt->execute([$hash,$expires,protected_ip_value()]);
    return $raw;
}

function verify_recovery_token($raw) {
    if (!is_string($raw) || strlen($raw) < 40) return false;
    $hash = hash('sha256', $raw);
    $stmt = db()->prepare("SELECT * FROM admin_recovery WHERE token_hash=? AND used_at IS NULL LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['expires_at'] < time()) return false;
    return $row;
}

function mark_recovery_used($raw) {
    $hash = hash('sha256', $raw);
    db()->prepare("UPDATE admin_recovery SET used_at=? WHERE token_hash=?")->execute([time(),$hash]);
}

function auth_store_path() {
    return __DIR__ . '/data/admin_auth.json';
}

function current_admin_password_hash() {
    $path = auth_store_path();
    if (is_file($path)) {
        $data = json_decode(@file_get_contents($path), true);
        if (!empty($data['password_hash'])) return $data['password_hash'];
    }
    // Backward compatibility with the existing config password.
    return password_hash(VPR_ADMIN_PASSWORD, PASSWORD_DEFAULT);
}

function verify_admin_password($password) {
    $path = auth_store_path();
    if (is_file($path)) {
        $data = json_decode(@file_get_contents($path), true);
        if (!empty($data['password_hash'])) return password_verify((string)$password, $data['password_hash']);
    }
    return hash_equals(VPR_ADMIN_PASSWORD, (string)$password);
}

function set_admin_password($password) {
    $data = [
        'password_hash' => password_hash((string)$password, PASSWORD_DEFAULT),
        'updated_at' => date('c')
    ];
    return @file_put_contents(auth_store_path(), json_encode($data, JSON_PRETTY_PRINT), LOCK_EX) !== false;
}



function clean($value) {
    return trim((string)$value);
}

function normalise_chassis($value) {
    $v = strtoupper(preg_replace('/\s+/', '', clean($value)));
    return $v === '' ? null : $v;
}

function normalise_reg($value) {
    $v = strtoupper(preg_replace('/\s+/', '', clean($value)));
    return $v === '' ? null : $v;
}

function ensure_v6_schema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_metadata (
        meta_key TEXT PRIMARY KEY,
        meta_value TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        ip_hash TEXT PRIMARY KEY,
        attempts INTEGER NOT NULL DEFAULT 0,
        first_attempt INTEGER NOT NULL,
        last_attempt INTEGER NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        action TEXT NOT NULL,
        details TEXT,
        ip_hash TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_recovery (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token_hash TEXT NOT NULL UNIQUE,
        expires_at INTEGER NOT NULL,
        used_at INTEGER,
        requested_ip_hash TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
$pdo->prepare("INSERT OR REPLACE INTO system_metadata(meta_key,meta_value) VALUES('schema_version','12')")->execute();

    $cols = $pdo->query("PRAGMA table_info(vehicles)")->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($cols, 'name');
    if (!in_array('vehicle_brand', $names, true)) {
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN vehicle_brand TEXT NOT NULL DEFAULT 'Other'");
    }

    $scols = $pdo->query("PRAGMA table_info(searches)")->fetchAll(PDO::FETCH_ASSOC);
    $snames = array_column($scols, 'name');
    if (!in_array('vehicle_brand', $snames, true)) {
        $pdo->exec("ALTER TABLE searches ADD COLUMN vehicle_brand TEXT NOT NULL DEFAULT 'Other'");
    }
    if (!in_array('certificate_number', $snames, true)) {
        $pdo->exec("ALTER TABLE searches ADD COLUMN certificate_number TEXT");
    }
    if (!in_array('access_token', $snames, true)) {
        $pdo->exec("ALTER TABLE searches ADD COLUMN access_token TEXT");
    }

    // Keep registration number unique: old duplicates are consolidated.
    $dupes = $pdo->query("
        SELECT registration_number, MIN(id) keep_id
        FROM vehicles
        WHERE registration_number IS NOT NULL AND registration_number <> ''
        GROUP BY registration_number
        HAVING COUNT(*) > 1
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dupes as $d) {
        $reg = $d['registration_number'];
        $keep = (int)$d['keep_id'];
        $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE registration_number = ? AND id <> ?");
        $stmt->execute([$reg, $keep]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $old) {
            $pdo->prepare("UPDATE searches SET vehicle_id=? WHERE vehicle_id=?")->execute([$keep, $old]);
            $pdo->prepare("UPDATE interests SET vehicle_id=? WHERE vehicle_id=?")->execute([$keep, $old]);
            $pdo->prepare("DELETE FROM vehicles WHERE id=?")->execute([$old]);
        }
    }

    $pdo->exec("DROP INDEX IF EXISTS idx_vehicles_reg_unique");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_vehicles_reg_unique ON vehicles(registration_number) WHERE registration_number IS NOT NULL AND registration_number <> ''");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_searches_date ON searches(searched_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_interests_vehicle ON interests(vehicle_id)");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_searches_certificate ON searches(certificate_number) WHERE certificate_number IS NOT NULL AND certificate_number <> ''");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_searches_access_token ON searches(access_token) WHERE access_token IS NOT NULL AND access_token <> ''");

    $missingTokens = $pdo->query("SELECT id FROM searches WHERE access_token IS NULL OR access_token=''")->fetchAll(PDO::FETCH_COLUMN);
    $tokStmt = $pdo->prepare("UPDATE searches SET access_token=? WHERE id=?");
    foreach ($missingTokens as $sid) {
        $tokStmt->execute([generate_cert_token(), (int)$sid]);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS search_interests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        search_id INTEGER NOT NULL,
        priority_rank INTEGER NOT NULL,
        interested_party TEXT NOT NULL,
        reference_note TEXT,
        interest_registered_at TEXT,
        FOREIGN KEY(search_id) REFERENCES searches(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_search_interests_search ON search_interests(search_id)");

    // V12.9: create or migrate the payment table on every application startup.
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        merchant_reference TEXT NOT NULL UNIQUE,
        paynow_reference TEXT,
        amount TEXT NOT NULL DEFAULT '1.00',
        currency TEXT NOT NULL DEFAULT 'USD',
        status TEXT NOT NULL DEFAULT 'Created',
        pollurl TEXT,
        return_token TEXT,
        registration_number TEXT,
        vehicle_brand TEXT,
        vehicle_model TEXT,
        chassis_number TEXT,
        search_id INTEGER,
        last_error TEXT,
        created_at TEXT,
        updated_at TEXT,
        paid_at TEXT
    )");

    $pcols = $pdo->query("PRAGMA table_info(payments)")->fetchAll(PDO::FETCH_ASSOC);
    $pnames = array_column($pcols, 'name');
    $pmigrations = [
        'paynow_reference' => "ALTER TABLE payments ADD COLUMN paynow_reference TEXT",
        'amount' => "ALTER TABLE payments ADD COLUMN amount TEXT NOT NULL DEFAULT '1.00'",
        'currency' => "ALTER TABLE payments ADD COLUMN currency TEXT NOT NULL DEFAULT 'USD'",
        'status' => "ALTER TABLE payments ADD COLUMN status TEXT NOT NULL DEFAULT 'Created'",
        'pollurl' => "ALTER TABLE payments ADD COLUMN pollurl TEXT",
        'return_token' => "ALTER TABLE payments ADD COLUMN return_token TEXT",
        'registration_number' => "ALTER TABLE payments ADD COLUMN registration_number TEXT",
        'vehicle_brand' => "ALTER TABLE payments ADD COLUMN vehicle_brand TEXT",
        'vehicle_model' => "ALTER TABLE payments ADD COLUMN vehicle_model TEXT",
        'chassis_number' => "ALTER TABLE payments ADD COLUMN chassis_number TEXT",
        'search_id' => "ALTER TABLE payments ADD COLUMN search_id INTEGER",
        'last_error' => "ALTER TABLE payments ADD COLUMN last_error TEXT",
        'created_at' => "ALTER TABLE payments ADD COLUMN created_at TEXT",
        'updated_at' => "ALTER TABLE payments ADD COLUMN updated_at TEXT",
        'paid_at' => "ALTER TABLE payments ADD COLUMN paid_at TEXT",
        'payment_method' => "ALTER TABLE payments ADD COLUMN payment_method TEXT"
    ];
    foreach ($pmigrations as $pname => $psql) {
        if (!in_array($pname, $pnames, true)) $pdo->exec($psql);
    }
    $pdo->exec("UPDATE payments SET amount='1.00' WHERE amount IS NULL OR amount=''");
    $pdo->exec("UPDATE payments SET currency='USD' WHERE currency IS NULL OR currency=''");
    $pdo->exec("UPDATE payments SET status='Created' WHERE status IS NULL OR status=''");
    $pdo->exec("UPDATE payments SET created_at=CURRENT_TIMESTAMP WHERE created_at IS NULL OR created_at=''");
    $pdo->exec("UPDATE payments SET updated_at=CURRENT_TIMESTAMP WHERE updated_at IS NULL OR updated_at=''");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_reg ON payments(registration_number)");


    vpr_checkout_schema($pdo);

    // Freeze a certificate number onto legacy searches so future exports/imports keep it stable.
    $legacy = $pdo->query("SELECT id,searched_at FROM searches WHERE certificate_number IS NULL OR certificate_number=''")->fetchAll(PDO::FETCH_ASSOC);
    $up = $pdo->prepare("UPDATE searches SET certificate_number=? WHERE id=?");
    foreach ($legacy as $row) {
        $up->execute([certificate_ref((int)$row['id'], $row['searched_at']), (int)$row['id']]);
    }
}

function find_vehicle_by_reg(PDO $pdo, $reg) {
    $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE registration_number = ? LIMIT 1");
    $stmt->execute([$reg]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function certificate_ref($searchId, $dateString = null) {
    $date = $dateString ? strtotime($dateString) : time();
    return 'VPR-' . date('Ymd', $date) . '-' . str_pad((string)$searchId, 6, '0', STR_PAD_LEFT);
}

function certificate_number_for_row(array $row) {
    if (!empty($row['certificate_number'])) return $row['certificate_number'];
    return certificate_ref((int)$row['id'], $row['searched_at'] ?? null);
}
?>
