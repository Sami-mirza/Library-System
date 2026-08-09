<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'Config/DB.php';

$userId = $_SESSION['user_id'];
$darkMode = isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true';
$message = "";
$messageClass = "success";

// Get book ID
$bookId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch the book
$stmt = $pdo->prepare("SELECT * FROM books WHERE id = ? AND user_id = ?");
$stmt->execute([$bookId, $userId]);
$book = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$book) {
    header("Location: books.php");
    exit();
}

// Get borrowed count to prevent reducing below it
$stmt = $pdo->prepare("SELECT COUNT(*) as borrowed FROM borrowings WHERE book_id = ? AND user_id = ?");
$stmt->execute([$bookId, $userId]);
$borrowedData = $stmt->fetch(PDO::FETCH_ASSOC);
$currentlyBorrowed = (int)$borrowedData['borrowed'];

// ---- HANDLE UPDATE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_book'])) {
    $bookName = trim($_POST['book_name'] ?? '');
    $totalCopies = (int) ($_POST['total_copies'] ?? 0);
    $location = trim($_POST['location'] ?? '');

    if ($bookName === '' || $totalCopies < 1) {
        $message = "Please enter a book name and at least 1 copy.";
        $messageClass = "error";
    } elseif ($totalCopies < $currentlyBorrowed) {
        $message = "Cannot set total copies below currently borrowed count (" . $currentlyBorrowed . ").";
        $messageClass = "error";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE books SET book_name = ?, total_copies = ?, location = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$bookName, $totalCopies, $location, $bookId, $userId]);
            $message = "Book updated successfully.";
            // Refresh data
            $book['book_name'] = $bookName;
            $book['total_copies'] = $totalCopies;
            $book['location'] = $location;
        } catch (PDOException $e) {
            $message = $e->getCode() === '23000' ? "That book name already exists." : "Error: " . $e->getMessage();
            $messageClass = "error";
        }
    }
}

// ---- HANDLE DELETE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book'])) {
    // Prevent deletion if there are active borrowings
    if ($currentlyBorrowed > 0) {
        $message = "Cannot delete this book. " . $currentlyBorrowed . " copy/copies are currently borrowed out. Return them first.";
        $messageClass = "error";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM books WHERE id = ? AND user_id = ?");
            $stmt->execute([$bookId, $userId]);
            header("Location: books.php?msg=deleted");
            exit();
        } catch (PDOException $e) {
            $message = "Error deleting book: " . $e->getMessage();
            $messageClass = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html data-theme="<?php echo $darkMode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book</title>
    <style>
        :root {
            --bg: #f5f5f5; --card: #ffffff; --text: #333333; --text-secondary: #666666;
            --shadow: 0 2px 8px rgba(0,0,0,0.1);
            --success-bg: #e8f5e9; --success-text: #2e7d32;
            --error-bg: #ffebee; --error-text: #c62828;
            --warning-bg: #fff3e0; --warning-text: #e65100;
        }
        [data-theme="dark"] {
            --bg: #1a1a2e; --card: #16213e; --text: #e0e0e0; --text-secondary: #a0a0a0;
            --shadow: 0 2px 8px rgba(0,0,0,0.3);
            --success-bg: #1b3a1b; --success-text: #81c784;
            --error-bg: #3a1b1b; --error-text: #ef5350;
            --warning-bg: #3a2a1b; --warning-text: #ffb74d;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: var(--bg); color: var(--text); }
        .navbar { background: var(--card); box-shadow: var(--shadow); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .navbar h1 { font-size: 22px; margin: 0; }
        .nav-right { display: flex; align-items: center; gap: 15px; font-size: 14px; color: var(--text-secondary); }
        .nav-right a { color: #2196F3; text-decoration: none; }
        .theme-toggle { background: transparent; border: 1px solid #ddd; border-radius: 20px; padding: 6px 14px; cursor: pointer; font-size: 13px; color: var(--text); }
        [data-theme="dark"] .theme-toggle { border-color: #444; }
        .container { max-width: 600px; margin: 30px auto; padding: 0 20px; }
        .panel { background: var(--card); border-radius: 12px; box-shadow: var(--shadow); padding: 30px; }
        .panel h2 { color: var(--text); margin-bottom: 20px; font-size: 20px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: var(--text-secondary); font-size: 14px; }
        input { width: 100%; padding: 12px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background: var(--card); color: var(--text); box-sizing: border-box; }
        [data-theme="dark"] input { border-color: #444; }
        .hint { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }
        .btn { background: #2196F3; color: white; border: none; border-radius: 8px; padding: 12px 24px; cursor: pointer; font-size: 15px; }
        .btn:hover { background: #1976D2; }
        .btn-success { background: #4CAF50; }
        .btn-success:hover { background: #45a049; }
        .btn-danger { background: #f44336; }
        .btn-danger:hover { background: #d32f2f; }
        .btn-group { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .msg { margin-bottom: 18px; padding: 12px 14px; border-radius: 8px; }
        .success { background: var(--success-bg); color: var(--success-text); }
        .error { background: var(--error-bg); color: var(--error-text); }
        .warning-box { background: var(--warning-bg); color: var(--warning-text); padding: 12px 14px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
        .stat-row { display: flex; gap: 15px; margin-bottom: 20px; }
        .stat-chip { background: var(--bg); padding: 8px 14px; border-radius: 20px; font-size: 13px; color: var(--text-secondary); }
        .stat-chip b { color: var(--text); }

        /* ========== RESPONSIVE STYLES ========== */
        /* Tablets */
        @media (max-width: 768px) {
            .container { margin: 25px auto; padding: 0 15px; }
            .panel { padding: 25px; }
            .panel h2 { font-size: 19px; margin-bottom: 18px; }
            .form-group { margin-bottom: 16px; }
            input { padding: 13px; font-size: 15px; }
            .btn { padding: 12px 20px; font-size: 15px; }
            .stat-row { gap: 12px; margin-bottom: 18px; }
            .stat-chip { padding: 7px 12px; font-size: 12px; }
            .warning-box { padding: 11px 12px; font-size: 13px; }
            .msg { padding: 11px 12px; margin-bottom: 16px; }
            .navbar { padding: 15px 20px; }
            .navbar h1 { font-size: 20px; }
            .nav-right { gap: 12px; font-size: 13px; }
        }

        /* Mobile phones */
        @media (max-width: 480px) {
            .container { margin: 15px auto; padding: 0 12px; }
            .panel { padding: 20px 16px; border-radius: 10px; }
            .panel h2 { font-size: 18px; margin-bottom: 16px; }
            .form-group { margin-bottom: 14px; }
            label { font-size: 13px; margin-bottom: 5px; }
            input { padding: 14px; font-size: 16px; }
            .hint { font-size: 11px; }
            .btn { width: 100%; padding: 14px; font-size: 16px; }
            .btn-group { flex-direction: column; gap: 8px; }
            .btn-group .btn { text-align: center; }
            .stat-row { flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
            .stat-chip { padding: 6px 10px; font-size: 12px; border-radius: 16px; }
            .warning-box { padding: 10px 12px; font-size: 13px; margin-bottom: 16px; }
            .msg { padding: 10px 12px; font-size: 14px; margin-bottom: 15px; }
            .navbar { padding: 12px 15px; }
            .navbar h1 { font-size: 18px; }
            .nav-right { flex-wrap: wrap; gap: 8px; font-size: 12px; width: 100%; justify-content: flex-end; }
            .theme-toggle { padding: 5px 10px; font-size: 12px; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>✏️ Edit Book</h1>
    <div class="nav-right">
        <button class="theme-toggle" onclick="toggleTheme()">
            <span id="theme-icon"><?php echo $darkMode ? '☀️' : '🌙'; ?></span>
            <span id="theme-text"><?php echo $darkMode ? 'Light' : 'Dark'; ?></span>
        </button>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['school_name']); ?>!</span>
        <a href="books.php">← Back to Inventory</a>
    </div>
</div>

<div class="container">
    <div class="panel">
        <h2>Edit "<?php echo htmlspecialchars($book['book_name']); ?>"</h2>

        <div class="stat-row">
            <div class="stat-chip">Currently Borrowed: <b><?php echo $currentlyBorrowed; ?></b></div>
            <div class="stat-chip">Available: <b><?php echo $book['total_copies'] - $currentlyBorrowed; ?></b></div>
        </div>

        <?php if ($message): ?>
            <div class="msg <?php echo $messageClass; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($currentlyBorrowed > 0): ?>
            <div class="warning-box">⚠️ This book has <?php echo $currentlyBorrowed; ?> active borrowing(s). You cannot delete it until all copies are returned.</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Book Name</label>
                <input type="text" name="book_name" required value="<?php echo htmlspecialchars($book['book_name']); ?>">
            </div>
            <div class="form-group">
                <label>Total Copies</label>
                <input type="number" name="total_copies" min="<?php echo $currentlyBorrowed > 0 ? $currentlyBorrowed : 1; ?>" required value="<?php echo (int)$book['total_copies']; ?>">
                <div class="hint">Must be at least <?php echo $currentlyBorrowed; ?> (currently borrowed) + available copies.</div>
            </div>
            <div class="form-group">
                <label>Location / Shelf</label>
                <input type="text" name="location" placeholder="e.g. Shelf A-3, Room 2" value="<?php echo htmlspecialchars($book['location'] ?? ''); ?>">
            </div>

            <div class="btn-group">
                <button type="submit" name="update_book" class="btn btn-success">💾 Save Changes</button>
                <a href="books.php" class="btn" style="background:#757575; text-decoration:none; display:inline-block; text-align:center;">Cancel</a>
            </div>
        </form>

        <hr style="margin: 25px 0; border: none; border-top: 1px solid #ddd; opacity: 0.5;">

        <h3 style="color: var(--text); font-size: 16px; margin-bottom: 12px;">🗑️ Danger Zone</h3>
        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this book? This cannot be undone.');">
            <button type="submit" name="delete_book" class="btn btn-danger" <?php echo $currentlyBorrowed > 0 ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>
                🗑️ Delete Book
            </button>
            <?php if ($currentlyBorrowed > 0): ?>
                <span style="color: var(--text-secondary); font-size: 13px; margin-left: 10px;">Disabled: active borrowings exist</span>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    document.cookie = 'darkMode=' + (next === 'dark') + '; path=/; max-age=31536000';
    const icon = document.getElementById('theme-icon');
    const text = document.getElementById('theme-text');
    if (next === 'dark') { icon.textContent = '☀️'; text.textContent = 'Light'; }
    else { icon.textContent = '🌙'; text.textContent = 'Dark'; }
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>