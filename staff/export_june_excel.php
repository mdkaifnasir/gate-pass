<?php
// staff/export_june_excel.php
// Generates a proper .xlsx (Excel 2007+) file for all June 2026 Admission Enquiries
// Uses PHP's built-in ZipArchive — no extra libraries required.

ini_set('display_errors', 0);
error_reporting(0);

require_once __DIR__ . '/../config.php';

if (empty($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

// ── Fetch all June 2026 admission enquiry records ──────────────────────────
// Try live DB first; fall back to CSV snapshot if DB is unreachable.
$rows    = [];
$csvPath = __DIR__ . '/../admission_enquiries.csv';

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            student_name,
            mobile,
            purpose,
            status,
            created_at
        FROM gate_passes
        WHERE created_at >= '2026-06-01 00:00:00'
          AND created_at <= '2026-06-30 23:59:59'
          AND (roll_no = 'Admission Visitor' OR purpose LIKE '%Admission Enquiry%' OR purpose LIKE '%Admission%')
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    $rows = [];
}

// Fallback: read from CSV
if (empty($rows) && file_exists($csvPath)) {
    if (($fh = fopen($csvPath, 'r')) !== false) {
        fgetcsv($fh); // skip header
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 4) continue;
            $dateRaw = trim($row[3]);
            $ts = strtotime($dateRaw);
            if ($ts === false) continue;
            $ymd = date('Y-m-d', $ts);
            if ($ymd < '2026-06-01' || $ymd > '2026-06-30') continue;
            $rows[] = [
                'id'           => '',
                'student_name' => trim($row[0]),
                'mobile'       => trim($row[1]),
                'purpose'      => trim($row[2]),
                'status'       => strtoupper(trim($row[4] ?? 'EXPIRED')),
                'created_at'   => date('Y-m-d H:i:s', $ts),
            ];
        }
        fclose($fh);
        usort($rows, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
    }
}

// ── Helper: escape cell value for XML ────────────────────────────────────
function xmlEsc(string $v): string {
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// ── Build shared-strings table (for text cells in xlsx) ──────────────────
$strings  = [];  // value => index
$strOrder = [];  // index => value

function addStr(string $v): int {
    global $strings, $strOrder;
    if (!isset($strings[$v])) {
        $strings[$v]  = count($strOrder);
        $strOrder[]   = $v;
    }
    return $strings[$v];
}

// ── Prepare row data ──────────────────────────────────────────────────────
// Each cell: ['t'=>'s'|'n'|'d', 'v'=> value]
// 's' = shared string index, 'n' = number, 'd' = date string (stored as string for simplicity)

$headerTitles = [
    'Sr. No.', 'Student Name', 'Mobile Number', 'Purpose', 'Date & Time', 'Status'
];
foreach ($headerTitles as $h) addStr($h);

$dataRows = [];
foreach ($rows as $i => $row) {
    $purposeClean = $row['purpose'];
    $purposeClean = preg_replace('/\s*\|\s*Date:\s*\S+/', '', $purposeClean);
    $purposeClean = preg_replace('/^Purpose:\s*/i', '', trim($purposeClean));

    $dateStr = date('d M Y, h:i A', strtotime($row['created_at']));
    $status  = strtoupper($row['status'] ?? 'EXPIRED');

    $dataRows[] = [
        ['t' => 'n', 'v' => $i + 1],
        ['t' => 's', 'v' => addStr($row['student_name'])],
        ['t' => 's', 'v' => addStr($row['mobile'])],
        ['t' => 's', 'v' => addStr($purposeClean)],
        ['t' => 's', 'v' => addStr($dateStr)],
        ['t' => 's', 'v' => addStr($status)],
    ];
}

// ── Column widths ──────────────────────────────────────────────────────────
$colWidths = [7, 36, 16, 34, 22, 12];

// ── Generate XML Parts ────────────────────────────────────────────────────

// [Content_Types].xml
$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml"  ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/sharedStrings.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  <Override PartName="/xl/styles.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';

// _rels/.rels
$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>
</Relationships>';

// xl/_rels/workbook.xml.rels
$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
    Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings"
    Target="sharedStrings.xml"/>
  <Relationship Id="rId3"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
    Target="styles.xml"/>
</Relationships>';

// xl/workbook.xml
$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="June 2026 Enquiries" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>';

// xl/styles.xml  — 3 styles: default(0), header(1), number(2)
$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="3">
    <font><sz val="10"/><name val="Calibri"/></font>
    <font><sz val="11"/><b/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
    <font><sz val="10"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFE2E8F0"/></left>
      <right style="thin"><color rgb="FFE2E8F0"/></right>
      <top style="thin"><color rgb="FFE2E8F0"/></top>
      <bottom style="thin"><color rgb="FFE2E8F0"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1">
      <alignment horizontal="center"/>
    </xf>
  </cellXfs>
</styleSheet>';

// xl/sharedStrings.xml
$ssXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
$ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strOrder) . '" uniqueCount="' . count($strOrder) . '">';
foreach ($strOrder as $sv) {
    $ssXml .= '<si><t xml:space="preserve">' . xmlEsc($sv) . '</t></si>';
}
$ssXml .= '</sst>';

// xl/worksheets/sheet1.xml
$colLetters = ['A','B','C','D','E','F'];

// Column defs
$colDefsXml = '<cols>';
foreach ($colWidths as $ci => $w) {
    $cn = $ci + 1;
    $colDefsXml .= '<col min="' . $cn . '" max="' . $cn . '" width="' . $w . '" customWidth="1"/>';
}
$colDefsXml .= '</cols>';

$totalRows = count($dataRows) + 1; // +1 header
$sheetXml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
$sheetXml .= '<sheetView workbookViewId="0"><selection activeCell="A1"/></sheetView>';
$sheetXml .= '<sheetFormatPr defaultRowHeight="16" customHeight="1"/>';
$sheetXml .= $colDefsXml;
$sheetXml .= '<sheetData>';

// Header row (style=1)
$sheetXml .= '<row r="1" ht="22" customHeight="1">';
foreach ($headerTitles as $ci => $ht) {
    $colL = $colLetters[$ci];
    $si   = $strings[$ht];
    $sheetXml .= '<c r="' . $colL . '1" t="s" s="1"><v>' . $si . '</v></c>';
}
$sheetXml .= '</row>';

// Data rows
foreach ($dataRows as $ri => $cells) {
    $rowNum = $ri + 2;
    $sheetXml .= '<row r="' . $rowNum . '">';
    foreach ($cells as $ci => $cell) {
        $colL = $colLetters[$ci];
        $ref  = $colL . $rowNum;
        if ($cell['t'] === 'n') {
            $sAttr = ($ci === 0) ? ' s="2"' : '';
            $sheetXml .= '<c r="' . $ref . '"' . $sAttr . '><v>' . (int)$cell['v'] . '</v></c>';
        } else {
            $sheetXml .= '<c r="' . $ref . '" t="s" s="0"><v>' . (int)$cell['v'] . '</v></c>';
        }
    }
    $sheetXml .= '</row>';
}

$sheetXml .= '</sheetData>';
$sheetXml .= '<autoFilter ref="A1:F' . $totalRows . '"/>';
$sheetXml .= '</worksheet>';

// ── Assemble the zip in memory ────────────────────────────────────────────
$tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die('Cannot create xlsx file.');
}

$zip->addFromString('[Content_Types].xml',          $contentTypes);
$zip->addFromString('_rels/.rels',                  $rootRels);
$zip->addFromString('xl/workbook.xml',              $workbook);
$zip->addFromString('xl/_rels/workbook.xml.rels',   $wbRels);
$zip->addFromString('xl/styles.xml',                $styles);
$zip->addFromString('xl/sharedStrings.xml',         $ssXml);
$zip->addFromString('xl/worksheets/sheet1.xml',     $sheetXml);
$zip->close();

// ── Stream to browser ─────────────────────────────────────────────────────
$filename = 'June_2026_Admission_Enquiries_' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: max-age=0');

readfile($tmpFile);
unlink($tmpFile);
exit;
