<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config_python.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/db_helpers.php';

$batchScript = realpath(__DIR__ . '/../scripts/predict_batch.py');
$uploadDir   = realpath(__DIR__ . '/../dataset') . DIRECTORY_SEPARATOR;
$uploadFile  = $uploadDir . 'upload.csv';
$activePage  = 'batch';

$modelOptions = [
    'svm' => 'SVM (Support Vector Machine)',
    'knn' => 'K-NN (K-Nearest Neighbor)',
    'dt'  => 'Decision Tree',
    'nn'  => 'Neural Network',
    'all' => '⭐ Semua Model (Bandingkan)',
];

$requiredCols = ['Pregnancies','Glucose','BloodPressure','SkinThickness','Insulin','BMI','DiabetesPedigreeFunction','Age'];
$modelColMap = [
    'Prediksi_SVM' => 'SVM',
    'Prediksi_K_NN' => 'K-NN',
    'Prediksi_Decision_Tree' => 'Decision Tree',
    'Prediksi_Neural_Network' => 'Neural Network',
    'Prediksi' => null,
];

$error = null;
$saveWarning = null;
$showUpload = true;
$showResults = false;
$summaryLines = [];
$hasilFile = null;
$hasilHeaders = [];
$hasilRows = [];
$uploadTimeS = 0;
$execTimeS = 0;
$origFileName = '';
$modelKeyUsed = '';
$totalRows = 0;
$isAllMode = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_batch'])) {
    $modelKeyUsed = $_POST['model'] ?? 'svm';
    if (!array_key_exists($modelKeyUsed, $modelOptions)) {
        $modelKeyUsed = 'svm';
    }
    $isAllMode = ($modelKeyUsed === 'all');

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload file gagal. Pastikan file CSV dipilih.';
    } else {
        $origFileName = basename($_FILES['csv_file']['name']);
        $ext = strtolower(pathinfo($origFileName, PATHINFO_EXTENSION));
        $fileSize = $_FILES['csv_file']['size'];

        if ($ext !== 'csv') {
            $error = 'Format file harus CSV (.csv)';
        } elseif ($fileSize > 5 * 1024 * 1024) {
            $error = 'Ukuran file maksimal 5 MB';
        } else {
            $uploadStart = microtime(true);
            if (move_uploaded_file($_FILES['csv_file']['tmp_name'], $uploadFile)) {
                $uploadTimeS = round(microtime(true) - $uploadStart, 2);

                $headerLine = fgets(fopen($uploadFile, 'r'));
                $headers = str_getcsv($headerLine ?: '');
                $missing = array_diff($requiredCols, $headers);
                if (!empty($missing)) {
                    $error = 'Kolom tidak ditemukan: ' . implode(', ', $missing);
                    @unlink($uploadFile);
                } else {
                    $hasilName = ($modelKeyUsed === 'all') ? 'hasil_semua_model.csv' : "hasil_{$modelKeyUsed}.csv";
                    $hasilPath = $uploadDir . $hasilName;

                    $execStart = microtime(true);
                    $command = $pythonExe . ' ' . escapeshellarg($batchScript)
                             . ' ' . escapeshellarg($uploadFile)
                             . ' ' . escapeshellarg($modelKeyUsed)
                             . ' ' . escapeshellarg($hasilPath)
                             . ' 2>&1';
                    $rawOutput = trim(shell_exec($command));
                    $execTimeS = round(microtime(true) - $execStart, 2);

                    if (strpos($rawOutput, 'OK|') === 0) {
                        $parts = explode('|', $rawOutput);
                        array_shift($parts);
                        $summaryLines = $parts;
                        $hasilFile = $hasilName;
                        $showResults = true;
                        $showUpload = false;

                        if (file_exists($hasilPath)) {
                            $lines = file($hasilPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            if (count($lines) > 0) {
                                $hasilHeaders = str_getcsv($lines[0]);
                                $totalRows = count($lines) - 1;
                                $previewCount = min(20, $totalRows);
                                for ($i = 1; $i <= $previewCount; $i++) {
                                    $hasilRows[] = str_getcsv($lines[$i]);
                                }
                            }
                        }

                        $batchId = saveBatchLog($conn, [
                            'nama_file' => $origFileName,
                            'jumlah_baris' => $totalRows,
                            'model_key' => $modelKeyUsed,
                            'execution_time_s' => $execTimeS,
                            'upload_time_s' => $uploadTimeS,
                        ]);

                        if ($batchId === false) {
                            $saveWarning = 'Metadata batch tidak dapat disimpan ke riwayat.';
                        } elseif (file_exists($hasilPath)) {
                            $imported = importBatchPrediksiFromCsv(
                                $conn,
                                $batchId,
                                $hasilPath,
                                $modelKeyUsed,
                                $origFileName,
                                $execTimeS
                            );
                            if ($imported === 0) {
                                $saveWarning = 'Metadata batch tersimpan, tetapi detail hasil per baris gagal diimpor.';
                            }
                        }
                    } else {
                        $errMsg = str_replace('ERROR|', '', $rawOutput);
                        $error = 'Prediksi gagal: ' . htmlspecialchars($errMsg);
                    }
                }
            } else {
                $error = 'Gagal menyimpan file upload.';
            }
        }
    }
}

function headerColClass($header, $isAllMode) {
    $map = [
        'Prediksi_SVM' => 'col-svm',
        'Prediksi_K_NN' => 'col-knn',
        'Prediksi_Decision_Tree' => 'col-dt',
        'Prediksi_Neural_Network' => 'col-nn',
    ];
    if (isset($map[$header])) {
        return $map[$header];
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prediksi Batch - Diabetes</title>
    <?php include __DIR__ . '/includes/assets.php'; ?>
    <style>
        .upload-zone {
            border: 2px dashed #3498db; border-radius: 10px; padding: 30px; text-align: center;
            background: #f8fbff; margin-bottom: 20px; cursor: pointer;
        }
        .upload-zone:hover { background: #eaf4ff; }
        .upload-zone input[type="file"] { display: none; }
        .model-selector { margin-bottom: 16px; }
        .model-selector > label { display: block; font-weight: bold; font-size: 13px; margin-bottom: 8px; color: #333; }
        .model-select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        button[type="submit"] {
            width: 100%; padding: 11px; background: #3498db; color: white; border: none;
            border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: bold;
        }
        .summary-box {
            margin: 16px 0; padding: 14px 18px; background: #e8f5e9;
            border: 1px solid #4caf50; border-radius: 8px; font-size: 13px; color: #2e7d32;
        }
        .timing-row { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 8px; font-size: 13px; }
        .timing-item { background: #f8f9fa; padding: 8px 12px; border-radius: 6px; }
        .btn-batch-ulang { margin-top: 12px; }
        .template-box {
            margin-bottom: 20px; padding: 12px 16px; background: #fffde7;
            border: 1px solid #f9a825; border-radius: 8px; font-size: 12px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📂 Prediksi Batch Diabetes</h1>
    <div class="subtitle">Upload file CSV berisi data pasien — prediksi semua sekaligus</div>

    <div id="sectionUpload" class="<?php echo $showResults ? 'section-hidden' : ''; ?>">
        <?php
        $templateCsv = "Pregnancies,Glucose,BloodPressure,SkinThickness,Insulin,BMI,DiabetesPedigreeFunction,Age\n6,148,72,35,0,33.6,0.627,50\n";
        $templateB64 = base64_encode($templateCsv);
        ?>
        <div class="template-box">
            <strong>📋 Format CSV:</strong> Kolom wajib:
            <code><?php echo implode(', ', $requiredCols); ?></code>
            <br><a href="data:text/csv;base64,<?php echo $templateB64; ?>" download="template_diabetes.csv">⬇ Download Template</a>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="upload-zone" onclick="document.getElementById('csv_file').click()">
                <div style="font-size:36px;">📁</div>
                <div>Klik untuk pilih file CSV</div>
                <div id="file-name" style="margin-top:8px;color:#3498db;font-weight:bold;">Belum ada file dipilih</div>
                <input type="file" id="csv_file" name="csv_file" accept=".csv" onchange="showFileName(this)">
            </div>

            <div class="model-selector">
                <label for="batch_model">Pilih Model Algoritma:</label>
                <select name="model" id="batch_model" class="model-select">
                    <?php foreach ($modelOptions as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $key === 'all' ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" name="submit_batch">🚀 Jalankan Prediksi</button>
        </form>

        <?php if ($error): ?>
            <div class="error-box">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
    </div>

    <div id="sectionResult" class="<?php echo $showResults ? '' : 'section-hidden'; ?>">
        <?php if ($saveWarning): ?>
            <div class="warn-box">⚠️ <?php echo htmlspecialchars($saveWarning); ?></div>
        <?php endif; ?>

        <div class="summary-box">
            <strong>✅ Prediksi berhasil!</strong>
            <ul>
                <?php foreach ($summaryLines as $line): ?>
                    <li><?php echo htmlspecialchars($line); ?></li>
                <?php endforeach; ?>
            </ul>
            <div class="timing-row">
                <div class="timing-item">
                    Waktu Upload: <strong><?php echo number_format($uploadTimeS, 2); ?> detik</strong>
                </div>
                <div class="timing-item" data-exec-ms="<?php echo $execTimeS * 1000; ?>">
                    Waktu Eksekusi: <strong><?php echo number_format($execTimeS, 2); ?> detik</strong>
                </div>
            </div>
        </div>

        <?php if ($isAllMode) { include __DIR__ . '/includes/filter_model.php'; } ?>

        <?php if (!empty($hasilHeaders) && !empty($hasilRows)): ?>
        <div class="table-wrap">
            <table class="result-table" id="resultTable">
                <thead>
                    <tr>
                        <?php foreach ($hasilHeaders as $h): ?>
                            <th class="<?php echo headerColClass($h, $isAllMode); ?>"><?php echo htmlspecialchars($h); ?></th>
                        <?php endforeach; ?>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hasilRows as $row): ?>
                    <tr>
                        <?php
                        $featureVals = [];
                        $preds = [];
                        foreach ($hasilHeaders as $idx => $h) {
                            $cell = $row[$idx] ?? '';
                            if (in_array($h, $requiredCols, true)) {
                                $featureVals[] = $cell;
                            }
                            if (isset($modelColMap[$h]) && $modelColMap[$h]) {
                                $preds[$modelColMap[$h]] = $cell;
                            } elseif ($h === 'Prediksi' && !$isAllMode) {
                                $singleLabel = $modelOptions[$modelKeyUsed] ?? 'SVM';
                                $predKey = strpos($singleLabel, 'SVM') !== false ? 'SVM' :
                                    (strpos($singleLabel, 'K-NN') !== false ? 'K-NN' :
                                    (strpos($singleLabel, 'Decision') !== false ? 'Decision Tree' : 'Neural Network'));
                                $preds[$predKey] = $cell;
                            }
                            $isPred = (strpos($h, 'Prediksi') !== false);
                            $colCls = headerColClass($h, $isAllMode);
                        ?>
                        <td class="<?php echo $colCls; ?>">
                            <?php if ($isPred): ?>
                                <?php $cls = ($cell === 'Diabetes') ? 'badge-diabetes' : 'badge-normal'; ?>
                                <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($cell); ?></span>
                            <?php else: ?>
                                <?php echo htmlspecialchars($cell); ?>
                            <?php endif; ?>
                        </td>
                        <?php } ?>
                        <?php
                            while (count($featureVals) < 8) { $featureVals[] = null; }
                            $detailData = json_encode(['features' => $featureVals, 'predictions' => $preds]);
                        ?>
                        <td>
                            <button type="button" class="btn-detail" data-detail='<?php echo htmlspecialchars($detailData, ENT_QUOTES); ?>'>Detail</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalRows > 20): ?>
            <p style="font-size:11px;color:#888;text-align:right;">... dan <?php echo $totalRows - 20; ?> baris lainnya.</p>
        <?php endif; ?>
        <?php endif; ?>

        <button type="button" class="btn-secondary btn-batch-ulang" onclick="location.href='batch_upload.php'">↩ Upload Baru</button>
    </div>

    <div class="info-box">Mode "Semua Model" menghasilkan 4 kolom prediksi untuk perbandingan langsung.</div>
    <?php include __DIR__ . '/includes/nav.php'; ?>
</div>
<?php include __DIR__ . '/includes/modal_detail.php'; ?>
<script>
function showFileName(input) {
    const label = document.getElementById('file-name');
    label.textContent = (input.files && input.files.length) ? '📄 ' + input.files[0].name : 'Belum ada file dipilih';
}
</script>
</body>
</html>
