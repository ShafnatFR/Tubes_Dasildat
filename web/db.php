<?php
/**
 * Koneksi database MySQL — konfigurasi default XAMPP.
 */
$conn = new mysqli('localhost', 'root', '', 'diabetes_app');

if ($conn->connect_error) {
    die('Koneksi database tidak berhasil. Silakan periksa konfigurasi XAMPP dan pastikan database diabetes_app tersedia.');
}

$conn->set_charset('utf8mb4');
