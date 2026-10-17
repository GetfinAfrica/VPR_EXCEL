<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
require $root.'/config.php';
$target=$root.'/data/vpr.dev.sqlite';
if (!is_file($target)) {
    if (!is_file(VPR_DB)) { fwrite(STDERR,"Source database is missing.\n");exit(1); }
    $pdo=new PDO('sqlite:'.VPR_DB);
    $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA busy_timeout=5000');
    // Consistent SQLite snapshot, including committed WAL content.
    $pdo->exec('VACUUM INTO '.$pdo->quote($target));
}
$config=file_get_contents($root.'/config.php');
$replacements=[
    'VPR_DB'=>var_export($target,true),
    'VPR_FORCE_HTTPS'=>'false',
    'VPR_PUBLIC_URL'=>var_export('http://127.0.0.1:'.((int)($argv[1]??8088)),true)
];
foreach($replacements as $name=>$value) {
    $pattern='/define\(\s*[\'"]'.preg_quote($name,'/').'[\'"]\s*,[^;]*\);/';
    $config=preg_replace_callback($pattern,fn()=>"define('$name', $value);",$config,1,$count);
    if($count!==1){fwrite(STDERR,"Unable to prepare development configuration.\n");exit(1);}
}
// The source config is already private. Keep the generated copy inside blocked,
// ignored storage and never print its contents (it includes existing admin settings).
file_put_contents($root.'/data/vpr.dev.config.php',$config,LOCK_EX);
echo "Development database and private configuration ready.\n";
