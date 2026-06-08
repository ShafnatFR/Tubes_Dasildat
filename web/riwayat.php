<?php
require_once __DIR__ . '/db.php';

$activePage = 'riwayat';

$prediksiRows = [];
$batchRows = [];

$res = $conn->query(
    'SELECT p.*, b.nama_file AS batch_file
     FROM prediksi_log p
     LEFT JOIN batch_log b ON p.batch_id = b.id
     ORDER BY p.created_at DESC, p.baris_no ASC, p.id DESC'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $prediksiRows[] = $row;
    }
    $res->free();
}

$resBatch = $conn->query('SELECT * FROM batch_log ORDER BY created_at DESC');
if ($resBatch) {
    while ($row = $resBatch->fetch_assoc()) {
        $batchRows[] = $row;
    }
    $resBatch->free();
}

$modelDisplay = [
    'svm' => 'SVM',
    'knn' => 'K-NN',
    'dt'  => 'Decision Tree',
    'nn'  => 'Neural Network',
    'all' => 'Semua Model',
];

$jsonRows = [];
foreach ($prediksiRows as $r) {
    $pasienCol = $r['pasien'] ?? '-';
    $isBatch = !empty($r['batch_id']);
    $jsonRows[] = [
        'id' => (int) $r['id'],
        'batch_id' => $r['batch_id'] ? (int) $r['batch_id'] : null,
        'baris_no' => $r['baris_no'] ? (int) $r['baris_no'] : null,
        'tipe' => $isBatch ? 'Batch' : 'Form',
        'batch_file' => $r['batch_file'] ?? '',
        'created_at' => $r['created_at'],
        'pasien' => $pasienCol,
        'model_key' => $r['model_key'],
        'model_label' => $modelDisplay[$r['model_key']] ?? $r['model_key'],
        'pregnancies' => $r['pregnancies'],
        'glucose' => $r['glucose'],
        'blood_pressure' => $r['blood_pressure'],
        'skin_thickness' => $r['skin_thickness'],
        'insulin' => $r['insulin'],
        'bmi' => $r['bmi'],
        'diabetes_pedigree' => $r['diabetes_pedigree'],
        'age' => $r['age'],
        'hasil_prediksi' => (int) $r['hasil_prediksi'],
        'hasil_label' => $r['hasil_prediksi'] ? 'Diabetes' : 'Tidak Diabetes',
        'execution_time_ms' => (float) $r['execution_time_ms'],
    ];
}

$jsonBatch = [];
foreach ($batchRows as $r) {
    $jsonBatch[] = [
        'id' => (int) $r['id'],
        'created_at' => $r['created_at'],
        'nama_file' => $r['nama_file'],
        'jumlah_baris' => (int) $r['jumlah_baris'],
        'model_key' => $r['model_key'],
        'model_label' => $modelDisplay[$r['model_key']] ?? $r['model_key'],
        'execution_time_s' => (float) $r['execution_time_s'],
        'upload_time_s' => (float) $r['upload_time_s'],
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Prediksi</title>
    <?php include __DIR__ . '/includes/assets.php'; ?>
</head>
<body class="bg-slate-100 min-h-screen p-5">
<div class="max-w-6xl mx-auto bg-white rounded-xl shadow-md p-7">
    <h1 class="text-center text-xl font-bold text-slate-800">📜 Riwayat Prediksi</h1>
    <p class="text-center text-sm text-slate-500 mb-6">Form manual dan hasil batch ditampilkan dalam satu tabel</p>

    <h2 class="text-base font-bold text-slate-700 mt-2 mb-3">Semua Hasil Prediksi</h2>

    <?php if (empty($prediksiRows)): ?>
        <div class="empty-msg">Belum ada riwayat prediksi. Gunakan Form Prediksi atau Prediksi Batch.</div>
    <?php else: ?>

    <div id="batchFilterBanner" class="hidden mb-3 px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800 flex justify-between items-center">
        <span id="batchFilterText"></span>
        <button type="button" id="btnClearBatchFilter" class="text-blue-600 underline text-xs font-bold">Tampilkan Semua</button>
    </div>

    <div class="riwayat-toolbar">
        <div class="field">
            <label for="filterTipe">Tipe</label>
            <select id="filterTipe">
                <option value="">Semua</option>
                <option value="Form">Form</option>
                <option value="Batch">Batch</option>
            </select>
        </div>
        <div class="field">
            <label for="filterModel">Model</label>
            <select id="filterModel">
                <option value="">Semua</option>
                <?php foreach (['svm','knn','dt','nn'] as $mk): ?>
                <option value="<?php echo $mk; ?>"><?php echo $modelDisplay[$mk]; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="filterPasien">Pasien</label>
            <input type="search" id="filterPasien" placeholder="Nama pasien...">
        </div>
        <div class="field">
            <label for="filterHasil">Hasil</label>
            <select id="filterHasil">
                <option value="">Semua</option>
                <option value="1">Diabetes</option>
                <option value="0">Tidak Diabetes</option>
            </select>
        </div>
        <div class="field">
            <label for="filterDateFrom">Dari Tanggal</label>
            <input type="date" id="filterDateFrom">
        </div>
        <div class="field">
            <label for="filterDateTo">Sampai Tanggal</label>
            <input type="date" id="filterDateTo">
        </div>
        <div class="field">
            <label for="filterSearch">Cari</label>
            <input type="search" id="filterSearch" placeholder="Kata kunci...">
        </div>
        <input type="hidden" id="filterBatchId" value="">
        <button type="button" class="btn-filter" id="btnApplyFilter">Terapkan</button>
        <!-- <button type="button" class="btn-filter secondary" id="btnResetFilter">Reset</button> -->
    </div>

    <div class="riwayat-dl-bar">
        <span>Unduh data terfilter:</span>
        <button type="button" class="riwayat-dl-btn" data-format="PNG">PNG</button>
        <button type="button" class="riwayat-dl-btn" data-format="PDF">PDF (Landscape)</button>
        <button type="button" class="riwayat-dl-btn" data-format="CSV">CSV</button>
        <button type="button" class="riwayat-dl-btn" data-format="XLSX">XLSX</button>
        <span class="riwayat-dl-error" id="riwayatDlError"></span>
    </div>

    <div class="empty-msg" id="riwayatEmpty" style="display:none;">Tidak ada data yang cocok dengan filter.</div>

    <div class="table-wrap" id="riwayatTableWrap">
        <table class="data-table" id="riwayatTable">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal / Waktu</th>
                    <th>Tipe</th>
                    <th>Pasien</th>
                    <th>Baris</th>
                    <th>Model</th>
                    <th>Kehamilan</th>
                    <th>Glukosa</th>
                    <th>Tekanan Darah</th>
                    <th>Ketebalan Kulit</th>
                    <th>Insulin</th>
                    <th>BMI</th>
                    <th>DPF</th>
                    <th>Usia</th>
                    <th>Hasil</th>
                    <th>Waktu (ms)</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="riwayatBody">
                <?php foreach ($prediksiRows as $i => $r):
                    $pasienCol = $r['pasien'] ?? '-';
                    $isBatch = !empty($r['batch_id']);
                    $receiptData = $jsonRows[$i];
                ?>
                <tr data-batch-id="<?php echo (int) ($r['batch_id'] ?? 0); ?>">
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                    <td>
                        <span class="badge <?php echo $isBatch ? 'badge-batch' : 'badge-form'; ?>">
                            <?php echo $isBatch ? 'Batch' : 'Form'; ?>
                        </span>
                    </td>
                    <td class="font-medium text-slate-800"><?php echo htmlspecialchars($pasienCol); ?></td>
                    <td><?php echo $r['baris_no'] ? (int) $r['baris_no'] : '-'; ?></td>
                    <td><?php echo htmlspecialchars($modelDisplay[$r['model_key']] ?? $r['model_key']); ?></td>
                    <td><?php echo $r['pregnancies']; ?></td>
                    <td><?php echo $r['glucose']; ?></td>
                    <td><?php echo $r['blood_pressure']; ?></td>
                    <td><?php echo $r['skin_thickness']; ?></td>
                    <td><?php echo $r['insulin']; ?></td>
                    <td><?php echo $r['bmi']; ?></td>
                    <td><?php echo $r['diabetes_pedigree']; ?></td>
                    <td><?php echo $r['age']; ?></td>
                    <td>
                        <span class="badge <?php echo $r['hasil_prediksi'] ? 'diabetes' : 'normal'; ?>">
                            <?php echo $r['hasil_prediksi'] ? 'Diabetes' : 'Tidak Diabetes'; ?>
                        </span>
                    </td>
                    <td><?php echo number_format($r['execution_time_ms'], 2); ?></td>
                    <td>
                        <button type="button" class="btn-detail btn-receipt"
                                data-receipt='<?php echo htmlspecialchars(json_encode($receiptData), ENT_QUOTES); ?>'>
                            Detail
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script type="application/json" id="riwayatData"><?php echo json_encode($jsonRows, JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <?php endif; ?>

    <h2 class="text-base font-bold text-slate-700 mt-8 mb-3">Ringkasan Sesi Batch</h2>
    <p class="text-xs text-slate-500 mb-3">Klik <strong>Lihat Hasil</strong> untuk menampilkan detail per baris di tabel di atas.</p>
    <?php if (empty($batchRows)): ?>
        <div class="empty-msg">Belum ada riwayat batch</div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="data-table batch-table" id="batchTable">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Tanggal / Waktu</th>
                    <th class="col-file">Nama File CSV</th>
                    <th>Jumlah Data</th>
                    <th>Model</th>
                    <th>Waktu Eksekusi</th>
                    <th>Waktu Upload</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batchRows as $i => $r): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                    <td class="col-file font-medium"><?php echo htmlspecialchars($r['nama_file']); ?></td>
                    <td><?php echo (int) $r['jumlah_baris']; ?> baris</td>
                    <td><?php echo htmlspecialchars($modelDisplay[$r['model_key']] ?? $r['model_key']); ?></td>
                    <td><?php echo number_format($r['execution_time_s'], 2); ?> dtk</td>
                    <td><?php echo number_format($r['upload_time_s'], 2); ?> dtk</td>
                    <td>
                        <button type="button" class="btn-detail btn-view-batch"
                                data-batch-id="<?php echo (int) $r['id']; ?>"
                                data-batch-file="<?php echo htmlspecialchars($r['nama_file'], ENT_QUOTES); ?>">
                            Lihat Hasil
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

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
<script src="includes/riwayat.js" defer></script>
</body>
</html>
