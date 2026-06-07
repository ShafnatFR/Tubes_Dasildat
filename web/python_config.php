<?php
/**
 * python_config.php
 * Deteksi path Python secara otomatis — cross-platform, tanpa hardcoded path.
 *
 * Include file ini di semua halaman PHP yang butuh memanggil Python:
 *   require_once __DIR__ . '/python_config.php';
 * Kemudian gunakan variabel $pythonExe untuk menjalankan Python.
 */

function findPython(): string
{
    // ── Langkah 1: Tanya OS di mana Python berada ──────────────────────────
    // Windows: where.exe python / where.exe python3
    // Linux/Mac: which python3 / which python
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

    if ($isWindows) {
        // Coba Python Launcher ('py') terlebih dahulu karena lebih stabil
        $out = shell_exec('py --version 2>nul');
        if ($out && stripos($out, 'Python') !== false) {
            return 'py';
        }

        // `where` mengembalikan semua path yang ditemukan, ambil baris pertama
        $found = shell_exec('where.exe python.exe 2>nul');
        if (!$found) {
            $found = shell_exec('where.exe python3.exe 2>nul');
        }
    } else {
        $found = shell_exec('which python3 2>/dev/null');
        if (!$found) {
            $found = shell_exec('which python 2>/dev/null');
        }
    }

    if ($found) {
        // Ambil baris pertama saja (mungkin ada beberapa hasil)
        $lines = array_filter(array_map('trim', explode("\n", $found)));
        $path  = reset($lines);
        if ($path && file_exists($path)) {
            return $path;
        }
    }

    // ── Langkah 2: Fallback — coba jalankan langsung ───────────────────────
    // Kalau PATH sudah benar di environment Apache/XAMPP ini sudah cukup
    foreach (['python', 'python3'] as $cmd) {
        $out = shell_exec('"' . $cmd . '" --version 2>&1');
        if ($out && stripos($out, 'python') !== false) {
            return $cmd;
        }
    }

    // ── Langkah 3: Scan lokasi instalasi standar Python di Windows ─────────
    // Hanya dijalankan jika langkah 1 & 2 gagal (misal PATH tidak di-set Apache)
    if ($isWindows) {
        $localAppData = getenv('LOCALAPPDATA') ?: 'C:/Users/Default/AppData/Local';
        $programFiles = [
            getenv('ProgramFiles')       ?: 'C:/Program Files',
            getenv('ProgramFiles(x86)')  ?: 'C:/Program Files (x86)',
            $localAppData . '/Programs',
        ];

        $pyVersions = ['Python313', 'Python312', 'Python311', 'Python310', 'Python39', 'Python38'];

        foreach ($programFiles as $base) {
            foreach ($pyVersions as $ver) {
                $candidate = str_replace('\\', '/', $base) . '/' . $ver . '/python.exe';
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
        }
    }

    // Tidak ditemukan — kembalikan 'python' dan biarkan gagal dengan pesan jelas
    return 'python';
}

// Deteksi sekali, simpan di variabel global
$pythonExe = findPython();
