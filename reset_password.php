<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Config/DB.php';

$message = "";
$validToken = false;
$email = "";

// Check token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($reset) {
        $validToken = true;
        $email = $reset['email'];
    } else {
        $message = "❌ Invalid or expired token.";
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $validToken) {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    if ($password !== $confirm) {
        $message = "❌ Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $message = "❌ Password must be at least 6 characters.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashed, $email]);
        
        // Delete used token
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);
        
        $message = "✅ Password updated! <a href='login.php'>Login now</a>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - Library System</title>
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
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        button:hover { background: #45a049; }
        .msg {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
        }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
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
        <?php if ($validToken): ?>
            <h2>🔑 Reset Password</h2>
            <p>Enter your new password for <?php echo htmlspecialchars($email); ?></p>
            
            <?php if ($message): ?>
                <div class="msg <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="password" name="password" placeholder="New password" required>
                <input type="password" name="confirm_password" placeholder="Confirm password" required>
                <button type="submit">Update Password</button>
            </form>
            
        <?php else: ?>
            <h2>❌ Invalid Link</h2>
            <p>This password reset link is invalid or has expired.</p>
            <a href="forgot_password.php" class="back">Request new link</a>
        <?php endif; ?>
        
        <a href="login.php" class="back">← Back to Login</a>
    </div>
</body>
</html>