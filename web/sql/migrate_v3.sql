USE diabetes_app;

ALTER TABLE prediksi_log ADD COLUMN batch_id INT NULL AFTER id;
ALTER TABLE prediksi_log ADD COLUMN baris_no INT NULL AFTER batch_id;
ALTER TABLE prediksi_log ADD INDEX idx_batch_id (batch_id);
