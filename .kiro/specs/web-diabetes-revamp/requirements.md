# Requirements Document

## Introduction

Fitur **web-diabetes-revamp** adalah revamp menyeluruh pada lapisan antarmuka web proyek DiabetesScanOnline. Proyek ini adalah aplikasi PHP + Python (XAMPP) yang menggunakan empat model Machine Learning (SVM, K-NN, Decision Tree, Neural Network) berbasis dataset Pima Indians Diabetes untuk memprediksi risiko diabetes.

Revamp ini mencakup sembilan area utama:
1. Penamaan ulang file PHP agar lebih deskriptif dan konsisten.
2. Pemisahan tampilan form input dan hasil prediksi satu pasien menjadi section/halaman terpisah.
3. Penambahan informasi waktu proses (execution time) pada prediksi dan upload batch.
4. Penambahan tombol "Detail" pada halaman hasil prediksi untuk menampilkan informasi lengkap per rekord.
5. Penggantian tombol download tunggal dengan dropdown multi-format (PNG, PDF, CSV, XLSX).
6. Penambahan filter model pada tabel hasil prediksi.
7. Integrasi database MySQL (XAMPP) dengan dua tabel: `prediksi_log` dan `batch_log`.
8. Auto-save hasil prediksi ke database dan halaman riwayat yang fetch langsung dari database.
9. Optimasi performa dengan caching path Python ke file agar overhead deteksi Python dieliminasi.

---

## Glossary

- **Aplikasi Web**: Kumpulan halaman PHP yang berjalan di atas XAMPP dan menjadi antarmuka pengguna DiabetesScanOnline.
- **Form_Prediksi**: Halaman (sebelumnya `panggil_machine_learning.php`) untuk memasukkan data satu pasien dan memperoleh hasil prediksi.
- **Halaman_Test_Cases**: Halaman (sebelumnya `coba_machine_learning.php`) yang menjalankan 6 skenario prediksi hardcoded pada semua model.
- **Halaman_Batch**: Halaman (sebelumnya `prediksi_batch.php`) untuk mengunggah file CSV berisi banyak data pasien dan menjalankan prediksi batch.
- **Modul_Download**: Skrip PHP (sebelumnya `download_hasil.php`) yang melayani pengiriman file hasil ke browser pengguna.
- **Halaman_Konfigurasi**: Halaman (sebelumnya `python_config.php`) untuk deteksi dan konfigurasi path Python.
- **Router**: File `index.php` yang berfungsi sebagai titik masuk dan mengarahkan pengguna ke halaman utama.
- **Model_ML**: Salah satu dari empat model machine learning yang tersedia — SVM, K-NN, Decision Tree, Neural Network.
- **Execution_Time**: Durasi waktu yang dibutuhkan untuk menyelesaikan satu siklus prediksi, diukur dalam detik atau milidetik.
- **Upload_Time**: Durasi waktu yang dibutuhkan untuk menerima dan menyimpan file CSV yang diunggah pengguna.
- **Modal_Detail**: Komponen antarmuka yang menampilkan informasi lengkap satu rekord prediksi dalam tampilan overlay atau panel.
- **Dropdown_Download**: Komponen antarmuka berupa menu tarik-turun yang menyediakan pilihan format unduhan.
- **Filter_Model**: Komponen antarmuka yang memungkinkan pengguna menampilkan atau menyembunyikan kolom/baris hasil berdasarkan model tertentu.
- **Scaler**: StandardScaler yang digunakan untuk menormalisasi fitur input sebelum diteruskan ke Model_ML.
- **Fitur_Input**: Delapan parameter medis — Pregnancies, Glucose, BloodPressure, SkinThickness, Insulin, BMI, DiabetesPedigreeFunction, Age.
- **prediksi_log**: Tabel MySQL yang menyimpan setiap hasil prediksi secara otomatis, mencakup fitur input, model, hasil, waktu eksekusi, dan sumber prediksi (`form`, `batch`, atau `test_case`).
- **batch_log**: Tabel MySQL yang menyimpan metadata setiap sesi prediksi batch, mencakup nama file, jumlah baris, model, dan waktu proses.
- **Auto-Save**: Mekanisme penyimpanan otomatis ke database yang terjadi segera setelah prediksi berhasil, tanpa memerlukan aksi eksplisit dari pengguna.
- **Halaman_Riwayat**: Halaman baru (`riwayat.php`) yang menampilkan semua hasil prediksi dengan fetch langsung dari tabel `prediksi_log` dan `batch_log`.

---

## Requirements

---

### Requirement 1: Penamaan Ulang File PHP

**User Story:** Sebagai pengembang, saya ingin nama file PHP mencerminkan fungsinya secara eksplisit, sehingga navigasi dan pemeliharaan kode menjadi lebih mudah.

#### Acceptance Criteria

1. THE Aplikasi_Web SHALL mengganti nama file `panggil_machine_learning.php` menjadi `form_prediksi.php`, dan file `panggil_machine_learning.php` tidak boleh lagi ada di direktori `web/`.
2. THE Aplikasi_Web SHALL mengganti nama file `coba_machine_learning.php` menjadi `test_cases.php`, dan file `coba_machine_learning.php` tidak boleh lagi ada di direktori `web/`.
3. THE Aplikasi_Web SHALL mengganti nama file `prediksi_batch.php` menjadi `batch_upload.php`, dan file `prediksi_batch.php` tidak boleh lagi ada di direktori `web/`.
4. THE Aplikasi_Web SHALL mengganti nama file `download_hasil.php` menjadi `download.php`, dan semua referensi internal (tautan, redirect, `href`) di seluruh file PHP harus mengacu ke `download.php`.
5. THE Aplikasi_Web SHALL mengganti nama file `python_config.php` menjadi `config_python.php`, dan semua pemanggilan `require_once` di file PHP lainnya harus diperbarui ke `config_python.php`.
6. THE Router SHALL memperbarui baris `header("Location: ...")` di `index.php` agar mengarah ke `form_prediksi.php`.
7. WHEN tautan navigasi antar halaman diakses, THE Aplikasi_Web SHALL merespons dengan HTTP 200 (bukan 404) sehingga tidak ada tautan mati (broken link) akibat penamaan ulang.

---

### Requirement 2: Pemisahan Form Input dan Hasil Prediksi

**User Story:** Sebagai pengguna, saya ingin form input dan hasil prediksi berada di halaman atau section yang terpisah, sehingga tampilan lebih bersih dan tidak membingungkan.

#### Acceptance Criteria

1. WHEN pengguna membuka `form_prediksi.php` tanpa data POST, THE Form_Prediksi SHALL menampilkan hanya section form input beserta pilihan model — section hasil dan pesan error tidak boleh ditampilkan.
2. WHEN pengguna menekan tombol "Prediksi Sekarang" dan semua input valid, THE Form_Prediksi SHALL menyembunyikan section form input dan menampilkan section hasil prediksi pada halaman yang sama (single-page pattern).
3. WHILE section hasil prediksi ditampilkan, THE Form_Prediksi SHALL menyediakan tombol "Prediksi Ulang" yang menyembunyikan section hasil, menampilkan kembali section form, dan mempertahankan semua nilai input yang sebelumnya dimasukkan pada field form.
4. IF pengguna mengirimkan form dengan satu atau lebih nilai Fitur_Input di luar rentang yang diizinkan, THEN THE Form_Prediksi SHALL menampilkan pesan kesalahan validasi pada section form input, section hasil tidak boleh ditampilkan, dan halaman tidak berpindah ke section hasil.
5. WHEN pengguna menekan tombol "Prediksi Ulang" dari section hasil, THE Form_Prediksi SHALL menampilkan kembali section form dengan nilai input yang sama persis seperti saat prediksi terakhir dijalankan.

---

### Requirement 3: Tampilan Waktu Proses (Execution Time)

**User Story:** Sebagai pengguna, saya ingin mengetahui berapa lama proses prediksi dan upload file berlangsung, sehingga saya bisa memantau performa sistem.

#### Acceptance Criteria

1. WHEN prediksi satu pasien selesai dijalankan, THE Form_Prediksi SHALL menampilkan Execution_Time dalam satuan milidetik (dibulatkan ke 2 angka desimal) di bawah hasil prediksi.
2. WHEN prediksi batch selesai dijalankan, THE Halaman_Batch SHALL menampilkan Execution_Time total eksekusi skrip Python (tidak termasuk waktu upload file) dalam satuan detik (dibulatkan ke 2 angka desimal) di bagian ringkasan hasil.
3. WHEN file CSV berhasil diunggah ke server, THE Halaman_Batch SHALL menampilkan Upload_Time dalam satuan detik (dibulatkan ke 2 angka desimal) sebagai elemen berlabel terpisah dari elemen Execution_Time prediksi di bagian ringkasan hasil.
4. WHEN prediksi test cases selesai dijalankan, THE Halaman_Test_Cases SHALL menampilkan Execution_Time untuk setiap baris skenario secara individual dalam satuan milidetik (dibulatkan ke 2 angka desimal) pada kolom bertabel "Waktu (ms)".
5. IF Execution_Time melebihi 10.000 milidetik (10 detik), THEN THE Aplikasi_Web SHALL menampilkan indikator peringatan visual berwarna oranye di samping nilai waktu; DAN IF Execution_Time kembali ke 10.000 milidetik atau kurang, THEN THE Aplikasi_Web SHALL menyembunyikan indikator peringatan tersebut.

---

### Requirement 4: Tombol Detail Per Rekord

**User Story:** Sebagai pengguna, saya ingin dapat melihat informasi lengkap setiap rekord prediksi dengan menekan tombol "Detail", sehingga saya tidak perlu mengunduh file untuk melihat data individual.

#### Acceptance Criteria

1. WHEN tabel hasil prediksi mode "Semua Model" ditampilkan di Form_Prediksi, THE Form_Prediksi SHALL menampilkan tombol "Detail" pada setiap baris hasil — tombol ini tidak muncul pada mode single-model.
2. WHEN prediksi batch berhasil dijalankan dan tabel preview ditampilkan, THE Halaman_Batch SHALL menampilkan tombol "Detail" pada setiap baris preview.
3. WHEN tabel test cases ditampilkan, THE Halaman_Test_Cases SHALL menampilkan tombol "Detail" pada setiap baris skenario.
4. WHEN pengguna menekan tombol "Detail" pada suatu baris, THE Aplikasi_Web SHALL menampilkan Modal_Detail berisi delapan nilai Fitur_Input (Pregnancies, Glucose, BloodPressure, SkinThickness, Insulin, BMI, DiabetesPedigreeFunction, Age) dan hasil prediksi keempat Model_ML (SVM, K-NN, Decision Tree, Neural Network) untuk rekord tersebut.
5. WHEN Modal_Detail ditampilkan, THE Aplikasi_Web SHALL menyediakan tombol "Tutup" dan area klik di luar modal yang keduanya menutup modal tanpa menyegarkan halaman, mempertahankan state form dan tabel sebelumnya.
6. IF satu atau lebih nilai Fitur_Input atau hasil prediksi Model_ML tidak dapat dimuat untuk rekord yang dipilih, THEN THE Aplikasi_Web SHALL menampilkan pesan "Data tidak tersedia" di dalam Modal_Detail sebagai pengganti nilai yang hilang.

---

### Requirement 5: Dropdown Multi-Format Download

**User Story:** Sebagai pengguna, saya ingin mengunduh hasil prediksi dalam berbagai format (PNG, PDF, CSV, XLSX), sehingga saya bisa menggunakan data sesuai kebutuhan laporan atau analisis lanjutan.

#### Acceptance Criteria

1. WHEN prediksi berhasil dijalankan dan hasil prediksi ditampilkan di halaman, THE Aplikasi_Web SHALL menampilkan Dropdown_Download sebagai pengganti tombol download tunggal.
2. WHEN Dropdown_Download dibuka, THE Dropdown_Download SHALL menampilkan tepat empat pilihan format: PNG, PDF, CSV, dan XLSX.
3. WHEN pengguna memilih format CSV dari Dropdown_Download, THE Modul_Download SHALL menghasilkan dan mengirimkan file berekstensi `.csv` berisi seluruh kolom Fitur_Input dan kolom prediksi setiap Model_ML yang ditampilkan.
4. WHEN pengguna memilih format XLSX dari Dropdown_Download, THE Modul_Download SHALL menghasilkan dan mengirimkan file berekstensi `.xlsx` berisi seluruh kolom Fitur_Input dan kolom prediksi setiap Model_ML dalam format spreadsheet dengan data ditempatkan mulai dari sel A1.
5. WHEN pengguna memilih format PNG dari Dropdown_Download, THE Aplikasi_Web SHALL menghasilkan dan mengunduh gambar screenshot dari elemen tabel hasil prediksi yang sedang ditampilkan di layar dalam format `.png`.
6. WHEN pengguna memilih format PDF dari Dropdown_Download, THE Aplikasi_Web SHALL menghasilkan dan mengunduh dokumen `.pdf` berisi seluruh kolom Fitur_Input dan kolom prediksi setiap Model_ML yang identik dengan konten CSV.
7. IF proses generate file untuk format tertentu gagal, THEN THE Aplikasi_Web SHALL menampilkan pesan kesalahan yang menyebutkan nama format yang gagal, menyarankan untuk mencoba format lain atau mengulang proses, dan tetap mempertahankan hasil prediksi yang ditampilkan di layar.
8. WHEN prediksi berhasil dijalankan dan hasil prediksi ditampilkan, THE Dropdown_Download SHALL tersedia pada halaman Form_Prediksi, Halaman_Batch, dan Halaman_Test_Cases.

---

### Requirement 6: Filter Model pada Tabel Hasil

**User Story:** Sebagai pengguna, saya ingin dapat memfilter tampilan hasil prediksi berdasarkan model tertentu, sehingga saya bisa fokus membandingkan model yang relevan tanpa gangguan kolom lainnya.

#### Acceptance Criteria

1. WHEN tabel hasil prediksi mode "Semua Model" ditampilkan di Form_Prediksi, THE Form_Prediksi SHALL menampilkan komponen Filter_Model berupa empat checkbox berlabel: "SVM", "K-NN", "Decision Tree", dan "Neural Network".
2. WHEN tabel hasil prediksi batch mode "all" ditampilkan di Halaman_Batch, THE Halaman_Batch SHALL menampilkan komponen Filter_Model berupa empat checkbox berlabel: "SVM", "K-NN", "Decision Tree", dan "Neural Network".
3. WHEN tabel hasil test cases ditampilkan, THE Halaman_Test_Cases SHALL menampilkan komponen Filter_Model berupa empat checkbox berlabel: "SVM", "K-NN", "Decision Tree", dan "Neural Network".
4. WHEN pengguna menonaktifkan satu atau lebih checkbox pada Filter_Model, THE Aplikasi_Web SHALL menyembunyikan kolom hasil model yang dinonaktifkan dari tabel tanpa menyegarkan halaman.
5. WHEN pengguna mengaktifkan kembali checkbox Model_ML yang sebelumnya dinonaktifkan, THE Aplikasi_Web SHALL menampilkan kembali kolom hasil model tersebut dalam waktu kurang dari 300 milidetik.
6. WHEN halaman pertama kali dimuat, THE Filter_Model SHALL menampilkan keempat checkbox (SVM, K-NN, Decision Tree, Neural Network) dalam keadaan tercentang (aktif).
7. IF pengguna mencoba menonaktifkan checkbox model terakhir yang masih aktif, THEN THE Filter_Model SHALL mencegah aksi tersebut dan menampilkan pesan "Setidaknya satu model harus ditampilkan".
8. WHEN pengguna berpindah ke halaman lain dan kembali, THE Filter_Model SHALL mereset ke kondisi default dengan keempat checkbox dalam keadaan aktif.

---

### Requirement 7: Konsistensi Navigasi Antar Halaman

**User Story:** Sebagai pengguna, saya ingin setiap halaman memiliki navigasi yang konsisten ke halaman lain, sehingga saya dapat berpindah antar fitur dengan mudah tanpa kebingungan.

#### Acceptance Criteria

1. THE Aplikasi_Web SHALL menampilkan komponen navigasi yang sama di setiap halaman utama (Form_Prediksi, Halaman_Batch, Halaman_Test_Cases), berisi tepat tiga tautan dalam urutan tetap: "Form Prediksi" → `form_prediksi.php`, "Prediksi Batch" → `batch_upload.php`, "Test Cases" → `test_cases.php`.
2. THE Aplikasi_Web SHALL memastikan semua tautan navigasi menggunakan nama file baru hasil penamaan ulang pada Requirement 1.
3. WHILE pengguna berada di suatu halaman, THE Aplikasi_Web SHALL menerapkan gaya visual yang berbeda (seperti warna latar atau garis bawah) pada elemen tautan yang sesuai dengan halaman aktif saat ini, sehingga dapat dibedakan secara visual dari tautan halaman lainnya.
4. IF halaman yang dituju tidak ditemukan (HTTP 404), THEN THE Router SHALL mengarahkan pengguna ke `form_prediksi.php` dan menampilkan pesan informasi singkat bahwa halaman tidak ditemukan dan pengguna telah dialihkan.

---

### Requirement 8: Validasi Input yang Informatif

**User Story:** Sebagai pengguna, saya ingin mendapatkan pesan validasi yang jelas ketika input tidak valid, sehingga saya tahu persis apa yang perlu diperbaiki.

#### Acceptance Criteria

1. WHEN pengguna mengirimkan form dengan satu atau lebih nilai Fitur_Input di luar rentang yang diizinkan, THE Form_Prediksi SHALL menampilkan pesan kesalahan per field yang menyebutkan nama field dan rentang nilai yang diizinkan: Kehamilan (0–20), Glukosa (0–300), Tekanan Darah (0–200), Ketebalan Kulit (0–100), Insulin (0–900), BMI (0–80), Diabetes Pedigree (0–3), Usia (1–120).
2. WHEN pengguna mengunggah file yang bukan berekstensi `.csv`, THE Halaman_Batch SHALL menampilkan pesan kesalahan "Format file harus CSV (.csv)" sebelum memproses atau menyimpan file di server.
3. WHEN file CSV yang diunggah tidak memiliki satu atau lebih kolom wajib, THE Halaman_Batch SHALL menampilkan daftar nama kolom yang hilang dari delapan kolom wajib: Pregnancies, Glucose, BloodPressure, SkinThickness, Insulin, BMI, DiabetesPedigreeFunction, Age.
4. IF file CSV yang diunggah berukuran lebih dari 5 MB (secara eksklusif), THEN THE Halaman_Batch SHALL menolak file tersebut sebelum diproses atau disimpan di server dan menampilkan pesan "Ukuran file maksimal 5 MB"; WHEN ukuran file tepat sama dengan 5 MB, THE Halaman_Batch SHALL menerima dan memproses file tersebut.
5. THE Form_Prediksi SHALL menampilkan rentang nilai yang valid di samping setiap label field input: Kehamilan (0–20), Glukosa (0–300), Tekanan Darah (0–200), Ketebalan Kulit (0–100), Insulin (0–900), BMI (0–80), Diabetes Pedigree (0–3), Usia (1–120).

---

### Requirement 9: Integrasi Database MySQL (XAMPP)

**User Story:** Sebagai pengembang, saya ingin aplikasi terhubung ke database MySQL yang tersedia di XAMPP, sehingga data prediksi dapat disimpan secara persisten dan diakses kembali di kemudian hari.

#### Acceptance Criteria

1. THE Aplikasi_Web SHALL menyediakan file koneksi `db.php` di direktori `web/` yang membuka koneksi ke database MySQL dengan konfigurasi: host = `localhost`, user = `root`, password = `''` (kosong), database = `diabetes_app` — sesuai konfigurasi default XAMPP.
2. IF koneksi ke database gagal saat file `db.php` di-include, THEN THE Aplikasi_Web SHALL menghentikan eksekusi dan menampilkan pesan kesalahan yang menyebutkan bahwa koneksi database tidak berhasil, tanpa mengekspos kredensial atau detail teknis internal ke tampilan pengguna.
3. THE Aplikasi_Web SHALL membuat tabel `prediksi_log` dengan skema berikut: `id` (INT, PRIMARY KEY, AUTO_INCREMENT), `sumber` (VARCHAR(20), nilai: `'form'`, `'batch'`, atau `'test_case'` — menandai asal prediksi), `model_key` (VARCHAR(20), nama model yang digunakan), `pregnancies` (FLOAT), `glucose` (FLOAT), `blood_pressure` (FLOAT), `skin_thickness` (FLOAT), `insulin` (FLOAT), `bmi` (FLOAT), `diabetes_pedigree` (FLOAT), `age` (FLOAT), `hasil_prediksi` (TINYINT, nilai 0 atau 1), `execution_time_ms` (FLOAT, waktu eksekusi dalam milidetik), `created_at` (DATETIME, default nilai saat INSERT).
4. THE Aplikasi_Web SHALL membuat tabel `batch_log` dengan skema berikut: `id` (INT, PRIMARY KEY, AUTO_INCREMENT), `nama_file` (VARCHAR), `jumlah_baris` (INT), `model_key` (VARCHAR), `execution_time_s` (FLOAT, waktu eksekusi Python dalam detik), `upload_time_s` (FLOAT, waktu upload file dalam detik), `created_at` (DATETIME, default nilai saat INSERT).
5. THE Aplikasi_Web SHALL membuat tabel `batch_log` dengan skema berikut: `id` (INT, PRIMARY KEY, AUTO_INCREMENT), `nama_file` (VARCHAR(255)), `jumlah_baris` (INT), `model_key` (VARCHAR(20)), `execution_time_s` (FLOAT, waktu eksekusi Python dalam detik), `upload_time_s` (FLOAT, waktu upload file dalam detik), `created_at` (DATETIME, default nilai saat INSERT).
6. WHEN skrip SQL pembuatan tabel dijalankan pada database `diabetes_app` yang kosong, THE Aplikasi_Web SHALL berhasil membuat kedua tabel (`prediksi_log` dan `batch_log`) tanpa error, dan kedua tabel tersebut dapat diverifikasi keberadaannya melalui phpMyAdmin atau perintah `SHOW TABLES`.

---

### Requirement 10: Auto-Save dan Riwayat Hasil Prediksi

**User Story:** Sebagai pengguna, saya ingin setiap hasil prediksi tersimpan otomatis ke database tanpa perlu menekan tombol simpan, sehingga saya dapat melihat seluruh riwayat prediksi kapan saja tanpa kehilangan data.

#### Acceptance Criteria

1. WHEN prediksi satu pasien berhasil dijalankan di Form_Prediksi dan hasil ditampilkan, THE Form_Prediksi SHALL secara otomatis menyimpan satu baris ke tabel `prediksi_log` yang berisi: nilai delapan Fitur_Input, `model_key` sesuai model yang digunakan, `hasil_prediksi` (0 untuk "Tidak Diabetes", 1 untuk "Diabetes"), `execution_time_ms`, dan `created_at` berisi timestamp saat penyimpanan — tanpa memerlukan aksi tambahan dari pengguna.
2. WHEN prediksi mode "Semua Model" berhasil dijalankan di Form_Prediksi, THE Form_Prediksi SHALL menyimpan empat baris ke tabel `prediksi_log` secara bersamaan — satu baris per Model_ML (SVM, K-NN, Decision Tree, Neural Network) — masing-masing dengan `model_key`, `hasil_prediksi`, dan `execution_time_ms` yang sesuai untuk model tersebut.
3. WHEN prediksi batch berhasil dijalankan di Halaman_Batch, THE Halaman_Batch SHALL secara otomatis menyimpan satu baris ke tabel `batch_log` yang berisi: `nama_file` (nama file CSV yang diunggah), `jumlah_baris` (jumlah data pasien yang diproses), `model_key`, `execution_time_s`, `upload_time_s`, dan `created_at`.
4. IF penyimpanan otomatis ke database gagal setelah prediksi berhasil dijalankan (koneksi terputus atau query error), THEN THE Aplikasi_Web SHALL menampilkan pesan peringatan non-blocking yang menyebutkan bahwa hasil tidak dapat disimpan ke riwayat, tanpa menyembunyikan atau menghapus hasil prediksi yang sedang ditampilkan.
5. THE Aplikasi_Web SHALL menyediakan halaman riwayat prediksi (`riwayat.php`) yang dapat diakses dari navigasi utama, menampilkan data yang di-fetch langsung dari tabel `prediksi_log` dalam bentuk tabel dengan kolom: No, Tanggal/Waktu (`created_at`), Model, delapan nilai Fitur_Input, Hasil Prediksi (ditampilkan sebagai "Diabetes" atau "Tidak Diabetes"), dan Waktu Eksekusi (ms).
6. WHEN halaman riwayat prediksi dimuat, THE Aplikasi_Web SHALL menampilkan data dari tabel `prediksi_log` yang di-fetch dari database, diurutkan berdasarkan `created_at` secara descending (terbaru di atas), tanpa menggunakan cache atau file perantara.
7. IF tabel `prediksi_log` tidak memiliki satu pun baris data, THEN THE Aplikasi_Web SHALL menampilkan pesan "Belum ada riwayat prediksi" sebagai pengganti tabel kosong pada halaman riwayat.
8. WHEN halaman riwayat prediksi dimuat, THE Aplikasi_Web SHALL juga menampilkan ringkasan riwayat batch dari tabel `batch_log` dalam section terpisah di bawah tabel prediksi individual, dengan kolom: No, Tanggal/Waktu, Nama File, Jumlah Baris, Model, Waktu Eksekusi (s), dan Waktu Upload (s).

---

### Requirement 11: Optimasi Performa — Eliminasi Python Cold-Start

**User Story:** Sebagai pengguna, saya ingin halaman prediksi dan test cases merespons dengan cepat, sehingga saya tidak perlu menunggu belasan detik setiap kali menggunakan aplikasi.

#### Acceptance Criteria

1. WHEN Halaman_Test_Cases dibuka, THE Halaman_Test_Cases SHALL menampilkan indikator loading yang terlihat di layar selama proses kalkulasi Python berlangsung, dan indikator tersebut hilang secara otomatis ketika seluruh hasil selesai ditampilkan.
2. WHEN prediksi test cases selesai dijalankan, THE Halaman_Test_Cases SHALL secara otomatis menyimpan hasil keenam skenario ke tabel `prediksi_log` dengan kolom tambahan `sumber` bernilai `'test_case'` untuk membedakannya dari prediksi pasien nyata.
3. THE Halaman_Konfigurasi SHALL menyimpan path Python yang berhasil terdeteksi ke dalam file `python_path.cache` di direktori `web/`, sehingga fungsi `findPython()` tidak perlu menjalankan `shell_exec` untuk deteksi ulang pada setiap request berikutnya.
4. WHEN file `python_path.cache` tersedia dan path di dalamnya valid (Python dapat dieksekusi), THE Halaman_Konfigurasi SHALL memuat path Python langsung dari file cache tanpa menjalankan perintah deteksi apapun, sehingga overhead deteksi Python per request turun menjadi 0 milidetik.
5. IF file `python_path.cache` tidak ditemukan atau path yang tersimpan tidak valid, THEN THE Halaman_Konfigurasi SHALL menjalankan ulang proses `findPython()`, memperbarui file cache dengan path yang valid, dan melanjutkan eksekusi tanpa menampilkan error ke pengguna.
6. IF Execution_Time satu prediksi melebihi 10.000 milidetik, THEN THE Aplikasi_Web SHALL menampilkan indikator peringatan visual berwarna oranye di samping nilai waktu untuk membantu pengguna mengidentifikasi bottleneck performa.
