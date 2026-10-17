<?php
// Shared presentation only; payment verification remains in payments.php.
function vpr_ui_assets(): void {
    echo '<link rel="stylesheet" href="assets/ui.css?v='.filemtime(__DIR__.'/assets/ui.css').'">';
}
function vpr_page_heading(string $title, string $subtitle='', int $level=1, string $id=''): void {
    $tag=$level===2?'h2':'h1';
    echo '<header class="vpr-page-heading"><div><'.$tag.($id!==''?' id="'.htmlspecialchars($id,ENT_QUOTES,'UTF-8').'"':'').'>'.htmlspecialchars($title,ENT_QUOTES,'UTF-8').'</'.$tag.'>';
    if($subtitle!=='')echo '<p>'.htmlspecialchars($subtitle,ENT_QUOTES,'UTF-8').'</p>';
    echo '</div><img class="vpr-brand-logo" src="assets/vpr-logo.png" alt="Vehicle Pledge Registry"></header>';
}
