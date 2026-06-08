<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config_python.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/db_helpers.php';

$predictScript = realpath(__DIR__ . '/../scripts/predict.py');
$activePage = 'form';

$modelOptions = [
    'svm' => 'SVM (Support Vector Machine)',
    'knn' => 'K-NN (K-Nearest Neighbor)',
    'dt'  => 'Decision Tree',
    'nn'  => 'Neural Network',
    'all' => 'Semua Model (Bandingkan)',
];

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

$result = null;
$fieldErrors = [];
$modelUsed = '';
$execTimeMs = 0;
$saveWarning = null;
$features = [];
$pasienName = '';
$receiptPayload = null;

function postVal($key, $default = '') {
    return isset($_POST[$key]) ? htmlspecialchars((string) $_POST[$key]) : $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $modelKey = $_POST['model'] ?? 'svm';
    if (!array_key_exists($modelKey, $modelOptions)) {
        $modelKey = 'svm';
    }
    $modelUsed = $modelKey;
    $pasienName = trim($_POST['nama_pasien'] ?? '');

    if ($pasienName === '') {
        $fieldErrors['nama_pasien'] = 'Nama pasien wajib diisi';
    } elseif (mb_strlen($pasienName) > 100) {
        $fieldErrors['nama_pasien'] = 'Nama pasien maksimal 100 karakter';
    }

    foreach ($rawFields as $field => $rule) {
        $val = (float) str_replace(',', '.', $_POST[$field] ?? 0);
        if ($val < $rule['min'] || $val > $rule['max']) {
            $fieldErrors[$field] = "{$rule['label']} harus antara {$rule['min']}–{$rule['max']}";
        }
        $features[] = $val;
    }

    if (empty($fieldErrors)) {
        $t0 = microtime(true);
        $inputJson = escapeshellarg(json_encode($features));
        $modelArg  = escapeshellarg($modelKey);
        $scriptArg = escapeshellarg($predictScript);
        $command   = "$pythonExe $scriptArg $inputJson $modelArg 2>&1";
        $output    = trim(shell_exec($command));
        $result    = json_decode($output, true);
        $execTimeMs = round((microtime(true) - $t0) * 1000, 2);

        if (!$result || isset($result['error'])) {
            $fieldErrors['_general'] = $result['error'] ?? 'Prediksi gagal. Output: ' . htmlspecialchars($output);
            $result = null;
        } else {
            if (isset($result['execution_time_ms'])) {
                $execTimeMs = (float) $result['execution_time_ms'];
            }

            $featureData = [
                'pregnancies' => $features[0],
                'glucose' => $features[1],
                'blood_pressure' => $features[2],
                'skin_thickness' => $features[3],
                'insulin' => $features[4],
                'bmi' => $features[5],
                'diabetes_pedigree' => $features[6],
                'age' => $features[7],
            ];

            $saveOk = true;
            if ($result['mode'] === 'single') {
                $row = array_merge($featureData, [
                    'pasien' => $pasienName,
                    'model_key' => $modelKey,
                    'hasil_prediksi' => predLabelToInt($result['class']),
                    'execution_time_ms' => $result['execution_time_ms'] ?? $execTimeMs,
                ]);
                $saveOk = savePrediksiLog($conn, $row);

                $receiptPayload = array_merge($featureData, [
                    'pasien' => $pasienName,
                    'model_label' => $result['model'],
                    'hasil_prediksi' => predLabelToInt($result['class']),
                    'execution_time_ms' => $result['execution_time_ms'] ?? $execTimeMs,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $timings = $result['timings'] ?? [];
                foreach ($result['results'] as $modelName => $predLabel) {
                    $row = array_merge($featureData, [
                        'pasien' => $pasienName,
                        'model_key' => modelKeyFromLabel($modelName),
                        'hasil_prediksi' => predLabelToInt($predLabel),
                        'execution_time_ms' => $timings[$modelName] ?? ($execTimeMs / 4),
                    ]);
                    if (!savePrediksiLog($conn, $row)) {
                        $saveOk = false;
                    }
                }

                $primaryLabel = $modelOptions[$modelKey] ?? 'Semua Model';
                $receiptPayload = array_merge($featureData, [
                    'pasien' => $pasienName,
                    'model_label' => 'Semua Model',
                    'hasil_prediksi' => predLabelToInt($result['results']['SVM'] ?? 'Tidak Diabetes'),
                    'execution_time_ms' => $execTimeMs,
                    'created_at' => date('Y-m-d H:i:s'),
                    'all_results' => $result['results'],
                ]);
            }

            if (!$saveOk) {
                $saveWarning = 'Hasil prediksi tidak dapat disimpan ke riwayat.';
            }
        }
    }
}

$notfoundMsg = isset($_GET['notfound']);
$labels = [
    'pregnancies' => 'Kehamilan (Pregnancies)',
    'glucose' => 'Kadar Glukosa',
    'blood_pressure' => 'Tekanan Darah',
    'skin_thickness' => 'Ketebalan Kulit',
    'insulin' => 'Insulin',
    'bmi' => 'BMI',
    'diabetes_pedigree' => 'Diabetes Pedigree',
    'age' => 'Usia',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prediksi Diabetes</title>
    <?php include __DIR__ . '/includes/assets.php'; ?>
</head>
<body class="bg-slate-100 min-h-screen p-5">
<div class="max-w-xl mx-auto bg-white rounded-xl shadow-md p-7">
    <h1 class="text-center text-xl font-bold text-slate-800">🩺 Prediksi Diabetes</h1>
    <p class="text-center text-sm text-slate-500 mb-6">Masukkan data pasien untuk prediksi risiko diabetes</p>

    <?php if ($notfoundMsg): ?>
        <div class="notfound-banner mb-4">Halaman tidak ditemukan. Anda telah dialihkan ke Form Prediksi.</div>
    <?php endif; ?>

    <?php if ($saveWarning): ?>
        <div class="warn-box mb-4">⚠️ <?php echo htmlspecialchars($saveWarning); ?></div>
    <?php endif; ?>

    <form method="POST" id="mainForm">
        <div class="mb-5">
            <label for="nama_pasien" class="block text-sm font-bold text-slate-700 mb-1.5">Nama Pasien</label>
            <input type="text" name="nama_pasien" id="nama_pasien" maxlength="100"
                   value="<?php echo postVal('nama_pasien', $pasienName); ?>" required
                   placeholder="Contoh: Budi Santoso"
                   class="w-full px-3 py-2.5 border rounded-lg text-sm focus:outline-none focus:border-primary <?php echo isset($fieldErrors['nama_pasien']) ? 'border-red-400' : 'border-gray-300'; ?>">
            <?php if (isset($fieldErrors['nama_pasien'])): ?>
                <span class="text-xs text-red-600 mt-1 block"><?php echo htmlspecialchars($fieldErrors['nama_pasien']); ?></span>
            <?php endif; ?>
        </div>

        <div class="mb-5">
            <label for="model" class="block text-sm font-bold text-slate-700 mb-1.5">Pilih Model Algoritma</label>
            <select name="model" id="model" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary">
                <?php foreach ($modelOptions as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo ($modelUsed === $key || ($modelUsed === '' && $key === 'svm')) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-5">
            <?php foreach ($rawFields as $field => $rule):
                $hasErr = isset($fieldErrors[$field]);
            ?>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1">
                    <?php echo $labels[$field]; ?>
                    <span class="font-normal text-gray-400">(<?php echo $rule['min']; ?>–<?php echo $rule['max']; ?>)</span>
                </label>
                <input type="number" step="any" name="<?php echo $field; ?>"
                       min="<?php echo $rule['min']; ?>" max="<?php echo $rule['max']; ?>"
                       value="<?php echo postVal($field); ?>" required
                       class="w-full px-2.5 py-2 border rounded-md text-sm focus:outline-none focus:border-primary <?php echo $hasErr ? 'border-red-400' : 'border-gray-300'; ?>">
                <?php if ($hasErr): ?>
                    <span class="text-xs text-red-600 mt-0.5 block"><?php echo htmlspecialchars($fieldErrors[$field]); ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="flex gap-2 flex-wrap">
            <button type="button" id="btnRandomize"
                    class="px-4 py-2.5 bg-purple-600 hover:bg-purple-700 text-white text-sm font-bold rounded-lg">
                🎲 Randomize
            </button>
            <button type="button" id="btnResetForm"
                    class="px-4 py-2.5 bg-slate-400 hover:bg-slate-500 text-white text-sm font-bold rounded-lg">
                ↺ Reset
            </button>
            <button type="submit"
                    class="flex-1 py-2.5 bg-primary hover:bg-primary-dark text-white text-sm font-bold rounded-lg">
                🔍 Prediksi Sekarang
            </button>
        </div>
    </form>

    <?php if (isset($fieldErrors['_general'])): ?>
        <div class="error-box mt-4">⚠️ <?php echo htmlspecialchars($fieldErrors['_general']); ?></div>
    <?php endif; ?>

    <div class="info-box mt-5">
        Dataset: Pima Indians Diabetes Database (768 sampel)<br>
        Tersedia 4 algoritma: SVM, K-NN, Decision Tree, Neural Network
    </div>

    <?php include __DIR__ . '/includes/nav.php'; ?>
</div>

<div class="receipt-overlay" id="receiptModal" role="dialog" aria-modal="true">
    <div class="receipt-modal">
        <div id="receiptBody"></div>
        <div class="receipt-actions">
            <button type="button" class="btn-print" id="receiptPrint">🖨️ Cetak</button>
            <button type="button" class="btn-png" id="receiptSavePng">💾 Simpan PNG</button>
            <button type="button" class="btn-pdf" id="receiptSavePdf">📄 Simpan PDF</button>
            <button type="button" class="btn-close-r" id="receiptClose">Tutup</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/confirm_modal.php'; ?>

<script src="includes/receipt.js"></script>
<script>
(function () {
    const ranges = <?php echo json_encode(array_map(function ($r) {
        return ['min' => $r['min'], 'max' => $r['max']];
    }, $rawFields)); ?>;
    const intFields = ['pregnancies', 'age'];
    const form = document.getElementById('mainForm');

    document.getElementById('btnRandomize')?.addEventListener('click', function () {
        Object.keys(ranges).forEach(function (name) {
            const input = form.querySelector('[name="' + name + '"]');
            if (!input) return;
            const min = ranges[name].min, max = ranges[name].max;
            let val;
            if (intFields.includes(name)) {
                val = Math.floor(Math.random() * (max - min + 1)) + min;
            } else if (name === 'diabetes_pedigree') {
                val = (Math.random() * (max - min) + min).toFixed(3);
            } else if (name === 'bmi') {
                val = (Math.random() * (max - min) + min).toFixed(1);
            } else {
                val = Math.floor(Math.random() * (max - min + 1)) + min;
            }
            input.value = val;
        });
    });

    document.getElementById('btnResetForm')?.addEventListener('click', function () {
        ConfirmUI.show(
            'Reset Form?',
            'Semua data yang telah diisi (nama pasien dan parameter medis) akan dihapus. Tindakan ini tidak dapat dibatalkan.',
            function () {
                form.querySelectorAll('input, select').forEach(function (el) {
                    if (el.type === 'hidden') return;
                    if (el.tagName === 'SELECT') {
                        el.selectedIndex = 0;
                    } else {
                        el.value = '';
                    }
                });
                document.querySelectorAll('.text-red-600').forEach(function (el) {
                    if (el.tagName === 'SPAN') el.remove();
                });
            }
        );
    });

    ReceiptUI.bindReceiptModal();

    <?php if ($receiptPayload): ?>
    ReceiptUI.showReceipt(<?php echo json_encode($receiptPayload, JSON_HEX_TAG | JSON_HEX_AMP); ?>);
    <?php endif; ?>
})();
</script>
</body>
</html>
