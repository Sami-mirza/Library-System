<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'Config/DB.php';
require_once 'header.php';

// Fetch profile photo
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$profilePhoto = !empty($user['profile_photo']) ? 'uploads/' . $user['profile_photo'] : '';
$hasPhoto = !empty($profilePhoto) && file_exists($profilePhoto);
?>

<title>Dashboard - Library System</title>
<style>
    .container {
        max-width: 1000px;
        margin: 50px auto;
        padding: 0 20px;
    }
    
    .welcome {
        text-align: center;
        margin-bottom: 40px;
    }
    
    .welcome h2 { color: var(--text); margin-bottom: 10px; }
    .welcome p { color: var(--text-secondary); }
    
    .buttons {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
    }
    
    .card {
        background: var(--card);
        padding: 40px;
        border-radius: 15px;
        text-align: center;
        box-shadow: var(--shadow);
        transition: transform 0.3s;
        cursor: pointer;
        text-decoration: none;
        color: var(--text);
    }
    
    .card:hover { transform: translateY(-5px); }
    
    .card .icon { font-size: 50px; margin-bottom: 15px; }
    .card h3 { margin-bottom: 10px; color: var(--text); }
    .card p { color: var(--text-secondary); font-size: 14px; }
    
    .add { border-top: 5px solid var(--accent); }
    .view { border-top: 5px solid var(--accent-blue); }
    .ai { border-top: 5px solid var(--accent-purple); }
    .inventory { border-top: 5px solid #ff9800; }
    .settings { border-top: 5px solid #9c27b0; }

    /* Navbar profile photo */
    .nav-profile {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid white;
        vertical-align: middle;
    }
    
    .nav-user {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Tablets */
    @media (max-width: 768px) {
        .container { margin: 30px auto; padding: 0 15px; }
        .welcome { margin-bottom: 30px; }
        .welcome h2 { font-size: 26px; }
        .buttons { gap: 20px; }
        .card { padding: 30px; }
        .card .icon { font-size: 42px; }
        .navbar { padding: 15px 20px; }
        .nav-right { gap: 12px; font-size: 14px; }
    }

    /* Mobile phones */
    @media (max-width: 480px) {
        .container { margin: 20px auto; padding: 0 12px; }
        .welcome { margin-bottom: 25px; }
        .welcome h2 { font-size: 22px; }
        .welcome p { font-size: 14px; }
        .buttons { grid-template-columns: 1fr; gap: 15px; }
        .card { padding: 25px 20px; border-radius: 12px; }
        .card .icon { font-size: 36px; margin-bottom: 10px; }
        .card h3 { font-size: 18px; margin-bottom: 8px; }
        .card p { font-size: 13px; }
        .navbar { padding: 12px 15px; flex-wrap: wrap; gap: 10px; }
        .navbar h1 { font-size: 20px; }
        .nav-right { flex-wrap: wrap; gap: 8px; font-size: 13px; }
        .nav-profile { width: 30px; height: 30px; }
    }
</style>

<div class="navbar">
    <h1>Library System</h1>
    <div class="nav-right">
        <button class="theme-toggle" onclick="toggleTheme()">
            <span id="theme-icon">🌙</span>
            <span id="theme-text">Dark</span>
        </button>
        <a href="settings.php" style="text-decoration:none; color:inherit;">
            <div class="nav-user">
                <?php if ($hasPhoto): ?>
                    <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile" class="nav-profile">
                <?php else: ?>
                    <div class="nav-profile" style="background:#666; display:flex; align-items:center; justify-content:center; font-size:16px; color:white;">👤</div>
                <?php endif; ?>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['school_name']); ?>!</span>
            </div>
        </a>
        <a href="settings.php">⚙️ Settings</a>
        <a href="logout.php">Logout</a>
    </div>
</div>
<script>updateToggleButton('<?php echo $darkMode ? 'dark' : 'light'; ?>');</script>

<div class="container">
    <div class="welcome">
        <h2>School Dashboard</h2>
        <p>Manage your library borrowing records</p>
    </div>

    <div class="buttons">
        <a href="add_borrowing.php" class="card add">
            <div class="icon">➕</div>
            <h3>Add New Data</h3>
            <p>Add borrowed books, borrower name, dates. Delete when returned.</p>
        </a>

        <a href="show_data.php" class="card view">
            <div class="icon">📊</div>
            <h3>Show Data</h3>
            <p>View all your borrowing records in a table format.</p>
        </a>

        <a href="ask_ai.php" class="card ai">
            <div class="icon">🤖</div>
            <h3>Ask AI</h3>
            <p>Ask questions about your library data using AI.</p>
        </a>

        <a href="books.php" class="card inventory">
            <div class="icon">📚</div>
            <h3>Book Inventory</h3>
            <p>Add books, set copy counts, and monitor borrowed versus available stock.</p>
        </a>

        <a href="settings.php" class="card settings">
            <div class="icon">⚙️</div>
            <h3>Settings</h3>
            <p>Edit profile, change password, upload photo, and manage your account.</p>
        </a>
    </div>
</div>

</body>
</html>