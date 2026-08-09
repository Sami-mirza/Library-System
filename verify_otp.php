<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Config/DB.php';
require_once 'config.php';

// Auto-detect dashboard filename
$dashboard_file = file_exists('Dashboard.php') ? 'Dashboard.php' : (file_exists('dashboard.php') ? 'dashboard.php' : 'Dashboard.php');

$resendApiKey = getenv('RESEND_API_KEY') ?: ($_ENV['RESEND_API_KEY'] ?? '');

$admin_id = $_SESSION['pending_user_id'] ?? null;
$admin_email = $_SESSION['pending_user_email'] ?? null;

if (!$admin_id) {
    header('Location: login.php');
    exit;
}

// Fallback: get email from DB if not in session
if (empty($admin_email)) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $admin_email = $user['email'] ?? '';
    $_SESSION['pending_user_email'] = $admin_email;
}

$now = time();

// Rate limiting for wrong OTP attempts
if (isset($_SESSION['otp_locked_until']) && $_SESSION['otp_locked_until'] > $now) {
    $wait = $_SESSION['otp_locked_until'] - $now;
    die("<p style='text-align:center; margin-top:50px; font-family:Arial;'>Too many failed attempts. Wait <strong>$wait</strong> seconds.</p>");
}

$message = "";
$messageType = "";

// ========== RESEND CODE ==========
if (isset($_POST['resend'])) {
    if (isset($_SESSION['last_otp_sent']) && ($now - $_SESSION['last_otp_sent']) < 60) {
        $wait = 60 - ($now - $_SESSION['last_otp_sent']);
        $message = "Please wait $wait seconds before requesting a new code.";
        $messageType = "error";
    } else {
        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $stmt = $pdo->prepare("DELETE FROM login_otp WHERE user_id = ?");
        $stmt->execute([$admin_id]);

        $stmt = $pdo->prepare("INSERT INTO login_otp (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$admin_id, $otp, $expires]);

        if (!empty($resendApiKey)) {
            $apiUrl = 'https://api.resend.com/emails';
            $emailHtml = '<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 500px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #2196F3;">Login Verification Code</h2>
        <p>Your new one-time login code for Library System is:</p>
        <p style="font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #2196F3; text-align: center; padding: 20px; background: #f0f2f5; border-radius: 8px;">' . $otp . '</p>
        <p>This code expires in <strong>10 minutes</strong>.</p>
        <p style="color: #666; font-size: 13px;">If you did not request this, please ignore this email.</p>
    </div>
</body>
</html>';

            $emailData = [
                'from' => 'Library System <onboarding@resend.dev>',
                'to' => [$admin_email],
                'subject' => 'Your New Login Code - Library System',
                'html' => $emailHtml,
                'text' => "Your new login code is: " . $otp . "\n\nThis code expires in 10 minutes."
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $resendApiKey,
                'Content-Type: application/json'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200 || $httpCode == 202) {
                $_SESSION['last_otp_sent'] = $now;
                $message = "✅ New code sent! Check your inbox.";
                $messageType = "success";
            } else {
                $message = "❌ Failed to send email. Please try again.";
                $messageType = "error";
            }
        } else {
            $message = "⚠️ Email service not configured.";
            $messageType = "error";
        }
    }
}

// ========== VERIFY CODE ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend'])) {
    $code = preg_replace('/\D/', '', $_POST['code'] ?? '');

    if (empty($code)) {
        $message = "Please enter the code.";
        $messageType = "error";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM login_otp WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$admin_id]);
        $otpRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$otpRecord) {
            $message = "No OTP found. Please login again.";
            $messageType = "error";
        } elseif (strtotime($otpRecord['expires_at']) < time()) {
            $message = "Code expired. Please request a new code.";
            $messageType = "error";
        } elseif ($otpRecord['otp_code'] === $code) {
            $_SESSION['user_id'] = $admin_id;
            $_SESSION['school_name'] = $_SESSION['pending_school_name'] ?? '';
            
            unset($_SESSION['pending_user_id'], $_SESSION['pending_user_email'], $_SESSION['pending_school_name'], $_SESSION['otp_attempts'], $_SESSION['otp_locked_until'], $_SESSION['last_otp_sent']);
            
            $stmt = $pdo->prepare("DELETE FROM login_otp WHERE user_id = ?");
            $stmt->execute([$admin_id]);
            
            header('Location: ' . $dashboard_file);
            exit;
        } else {
            $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
            if ($_SESSION['otp_attempts'] >= 5) {
                $_SESSION['otp_locked_until'] = $now + 300;
                die("<p style='text-align:center; margin-top:50px; font-family:Arial;'>Too many failed attempts. Locked for <strong>5 minutes</strong>. <a href='login.php'>Back to Login</a></p>");
            }
            $remaining = 5 - $_SESSION['otp_attempts'];
            $message = "Invalid code. $remaining attempts remaining.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify Login - Library System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 100%; max-width: 350px; text-align: center; }
        h2 { color: #333; margin-bottom: 10px; }
        p { color: #666; font-size: 14px; margin-bottom: 20px; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; text-align: center; font-size: 18px; letter-spacing: 5px; }
        button { width: 100%; padding: 12px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
        .msg { margin: 15px 0; padding: 10px; border-radius: 5px; font-size: 14px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .resend-btn { background: transparent; color: #2196F3; border: none; cursor: pointer; font-size: 14px; text-decoration: underline; margin-top: 15px; }
        .resend-btn:hover { color: #1976D2; }
        .resend-btn:disabled { color: #999; cursor: not-allowed; text-decoration: none; }
        .back { display: block; margin-top: 20px; color: #666; text-decoration: none; font-size: 14px; }
        .back:hover { color: #333; }

        @media (max-width: 480px) {
            .box { padding: 30px 20px; }
            h2 { font-size: 20px; }
            input { padding: 14px; font-size: 18px; }
            button { padding: 14px; font-size: 17px; }
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔐 Verify Login</h2>
        <p>We sent a 6-digit code to<br><strong><?php echo htmlspecialchars($admin_email); ?></strong></p>

        <?php if ($message): ?>
            <div class="msg <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="code" placeholder="000000" maxlength="6" required autofocus>
            <button type="submit">Verify</button>
        </form>

        <form method="POST" style="margin-top: 10px;">
            <button type="submit" name="resend" class="resend-btn" value="1">Didn't receive it? Resend Code</button>
        </form>

        <a href="login.php" class="back">← Back to Login</a>
    </div>
</body>
</html>