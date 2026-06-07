<?php
// ── Konfigurasi ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/python_config.php';
// $pythonExe sudah tersedia dari python_config.php

$batchScript = realpath(__DIR__ . '/../scripts/predict_batch.py');
$uploadDir   = realpath(__DIR__ . '/../dataset') . DIRECTORY_SEPARATOR;
$uploadFile  = $uploadDir . 'upload.csv';
$hasilDir    = $uploadDir;

$modelOptions = [
    'svm' => 'SVM (Support Vector Machine)',
    'knn' => 'K-NN (K-Nearest Neighbor)',
    'dt'  => 'Decision Tree',
    'nn'  => 'Neural Network',
    'all' => '⭐ Semua Model (Bandingkan)',
];

// ── Proses Upload & Prediksi ──────────────────────────────────────────────────
$uploadStatus = null;
$predOutput   = null;
$hasilFile    = null;
$summaryLines = [];
$hasilRows    = [];
$hasilHeaders = [];
$error        = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_batch'])) {
    $modelKey = isset($_POST['model']) ? $_POST['model'] : 'svm';
    if (!array_key_exists($modelKey, $modelOptions)) $modelKey = 'svm';

    // Validasi file upload
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload file gagal. Pastikan file CSV dipilih.';
    } else {
        $tmpName  = $_FILES['csv_file']['tmp_name'];
        $origName = basename($_FILES['csv_file']['name']);
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if ($ext !== 'csv') {
            $error = 'Format file harus CSV (.csv).';
        } else {
            // Pindahkan file upload
            if (move_uploaded_file($tmpName, $uploadFile)) {
                // Tentukan nama file output
                $hasilName = ($modelKey === 'all') ? 'hasil_semua_model.csv' : "hasil_{$modelKey}.csv";
                $hasilPath = $hasilDir . $hasilName;

                // Jalankan Python batch prediction
                $command   = $pythonExe . ' ' . escapeshellarg($batchScript)
                           . ' ' . escapeshellarg($uploadFile)
                           . ' ' . escapeshellarg($modelKey)
                           . ' ' . escapeshellarg($hasilPath)
                           . ' 2>&1';
                $rawOutput = trim(shell_exec($command));

                if (strpos($rawOutput, 'OK|') === 0) {
                    // Parse summary
                    $parts = explode('|', $rawOutput);
                    array_shift($parts); // hapus "OK"
                    $summaryLines = $parts;
                    $hasilFile    = $hasilName;

                    // Baca hasil untuk preview tabel (max 20 baris)
                    if (file_exists($hasilPath)) {
                        $lines = file($hasilPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        if (count($lines) > 0) {
                            $hasilHeaders = str_getcsv($lines[0]);
                            $previewCount = min(20, count($lines) - 1);
                            for ($i = 1; $i <= $previewCount; $i++) {
                                $hasilRows[] = str_getcsv($lines[$i]);
                            }
                        }
                    }
                } else {
                    $errMsg = str_replace('ERROR|', '', $rawOutput);
                    $error  = 'Prediksi gagal: ' . htmlspecialchars($errMsg);
                }
            } else {
                $error = 'Gagal menyimpan file upload.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prediksi Batch - Diabetes</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 20px; margin: 0; }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.1);
        }

        h1 { text-align: center; color: #2c3e50; margin-bottom: 4px; font-size: 22px; }
        .subtitle { text-align: center; color: #7f8c8d; margin-bottom: 24px; font-size: 13px; }

        /* Upload zone */
        .upload-zone {
            border: 2px dashed #3498db;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #f8fbff;
            margin-bottom: 20px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .upload-zone:hover { background: #eaf4ff; }
        .upload-zone input[type="file"] { display: none; }
        .upload-zone .icon { font-size: 36px; margin-bottom: 8px; }
        .upload-zone .hint { font-size: 13px; color: #888; margin-top: 6px; }
        #file-name { font-size: 13px; color: #3498db; font-weight: bold; margin-top: 8px; }

        /* Model selector */
        .model-selector { margin-bottom: 20px; }
        .model-selector > label { display: block; font-weight: bold; color: #333; margin-bottom: 8px; font-size: 13px; }
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
            transition: background 0.2s;
        }
        button[type="submit"]:hover { background: #2980b9; }

        /* Error */
        .error-box {
            margin: 16px 0;
            padding: 12px;
            background: #fee;
            border: 1px solid #e74c3c;
            border-radius: 8px;
            color: #c0392b;
            font-size: 13px;
        }

        /* Summary */
        .summary-box {
            margin: 16px 0;
            padding: 14px 18px;
            background: #e8f5e9;
            border: 1px solid #4caf50;
            border-radius: 8px;
            font-size: 13px;
            color: #2e7d32;
        }
        .summary-box strong { display: block; margin-bottom: 4px; font-size: 14px; }
        .summary-box ul { margin: 6px 0 0 0; padding-left: 18px; }
        .summary-box li { margin-bottom: 2px; }

        /* Download */
        .download-btn {
            display: block;
            margin: 12px 0;
            padding: 10px;
            background: #27ae60;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
        }
        .download-btn:hover { background: #219a52; }

        /* Tabel preview */
        .preview-section h3 {
            font-size: 14px;
            color: #444;
            margin: 16px 0 8px;
        }
        .table-wrap { overflow-x: auto; border-radius: 8px; border: 1px solid #e0e0e0; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; min-width: 600px; }
        thead th {
            background: #3498db;
            color: white;
            padding: 8px 10px;
            text-align: left;
            white-space: nowrap;
        }
        tbody td { padding: 7px 10px; border-bottom: 1px solid #f0f0f0; white-space: nowrap; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #f8f9fa; }

        /* Badge hasil */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-diabetes { background: #fee; color: #e74c3c; border: 1px solid #e74c3c; }
        .badge-normal   { background: #e8f5e9; color: #4caf50; border: 1px solid #4caf50; }

        /* Template download */
        .template-box {
            margin-bottom: 20px;
            padding: 12px 16px;
            background: #fffde7;
            border: 1px solid #f9a825;
            border-radius: 8px;
            font-size: 12px;
            color: #555;
        }
        .template-box strong { color: #333; }
        .template-link {
            display: inline-block;
            margin-top: 8px;
            padding: 5px 12px;
            background: #f9a825;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        .template-link:hover { background: #e65100; }

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

        .more-note { font-size: 11px; color: #888; text-align: right; margin-top: 6px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📂 Prediksi Batch Diabetes</h1>
    <div class="subtitle">Upload file CSV berisi data pasien — prediksi semua sekaligus</div>

    <!-- Template CSV -->
    <?php
        $templateCsv = "Pregnancies,Glucose,BloodPressure,SkinThickness,Insulin,BMI,DiabetesPedigreeFunction,Age\n"
                     . "6,148,72,35,0,33.6,0.627,50\n"
                     . "1,85,66,29,0,26.6,0.351,31\n"
                     . "8,183,64,0,0,23.3,0.672,32\n"
                     . "0,89,66,23,94,28.1,0.167,21\n"
                     . "3,120,80,30,50,35.0,0.500,45\n";
        $templateB64 = base64_encode($templateCsv);
    ?>
    <div class="template-box">
        <strong>📋 Format CSV yang diperlukan:</strong><br>
        Kolom wajib: <code>Pregnancies, Glucose, BloodPressure, SkinThickness, Insulin, BMI, DiabetesPedigreeFunction, Age</code><br>
        Kolom tambahan lain diperbolehkan dan akan tetap disertakan di hasil.
        <br>
        <a class="template-link"
           href="data:text/csv;base64,<?php echo $templateB64; ?>"
           download="template_diabetes.csv">
            ⬇️ Download Template CSV
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data" id="batchForm">

        <!-- Upload zone -->
        <div class="upload-zone" onclick="document.getElementById('csv_file').click()">
            <div class="icon">📁</div>
            <div>Klik untuk pilih file CSV</div>
            <div class="hint">atau drag & drop file di sini</div>
            <div id="file-name">Belum ada file dipilih</div>
            <input type="file" id="csv_file" name="csv_file" accept=".csv" onchange="showFileName(this)">
        </div>

        <!-- Pilih Model -->
        <div class="model-selector">
            <label>Pilih Model Algoritma:</label>
            <div class="model-tabs">
                <?php foreach ($modelOptions as $key => $label): ?>
                    <div class="model-tab <?php echo $key === 'all' ? 'all' : ''; ?>">
                        <input
                            type="radio"
                            name="model"
                            id="bmodel_<?php echo $key; ?>"
                            value="<?php echo $key; ?>"
                            <?php echo ($key === 'all') ? 'checked' : ''; ?>
                        >
                        <label for="bmodel_<?php echo $key; ?>"><?php echo $label; ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" name="submit_batch">🚀 Jalankan Prediksi</button>
    </form>

    <!-- Error -->
    <?php if ($error): ?>
        <div class="error-box">⚠️ <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Summary & Download -->
    <?php if (!empty($summaryLines) && $hasilFile): ?>
        <div class="summary-box">
            <strong>✅ Prediksi berhasil!</strong>
            <ul>
                <?php foreach ($summaryLines as $line): ?>
                    <li><?php echo htmlspecialchars($line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <a class="download-btn" href="download_hasil.php?file=<?php echo urlencode($hasilFile); ?>">
            ⬇️ Download Hasil Prediksi (CSV)
        </a>
    <?php endif; ?>

    <!-- Preview Tabel -->
    <?php if (!empty($hasilHeaders) && !empty($hasilRows)): ?>
        <div class="preview-section">
            <h3>👁️ Preview Hasil (<?php echo count($hasilRows); ?> baris pertama)</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($hasilHeaders as $h): ?>
                                <th><?php echo htmlspecialchars($h); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hasilRows as $row): ?>
                            <tr>
                                <?php foreach ($row as $idx => $cell): ?>
                                    <?php
                                        // Deteksi kolom prediksi untuk badge warna
                                        $header = $hasilHeaders[$idx] ?? '';
                                        $isPred = (strpos($header, 'Prediksi') !== false);
                                    ?>
                                    <td>
                                        <?php if ($isPred): ?>
                                            <?php $cls = ($cell === 'Diabetes') ? 'badge-diabetes' : 'badge-normal'; ?>
                                            <span class="badge <?php echo $cls; ?>">
                                                <?php echo $cell === 'Diabetes' ? '⚠️' : '✅'; ?>
                                                <?php echo htmlspecialchars($cell); ?>
                                            </span>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($cell); ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
                // Hitung total baris di file hasil
                $hasilPath = $hasilDir . $hasilFile;
                $totalRows = 0;
                if (file_exists($hasilPath)) {
                    $totalRows = max(0, count(file($hasilPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) - 1);
                }
                if ($totalRows > 20):
            ?>
                <div class="more-note">... dan <?php echo $totalRows - 20; ?> baris lainnya. Download CSV untuk data lengkap.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="info-box">
        Algoritma tersedia: SVM, K-NN, Decision Tree, Neural Network<br>
        Mode "Semua Model" akan menghasilkan 4 kolom prediksi untuk perbandingan langsung.
    </div>

    <div class="nav">
        <a href="panggil_machine_learning.php">🩺 Prediksi Satu Pasien</a>
        <a href="coba_machine_learning.php">🧪 Test Cases</a>
    </div>
</div>

<script>
function showFileName(input) {
    const label = document.getElementById('file-name');
    if (input.files && input.files.length > 0) {
        label.textContent = '📄 ' + input.files[0].name;
    } else {
        label.textContent = 'Belum ada file dipilih';
    }
}

// Drag and drop support
const zone = document.querySelector('.upload-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.background = '#eaf4ff'; });
zone.addEventListener('dragleave',  () => { zone.style.background = '#f8fbff'; });
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.style.background = '#f8fbff';
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const input = document.getElementById('csv_file');
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        input.files = dt.files;
        showFileName(input);
    }
});
</script>
</body>
</html>
