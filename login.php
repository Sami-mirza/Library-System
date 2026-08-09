<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!file_exists('Config/DB.php')) die("Config/DB.php missing");
if (!file_exists('config.php')) die("config.php missing");

require_once 'Config/DB.php';
require_once 'config.php';

// Auto-detect dashboard filename (InfinityFree Linux is case-sensitive)
$dashboard_file = file_exists('Dashboard.php') ? 'Dashboard.php' : (file_exists('dashboard.php') ? 'dashboard.php' : 'Dashboard.php');

$message = "";
$resendApiKey = getenv('RESEND_API_KEY') ?: ($_ENV['RESEND_API_KEY'] ?? '');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            
            // Skip OTP if disabled (default for new users)
            if (empty($user['twofa_enabled']) || $user['twofa_enabled'] != 1) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['school_name'] = $user['school_name'];
                $_SESSION['email'] = $user['email'];
                header("Location: " . $dashboard_file);
                exit();
            }
            
            // Send Email OTP
            $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $stmt = $pdo->prepare("DELETE FROM login_otp WHERE user_id = ?");
            $stmt->execute([$user['id']]);

            $stmt = $pdo->prepare("INSERT INTO login_otp (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $otp, $expires]);

            if (!empty($resendApiKey)) {
                $apiUrl = 'https://api.resend.com/emails';
                $emailHtml = '<!DOCTYPE html>
<html><body style="font-family:Arial;line-height:1.6;color:#333;">
<div style="max-width:500px;margin:0 auto;padding:20px;">
<h2 style="color:#2196F3;">Login Verification Code</h2>
<p>Your one-time login code is:</p>
<p style="font-size:32px;font-weight:bold;letter-spacing:5px;color:#2196F3;text-align:center;padding:20px;background:#f0f2f5;border-radius:8px;">' . $otp . '</p>
<p>This code expires in <strong>10 minutes</strong>.</p>
</div></body></html>';

                $emailData = [
                    'from' => 'Library System <onboarding@resend.dev>',
                    'to' => [$email],
                    'subject' => 'Your Login Code - Library System',
                    'html' => $emailHtml,
                    'text' => "Your login code is: " . $otp . "\n\nExpires in 10 minutes."
                ];

                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $resendApiKey,
                    'Content-Type: application/json'
                ]);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode == 200 || $httpCode == 202) {
                    $_SESSION['pending_user_id'] = $user['id'];
                    $_SESSION['pending_user_email'] = $user['email'];
                    $_SESSION['pending_school_name'] = $user['school_name'];
                    header("Location: verify_otp.php");
                    exit();
                } else {
                    $message = "❌ Failed to send email. Try again.";
                }
            } else {
                $message = "⚠️ Email service not configured.";
            }
        } else {
            $message = "Invalid email or password!";
        }
    } catch(PDOException $e) {
        $message = "Database Error: " . $e->getMessage();
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Library System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 350px; }
        h2 { text-align: center; color: #333; }
        input { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #2196F3; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #1976D2; }
        .msg { text-align: center; margin-top: 15px; color: red; }

        @media (max-width: 768px) {
            .box { width: 85%; padding: 30px; }
            h2 { font-size: 22px; }
            input { padding: 14px; font-size: 16px; }
            button { padding: 14px; font-size: 17px; }
        }
        @media (max-width: 480px) {
            body { padding: 15px; height: auto; min-height: 100vh; }
            .box { width: 100%; padding: 25px 20px; border-radius: 12px; }
            h2 { font-size: 20px; margin-bottom: 20px; }
            input { padding: 14px; font-size: 16px; margin: 10px 0; }
            button { padding: 14px; font-size: 17px; margin-top: 5px; }
            .msg { font-size: 14px; }
            p { font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="box">
        <h2> School Login</h2>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
            <p style="text-align:center; margin-top:15px;">
                <a href="forgot_password.php" style="color:#2196F3; text-decoration:none;">Forgot Password?</a>
            </p>
        </form>
        <div class="msg"><?php echo $message; ?></div>
        <p style="text-align:center; margin-top:15px;">No account? <a href="Signup.php">Signup</a></p>
    </div>
</body>
</html>