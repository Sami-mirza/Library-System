<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'Config/DB.php';
$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';
$password = $_POST['password'] ?? '';

// Verify password first for both actions
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$hash = $stmt->fetch(PDO::FETCH_ASSOC)['password'];

if (!password_verify($password, $hash)) {
    $_SESSION['error'] = "Incorrect password.";
    header("Location: settings.php");
    exit();
}

// Enable Email OTP
if ($action === 'enable') {
    $stmt = $pdo->prepare("UPDATE users SET twofa_enabled = 1 WHERE id = ?");
    $stmt->execute([$user_id]);
    $_SESSION['success'] = "Email OTP enabled. You'll receive a code at login.";
    header("Location: settings.php");
    exit();
}

// Disable Email OTP
if ($action === 'disable') {
    $stmt = $pdo->prepare("UPDATE users SET twofa_enabled = 0 WHERE id = ?");
    $stmt->execute([$user_id]);
    $_SESSION['success'] = "Email OTP disabled. You will log in directly.";
    header("Location: settings.php");
    exit();
}

header("Location: settings.php");
exit();
?>