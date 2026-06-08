<?php
/**
 * config_python.php — Deteksi path Python dengan caching ke python_path.cache.
 */

define('PYTHON_CACHE_FILE', __DIR__ . '/python_path.cache');

function isPythonPathValid(string $path): bool
{
    if ($path === '') {
        return false;
    }
    $out = shell_exec(escapeshellarg($path) . ' --version 2>&1');
    if ($out && stripos($out, 'python') !== false) {
        return true;
    }
    if ($path === 'py') {
        $out = shell_exec('py --version 2>&1');
        return $out && stripos($out, 'Python') !== false;
    }
    foreach (['python', 'python3'] as $cmd) {
        if ($path === $cmd) {
            $out = shell_exec('"' . $cmd . '" --version 2>&1');
            return $out && stripos($out, 'python') !== false;
        }
    }
    return false;
}

function findPython(): string
{
    $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

    if ($isWindows) {
        $out = shell_exec('py --version 2>nul');
        if ($out && stripos($out, 'Python') !== false) {
            return 'py';
        }
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
        $lines = array_filter(array_map('trim', explode("\n", $found)));
        $path  = reset($lines);
        if ($path && file_exists($path)) {
            return $path;
        }
    }

    foreach (['python', 'python3'] as $cmd) {
        $out = shell_exec('"' . $cmd . '" --version 2>&1');
        if ($out && stripos($out, 'python') !== false) {
            return $cmd;
        }
    }

    if ($isWindows) {
        $localAppData = getenv('LOCALAPPDATA') ?: 'C:/Users/Default/AppData/Local';
        $programFiles = [
            getenv('ProgramFiles')      ?: 'C:/Program Files',
            getenv('ProgramFiles(x86)') ?: 'C:/Program Files (x86)',
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

    return 'python';
}

function loadPythonPath(): string
{
    if (file_exists(PYTHON_CACHE_FILE)) {
        $cached = trim((string) file_get_contents(PYTHON_CACHE_FILE));
        if ($cached !== '' && isPythonPathValid($cached)) {
            return $cached;
        }
    }

    $path = findPython();
    if (isPythonPathValid($path)) {
        file_put_contents(PYTHON_CACHE_FILE, $path);
    }
    return $path;
}

$pythonExe = loadPythonPath();
