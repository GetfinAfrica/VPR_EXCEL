<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__.'/db.php'; security_headers();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'message'=>'Invalid request method.']);exit;}
if(!defined('VPR_ZIG_API_ENABLED') || !VPR_ZIG_API_ENABLED){
 http_response_code(503);echo json_encode(['ok'=>false,'message'=>'ZiG API is prepared but disabled until the provider supplies the complete charge request schema, production URL and payment confirmation method.']);exit;
}
http_response_code(503);echo json_encode(['ok'=>false,'message'=>'ZiG API configuration is incomplete.']);
?>