CREATE DATABASE IF NOT EXISTS diabetes_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE diabetes_app;

CREATE TABLE IF NOT EXISTS prediksi_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NULL,
    baris_no INT NULL,
    pasien VARCHAR(255) NOT NULL DEFAULT '-',
    model_key VARCHAR(20) NOT NULL,
    pregnancies FLOAT NOT NULL,
    glucose FLOAT NOT NULL,
    blood_pressure FLOAT NOT NULL,
    skin_thickness FLOAT NOT NULL,
    insulin FLOAT NOT NULL,
    bmi FLOAT NOT NULL,
    diabetes_pedigree FLOAT NOT NULL,
    age FLOAT NOT NULL,
    hasil_prediksi TINYINT NOT NULL,
    execution_time_ms FLOAT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_batch_id (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS batch_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_file VARCHAR(255) NOT NULL,
    jumlah_baris INT NOT NULL,
    model_key VARCHAR(20) NOT NULL,
    execution_time_s FLOAT NOT NULL DEFAULT 0,
    upload_time_s FLOAT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
