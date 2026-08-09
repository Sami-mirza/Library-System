<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'Config/DB.php';

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM borrowings WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        header("Location: show_data.php?deleted=1");
        exit();
    } catch(PDOException $e) {
        // error handled silently
    }
}
require_once 'header.php'; 

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM borrowings WHERE user_id = ? AND (book_name LIKE ? OR borrower_name LIKE ? OR class_name LIKE ? OR phone LIKE ?) ORDER BY borrow_date DESC");
    $searchTerm = "%$search%";
    $stmt->execute([$_SESSION['user_id'], $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM borrowings WHERE user_id = ? ORDER BY borrow_date DESC");
    $stmt->execute([$_SESSION['user_id']]);
}

$borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count stats
$total = count($borrowings);
$active = 0;
$overdue = 0;

foreach ($borrowings as $b) {
    if (strtotime($b['return_date']) < strtotime(date('Y-m-d'))) {
        $overdue++;
    } else {
        $active++;
    }
}
?>

<!-- PAGE-SPECIFIC STYLES -->
<style>
    .container { 
        max-width: 1200px; 
        margin: 30px auto; 
        padding: 0 20px; 
    }
    
    h2 { 
        color: var(--text); 
        margin-bottom: 20px; 
    }
    
    /* Stats Cards */
    .stats {
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px; 
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: var(--card); 
        padding: 25px; 
        border-radius: 10px;
        box-shadow: var(--shadow); 
        text-align: center;
    }
    
    .stat-card h3 { 
        font-size: 32px; 
        margin-bottom: 5px; 
    }
    
    .stat-card p { 
        color: var(--text-secondary); 
    }
    
    .total h3 { 
        color: #2196F3; 
    }
    
    .active h3 { 
        color: #4CAF50; 
    }
    
    .overdue h3 { 
        color: #f44336; 
    }
    
    /* Search Bar */
    .search-box {
        background: var(--card); 
        padding: 20px; 
        border-radius: 10px;
        box-shadow: var(--shadow); 
        margin-bottom: 20px;
        display: flex; 
        gap: 10px;
    }
    
    .search-box input {
        flex: 1; 
        padding: 12px; 
        border: 2px solid #ddd;
        border-radius: 8px; 
        font-size: 15px; 
        outline: none;
        background: var(--bg);
        color: var(--text);
    }
    
    .search-box input:focus { 
        border-color: #2196F3; 
    }
    
    .search-box button {
        padding: 12px 25px; 
        background: #2196F3; 
        color: white;
        border: none; 
        border-radius: 8px; 
        cursor: pointer; 
        font-size: 15px;
    }
    
    .search-box button:hover { 
        background: #1976D2; 
    }
    
    .clear-btn {
        background: #757575 !important;
    }
    
    /* Table */
    .table-box {
        background: var(--card); 
        padding: 25px; 
        border-radius: 10px;
        box-shadow: var(--shadow);
    }
    
    table {
        width: 100%; 
        border-collapse: collapse;
    }
    
    th, td { 
        padding: 14px; 
        text-align: left; 
        border-bottom: 1px solid #eee; 
        color: var(--text);
    }
    
    th { 
        background: #2196F3; 
        color: white; 
        font-weight: 600; 
    }
    
    tr:hover { 
        background: var(--bg); 
    }
    
    .status {
        padding: 5px 12px; 
        border-radius: 20px; 
        font-size: 12px; 
        font-weight: bold;
    }
    
    .active-status { 
        background: #d4edda; 
        color: #155724; 
    }
    
    .overdue-status { 
        background: #f8d7da; 
        color: #721c24; 
    }
    
    .action-btns {
        display: flex; 
        gap: 5px;
    }
    
    .edit-btn {
        background: #2196F3; 
        color: white; 
        padding: 6px 12px;
        text-decoration: none; 
        border-radius: 4px; 
        font-size: 12px;
    }
    
    .edit-btn:hover { 
        background: #1976D2; 
    }
    
    .delete-btn {
        background: #f44336; 
        color: white; 
        padding: 6px 12px;
        text-decoration: none; 
        border-radius: 4px; 
        font-size: 12px;
    }
    
    .delete-btn:hover { 
        background: #d32f2f; 
    }
    
    .no-data { 
        text-align: center; 
        padding: 50px; 
        color: var(--text-secondary); 
    }
    
    .no-data h3 { 
        margin-bottom: 10px; 
        color: var(--text); 
    }
    
    .result-count {
        color: var(--text-secondary); 
        margin-bottom: 15px; 
        font-size: 14px;
    }
    
    .success-msg {
        background: var(--success-bg); 
        color: var(--success-text); 
        padding: 12px;
        border-radius: 8px; 
        margin-bottom: 15px;
    }

    /* Responsive table */
    .table-wrap {
        overflow-x: auto;
    }

    /* ========== RESPONSIVE STYLES ========== */
    /* Tablets */
    @media (max-width: 768px) {
        .container { margin: 20px auto; padding: 0 15px; }
        h2 { font-size: 22px; margin-bottom: 18px; }
        .stats { gap: 15px; margin-bottom: 25px; }
        .stat-card { padding: 20px; }
        .stat-card h3 { font-size: 28px; }
        .search-box { padding: 15px; }
        .search-box input { padding: 11px; font-size: 15px; }
        .search-box button { padding: 11px 20px; font-size: 14px; }
        .table-box { padding: 20px; }
        th, td { padding: 11px; font-size: 14px; }
        .status { padding: 4px 10px; font-size: 11px; }
        .edit-btn, .delete-btn { padding: 6px 10px; font-size: 11px; }
        .no-data { padding: 40px 20px; }
        .navbar { padding: 15px 20px; }
        .navbar h1 { font-size: 22px; }
        .nav-right { gap: 12px; font-size: 14px; }
    }

    /* Mobile phones */
    @media (max-width: 480px) {
        .container { margin: 15px auto; padding: 0 12px; }
        h2 { font-size: 20px; margin-bottom: 16px; }
        .stats { grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
        .stat-card { padding: 15px 10px; border-radius: 8px; }
        .stat-card h3 { font-size: 22px; }
        .stat-card p { font-size: 12px; }
        .search-box { padding: 12px; flex-direction: column; gap: 8px; }
        .search-box form { flex-direction: column; gap: 8px !important; }
        .search-box input { width: 100%; padding: 12px; font-size: 16px; }
        .search-box button { width: 100%; padding: 12px; font-size: 16px; }
        .table-box { padding: 15px 12px; border-radius: 12px; }
        th, td { padding: 10px 8px; font-size: 13px; white-space: nowrap; }
        .status { padding: 3px 8px; font-size: 10px; }
        .action-btns { flex-direction: column; gap: 4px; }
        .edit-btn, .delete-btn { padding: 6px 8px; font-size: 11px; text-align: center; }
        .no-data { padding: 30px 15px; }
        .no-data h3 { font-size: 18px; }
        .result-count { font-size: 13px; margin-bottom: 12px; }
        .success-msg { padding: 10px; font-size: 14px; }
        .navbar { padding: 12px 15px; flex-wrap: wrap; gap: 10px; }
        .navbar h1 { font-size: 18px; }
        .nav-right { flex-wrap: wrap; gap: 8px; font-size: 13px; width: 100%; justify-content: flex-end; }
    }
</style>

<!-- NAVBAR WITH DARK MODE TOGGLE -->
<div class="navbar">
    <h1>📊 All Borrowing Data</h1>
    <div class="nav-right">
        <button class="theme-toggle" onclick="toggleTheme()">
            <span id="theme-icon">🌙</span>
            <span id="theme-text">Dark</span>
        </button>
        <span>Welcome, <?php echo $_SESSION['school_name']; ?>!</span>
        <a href="Dashboard.php">← Back to Dashboard</a>
    </div>
</div>
<script>updateToggleButton('<?php echo $darkMode ? 'dark' : 'light'; ?>');</script>

<div class="container">
    
    <!-- Stats -->
    <div class="stats">
        <div class="stat-card total">
            <h3><?php echo $total; ?></h3>
            <p>Total Borrowings</p>
        </div>
        <div class="stat-card active">
            <h3><?php echo $active; ?></h3>
            <p>Active</p>
        </div>
        <div class="stat-card overdue">
            <h3><?php echo $overdue; ?></h3>
            <p>Overdue</p>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-box">
        <form method="GET" style="display: flex; gap: 10px; width: 100%;">
            <input 
                type="text" 
                name="search" 
                placeholder="🔍 Search by book, borrower, class, or phone..." 
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <button type="submit">Search</button>
            <?php if ($search): ?>
                <a href="show_data.php" style="text-decoration: none;">
                    <button type="button" class="clear-btn">Clear</button>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="table-box">
        <h2>📋 Borrowing Records</h2>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="success-msg">✅ Record deleted successfully!</div>
        <?php endif; ?>
        
        <p class="result-count">
            <?php if ($search): ?>
                Showing results for "<?php echo htmlspecialchars($search); ?>" (<?php echo $total; ?> found)
            <?php else: ?>
                Showing all records
            <?php endif; ?>
        </p>
        
        <?php if (count($borrowings) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Book Name</th>
                            <th>Borrower</th>
                            <th>Class</th>
                            <th>Phone</th>
                            <th>Borrow Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($borrowings as $row): 
                            $is_overdue = strtotime($row['return_date']) < strtotime(date('Y-m-d'));
                        ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($row['book_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['borrower_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['class_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['borrow_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['return_date'])); ?></td>
                                <td>
                                    <?php if ($is_overdue): ?>
                                        <span class="status overdue-status">⚠️ Overdue</span>
                                    <?php else: ?>
                                        <span class="status active-status">✅ Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="edit_borrowing.php?id=<?php echo $row['id']; ?>" class="edit-btn">✏️ Edit</a>
                                        <a href="?delete=<?php echo $row['id']; ?>" 
                                           class="delete-btn" 
                                           onclick="return confirm('Delete this record? Book returned?')">
                                           🗑️ Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="no-data">
                <h3>📭 No Data Found</h3>
                <p><?php echo $search ? 'No matches for your search.' : 'No borrowing records yet. Go to "Add New Data" to create one.'; ?></p>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>