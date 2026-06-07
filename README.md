# Tubes Dasildat - Sistem Prediksi Diabetes

Aplikasi prediksi penyakit diabetes berbasis Web (PHP) dan Machine Learning (Python). Aplikasi ini menggunakan beberapa model yang telah dilatih (K-NN, SVM, Decision Tree, Neural Network) untuk memprediksi probabilitas seseorang menderita diabetes berdasarkan data medis.

## Prasyarat (Requirements)
1. Web Server lokal seperti **XAMPP**, **WAMP**, atau **Laragon** (untuk menjalankan PHP).
2. **Python** (disarankan versi 3.8 atau lebih baru).

## Cara Menjalankan (Setup)

Proyek ini telah dikonfigurasi agar dapat secara otomatis mendeteksi path instalasi Python pada sistem operasi Anda, sehingga tidak perlu melakukan *hardcode* direktori Python.

### 1. Install Library Python
Buka folder proyek ini dan jalankan instalasi *library* Python yang dibutuhkan:
- **Pengguna Windows:** Cukup klik dua kali (*double-click*) pada file `setup.bat`. Script tersebut otomatis menginstall pandas, scikit-learn, dan joblib.
- **Manual (Semua OS):** Buka terminal / command prompt pada folder proyek, lalu jalankan perintah:
  ```bash
  pip install -r requirements.txt
  ```

### 2. Jalankan Server Web
1. Pindahkan atau *clone* folder `Tubes_Dasildat` ke dalam folder root server Anda (misalnya `C:\xampp\htdocs\Tubes_Dasildat` jika menggunakan XAMPP).
2. Buka aplikasi XAMPP/WAMP/Laragon dan nyalakan *service* **Apache**.
3. Buka browser dan akses alamat berikut:
   ```
   http://localhost/Tubes_Dasildat/web/
   ```
   *(Catatan: Sesuaikan URL jika nama folder atau struktur server Anda berbeda).*

## Fitur Utama
- **Prediksi Akurat:** Menjalankan model klasifikasi berbasis scikit-learn langsung di latar belakang melalui PHP.
- **Dynamic Path Allocation:** Integrasi mulus antara PHP dan Python lintas sistem operasi tanpa perlu mengatur ulang path pada script PHP.
- **Batch Processing:** Kemampuan memprediksi lebih dari satu data dalam sekali proses.
