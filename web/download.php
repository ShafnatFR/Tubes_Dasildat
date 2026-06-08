<?php
/**
 * download.php — Modul unduh multi-format (CSV, XLSX) untuk hasil prediksi batch & session.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowedFiles = [
    'hasil_semua_model.csv',
    'hasil_svm.csv',
    'hasil_knn.csv',
    'hasil_dt.csv',
    'hasil_nn.csv',
];

$format = strtolower($_GET['format'] ?? 'csv');
$source = $_GET['source'] ?? '';
$file   = isset($_GET['file']) ? basename($_GET['file']) : '';

if (!in_array($format, ['csv', 'xlsx'], true)) {
    http_response_code(400);
    exit('Format tidak valid.');
}

function loadTabularData(): ?array
{
    global $source, $file, $allowedFiles;

    if ($source === 'riwayat' && !empty($_POST['data'])) {
        $payload = json_decode($_POST['data'], true);
        if ($payload && !empty($payload['headers']) && isset($payload['rows'])) {
            return [
                'headers' => $payload['headers'],
                'rows' => $payload['rows'],
                'filename' => $payload['filename'] ?? 'riwayat_prediksi',
            ];
        }
    }

    if ($source === 'session' && !empty($_SESSION['download_data'])) {
        $data = $_SESSION['download_data'];
        if (!empty($data['headers']) && !empty($data['rows'])) {
            return ['headers' => $data['headers'], 'rows' => $data['rows'], 'filename' => $data['filename'] ?? 'hasil_prediksi'];
        }
        if (!empty($data['file']) && in_array($data['file'], $allowedFiles, true)) {
            $file = $data['file'];
        }
    }

    if ($file && in_array($file, $allowedFiles, true)) {
        $filePath = realpath(__DIR__ . '/../dataset/' . $file);
        if (!$filePath || !file_exists($filePath)) {
            return null;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return null;
        }
        $headers = str_getcsv($lines[0]);
        $rows = [];
        for ($i = 1; $i < count($lines); $i++) {
            $rows[] = str_getcsv($lines[$i]);
        }
        return ['headers' => $headers, 'rows' => $rows, 'filename' => pathinfo($file, PATHINFO_FILENAME)];
    }

    return null;
}

function xmlEscape(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function generateXlsx(array $headers, array $rows, string $outPath): bool
{
    if (!class_exists('ZipArchive')) {
        return false;
    }

    $sheetRows = '';
    $r = 1;
    $colLetters = function ($n) {
        $s = '';
        while ($n >= 0) {
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26) - 1;
        }
        return $s;
    };

    $writeRow = function (array $cells) use (&$sheetRows, &$r, $colLetters) {
        $sheetRows .= '<row r="' . $r . '">';
        foreach ($cells as $ci => $val) {
            $ref = $colLetters($ci) . $r;
            $sheetRows .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . xmlEscape((string) $val) . '</t></is></c>';
        }
        $sheetRows .= '</row>';
        $r++;
    };

    $writeRow($headers);
    foreach ($rows as $row) {
        $writeRow($row);
    }

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Hasil" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';

    $zip = new ZipArchive();
    if ($zip->open($outPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();
    return true;
}

$data = loadTabularData();
if (!$data) {
    http_response_code(404);
    exit('Data unduhan tidak tersedia. Jalankan prediksi terlebih dahulu.');
}

$baseName = $data['filename'];
$headers  = $data['headers'];
$rows     = $data['rows'];

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $baseName . '.csv"');
    header('Cache-Control: no-cache');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dl_' . uniqid() . '.xlsx';
if (!generateXlsx($headers, $rows, $tmpFile)) {
    http_response_code(500);
    exit('Gagal membuat file XLSX.');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $baseName . '.xlsx"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-cache');
readfile($tmpFile);
@unlink($tmpFile);
exit;
