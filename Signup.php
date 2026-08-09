<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'Config/DB.php';

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $school_name = $_POST['school_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (school_name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$school_name, $email, $password]);
        $message = "Account created! <a href='login.php'>Login here</a>";
    } catch(PDOException $e) {
        $message = " Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Signup - Library System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 350px; }
        h2 { text-align: center; color: #333; }
        input { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
        .msg { text-align: center; margin-top: 15px; }
        .msg a { color: #4CAF50; }

        /* Tablets */
        @media (max-width: 768px) {
            .box { width: 85%; padding: 30px; }
            h2 { font-size: 22px; }
            input { padding: 14px; font-size: 16px; }
            button { padding: 14px; font-size: 17px; }
        }

        /* Mobile phones */
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
        <h2> School Signup</h2>
        <form method="POST">
            <input type="text" name="school_name" placeholder="School Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Create Account</button>
        </form>
        <div class="msg"><?php echo $message; ?></div>
        <p style="text-align:center; margin-top:15px;">Already have account? <a href="login.php">Login</a></p>
    </div>
</body>
</html>