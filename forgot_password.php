<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Config/DB.php';
require_once 'config.php';

$message = "";
$messageType = "";

$resendApiKey = getenv('RESEND_API_KEY') ?: $_ENV['RESEND_API_KEY'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expires]);

        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = rtrim(dirname($_SERVER['PHP_SELF']), '/');
        $resetLink = $protocol . "://" . $host . $path . "/reset_password.php?token=" . $token;

        if (!empty($resendApiKey)) {
            $apiUrl = 'https://api.resend.com/emails';

            // Ultra-simple HTML that works everywhere
            $emailHtml = '<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 500px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #2196F3;">Password Reset</h2>
        <p>Hello,</p>
        <p>You requested a password reset for your Library System account.</p>
        <p><strong>Click this link to reset your password:</strong></p>
        <p>
            <a href="' . $resetLink . '" style="color: #2196F3; font-size: 16px; text-decoration: underline;">
                ' . $resetLink . '
            </a>
        </p>
        <p style="color: #666; font-size: 13px;">This link expires in 15 minutes.</p>
        <p style="color: #666; font-size: 13px;">If you did not request this, please ignore this email.</p>
    </div>
</body>
</html>';

            $emailData = [
                'from' => 'Library System <onboarding@resend.dev>',
                'to' => [$email],
                'subject' => 'Password Reset - Library System',
                'html' => $emailHtml,
                'text' => "Password Reset\n\nClick this link: " . $resetLink . "\n\nThis link expires in 15 minutes."
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
                $message = "✅ Reset link sent! Check your inbox and spam folder.";
                $messageType = "success";
            } else {
                $result = json_decode($response, true);
                $errorMsg = $result['message'] ?? 'Unknown error';
                $message = "❌ Failed: " . $errorMsg;
                $messageType = "error";
            }
        } else {
            $message = "⚠️ RESEND_API_KEY not set in .env file.";
            $messageType = "warning";
        }

    } else {
        $message = "❌ Email not found in our system.";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - Library System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 400px;
            text-align: center;
        }
        h2 { color: #333; margin-bottom: 10px; }
        p { color: #666; margin-bottom: 25px; }
        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #2196F3;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover { background: #1976D2; }
        .msg {
            margin-bottom: 15px;
            padding: 12px;
            border-radius: 5px;
        }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .warning { background: #fff3cd; color: #856404; }
        .back {
            display: block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }

        /* Tablets */
        @media (max-width: 768px) {
            .box { width: 85%; padding: 35px; }
            h2 { font-size: 24px; }
            input { padding: 14px; font-size: 16px; }
            button { padding: 14px; font-size: 17px; }
        }

        /* Mobile phones */
        @media (max-width: 480px) {
            body { padding: 15px; height: auto; min-height: 100vh; }
            .box { width: 100%; padding: 28px 20px; border-radius: 12px; }
            h2 { font-size: 22px; margin-bottom: 8px; }
            p { font-size: 14px; margin-bottom: 20px; }
            input { padding: 14px; font-size: 16px; margin: 8px 0; }
            button { padding: 14px; font-size: 17px; margin-top: 8px; }
            .msg { padding: 10px; font-size: 14px; }
            .back { margin-top: 18px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>🔐 Forgot Password?</h2>
        <p>Enter your email and we'll send you a reset link.</p>

        <?php if ($message): ?>
            <div class="msg <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" placeholder="Your email address" required>
            <button type="submit">Send Reset Link</button>
        </form>

        <a href="login.php" class="back">← Back to Login</a>
    </div>
</body>
</html>