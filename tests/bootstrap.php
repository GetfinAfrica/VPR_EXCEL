<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('VPR_CONFIG_FILE'); // Tests must never inherit a real application's runtime config.
$fixture = getenv('VPR_TEST_FIXTURE');
if (!$fixture) {
    $fixture = sys_get_temp_dir() . '/vpr-checkout-' . bin2hex(random_bytes(8));
    mkdir($fixture); mkdir($fixture . '/data');
    foreach (['db.php','payments.php','paynow_profiles.php','express_checkout.php','index.php','search_process.php','paynow_create.php','paynow_result.php','paynow_status.php','paynow_return.php','certificate.php','admin2000.php','ui.php','payment_success.php'] as $file) {
        copy(dirname(__DIR__) . '/' . $file, $fixture . '/' . $file);
    }
    mkdir($fixture.'/assets');
    copy(dirname(__DIR__).'/assets/ui.css',$fixture.'/assets/ui.css');
    copy(dirname(__DIR__).'/assets/checkout.js',$fixture.'/assets/checkout.js');
    $config = <<<'PHP'
<?php
define('VPR_DB', __DIR__.'/data/vpr.sqlite');
define('VPR_FORCE_HTTPS', false);
define('VPR_PUBLIC_URL', 'http://127.0.0.1:8000');
define('VPR_SEARCH_FEE', '1.00');
define('VPR_ZIG_SEARCH_FEE', '2.00');
define('VPR_IP_HASH_KEY', 'fixture-ip-key');
define('VPR_ADMIN_PASSWORD', 'fixture-admin-password');
define('VPR_ADMIN_SESSION_TIMEOUT', 1800);
define('VPR_VERSION', 'test');
define('VPR_MAX_LOGIN_ATTEMPTS', 5);
define('VPR_LOGIN_BLOCK_SECONDS', 900);
PHP;
    file_put_contents($fixture . '/config.php', $config);
    $base = new PDO('sqlite:' . $fixture . '/data/vpr.sqlite');
    $base->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $base->exec("CREATE TABLE vehicles(id INTEGER PRIMARY KEY AUTOINCREMENT,chassis_number TEXT,
        vehicle_model TEXT NOT NULL,registration_number TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
        CREATE TABLE interests(id INTEGER PRIMARY KEY AUTOINCREMENT,vehicle_id INTEGER NOT NULL,interested_party TEXT NOT NULL,
        reference_note TEXT,status TEXT DEFAULT 'ACTIVE',created_at TEXT DEFAULT CURRENT_TIMESTAMP,cleared_at TEXT);
        CREATE TABLE searches(id INTEGER PRIMARY KEY AUTOINCREMENT,vehicle_id INTEGER,chassis_number TEXT,vehicle_model TEXT,
        registration_number TEXT,result TEXT,ip_address TEXT,searched_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $base = null;
    putenv('VPR_TEST_FIXTURE=' . $fixture);
}
foreach (['USD','ZWG'] as $ccy) {
    putenv('VPR_PAYNOW_'.$ccy.'_ID=' . ($ccy === 'USD' ? '10001' : '10002'));
    putenv('VPR_PAYNOW_'.$ccy.'_KEY=fixture-' . $ccy . '-key');
    putenv('VPR_PAYNOW_'.$ccy.'_ENVIRONMENT=test');
    putenv('VPR_PAYNOW_'.$ccy.'_ENABLED=1');
    putenv('VPR_PAYNOW_'.$ccy.'_CURRENCY_CONFIRMED=1');
}
require_once $fixture . '/db.php';
$pdo = db();
