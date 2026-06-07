@echo off
echo ==============================================
echo Setup Environment Python - Prediksi Diabetes
echo ==============================================

set PYTHON_CMD=python
py --version >nul 2>nul
if %errorlevel% equ 0 (
    set PYTHON_CMD=py
)

echo [1/3] Mengecek instalasi Python... Menggunakan: %PYTHON_CMD%
%PYTHON_CMD% --version

echo [2/3] Memperbarui pip...
%PYTHON_CMD% -m pip install --upgrade pip

echo [3/3] Menginstall library yang dibutuhkan...
%PYTHON_CMD% -m pip install -r requirements.txt

echo ==============================================
echo Setup Selesai! Library Python sudah terinstall.
echo Silakan nyalakan Apache/XAMPP dan buka aplikasi di browser.
echo ==============================================
pause
