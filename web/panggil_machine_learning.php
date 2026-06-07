<?php
// ── Konfigurasi path ──────────────────────────────────────────────────────────
require_once __DIR__ . '/python_config.php';
// $pythonExe sudah tersedia dari python_config.php

$predictScript = realpath(__DIR__ . '/../scripts/predict.py');

// ── Daftar model ─────────────────────────────────────────────────────────────
$modelOptions = [
    'svm' => 'SVM (Support Vector Machine)',
    'knn' => 'K-NN (K-Nearest Neighbor)',
    'dt'  => 'Decision Tree',
    'nn'  => 'Neural Network',
    'all' => '⭐ Semua Model (Bandingkan)',
];

// ── Prediksi ──────────────────────────────────────────────────────────────────
$result    = null;
$error     = null;
$modelUsed = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modelKey = isset($_POST['model']) ? $_POST['model'] : 'svm';
    if (!array_key_exists($modelKey, $modelOptions)) {
        $modelKey = 'svm';
    }
    $modelUsed = $modelKey;

    // Validasi dan sanitasi input
    $rawFields = [
        'pregnancies'       => ['min' => 0,   'max' => 20,   'label' => 'Kehamilan'],
        'glucose'           => ['min' => 0,   'max' => 300,  'label' => 'Glukosa'],
        'blood_pressure'    => ['min' => 0,   'max' => 200,  'label' => 'Tekanan Darah'],
        'skin_thickness'    => ['min' => 0,   'max' => 100,  'label' => 'Ketebalan Kulit'],
        'insulin'           => ['min' => 0,   'max' => 900,  'label' => 'Insulin'],
        'bmi'               => ['min' => 0,   'max' => 80,   'label' => 'BMI'],
        'diabetes_pedigree' => ['min' => 0,   'max' => 3,    'label' => 'Diabetes Pedigree'],
        'age'               => ['min' => 1,   'max' => 120,  'label' => 'Usia'],
    ];

    $features    = [];
    $validErrors = [];

    foreach ($rawFields as $field => $rule) {
        $val = (float) str_replace(',', '.', $_POST[$field] ?? 0);
        if ($val < $rule['min'] || $val > $rule['max']) {
            $validErrors[] = "{$rule['label']} harus antara {$rule['min']}–{$rule['max']}";
        }
        $features[] = $val;
    }

    if (!empty($validErrors)) {
        $error = 'Input tidak valid: ' . implode(', ', $validErrors) . '.';
    } else {
        $inputJson = escapeshellarg(json_encode($features));
        $modelArg  = escapeshellarg($modelKey);
        $scriptArg = escapeshellarg($predictScript);
        $command   = "$pythonExe $scriptArg $inputJson $modelArg 2>&1";
        $output    = trim(shell_exec($command));
        $result    = json_decode($output, true);

        if (!$result || isset($result['error'])) {
            $error  = $result['error'] ?? 'Prediksi gagal. Output: ' . htmlspecialchars($output);
            $result = null;
        }
    }
}
// Nilai form sebelumnya
function postVal($key, $default) {
    return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prediksi Diabetes</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 20px; margin: 0; }

        .container {
            max-width: 580px;
            margin: 0 auto;
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }

        h1 { text-align: center; color: #2c3e50; margin-bottom: 4px; font-size: 22px; }
        .subtitle { text-align: center; color: #7f8c8d; margin-bottom: 24px; font-size: 13px; }

        /* Model selector tabs */
        .model-selector { margin-bottom: 20px; }
        .model-selector label { display: block; font-weight: bold; color: #333; margin-bottom: 8px; font-size: 13px; }
        .model-tabs { display: flex; flex-wrap: wrap; gap: 6px; }
        .model-tab input[type="radio"] { display: none; }
        .model-tab label {
            display: inline-block;
            padding: 6px 13px;
            border: 2px solid #ddd;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12px;
            color: #555;
            transition: all 0.2s;
            background: #fafafa;
        }
        .model-tab input[type="radio"]:checked + label {
            border-color: #3498db;
            background: #3498db;
            color: white;
            font-weight: bold;
        }
        .model-tab.all input[type="radio"]:checked + label {
            border-color: #e67e22;
            background: #e67e22;
        }

        /* Form */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 12px; font-weight: bold; color: #444; margin-bottom: 4px; }
        .form-group label .range { font-weight: normal; color: #aaa; font-size: 11px; }
        .form-group input {
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus { outline: none; border-color: #3498db; }

        button[type="submit"] {
            width: 100%;
            padding: 11px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            margin-top: 16px;
            transition: background 0.2s;
        }
        button[type="submit"]:hover { background: #2980b9; }

        /* Error */
        .error-box {
            margin-top: 16px;
            padding: 12px;
            background: #fee;
            border: 1px solid #e74c3c;
            border-radius: 8px;
            color: #c0392b;
            font-size: 13px;
        }

        /* Result — single model */
        .result-single {
            margin-top: 16px;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }
        .result-single.diabetes  { background: #fee; border: 1px solid #e74c3c; }
        .result-single.normal    { background: #e8f5e9; border: 1px solid #4caf50; }
        .result-single .model-badge {
            font-size: 11px; color: #888; margin-bottom: 6px;
        }
        .result-single .label {
            font-size: 20px; font-weight: bold;
        }
        .result-single.diabetes .label { color: #e74c3c; }
        .result-single.normal   .label { color: #4caf50; }

        /* Result — all models */
        .result-all { margin-top: 16px; }
        .result-all h3 { font-size: 14px; color: #444; margin-bottom: 10px; text-align: center; }
        .result-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .result-table th {
            background: #3498db; color: white;
            padding: 8px 12px; text-align: left;
        }
        .result-table td { padding: 8px 12px; border-bottom: 1px solid #eee; }
        .result-table tr:last-child td { border-bottom: none; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge.diabetes { background: #fee; color: #e74c3c; border: 1px solid #e74c3c; }
        .badge.normal   { background: #e8f5e9; color: #4caf50; border: 1px solid #4caf50; }

        /* Download button */
        .download-btn {
            display: block;
            margin-top: 10px;
            padding: 9px;
            background: #27ae60;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
        }
        .download-btn:hover { background: #219a52; }

        /* Info & Nav */
        .info-box {
            margin-top: 20px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 11px;
            color: #666;
            text-align: center;
        }
        .nav { display: flex; justify-content: center; gap: 12px; margin-top: 14px; flex-wrap: wrap; }
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
    <h1>🩺 Prediksi Diabetes</h1>
    <div class="subtitle">Masukkan data pasien untuk prediksi risiko diabetes</div>

    <form method="POST" id="mainForm">

        <!-- Pilih Model -->
        <div class="model-selector">
            <label>Pilih Model Algoritma:</label>
            <div class="model-tabs">
                <?php foreach ($modelOptions as $key => $label): ?>
                    <div class="model-tab <?php echo $key === 'all' ? 'all' : ''; ?>">
                        <input
                            type="radio"
                            name="model"
                            id="model_<?php echo $key; ?>"
                            value="<?php echo $key; ?>"
                            <?php echo ($modelUsed === $key || ($modelUsed === '' && $key === 'svm')) ? 'checked' : ''; ?>
                        >
                        <label for="model_<?php echo $key; ?>"><?php echo $label; ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Input Fitur -->
        <div class="form-grid">
            <div class="form-group">
                <label>Kehamilan (Pregnancies) <span class="range">0–20</span></label>
                <input type="number" step="1" min="0" max="20" name="pregnancies" value="<?php echo postVal('pregnancies','6'); ?>" required>
            </div>
            <div class="form-group">
                <label>Kadar Glukosa <span class="range">0–300</span></label>
                <input type="number" step="any" min="0" max="300" name="glucose" value="<?php echo postVal('glucose','148'); ?>" required>
            </div>
            <div class="form-group">
                <label>Tekanan Darah <span class="range">0–200</span></label>
                <input type="number" step="any" min="0" max="200" name="blood_pressure" value="<?php echo postVal('blood_pressure','72'); ?>" required>
            </div>
            <div class="form-group">
                <label>Ketebalan Kulit <span class="range">0–100</span></label>
                <input type="number" step="any" min="0" max="100" name="skin_thickness" value="<?php echo postVal('skin_thickness','35'); ?>" required>
            </div>
            <div class="form-group">
                <label>Insulin <span class="range">0–900</span></label>
                <input type="number" step="any" min="0" max="900" name="insulin" value="<?php echo postVal('insulin','0'); ?>" required>
            </div>
            <div class="form-group">
                <label>BMI <span class="range">0–80</span></label>
                <input type="number" step="any" min="0" max="80" name="bmi" value="<?php echo postVal('bmi','33.6'); ?>" required>
            </div>
            <div class="form-group">
                <label>Diabetes Pedigree <span class="range">0–3</span></label>
                <input type="number" step="any" min="0" max="3" name="diabetes_pedigree" value="<?php echo postVal('diabetes_pedigree','0.627'); ?>" required>
            </div>
            <div class="form-group">
                <label>Usia <span class="range">1–120</span></label>
                <input type="number" step="1" min="1" max="120" name="age" value="<?php echo postVal('age','50'); ?>" required>
            </div>
        </div>

        <div class="form-actions" style="display: flex; gap: 10px; margin-top: 16px;">
            <button type="submit" style="flex: 2;">🔍 Prediksi Sekarang</button>
            <button type="button" onclick="isiDataAcak()" style="flex: 1; background: #95a5a6; transition: background 0.2s;" onmouseover="this.style.background='#7f8c8d'" onmouseout="this.style.background='#95a5a6'">🎲 Isi Data Acak</button>
        </div>
    </form>

    <script>
    function isiDataAcak() {
        const dummies = [
            // Positif (Diabetes)
            {p:6, g:148, bp:72, s:35, i:0, bmi:33.6, dpf:0.627, a:50},
            {p:8, g:183, bp:64, s:0, i:0, bmi:23.3, dpf:0.672, a:32},
            // Negatif (Normal)
            {p:1, g:85, bp:66, s:29, i:0, bmi:26.6, dpf:0.351, a:31},
            {p:0, g:89, bp:66, s:23, i:94, bmi:28.1, dpf:0.167, a:21}
        ];
        const r = dummies[Math.floor(Math.random() * dummies.length)];
        document.querySelector('input[name="pregnancies"]').value = r.p;
        document.querySelector('input[name="glucose"]').value = r.g;
        document.querySelector('input[name="blood_pressure"]').value = r.bp;
        document.querySelector('input[name="skin_thickness"]').value = r.s;
        document.querySelector('input[name="insulin"]').value = r.i;
        document.querySelector('input[name="bmi"]').value = r.bmi;
        document.querySelector('input[name="diabetes_pedigree"]').value = r.dpf;
        document.querySelector('input[name="age"]').value = r.a;
    }
    </script>

    <!-- Error -->
    <?php if ($error): ?>
        <div class="error-box">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Hasil Single Model -->
    <?php if ($result && $result['mode'] === 'single'): ?>
        <?php 
            $cls = strtolower($result['class']) === 'diabetes' ? 'diabetes' : 'normal'; 
            
            // Siapkan CSV untuk download (Single Model)
            $csvLines  = ["Model,Hasil Prediksi"];
            $csvLines[] = "\"" . $result['model'] . "\",\"" . $result['class'] . "\"";
            $csvLines[] = "";
            $csvLines[] = "\"Data Input\"";
            $fieldNames = ['Pregnancies','Glucose','BloodPressure','SkinThickness','Insulin','BMI','DiabetesPedigreeFunction','Age'];
            $fieldVals  = [
                $_POST['pregnancies'], $_POST['glucose'], $_POST['blood_pressure'],
                $_POST['skin_thickness'], $_POST['insulin'], $_POST['bmi'],
                $_POST['diabetes_pedigree'], $_POST['age']
            ];
            $csvLines[] = implode(',', $fieldNames);
            $csvLines[] = implode(',', $fieldVals);
            $csvContent = implode("\n", $csvLines);
            $csvBase64  = base64_encode($csvContent);
        ?>
        <div class="result-single <?php echo $cls; ?>">
            <div class="model-badge">Model: <?php echo htmlspecialchars($result['model']); ?></div>
            <div class="label"><?php echo $result['class'] === 'Diabetes' ? '⚠️' : '✅'; ?> <?php echo htmlspecialchars($result['class']); ?></div>
        </div>
        
        <!-- Download CSV hasil single model -->
        <a class="download-btn"
           href="data:text/csv;base64,<?php echo $csvBase64; ?>"
           download="hasil_prediksi_<?php echo strtolower(str_replace(' ', '_', $result['model'])); ?>.csv">
            ⬇️ Download Hasil (CSV)
        </a>
    <?php endif; ?>

    <!-- Hasil Semua Model -->
    <?php if ($result && $result['mode'] === 'all'): ?>
        <?php
            // Siapkan CSV untuk download
            $csvLines  = ["Model,Hasil Prediksi"];
            foreach ($result['results'] as $modelName => $predLabel) {
                $csvLines[] = "\"$modelName\",\"$predLabel\"";
            }
            // Tambahkan input data ke CSV
            $csvLines[] = "";
            $csvLines[] = "\"Data Input\"";
            $fieldNames = ['Pregnancies','Glucose','BloodPressure','SkinThickness','Insulin','BMI','DiabetesPedigreeFunction','Age'];
            $fieldVals  = [
                $_POST['pregnancies'], $_POST['glucose'], $_POST['blood_pressure'],
                $_POST['skin_thickness'], $_POST['insulin'], $_POST['bmi'],
                $_POST['diabetes_pedigree'], $_POST['age']
            ];
            $csvLines[] = implode(',', $fieldNames);
            $csvLines[] = implode(',', $fieldVals);
            $csvContent = implode("\n", $csvLines);
            $csvBase64  = base64_encode($csvContent);
        ?>
        <div class="result-all">
            <h3>📊 Hasil Perbandingan Semua Model</h3>
            <table class="result-table">
                <thead>
                    <tr><th>Model</th><th>Hasil Prediksi</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($result['results'] as $modelName => $predLabel): ?>
                        <?php $cls = $predLabel === 'Diabetes' ? 'diabetes' : 'normal'; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($modelName); ?></td>
                            <td>
                                <span class="badge <?php echo $cls; ?>">
                                    <?php echo $predLabel === 'Diabetes' ? '⚠️' : '✅'; ?>
                                    <?php echo htmlspecialchars($predLabel); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Download CSV hasil semua model -->
            <a class="download-btn"
               href="data:text/csv;base64,<?php echo $csvBase64; ?>"
               download="hasil_prediksi_semua_model.csv">
                ⬇️ Download Hasil (CSV)
            </a>
        </div>
    <?php endif; ?>

    <div class="info-box">
        Dataset: Pima Indians Diabetes Database (768 sampel)<br>
        Tersedia 4 algoritma: SVM, K-NN, Decision Tree, Neural Network<br>
        Hasil ini bersifat prediksi — konsultasikan dengan dokter untuk diagnosis pasti.
    </div>

    <div class="nav">
        <a href="coba_machine_learning.php">🧪 Test Cases</a>
        <a href="prediksi_batch.php">📂 Prediksi Batch (Upload CSV)</a>
    </div>
</div>
</body>
</html>
