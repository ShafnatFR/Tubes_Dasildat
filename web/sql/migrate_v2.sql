USE diabetes_app;

ALTER TABLE prediksi_log
    CHANGE COLUMN sumber pasien VARCHAR(255) NOT NULL DEFAULT '-';
