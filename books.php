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

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') $message = "Book inventory updated successfully.";
    if ($_GET['msg'] === 'deleted') $message = "Book deleted successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    $bookName = trim($_POST['book_name'] ?? '');
    $totalCopies = (int) ($_POST['total_copies'] ?? 0);
    $location = trim($_POST['location'] ?? '');

    if ($bookName === '' || $totalCopies < 1) {
        $message = "Please enter a book name and at least 1 copy.";
        $messageClass = "error";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO books (user_id, book_name, total_copies, location) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $bookName, $totalCopies, $location]);
            $message = "Book added successfully.";
        } catch (PDOException $e) {
            $message = $e->getCode() === '23000' ? "That book already exists." : "Error: " . $e->getMessage();
            $messageClass = "error";
        }
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$stmt = $pdo->prepare("
    SELECT b.*,
        COALESCE((SELECT COUNT(*) FROM borrowings bo WHERE bo.book_id = b.id AND bo.user_id = b.user_id), 0) as borrowed_count,
        (b.total_copies - COALESCE((SELECT COUNT(*) FROM borrowings bo WHERE bo.book_id = b.id AND bo.user_id = b.user_id), 0)) as available_count
    FROM books b
    WHERE b.user_id = ?
    ORDER BY b.book_name ASC
");
$stmt->execute([$userId]);
$allBooks = $stmt->fetchAll();

$books = $allBooks;
if ($search !== '') {
    $filtered = [];
    $s = strtolower($search);
    foreach ($allBooks as $book) {
        $nameMatch = strpos(strtolower($book['book_name']), $s) !== false;
        $locMatch = !empty($book['location']) && strpos(strtolower($book['location']), $s) !== false;
        if ($nameMatch || $locMatch) $filtered[] = $book;
    }
    $books = $filtered;
}

$totalTitles = count($books);
$totalCopies = $totalBorrowed = $totalAvailable = 0;
foreach ($books as $book) {
    $totalCopies += $book['total_copies'];
    $totalBorrowed += $book['borrowed_count'];
    $totalAvailable += $book['available_count'];
}
?>

<!DOCTYPE html>
<html data-theme="<?php echo $darkMode ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Inventory</title>
    <style>
        :root {
            --bg: #f5f5f5; --card: #ffffff; --text: #333333; --text-secondary: #666666;
            --shadow: 0 2px 8px rgba(0,0,0,0.1);
            --success-bg: #e8f5e9; --success-text: #2e7d32;
            --error-bg: #ffebee; --error-text: #c62828;
        }
        [data-theme="dark"] {
            --bg: #1a1a2e; --card: #16213e; --text: #e0e0e0; --text-secondary: #a0a0a0;
            --shadow: 0 2px 8px rgba(0,0,0,0.3);
            --success-bg: #1b3a1b; --success-text: #81c784;
            --error-bg: #3a1b1b; --error-text: #ef5350;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: var(--bg); color: var(--text); }
        .navbar { background: var(--card); box-shadow: var(--shadow); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .navbar h1 { font-size: 22px; margin: 0; }
        .nav-right { display: flex; align-items: center; gap: 15px; font-size: 14px; color: var(--text-secondary); }
        .nav-right a { color: #2196F3; text-decoration: none; }
        .theme-toggle { background: transparent; border: 1px solid #ddd; border-radius: 20px; padding: 6px 14px; cursor: pointer; font-size: 13px; color: var(--text); }
        [data-theme="dark"] .theme-toggle { border-color: #444; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: var(--card); border-radius: 12px; box-shadow: var(--shadow); padding: 20px; text-align: center; }
        .stat-card h3 { font-size: 28px; margin-bottom: 6px; color: var(--text); }
        .stat-card p { color: var(--text-secondary); font-size: 14px; }
        .panel { background: var(--card); border-radius: 12px; box-shadow: var(--shadow); padding: 25px; margin-bottom: 25px; }
        .panel h2 { color: var(--text); margin-bottom: 18px; font-size: 20px; }
        .grid { display: grid; grid-template-columns: 360px 1fr; gap: 25px; align-items: start; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: var(--text-secondary); font-size: 14px; }
        input { width: 100%; padding: 11px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background: var(--card); color: var(--text); box-sizing: border-box; }
        [data-theme="dark"] input { border-color: #444; }
        .btn { background: #4CAF50; color: white; border: none; border-radius: 8px; padding: 12px 18px; cursor: pointer; font-size: 15px; }
        .btn:hover { background: #45a049; }
        .search-bar { display: flex; gap: 10px; margin-bottom: 18px; }
        .search-bar input { flex: 1; }
        .search-bar .btn { padding: 11px 18px; }
        .clear-link { color: var(--text-secondary); font-size: 13px; text-decoration: none; margin-left: 8px; }
        .clear-link:hover { color: var(--text); text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 10px; box-shadow: var(--shadow); }
        th, td { padding: 14px; text-align: left; border-bottom: 1px solid #eee; color: var(--text); }
        th { background: #2196F3; color: white; }
        tr:hover { background: var(--bg); }
        .action-link { display: inline-block; padding: 6px 12px; border-radius: 5px; background: #2196F3; color: white; text-decoration: none; font-size: 12px; }
        .action-link:hover { background: #1976D2; }
        .empty-state { color: var(--text-secondary); text-align: center; padding: 30px 0 10px; }
        .result-count { font-size: 13px; color: var(--text-secondary); margin-bottom: 10px; }
        .location-tag { display: inline-block; background: #e3f2fd; color: #1565c0; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        [data-theme="dark"] .location-tag { background: #0d47a1; color: #bbdefb; }
        .msg { margin-bottom: 18px; padding: 12px 14px; border-radius: 8px; }
        .success { background: var(--success-bg); color: var(--success-text); }
        .error { background: var(--error-bg); color: var(--error-text); }

        /* ========== RESPONSIVE STYLES ========== */
        /* Tablets */
        @media (max-width: 768px) {
            .container { margin: 20px auto; padding: 0 15px; }
            .navbar { padding: 15px 20px; }
            .navbar h1 { font-size: 20px; }
            .nav-right { gap: 12px; font-size: 13px; }
            .stats { gap: 12px; margin-bottom: 20px; }
            .stat-card { padding: 18px; }
            .stat-card h3 { font-size: 24px; }
            .panel { padding: 20px; margin-bottom: 20px; }
            .panel h2 { font-size: 18px; margin-bottom: 15px; }
            .grid { gap: 20px; }
            th, td { padding: 12px; font-size: 14px; }
            .action-link { padding: 6px 10px; }
            .search-bar .btn { padding: 11px 16px; }
        }

        /* Mobile phones */
        @media (max-width: 480px) {
            .container { margin: 15px auto; padding: 0 12px; }
            .navbar { padding: 12px 15px; }
            .navbar h1 { font-size: 18px; }
            .nav-right { flex-wrap: wrap; gap: 8px; font-size: 12px; width: 100%; justify-content: flex-end; }
            .theme-toggle { padding: 5px 10px; font-size: 12px; }
            .stats { grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 18px; }
            .stat-card { padding: 15px 10px; border-radius: 10px; }
            .stat-card h3 { font-size: 22px; margin-bottom: 4px; }
            .stat-card p { font-size: 12px; }
            .panel { padding: 18px 14px; margin-bottom: 18px; border-radius: 10px; overflow-x: auto; }
            .panel h2 { font-size: 17px; margin-bottom: 14px; }
            .grid { gap: 15px; }
            .form-group { margin-bottom: 12px; }
            label { font-size: 13px; margin-bottom: 5px; }
            input { padding: 13px; font-size: 15px; }
            .btn { padding: 12px 16px; font-size: 15px; width: 100%; }
            .search-bar { flex-direction: column; gap: 8px; }
            .search-bar input { width: 100%; }
            .search-bar .btn { width: 100%; padding: 12px; }
            th, td { padding: 10px 8px; font-size: 13px; white-space: nowrap; }
            .action-link { padding: 6px 10px; font-size: 12px; }
            .location-tag { font-size: 10px; padding: 2px 6px; }
            .result-count { font-size: 12px; margin-bottom: 8px; }
            .msg { padding: 10px 12px; font-size: 14px; margin-bottom: 15px; }
            .empty-state { padding: 20px 0 5px; font-size: 14px; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>📚 Book Inventory</h1>
    <div class="nav-right">
        <button class="theme-toggle" onclick="toggleTheme()">
            <span id="theme-icon"><?php echo $darkMode ? '☀️' : '🌙'; ?></span>
            <span id="theme-text"><?php echo $darkMode ? 'Light' : 'Dark'; ?></span>
        </button>
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['school_name']); ?>!</span>
        <a href="Dashboard.php">← Back to Dashboard</a>
    </div>
</div>

<div class="container">

    <?php if ($message): ?>
        <div class="msg <?php echo $messageClass; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card"><h3><?php echo $totalTitles; ?></h3><p>Book Titles</p></div>
        <div class="stat-card"><h3><?php echo $totalCopies; ?></h3><p>Total Copies</p></div>
        <div class="stat-card"><h3><?php echo $totalBorrowed; ?></h3><p>Borrowed Now</p></div>
        <div class="stat-card"><h3><?php echo $totalAvailable; ?></h3><p>Available Now</p></div>
    </div>

    <div class="grid">
        <div class="panel">
            <h2>Add New Book</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Book Name</label>
                    <input type="text" name="book_name" required placeholder="Enter book title">
                </div>
                <div class="form-group">
                    <label>Total Copies</label>
                    <input type="number" name="total_copies" min="1" required value="1">
                </div>
                <div class="form-group">
                    <label>Location / Shelf</label>
                    <input type="text" name="location" placeholder="e.g. Shelf A-3, Room 2">
                </div>
                <button type="submit" name="add_book" class="btn">Add Book</button>
            </form>
        </div>

        <div class="panel">
            <h2>Current Inventory</h2>
            <form method="GET" class="search-bar">
                <input type="text" name="search" placeholder="Search by book name or location..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn">🔍 Search</button>
            </form>
            <?php if ($search !== ''): ?>
                <div class="result-count"><?php echo $totalTitles; ?> result(s) for "<?php echo htmlspecialchars($search); ?>"<a href="books.php" class="clear-link">Clear</a></div>
            <?php endif; ?>

            <?php if ($books): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Book Name</th>
                            <th>Location</th>
                            <th>Total</th>
                            <th>Borrowed</th>
                            <th>Available</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                                <td>
                                    <?php if (!empty($book['location'])): ?>
                                        <span class="location-tag">📍 <?php echo htmlspecialchars($book['location']); ?></span>
                                    <?php else: ?>
                                        <span style="color:#999;font-size:12px;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $book['total_copies']; ?></td>
                                <td><?php echo $book['borrowed_count']; ?></td>
                                <td><?php echo $book['available_count']; ?></td>
                                <td><a href="edit_book.php?id=<?php echo (int)$book['id']; ?>" class="action-link">Edit</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <?php echo $search !== '' ? 'No books match. <a href="books.php" class="clear-link">Clear</a>' : 'No books added yet.'; ?>
                </div>
            <?php endif; ?>
        </div>
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