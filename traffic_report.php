<?php
require_once __DIR__ . '/db.php';
security_headers();
secure_session_start();
if (!admin_session_valid()) { http_response_code(403); exit('Admin login required.'); }

$pdo=db();
$format=strtolower($_GET['format'] ?? 'xlsx');
$class=$_GET['stats_class'] ?? 'day';
if(!in_array($class,['day','week','month'],true)) $class='day';
$period=trim((string)($_GET['stats_period'] ?? ''));

if($class==='day'){
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$period)) $period=date('Y-m-d');
    $where="date(searched_at)=?"; $params=[$period]; $label=date('d M Y',strtotime($period));
}elseif($class==='week'){
    if(!preg_match('/^(\d{4})-W(\d{2})$/',$period,$m)){ $period=date('o-\WW'); preg_match('/^(\d{4})-W(\d{2})$/',$period,$m); }
    $d=new DateTime(); $d->setISODate((int)$m[1],(int)$m[2],1); $start=$d->format('Y-m-d'); $d->modify('+6 days'); $end=$d->format('Y-m-d');
    $where="date(searched_at) BETWEEN ? AND ?"; $params=[$start,$end]; $label='Week '.$m[2].' ('.date('d M',strtotime($start)).' - '.date('d M Y',strtotime($end)).')';
}else{
    if(!preg_match('/^\d{4}-\d{2}$/',$period)) $period=date('Y-m');
    $where="strftime('%Y-%m',searched_at)=?"; $params=[$period]; $label=date('F Y',strtotime($period.'-01'));
}
$q=$pdo->prepare("SELECT certificate_number,registration_number,vehicle_brand,vehicle_model,result,searched_at FROM searches WHERE $where ORDER BY datetime(searched_at) DESC,id DESC");
$q->execute($params); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
audit_admin('traffic_report_'.$format,$class.' '.$period);

function pe($s){return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],(string)$s);}
function simple_pdf($label,$rows){
    $lines=['VPR SITE TRAFFIC REPORT',$label,'Generated: '.date('d M Y H:i'),'Total Searches: '.count($rows),'','Date/Time | Registration | Vehicle | Result | Certificate',str_repeat('-',105)];
    foreach($rows as $r){
        $x=date('d M Y H:i',strtotime($r['searched_at'])).' | '.$r['registration_number'].' | '.$r['vehicle_brand'].' '.$r['vehicle_model'].' | '.$r['result'].' | '.($r['certificate_number']??'');
        $lines[]=strlen($x)>118?substr($x,0,115).'...':$x;
    }
    $chunks=array_chunk($lines,48); $objs=[]; $pages=[]; $contents=[]; $next=4;
    foreach($chunks as $_){$pages[]=$next++;$contents[]=$next++;}
    $objs[1]='<< /Type /Catalog /Pages 2 0 R >>';
    $kids=implode(' ',array_map(fn($x)=>$x.' 0 R',$pages));
    $objs[2]='<< /Type /Pages /Kids [ '.$kids.' ] /Count '.count($pages).' >>';
    $objs[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    foreach($chunks as $i=>$chunk){
        $stream="BT\n/F1 8 Tf\n40 555 Td\n";
        foreach($chunk as $j=>$line){ if($j===0)$stream.="/F1 14 Tf\n"; elseif($j===1)$stream.="/F1 10 Tf\n"; elseif($j===2)$stream.="/F1 8 Tf\n"; $stream.='('.pe($line).") Tj\n0 -11 Td\n"; }
        $stream.="ET";
        $objs[$pages[$i]]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contents[$i].' 0 R >>';
        $objs[$contents[$i]]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
    }
    ksort($objs); $pdf="%PDF-1.4\n"; $offs=[0];
    foreach($objs as $id=>$obj){$offs[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$obj."\nendobj\n";}
    $xref=strlen($pdf);$max=max(array_keys($objs));$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
    for($i=1;$i<=$max;$i++)$pdf.=sprintf("%010d 00000 n \n",$offs[$i]??0);
    return $pdf."trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
}
if($format==='pdf'){
    $pdf=simple_pdf($label,$rows);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="VPR_Traffic_'.$class.'_'.preg_replace('/[^0-9A-Za-z-]/','_',$period).'.pdf"');
    header('Content-Length: '.strlen($pdf)); echo $pdf; exit;
}

if(!class_exists('ZipArchive')){http_response_code(500);exit('PHP ZipArchive extension is required for Excel export.');}
function xe($s){return htmlspecialchars((string)$s,ENT_XML1|ENT_QUOTES,'UTF-8');}
function cl($n){$s='';while($n>0){$n--;$s=chr(65+($n%26)).$s;$n=intdiv($n,26);}return $s;}
function sx($rows){$x='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';foreach($rows as $ri=>$row){$r=$ri+1;$x.='<row r="'.$r.'">';foreach(array_values($row) as $ci=>$v){$ref=cl($ci+1).$r;$x.='<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.xe($v).'</t></is></c>';}$x.='</row>';}$x.='</sheetData></worksheet>';return $x;}
function make_xlsx_report($rows,$path){
 $z=new ZipArchive(); if($z->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new Exception('Could not create Excel file.');
 $z->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
 $z->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
 $z->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Traffic" sheetId="1" r:id="rId1"/></sheets></workbook>');
 $z->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
 $z->addFromString('xl/worksheets/sheet1.xml',sx($rows));$z->close();
}
$er=[['VPR Site Traffic Report'],['Period Class',$class],['Selected Period',$label],['Generated',date('Y-m-d H:i:s')],['Total Searches',count($rows)],[],['Date/Time','Registration Number','Vehicle Brand','Vehicle Model','Result','Certificate Number']];
foreach($rows as $r)$er[]=[$r['searched_at'],$r['registration_number'],$r['vehicle_brand'],$r['vehicle_model'],$r['result'],$r['certificate_number']??''];
$tmp=tempnam(sys_get_temp_dir(),'vprtraffic_').'.xlsx';make_xlsx_report($er,$tmp);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="VPR_Traffic_'.$class.'_'.preg_replace('/[^0-9A-Za-z-]/','_',$period).'.xlsx"');
header('Content-Length: '.filesize($tmp));readfile($tmp);@unlink($tmp);exit;
?>