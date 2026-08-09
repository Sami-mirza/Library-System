<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'Config/DB.php';

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT school_name, email, profile_photo, twofa_enabled FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

require_once 'header.php';

// Safe photo path - no infinite onerror loop
$photoFile = !empty($user['profile_photo']) ? 'uploads/' . $user['profile_photo'] : '';
$hasPhoto = !empty($photoFile) && file_exists($photoFile);
?>

<title>Settings - Library System</title>
<style>
    .settings-container {
        max-width: 700px;
        margin: 50px auto;
        padding: 0 20px;
    }
    
    .settings-card {
        background: var(--card);
        padding: 35px;
        border-radius: 15px;
        box-shadow: var(--shadow);
        margin-bottom: 25px;
    }
    
    .settings-card h3 {
        margin-bottom: 20px;
        color: var(--text);
        border-bottom: 2px solid var(--accent);
        padding-bottom: 10px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: var(--text);
        font-weight: 500;
    }
    
    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="password"],
    .form-group input[type="file"] {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #ddd;
        border-radius: 10px;
        background: var(--bg);
        color: var(--text);
        font-size: 15px;
        box-sizing: border-box;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: var(--accent);
    }
    
    .photo-preview {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--accent);
        margin-bottom: 15px;
        background: #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        color: #999;
        overflow: hidden;
    }
    
    .btn-save {
        background: var(--accent);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 16px;
        transition: opacity 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-save:hover { opacity: 0.9; }
    .btn-danger { background: #dc3545 !important; }
    
    .alert {
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
    }
    .alert-success { background: #d4edda; color: #155724; }
    .alert-error { background: #f8d7da; color: #721c24; }
    
    .hint {
        font-size: 13px;
        color: var(--text-secondary);
        margin-top: 5px;
    }
    
    .status-badge {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 15px;
    }
    .status-on { background: #d4edda; color: #155724; }
    .status-off { background: #f8f9fa; color: #666; border: 1px solid #ddd; }
    
    @media (max-width: 768px) {
        .settings-container { margin: 30px auto; padding: 0 15px; }
        .settings-card { padding: 30px; }
    }
    
    @media (max-width: 480px) {
        .settings-container { margin: 20px auto; padding: 0 12px; }
        .settings-card { padding: 25px 20px; }
        .photo-preview { width: 90px; height: 90px; }
    }
</style>

<div class="navbar">
    <h1>Library System</h1>
    <div class="nav-right">
        <button class="theme-toggle" onclick="toggleTheme()">
            <span id="theme-icon">🌙</span>
            <span id="theme-text">Dark</span>
        </button>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['school_name']); ?>!</span>
        <a href="Dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    </div>
</div>
<script>updateToggleButton('<?php echo $darkMode ? 'dark' : 'light'; ?>');</script>

<div class="settings-container">
    <div class="welcome" style="text-align:center; margin-bottom:30px;">
        <h2>Account Settings</h2>
        <p style="color: var(--text-secondary);">Manage your profile and security</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Profile Info -->
    <div class="settings-card">
        <h3>👤 Profile Information</h3>
        <form action="update_profile.php" method="POST" enctype="multipart/form-data">
            <div style="text-align:center;">
                <?php if ($hasPhoto): ?>
                    <img src="<?php echo htmlspecialchars($photoFile); ?>" alt="Profile" class="photo-preview">
                <?php else: ?>
                    <div class="photo-preview">👤</div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Profile Photo</label>
                <input type="file" name="profile_photo" accept="image/*">
                <div class="hint">Max 2MB. JPG, PNG, GIF only.</div>
            </div>
            <div class="form-group">
                <label>School Name</label>
                <input type="text" name="school_name" 
                       value="<?php echo htmlspecialchars($user['school_name']); ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                <div class="hint">Must be unique — not used by another account.</div>
            </div>
            <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
        </form>
    </div>

    <!-- Change Password -->
    <div class="settings-card">
        <h3>🔒 Change Password</h3>
        <form action="update_profile.php" method="POST">
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required minlength="6">
                <div class="hint">Minimum 6 characters.</div>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" name="change_password" class="btn-save">Update Password</button>
        </form>
    </div>

    <!-- Email OTP Toggle -->
    <div class="settings-card">
        <h3>📧 Login Verification (Email OTP)</h3>
        <?php if ($user['twofa_enabled']): ?>
            <span class="status-badge status-on">✅ Enabled</span>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                A 6-digit code is sent to your email every time you log in.
            </p>
            <form action="2fa_actions.php?action=disable" method="POST">
                <div class="form-group">
                    <label>Current Password (required to disable)</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn-save btn-danger">Disable Email OTP</button>
            </form>
        <?php else: ?>
            <span class="status-badge status-off">⚪ Disabled</span>
            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                You log in with just email and password. Enable this for extra security.
            </p>
            <form action="2fa_actions.php?action=enable" method="POST">
                <div class="form-group">
                    <label>Current Password (required to enable)</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn-save">Enable Email OTP</button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>