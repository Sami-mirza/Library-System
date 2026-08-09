<?php
echo "<h2>🔍 PHPMailer Finder</h2>";
echo "<p>Checking common PHPMailer locations...</p>";

$paths = [
    'vendor/autoload.php',
    'vendor/composer/autoload_real.php',
    'vendor/phpmailer/phpmailer/src/PHPMailer.php',
    'vendor/phpmailer/src/PHPMailer.php',
    'vendor/PHPMailer/src/PHPMailer.php',
    'PHPMailer/src/PHPMailer.php',
    'phpmailer/src/PHPMailer.php',
    'PHPMailer.php',
];

$found = false;
foreach ($paths as $path) {
    $fullPath = __DIR__ . '/' . $path;
    if (file_exists($fullPath)) {
        echo "✅ FOUND: $path<br>";
        $found = true;

        // If it's the autoload, try to load it
        if ($path === 'vendor/autoload.php') {
            require_once $fullPath;
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                echo "   → Class loaded successfully via Composer!<br>";
            } else {
                echo "   → Autoload exists but class NOT found<br>";
            }
        }

        // If it's the PHPMailer file itself
        if (strpos($path, 'PHPMailer.php') !== false) {
            $dir = dirname($fullPath);
            if (file_exists($dir . '/Exception.php') && file_exists($dir . '/SMTP.php')) {
                echo "   → All 3 files (Exception, PHPMailer, SMTP) found in same folder!<br>";

                // Try loading manually
                require_once $dir . '/Exception.php';
                require_once $dir . '/PHPMailer.php';
                require_once $dir . '/SMTP.php';

                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    echo "   → Class loaded successfully manually!<br>";
                } else {
                    echo "   → Files loaded but class NOT found (namespace issue?)<br>";
                }
            }
        }
    } else {
        echo "❌ NOT FOUND: $path<br>";
    }
}

if (!$found) {
    echo "<hr><p style='color:red'><strong>PHPMailer not found anywhere!</strong></p>";
    echo "<p>You need to upload the PHPMailer files. Options:</p>";
    echo "<ol>";
    echo "<li>Upload the <code>vendor/</code> folder from your local machine</li>";
    echo "<li>Or download PHPMailer directly to your server:</li>";
    echo "<pre>cd " . __DIR__ . "
mkdir -p vendor/phpmailer
wget https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.tar.gz
tar -xzf v6.9.1.tar.gz
mv PHPMailer-6.9.1/src vendor/phpmailer/
rm -rf PHPMailer-6.9.1 v6.9.1.tar.gz</pre>";
    echo "</ol>";
}

echo "<hr><p><strong>Current directory:</strong> " . __DIR__ . "</p>";
echo "<p><a href='forgot_password.php'>← Back to Forgot Password</a></p>";
?>