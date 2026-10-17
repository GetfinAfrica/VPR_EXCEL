<?php
require_once __DIR__ . '/db.php';
security_headers();
secure_session_start();

if (!admin_session_valid()) {
    http_response_code(403);
    exit('Admin login required.');
}

$pdo = db();
$action = $_GET['action'] ?? '';

function xesc($s) {
    return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function col_letter($n) {
    $s = '';
    while ($n > 0) {
        $n--;
        $s = chr(65 + ($n % 26)) . $s;
        $n = intdiv($n, 26);
    }
    return $s;
}

function sheet_xml(array $rows) {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $ri => $row) {
        $r = $ri + 1;
        $xml .= '<row r="'.$r.'">';
        foreach (array_values($row) as $ci => $value) {
            $ref = col_letter($ci + 1) . $r;
            if ($value === null) $value = '';
            if (is_int($value) || is_float($value)) {
                $xml .= '<c r="'.$ref.'"><v>'.xesc($value).'</v></c>';
            } else {
                $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.xesc($value).'</t></is></c>';
            }
        }
        $xml .= '</row>';
    }
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function make_xlsx(array $sheets, $filepath) {
    if (!class_exists('ZipArchive')) throw new Exception('PHP ZipArchive extension is required.');
    $zip = new ZipArchive();
    if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new Exception('Could not create Excel file.');

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
    $contentTypes .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
    $contentTypes .= '<Default Extension="xml" ContentType="application/xml"/>';
    $contentTypes .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
    $contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    foreach (array_keys($sheets) as $i => $name) {
        $contentTypes .= '<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }
    $contentTypes .= '</Types>';
    $zip->addFromString('[Content_Types].xml', $contentTypes);

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
        '</Relationships>');

    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    foreach (array_keys($sheets) as $i => $name) {
        $n = $i + 1;
        $wb .= '<sheet name="'.xesc($name).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
        $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
    }
    $styleRid = count($sheets) + 1;
    $rels .= '<Relationship Id="rId'.$styleRid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $rels .= '</Relationships>';
    $wb .= '</sheets></workbook>';
    $zip->addFromString('xl/workbook.xml', $wb);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
              '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'.
              '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'.
              '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'.
              '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'.
              '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'.
              '</styleSheet>';
    $zip->addFromString('xl/styles.xml', $styles);

    foreach (array_values($sheets) as $i => $rows) {
        $zip->addFromString('xl/worksheets/sheet'.($i+1).'.xml', sheet_xml($rows));
    }
    $zip->close();
}

function rows_with_headers(PDO $pdo, $sql, array $headers) {
    $data = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $rows = [$headers];
    foreach ($data as $row) {
        $out = [];
        foreach ($headers as $h) $out[] = $row[$h] ?? '';
        $rows[] = $out;
    }
    return $rows;
}

function read_xlsx($file) {
    if (!class_exists('ZipArchive')) throw new Exception('PHP ZipArchive extension is required.');
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) throw new Exception('Could not open uploaded Excel file.');

    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss) {
        $xml = simplexml_load_string($ss);
        $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        foreach ($xml->xpath('//m:si') as $si) {
            $parts = $si->xpath('.//m:t');
            $txt = '';
            foreach ($parts as $p) $txt .= (string)$p;
            $shared[] = $txt;
        }
    }

    $wb = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $wb->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    $rels = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
    $rels->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');
    $relmap = [];
    foreach ($rels->xpath('//p:Relationship') as $rel) {
        $relmap[(string)$rel['Id']] = (string)$rel['Target'];
    }

    $out = [];
    foreach ($wb->xpath('//m:sheets/m:sheet') as $sheet) {
        $name = (string)$sheet['name'];
        $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rid = (string)$attrs['id'];
        $target = $relmap[$rid] ?? '';
        if (!$target) continue;
        if (strpos($target, '/') === 0) $path = ltrim($target, '/');
        else $path = 'xl/' . ltrim($target, '/');
        $sx = simplexml_load_string($zip->getFromName($path));
        $sx->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($sx->xpath('//m:sheetData/m:row') as $row) {
            $vals = [];
            foreach ($row->xpath('./m:c') as $c) {
                $ref = (string)$c['r'];
                preg_match('/([A-Z]+)/', $ref, $mm);
                $letters = $mm[1] ?? 'A';
                $col = 0;
                for ($i=0; $i<strlen($letters); $i++) $col = $col*26 + (ord($letters[$i])-64);
                $col--;

                $type = (string)$c['t'];
                $value = '';
                if ($type === 'inlineStr') {
                    $ts = $c->xpath('.//m:t');
                    foreach ($ts as $t) $value .= (string)$t;
                } else {
                    $v = $c->xpath('./m:v');
                    $raw = isset($v[0]) ? (string)$v[0] : '';
                    if ($type === 's') $value = $shared[(int)$raw] ?? '';
                    else $value = $raw;
                }
                $vals[$col] = $value;
            }
            if ($vals) {
                $max = max(array_keys($vals));
                $full = [];
                for ($i=0; $i<=$max; $i++) $full[] = $vals[$i] ?? '';
                $rows[] = $full;
            }
        }
        $out[$name] = $rows;
    }
    $zip->close();
    return $out;
}

function assoc_rows(array $rows) {
    if (!$rows) return [];
    $headers = array_map('trim', array_shift($rows));
    $out = [];
    foreach ($rows as $r) {
        if (!array_filter($r, fn($v) => trim((string)$v) !== '')) continue;
        $a = [];
        foreach ($headers as $i => $h) $a[$h] = $r[$i] ?? '';
        $out[] = $a;
    }
    return $out;
}

if ($action === 'export') {
    audit_admin('excel_export');
    $metadata = [
        ['key','value'],
        ['system_name','Vehicle Pledge Registry'],
        ['schema_version','12'],
        ['exported_at',date('Y-m-d H:i:s')],
        ['purpose','VPR Excel backup and future migration file'],
        ['certificate_note','Certificate numbers are preserved independently of internal database IDs']
    ];

    $vehicles = rows_with_headers($pdo,
        "SELECT id,chassis_number,vehicle_brand,vehicle_model,registration_number,created_at,updated_at FROM vehicles ORDER BY id",
        ['id','chassis_number','vehicle_brand','vehicle_model','registration_number','created_at','updated_at']);

    $interests = rows_with_headers($pdo,
        "SELECT i.id,i.vehicle_id,v.registration_number,i.interested_party,i.reference_note,i.status,i.created_at,i.cleared_at FROM interests i JOIN vehicles v ON v.id=i.vehicle_id ORDER BY i.id",
        ['id','vehicle_id','registration_number','interested_party','reference_note','status','created_at','cleared_at']);

    $searches = rows_with_headers($pdo,
        "SELECT s.id,s.vehicle_id,s.certificate_number,s.access_token,s.chassis_number,s.vehicle_brand,s.vehicle_model,s.registration_number,s.result,s.ip_address,s.searched_at FROM searches s ORDER BY s.id",
        ['id','vehicle_id','certificate_number','access_token','chassis_number','vehicle_brand','vehicle_model','registration_number','result','ip_address','searched_at']);

    $snapshots = rows_with_headers($pdo,
        "SELECT si.id,si.search_id,s.certificate_number,si.priority_rank,si.interested_party,si.reference_note,si.interest_registered_at FROM search_interests si JOIN searches s ON s.id=si.search_id ORDER BY si.search_id,si.priority_rank",
        ['id','search_id','certificate_number','priority_rank','interested_party','reference_note','interest_registered_at']);
$tmp = tempnam(sys_get_temp_dir(), 'vpr_') . '.xlsx';
    make_xlsx([
        'Metadata'=>$metadata,
        'Vehicles'=>$vehicles,
        'Interests'=>$interests,
        'Searches'=>$searches,
        'SearchInterests'=>$snapshots
    ], $tmp);

    $filename = 'VPR_Backup_' . date('Y-m-d_His') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

if ($action === 'import') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403); exit('Invalid security token.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['backup_file']['tmp_name'])) {
        http_response_code(400); exit('No Excel backup uploaded.');
    }
    if (($_FILES['backup_file']['size'] ?? 0) > 25 * 1024 * 1024) {
        http_response_code(400); exit('Backup file is too large.');
    }

    try {
        $book = read_xlsx($_FILES['backup_file']['tmp_name']);
        foreach (['Metadata','Vehicles','Interests','Searches'] as $required) {
            if (!isset($book[$required])) throw new Exception("This is not a VPR backup workbook. Missing sheet: $required. Upload the .xlsx file downloaded from VPR Data Backup & Migration.");
        }

        $metaRows = assoc_rows($book['Metadata']);
        $meta = [];
        foreach ($metaRows as $m) $meta[$m['key'] ?? ''] = $m['value'] ?? '';
        $version = (int)($meta['schema_version'] ?? 0);
        if ($version < 1 || $version > 12) throw new Exception('Unsupported or invalid VPR backup schema version.');

        $vehicles = assoc_rows($book['Vehicles']);
        $interests = assoc_rows($book['Interests']);
        $searches = assoc_rows($book['Searches']);
        $snaps = isset($book['SearchInterests']) ? assoc_rows($book['SearchInterests']) : [];
$pdo->beginTransaction();
        $vehicleMap = [];
        $vFind = $pdo->prepare("SELECT id FROM vehicles WHERE registration_number=?");
        $vIns = $pdo->prepare("INSERT INTO vehicles(chassis_number,vehicle_brand,vehicle_model,registration_number,created_at,updated_at) VALUES(?,?,?,?,?,?)");
        $vUpd = $pdo->prepare("UPDATE vehicles SET chassis_number=COALESCE(NULLIF(?,''),chassis_number), vehicle_brand=CASE WHEN vehicle_brand='Other' OR vehicle_brand='' THEN ? ELSE vehicle_brand END, vehicle_model=CASE WHEN length(?)>length(vehicle_model) THEN ? ELSE vehicle_model END, updated_at=CASE WHEN ?<>'' THEN ? ELSE updated_at END WHERE id=?");

        foreach ($vehicles as $v) {
            $reg = normalise_reg($v['registration_number'] ?? '');
            if (!$reg) continue;
            $vFind->execute([$reg]);
            $existing = $vFind->fetchColumn();
            if ($existing) {
                $newId = (int)$existing;
                $vUpd->execute([$v['chassis_number'] ?? '', $v['vehicle_brand'] ?? 'Other', $v['vehicle_model'] ?? '', $v['vehicle_model'] ?? '', $v['updated_at'] ?? '', $v['updated_at'] ?? '', $newId]);
            } else {
                $vIns->execute([
                    normalise_chassis($v['chassis_number'] ?? ''),
                    $v['vehicle_brand'] ?? 'Other',
                    $v['vehicle_model'] ?? '',
                    $reg,
                    $v['created_at'] ?: date('Y-m-d H:i:s'),
                    $v['updated_at'] ?: date('Y-m-d H:i:s')
                ]);
                $newId = (int)$pdo->lastInsertId();
            }
            $vehicleMap[(string)($v['id'] ?? '')] = $newId;
            $vehicleMap['reg:'.$reg] = $newId;
        }

        $iCheck = $pdo->prepare("SELECT id FROM interests WHERE vehicle_id=? AND interested_party=? AND COALESCE(reference_note,'')=? AND created_at=? LIMIT 1");
        $iIns = $pdo->prepare("INSERT INTO interests(vehicle_id,interested_party,reference_note,status,created_at,cleared_at) VALUES(?,?,?,?,?,?)");
        foreach ($interests as $i) {
            $reg = normalise_reg($i['registration_number'] ?? '');
            $vid = $vehicleMap[(string)($i['vehicle_id'] ?? '')] ?? $vehicleMap['reg:'.$reg] ?? null;
            if (!$vid) continue;
            $party = trim((string)($i['interested_party'] ?? ''));
            if ($party === '') continue;
            $note = (string)($i['reference_note'] ?? '');
            $created = $i['created_at'] ?: date('Y-m-d H:i:s');
            $iCheck->execute([$vid,$party,$note,$created]);
            if (!$iCheck->fetchColumn()) {
                $status = strtoupper((string)($i['status'] ?? 'ACTIVE'));
                if (!in_array($status,['ACTIVE','CLEARED'],true)) $status='ACTIVE';
                $iIns->execute([$vid,$party,$note ?: null,$status,$created,$i['cleared_at'] ?: null]);
            }
        }

        $sFind = $pdo->prepare("SELECT id FROM searches WHERE certificate_number=? LIMIT 1");
        $sIns = $pdo->prepare("INSERT INTO searches(vehicle_id,certificate_number,access_token,chassis_number,vehicle_brand,vehicle_model,registration_number,result,ip_address,searched_at) VALUES(?,?,?,?,?,?,?,?,?,?)");
        $certMap = [];
        foreach ($searches as $s) {
            $cert = trim((string)($s['certificate_number'] ?? ''));
            if ($cert === '') {
                $oldId = (int)($s['id'] ?? 0);
                $cert = certificate_ref($oldId ?: 1, $s['searched_at'] ?? null);
            }
            $sFind->execute([$cert]);
            $existing = $sFind->fetchColumn();
            if ($existing) {
                $newSid = (int)$existing;
            } else {
                $reg = normalise_reg($s['registration_number'] ?? '');
                $vid = $vehicleMap[(string)($s['vehicle_id'] ?? '')] ?? $vehicleMap['reg:'.$reg] ?? null;
                $token = trim((string)($s['access_token'] ?? ''));
                if ($token === '') $token = generate_cert_token();
                $sIns->execute([
                    $vid,
                    $cert,
                    $token,
                    normalise_chassis($s['chassis_number'] ?? ''),
                    $s['vehicle_brand'] ?? 'Other',
                    $s['vehicle_model'] ?? '',
                    $reg,
                    $s['result'] ?? 'NO INTEREST RECORDED',
                    $s['ip_address'] ?? '',
                    $s['searched_at'] ?: date('Y-m-d H:i:s')
                ]);
                $newSid = (int)$pdo->lastInsertId();
            }
            $certMap[$cert] = $newSid;
        }

        $snapCheck = $pdo->prepare("SELECT id FROM search_interests WHERE search_id=? AND priority_rank=? AND interested_party=? LIMIT 1");
        $snapIns = $pdo->prepare("INSERT INTO search_interests(search_id,priority_rank,interested_party,reference_note,interest_registered_at) VALUES(?,?,?,?,?)");
        foreach ($snaps as $si) {
            $cert = trim((string)($si['certificate_number'] ?? ''));
            $sid = $certMap[$cert] ?? null;
            if (!$sid) continue;
            $rank = max(1,(int)($si['priority_rank'] ?? 1));
            $party = trim((string)($si['interested_party'] ?? ''));
            if ($party === '') continue;
            $snapCheck->execute([$sid,$rank,$party]);
            if (!$snapCheck->fetchColumn()) {
                $snapIns->execute([$sid,$rank,$party,$si['reference_note'] ?: null,$si['interest_registered_at'] ?: null]);
            }
        }


        $pdo->commit();
        audit_admin('excel_import');
        checkpoint_db('PASSIVE');
        header('Location: admin2000.php?imported=1');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(400);
        echo '<!doctype html><html><body style="font-family:Arial;padding:30px"><h2>VPR Import Failed</h2><p>'.htmlspecialchars($e->getMessage()).'</p><p><a href="admin2000.php">Return to Admin</a></p></body></html>';
        exit;
    }
}

http_response_code(400);
echo 'Invalid action.';
?>
