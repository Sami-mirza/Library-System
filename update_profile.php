<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'Config/DB.php';
$user_id = $_SESSION['user_id'];

// ==================== UPDATE PROFILE ====================
if (isset($_POST['update_profile'])) {
    $school_name = trim($_POST['school_name']);
    $email = trim($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
        header("Location: settings.php");
        exit();
    }
    
    // Check if email is already used by another user
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->execute([$email, $user_id]);
    if ($check->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['error'] = "This email is already linked to another account.";
        header("Location: settings.php");
        exit();
    }
    
    // Handle profile photo upload
    $photo_name = null;
    if (!empty($_FILES['profile_photo']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $file_name = $_FILES['profile_photo']['name'];
        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_size = $_FILES['profile_photo']['size'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = "Only JPG, PNG, GIF files allowed.";
            header("Location: settings.php");
            exit();
        }
        if ($file_size > 2 * 1024 * 1024) {
            $_SESSION['error'] = "File size must be under 2MB.";
            header("Location: settings.php");
            exit();
        }
        
        if (!is_dir('uploads')) mkdir('uploads', 0755, true);
        
        $photo_name = 'user_' . $user_id . '_' . time() . '.' . $ext;
        move_uploaded_file($file_tmp, 'uploads/' . $photo_name);
        
        // Delete old photo (except default)
        $old = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
        $old->execute([$user_id]);
        $old_photo = $old->fetch(PDO::FETCH_ASSOC)['profile_photo'] ?? null;
        if ($old_photo && $old_photo != 'default.png' && file_exists('uploads/' . $old_photo)) {
            unlink('uploads/' . $old_photo);
        }
    }
    
    if ($photo_name) {
        $stmt = $pdo->prepare("UPDATE users SET school_name = ?, email = ?, profile_photo = ? WHERE id = ?");
        $stmt->execute([$school_name, $email, $photo_name, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET school_name = ?, email = ? WHERE id = ?");
        $stmt->execute([$school_name, $email, $user_id]);
    }
    
    if ($stmt->rowCount() >= 0) {
        $_SESSION['school_name'] = $school_name;
        $_SESSION['success'] = "Profile updated successfully!";
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again.";
    }
    
    header("Location: settings.php");
    exit();
}

// ==================== CHANGE PASSWORD ====================
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if ($new !== $confirm) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: settings.php");
        exit();
    }
    if (strlen($new) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters.";
        header("Location: settings.php");
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $hash = $stmt->fetch(PDO::FETCH_ASSOC)['password'];
    
    if (!password_verify($current, $hash)) {
        $_SESSION['error'] = "Current password is incorrect.";
        header("Location: settings.php");
        exit();
    }
    
    $new_hash = password_hash($new, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd->execute([$new_hash, $user_id]);
    
    if ($upd->rowCount() >= 0) {
        $_SESSION['success'] = "Password changed successfully!";
    } else {
        $_SESSION['error'] = "Failed to update password.";
    }
    
    header("Location: settings.php");
    exit();
}
?>