<?php
// Shared presentation only; payment verification remains in payments.php.
function vpr_ui_assets(): void {
    echo '<link rel="stylesheet" href="assets/ui.css?v='.filemtime(__DIR__.'/assets/ui.css').'">';
}
function vpr_breadcrumbs(string $current,array $parents=[]): void {
    echo '<nav class="vpr-breadcrumbs" aria-label="Breadcrumb"><ol><li><a href="index.php">Home</a></li>';
    foreach($parents as $parent)echo '<li><a href="'.htmlspecialchars($parent['href'],ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($parent['label'],ENT_QUOTES,'UTF-8').'</a></li>';
    echo '<li aria-current="page">'.htmlspecialchars($current,ENT_QUOTES,'UTF-8').'</li></ol></nav>';
}
function vpr_page_heading(string $title, string $subtitle='', int $level=1, string $id=''): void {
    if($level===1){
        $page=basename($_SERVER['SCRIPT_NAME']??'');$parents=[];
        if(in_array($page,['recover_admin.php','domain_check.php','repair_payment_db.php','data_exchange.php'],true))$parents[]=['label'=>'Administration','href'=>'admin2000.php'];
        if($page==='payment_success.php'&&is_string($_GET['ref']??null)&&is_string($_GET['token']??null))$parents[]=['label'=>'Payment status','href'=>'paynow_return.php?ref='.urlencode($_GET['ref']).'&token='.urlencode($_GET['token'])];
        vpr_breadcrumbs($title,$parents);
    }
    $tag=$level===2?'h2':'h1';
    echo '<header class="vpr-page-heading"><div><'.$tag.($id!==''?' id="'.htmlspecialchars($id,ENT_QUOTES,'UTF-8').'"':'').'>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</'.$tag.'>';
    if($subtitle!=='')echo '<p>'.htmlspecialchars($subtitle,ENT_QUOTES,'UTF-8').'</p>';
    echo '</div><img class="vpr-brand-logo" src="assets/vpr-logo.png" alt="Vehicle Pledge Registry"></header>';
}
