<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Config/DB.php';

$message = "";

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['pending_user_id'];

// Fetch user's 2FA secret
$stmt = $pdo->prepare("SELECT twofa_secret, school_name FROM users WHERE id = ? AND twofa_enabled = 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = preg_replace('/\s+/', '', $_POST['code'] ?? '');
    
    if (verifyTOTP($user['twofa_secret'], $code)) {
        // Success - fully log them in
        $_SESSION['user_id'] = $user_id;
        $_SESSION['school_name'] = $user['school_name'];
        $_SESSION['email'] = $_SESSION['pending_user_email'];
        
        // Clear pending session
        unset($_SESSION['pending_user_id'], $_SESSION['pending_user_email'], $_SESSION['pending_school_name']);
        
        header("Location: dashboard.php");
        exit();
    } else {
        $message = "❌ Invalid code. Please try again.";
    }
}

// ==================== TOTP HELPER FUNCTIONS ====================

class Base32 {
    private static $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    
    public static function decode($data) {
        $map = self::$map;
        $data = strtoupper(str_replace('=', '', $data));
        $binary = '';
        foreach (str_split($data) as $char) {
            $pos = strpos($map, $char);
            if ($pos === false) continue;
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $decoded = '';
        foreach (str_split($binary, 8) as $segment) {
            if (strlen($segment) === 8) {
                $decoded .= chr(bindec($segment));
            }
        }
        return $decoded;
    }
}

function generateTOTP($secret, $time = null) {
    if ($time === null) $time = time();
    $secret = Base32::decode($secret);
    $time = pack('N*', 0) . pack('N*', floor($time / 30));
    $hm = hash_hmac('sha1', $time, $secret, true);
    $offset = ord($hm[19]) & 0x0F;
    $code = (
        ((ord($hm[$offset]) & 0x7F) << 24) |
        ((ord($hm[$offset + 1]) & 0xFF) << 16) |
        ((ord($hm[$offset + 2]) & 0xFF) << 8) |
        (ord($hm[$offset + 3]) & 0xFF)
    ) % 1000000;
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

function verifyTOTP($secret, $code, $window = 1) {
    $time = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (generateTOTP($secret, $time + ($i * 30)) === $code) {
            return true;
        }
    }
    return false;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Two-Factor Authentication - Library System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 350px; text-align: center; }
        h2 { color: #333; margin-bottom: 10px; }
        p { color: #666; margin-bottom: 25px; }
        input { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; text-align: center; letter-spacing: 6px; font-size: 20px; font-weight: bold; }
        button { width: 100%; padding: 12px; background: #2196F3; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        button:hover { background: #1976D2; }
        .msg { margin-top: 15px; color: red; }
        .back { margin-top: 20px; display: inline-block; color: #666; text-decoration: none; font-size: 14px; }
        .back:hover { text-decoration: underline; }
        
        @media (max-width: 480px) {
            body { padding: 15px; height: auto; min-height: 100vh; }
            .box { width: 100%; padding: 30px 20px; }
            input { font-size: 22px; padding: 14px; }
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔐 Two-Factor Auth</h2>
        <p>Enter the 6-digit code from your authenticator app.</p>
        <form method="POST">
            <input type="text" name="code" placeholder="000000" required maxlength="6" pattern="[0-9]{6}" autocomplete="off" autofocus>
            <button type="submit">Verify</button>
        </form>
        <div class="msg"><?php echo $message; ?></div>
        <a href="login.php" class="back">← Back to Login</a>
    </div>
</body>
</html>