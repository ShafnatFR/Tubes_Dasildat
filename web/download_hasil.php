<?php
// download_hasil.php — Serve file CSV hasil prediksi batch dengan aman
$allowedFiles = [
    'hasil_semua_model.csv',
    'hasil_svm.csv',
    'hasil_knn.csv',
    'hasil_dt.csv',
    'hasil_nn.csv',
];

$file = isset($_GET['file']) ? basename($_GET['file']) : '';

if (!$file || !in_array($file, $allowedFiles)) {
    http_response_code(400);
    exit('File tidak valid.');
}

$filePath = realpath(__DIR__ . '/../dataset/' . $file);

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    exit('File tidak ditemukan. Jalankan prediksi batch terlebih dahulu.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache');

readfile($filePath);
exit;
