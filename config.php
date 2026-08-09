<?php
function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip comments and empty lines
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Split on first = only
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        
        // Remove surrounding quotes (single or double)
        if ((strlen($value) >= 2) && (
            (substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")
        )) {
            $value = substr($value, 1, -1);
        }
        
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
}

loadEnv();
?>