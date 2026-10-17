<?php
// Compatibility entry point: same verified Paynow checkout and authorization rules.
$_POST['payment_currency'] = 'ZWG';
require __DIR__ . '/paynow_create.php';
