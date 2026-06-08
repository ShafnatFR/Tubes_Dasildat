<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config_python.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/db_helpers.php';

$predictScript = realpath(__DIR__ . '/../scripts/predict.py');
$activePage = 'test';

$testCases = [
    ['name' => 'Pasien Risiko Tinggi Diabetes',  'features' => [6, 148, 72, 35,  0,  33.6, 0.627, 50]],
    ['name' => 'Pasien Sehat (Normal)',           'features' => [1,  85, 66, 29,  0,  26.6, 0.351, 31]],
    ['name' => 'Pasien Risiko Tinggi (Kasus 2)', 'features' => [8, 183, 64,  0,  0,  23.3, 0.672, 32]],
    ['name' => 'Pasien Muda Sehat',              'features' => [0,  89, 66, 23, 94,  28.1, 0.167, 21]],
    ['name' => 'Pasien dengan BMI Tinggi',       'features' => [3, 120, 80, 30, 50,  35.0, 0.500, 45]],
    ['name' => 'Pasien Usia Lanjut',             'features' => [5, 130, 75, 28,  0,  32.0, 0.800, 65]],
];

$modelLabels = ['SVM', 'K-NN', 'Decision Tree', 'Neural Network'];
$saveWarning = null;

// Kirim loading HTML ke browser sebelum proses Python
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Cases - Prediksi Diabetes</title>
    <link rel="stylesheet" href="includes/common.css">
    <style>
        .features-col { font-size: 11px; color: #888; }
        .no-col { width: 40px; text-align: center; }
    </style>
</head>
<body>
<div id="pageLoader" class="loading-overlay">
    <div class="loading-spinner"></div>
    <p>Menjalankan prediksi Python untuk <?php echo count($testCases); ?> skenario...</p>
</div>
<?php
if (ob_get_level()) { ob_end_flush(); }
flush();

$results = [];
$exportRows = [];
$exportHeaders = array_merge(['Skenario'], $modelLabels, ['Waktu (ms)']);

foreach ($testCases as $case) {
    $t0 = microtime(true);
    $inputJson = escapeshellarg(json_encode($case['features']));
    $scriptArg = escapeshellarg($predictScript);
    $command   = "$pythonExe $scriptArg $inputJson " . escapeshellarg('all') . " 2>&1";
    $output    = trim(shell_exec($command));
    $decoded   = json_decode($output, true);
    $elapsedMs = round((microtime(true) - $t0) * 1000, 2);

    if ($decoded && isset($decoded['execution_time_ms'])) {
        $elapsedMs = (float) $decoded['execution_time_ms'];
    }

    $row = [
        'name' => $case['name'],
        'features' => $case['features'],
        'preds' => $decoded['results'] ?? [],
        'time_ms' => $elapsedMs,
        'timings' => $decoded['timings'] ?? [],
    ];
    $results[] = $row;

    $exportRows[] = array_merge(
        [$case['name']],
        [
            $row['preds']['SVM'] ?? '',
            $row['preds']['K-NN'] ?? '',
            $row['preds']['Decision Tree'] ?? '',
            $row['preds']['Neural Network'] ?? '',
        ],
        [$elapsedMs]
    );

    if ($decoded && isset($decoded['results'])) {
        $timings = $decoded['timings'] ?? [];
        foreach ($decoded['results'] as $modelName => $predLabel) {
            savePrediksiLog($conn, [
                'pasien' => $case['name'],
                'model_key' => modelKeyFromLabel($modelName),
                'pregnancies' => $case['features'][0],
                'glucose' => $case['features'][1],
                'blood_pressure' => $case['features'][2],
                'skin_thickness' => $case['features'][3],
                'insulin' => $case['features'][4],
                'bmi' => $case['features'][5],
                'diabetes_pedigree' => $case['features'][6],
                'age' => $case['features'][7],
                'hasil_prediksi' => predLabelToInt($predLabel),
                'execution_time_ms' => $timings[$modelName] ?? ($elapsedMs / 4),
            ]);
        }
    }
}

$_SESSION['download_data'] = [
    'headers' => $exportHeaders,
    'rows' => $exportRows,
    'filename' => 'hasil_test_cases',
];
?>
<div class="container">
    <h1>🧪 Test Cases — Prediksi Diabetes</h1>
    <div class="subtitle">Perbandingan hasil prediksi 4 model untuk skenario yang sama</div>

    <?php include __DIR__ . '/includes/filter_model.php'; ?>

    <div class="table-wrap">
        <table class="result-table" id="resultTable">
            <thead>
                <tr>
                    <th class="no-col">No</th>
                    <th>Skenario</th>
                    <th>Data (8 Fitur)</th>
                    <?php foreach ($modelLabels as $ml): ?>
                        <th class="col-<?php echo $ml === 'SVM' ? 'svm' : ($ml === 'K-NN' ? 'knn' : ($ml === 'Decision Tree' ? 'dt' : 'nn')); ?>"><?php echo $ml; ?></th>
                    <?php endforeach; ?>
                    <th>Waktu (ms)</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $i => $r):
                    $colMap = ['SVM'=>'col-svm','K-NN'=>'col-knn','Decision Tree'=>'col-dt','Neural Network'=>'col-nn'];
                    $detailData = json_encode(['features' => $r['features'], 'predictions' => $r['preds']]);
                ?>
                <tr>
                    <td class="no-col"><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars($r['name']); ?></td>
                    <td class="features-col"><?php echo implode(', ', $r['features']); ?></td>
                    <?php foreach ($modelLabels as $ml):
                        $pred = $r['preds'][$ml] ?? 'Error';
                        $cls = ($pred === 'Diabetes') ? 'badge-diabetes' : (($pred === 'Tidak Diabetes') ? 'badge-normal' : 'badge-error');
                    ?>
                    <td class="<?php echo $colMap[$ml]; ?>">
                        <span class="badge <?php echo $cls; ?>"><?php echo htmlspecialchars($pred); ?></span>
                    </td>
                    <?php endforeach; ?>
                    <td data-exec-ms="<?php echo $r['time_ms']; ?>"><?php echo number_format($r['time_ms'], 2); ?></td>
                    <td>
                        <button type="button" class="btn-detail" data-detail='<?php echo htmlspecialchars($detailData, ENT_QUOTES); ?>'>Detail</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php
    $tableId = 'resultTable';
    $dlType = 'session';
    $exportJson = json_encode(['headers' => $exportHeaders, 'rows' => $exportRows]);
    include __DIR__ . '/includes/download_dropdown.php';
    ?>

    <div class="info-box">
        Total test cases: <?php echo count($results); ?> skenario | Dataset: Pima Indians Diabetes
    </div>

    <?php include __DIR__ . '/includes/nav.php'; ?>
</div>
<?php include __DIR__ . '/includes/modal_detail.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="includes/common.js" defer></script>
</body>
</html>
