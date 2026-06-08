<?php

function predLabelToInt(string $label): int
{
    return ($label === 'Diabetes') ? 1 : 0;
}

function savePrediksiLog(mysqli $conn, array $row): bool
{
    $stmt = $conn->prepare(
        'INSERT INTO prediksi_log
        (batch_id, baris_no, pasien, model_key, pregnancies, glucose, blood_pressure, skin_thickness,
         insulin, bmi, diabetes_pedigree, age, hasil_prediksi, execution_time_ms, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return false;
    }

    $batchId = $row['batch_id'] ?? null;
    $barisNo = $row['baris_no'] ?? null;
    $pasien = $row['pasien'];
    $modelKey = $row['model_key'];
    $pregnancies = (float) $row['pregnancies'];
    $glucose = (float) $row['glucose'];
    $bloodPressure = (float) $row['blood_pressure'];
    $skinThickness = (float) $row['skin_thickness'];
    $insulin = (float) $row['insulin'];
    $bmi = (float) $row['bmi'];
    $diabetesPedigree = (float) $row['diabetes_pedigree'];
    $age = (float) $row['age'];
    $hasil = (int) $row['hasil_prediksi'];
    $execMs = (float) $row['execution_time_ms'];

    $stmt->bind_param(
        'iissddddddddid',
        $batchId,
        $barisNo,
        $pasien,
        $modelKey,
        $pregnancies,
        $glucose,
        $bloodPressure,
        $skinThickness,
        $insulin,
        $bmi,
        $diabetesPedigree,
        $age,
        $hasil,
        $execMs
    );

    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function saveBatchLog(mysqli $conn, array $row): int|false
{
    $stmt = $conn->prepare(
        'INSERT INTO batch_log
        (nama_file, jumlah_baris, model_key, execution_time_s, upload_time_s, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())'
    );
    if (!$stmt) {
        return false;
    }

    $namaFile = $row['nama_file'];
    $jumlahBaris = (int) $row['jumlah_baris'];
    $modelKey = $row['model_key'];
    $execS = (float) $row['execution_time_s'];
    $uploadS = (float) $row['upload_time_s'];

    $stmt->bind_param('sisdd', $namaFile, $jumlahBaris, $modelKey, $execS, $uploadS);

    $ok = $stmt->execute();
    $insertId = $ok ? (int) $conn->insert_id : false;
    $stmt->close();
    return $insertId;
}

function importBatchPrediksiFromCsv(
    mysqli $conn,
    int $batchId,
    string $hasilPath,
    string $modelKey,
    string $origFileName,
    float $execTimeS
): int {
    if (!file_exists($hasilPath)) {
        return 0;
    }

    $featureMap = [
        'Pregnancies' => 'pregnancies',
        'Glucose' => 'glucose',
        'BloodPressure' => 'blood_pressure',
        'SkinThickness' => 'skin_thickness',
        'Insulin' => 'insulin',
        'BMI' => 'bmi',
        'DiabetesPedigreeFunction' => 'diabetes_pedigree',
        'Age' => 'age',
    ];

    $allPredCols = [
        'Prediksi_SVM' => 'svm',
        'Prediksi_K_NN' => 'knn',
        'Prediksi_Decision_Tree' => 'dt',
        'Prediksi_Neural_Network' => 'nn',
    ];

    $lines = file($hasilPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lines) < 2) {
        return 0;
    }

    $headers = str_getcsv($lines[0]);
    $headerMap = array_flip($headers);
    $dataRowCount = count($lines) - 1;
    $msPerRow = $dataRowCount > 0 ? ($execTimeS * 1000) / $dataRowCount : 0;
    $baseName = pathinfo($origFileName, PATHINFO_FILENAME);
    $saved = 0;

    for ($i = 1; $i < count($lines); $i++) {
        $cells = str_getcsv($lines[$i]);
        $barisNo = $i;
        $pasien = $baseName . ' - Baris ' . $barisNo;

        $features = [];
        foreach ($featureMap as $csvCol => $dbField) {
            $idx = $headerMap[$csvCol] ?? null;
            $features[$dbField] = ($idx !== null && isset($cells[$idx])) ? (float) $cells[$idx] : 0;
        }

        if ($modelKey === 'all') {
            $msEach = $msPerRow / 4;
            foreach ($allPredCols as $col => $mk) {
                $idx = $headerMap[$col] ?? null;
                if ($idx === null) {
                    continue;
                }
                $label = $cells[$idx] ?? 'Tidak Diabetes';
                if (savePrediksiLog($conn, array_merge($features, [
                    'batch_id' => $batchId,
                    'baris_no' => $barisNo,
                    'pasien' => $pasien,
                    'model_key' => $mk,
                    'hasil_prediksi' => predLabelToInt($label),
                    'execution_time_ms' => $msEach,
                ]))) {
                    $saved++;
                }
            }
        } else {
            $idx = $headerMap['Prediksi'] ?? null;
            $label = ($idx !== null && isset($cells[$idx])) ? $cells[$idx] : 'Tidak Diabetes';
            if (savePrediksiLog($conn, array_merge($features, [
                'batch_id' => $batchId,
                'baris_no' => $barisNo,
                'pasien' => $pasien,
                'model_key' => $modelKey,
                'hasil_prediksi' => predLabelToInt($label),
                'execution_time_ms' => $msPerRow,
            ]))) {
                $saved++;
            }
        }
    }

    return $saved;
}

function modelKeyFromLabel(string $label): string
{
    $map = [
        'SVM' => 'svm',
        'K-NN' => 'knn',
        'Decision Tree' => 'dt',
        'Neural Network' => 'nn',
    ];
    return $map[$label] ?? strtolower(str_replace(' ', '_', $label));
}
