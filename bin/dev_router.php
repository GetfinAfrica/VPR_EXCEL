<?php
// Only for PHP's loopback development server. Apache enforces its own .htaccess.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path=preg_replace('~/+~','/',rawurldecode(explode('?',$_SERVER['REQUEST_URI'],2)[0] ?: '/'));
if (strpos($path,'\\')!==false || strpos($path,"\0")!==false || preg_match('~(?:^|/)\.~',$path) ||
    preg_match('~^/(?:data|bin|tests|Documentation)(?:/|$)~i',$path) ||
    preg_match('~/(?:config|db|payments|paynow_profiles)\.php$~i',$path) ||
    preg_match('~\.(?:env|example|sqlite\w*|db|bak|log|sql|ps1|md|txt|json)$~i',$path)) {
    http_response_code(403); echo 'Access denied.'; return true;
}
return false;
