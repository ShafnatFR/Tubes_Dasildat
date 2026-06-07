<?php
// ── Konfigurasi ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/python_config.php';
// $pythonExe sudah tersedia dari python_config.php

$pythonScript  = realpath(__DIR__ . '/../scripts/predict.py');
$predictScript = $pythonScript;

$modelOptions = [
    'svm' => 'SVM',
    'knn' => 'K-NN',
    'dt'  => 'Decision Tree',
    'nn'  => 'Neural Network',
];

// ── Test cases ────────────────────────────────────────────────────────────────
$testCases = [
    ['name' => 'Pasien Risiko Tinggi Diabetes',  'features' => [6, 148, 72, 35,  0,  33.6, 0.627, 50]],
    ['name' => 'Pasien Sehat (Normal)',           'features' => [1,  85, 66, 29,  0,  26.6, 0.351, 31]],
    ['name' => 'Pasien Risiko Tinggi (Kasus 2)', 'features' => [8, 183, 64,  0,  0,  23.3, 0.672, 32]],
    ['name' => 'Pasien Muda Sehat',              'features' => [0,  89, 66, 23, 94,  28.1, 0.167, 21]],
    ['name' => 'Pasien dengan BMI Tinggi',       'features' => [3, 120, 80, 30, 50,  35.0, 0.500, 45]],
    ['name' => 'Pasien Usia Lanjut',             'features' => [5, 130, 75, 28,  0,  32.0, 0.800, 65]],
];

// ── Jalankan prediksi semua model untuk setiap test case ─────────────────────
// Gunakan mode "all" sekaligus agar lebih efisien (1 pemanggilan per baris)
$results = [];
foreach ($testCases as $case) {
    $inputJson = escapeshellarg(json_encode($case['features']));
    $scriptArg = escapeshellarg($predictScript);
    $command   = "$pythonExe $scriptArg $inputJson " . escapeshellarg('all') . " 2>&1";
    $output    = trim(shell_exec($command));
    $decoded   = json_decode($output, true);

    $row = ['name' => $case['name'], 'features' => $case['features'], 'preds' => []];
    if ($decoded && isset($decoded['results'])) {
        $row['preds'] = $decoded['results']; // ['SVM' => '...', 'K-NN' => '...', ...]
    } else {
        foreach ($modelOptions as $label) {
            $row['preds'][$label] = 'Error';
        }
    }
    $results[] = $row;
}

$modelLabels = ['SVM', 'K-NN', 'Decision Tree', 'Neural Network'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Cases - Prediksi Diabetes</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 20px; margin: 0; }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }

        h1 { text-align: center; color: #2c3e50; font-size: 22px; margin-bottom: 4px; }
        .subtitle { text-align: center; color: #7f8c8d; margin-bottom: 24px; font-size: 13px; }

        .table-wrap { overflow-x: auto; border-radius: 8px; border: 1px solid #e0e0e0; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 700px; }
        thead th {
            background: #3498db;
            color: white;
            padding: 10px 12px;
            text-align: left;
            white-space: nowrap;
        }
        tbody td { padding: 9px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f8f9fa; }
        .no-col { width: 40px; text-align: center; }
        .features-col { font-size: 11px; color: #888; }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }
        .badge-diabetes { background: #fee; color: #e74c3c; border: 1px solid #e74c3c; }
        .badge-normal   { background: #e8f5e9; color: #4caf50; border: 1px solid #4caf50; }
        .badge-error    { background: #f0f0f0; color: #999; border: 1px solid #ccc; }

        .btn-back {
            display: inline-block;
            margin-top: 20px;
            padding: 9px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
        }
        .btn-back:hover { background: #2980b9; }

        .info-box {
            margin-top: 20px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 12px;
            color: #666;
        }

        .nav { display: flex; gap: 12px; margin-top: 14px; flex-wrap: wrap; }
        .nav a {
            color: #3498db;
            font-size: 13px;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
        }
        .nav a:hover { background: #e3f2fd; }
    </style>
</head>
<body>
<div class="container">
    <h1>🧪 Test Cases — Prediksi Diabetes</h1>
    <div class="subtitle">Perbandingan hasil prediksi 4 model untuk skenario yang sama</div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th class="no-col">No</th>
                    <th>Skenario</th>
                    <th>Data (8 Fitur)</th>
                    <?php foreach ($modelLabels as $ml): ?>
                        <th><?php echo $ml; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $i => $r): ?>
                    <tr>
                        <td class="no-col"><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="features-col"><?php echo implode(', ', $r['features']); ?></td>
                        <?php foreach ($modelLabels as $ml): ?>
                            <?php
                                $pred = $r['preds'][$ml] ?? 'Error';
                                if ($pred === 'Diabetes') $cls = 'badge-diabetes';
                                elseif ($pred === 'Tidak Diabetes') $cls = 'badge-normal';
                                else $cls = 'badge-error';
                                $icon = $pred === 'Diabetes' ? '⚠️ ' : ($pred === 'Tidak Diabetes' ? '✅ ' : '');
                            ?>
                            <td><span class="badge <?php echo $cls; ?>"><?php echo $icon . htmlspecialchars($pred); ?></span></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="info-box">
        <strong>📊 Informasi Model:</strong><br>
        • Dataset: Pima Indians Diabetes Database (768 sampel)<br>
        • Fitur: 8 parameter medis (Pregnancies, Glucose, BloodPressure, SkinThickness, Insulin, BMI, DiabetesPedigreeFunction, Age)<br>
        • Algoritma: SVM, K-NN, Decision Tree, Neural Network<br>
        • Total test cases: <?php echo count($results); ?> skenario
    </div>

    <div class="nav">
        <a href="panggil_machine_learning.php">🩺 Form Prediksi</a>
        <a href="prediksi_batch.php">📂 Prediksi Batch</a>
    </div>
</div>
</body>
</html>
