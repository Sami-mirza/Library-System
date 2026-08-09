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
require_once 'Config/inventory.php';
require_once 'header.php';

ensureInventorySchema($pdo);

$userId = $_SESSION['user_id'];
$message = "";
$messageClass = "success";
$books = fetchUserBooks($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $bookName = trim($_POST['book_name'] ?? '');
    $borrowerName = trim($_POST['borrower_name'] ?? '');
    $className = trim($_POST['class_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $borrowDate = $_POST['borrow_date'] ?? '';
    $returnDate = $_POST['return_date'] ?? '';

    $selectedBook = null;
    foreach ($books as $book) {
        if ($book['book_name'] === $bookName) {
            $selectedBook = $book;
            break;
        }
    }

    if (!$selectedBook) {
        $message = "Please select a valid book from your inventory.";
        $messageClass = "error";
    } elseif ($borrowerName === '' || $className === '' || $phone === '' || $borrowDate === '' || $returnDate === '') {
        $message = "Please fill in all borrowing details.";
        $messageClass = "error";
    } elseif ($returnDate < $borrowDate) {
        $message = "Return date cannot be earlier than the borrow date.";
        $messageClass = "error";
    } elseif ($selectedBook['available_count'] <= 0) {
        $message = "No copies of '" . htmlspecialchars($bookName) . "' are currently available.";
        $messageClass = "error";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO borrowings (user_id, book_id, book_name, borrower_name, class_name, phone, borrow_date, return_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $selectedBook['id'],
                $selectedBook['book_name'],
                $borrowerName,
                $className,
                $phone,
                $borrowDate,
                $returnDate
            ]);
            $message = "Book borrowing added successfully.";
            $books = fetchUserBooks($pdo, $userId);
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
            $messageClass = "error";
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM borrowings WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        $message = "Record deleted (book returned)!";
        $books = fetchUserBooks($pdo, $userId);
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $messageClass = "error";
    }
}

$stmt = $pdo->prepare("SELECT * FROM borrowings WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
    .form-box { background: var(--card); padding: 30px; border-radius: 10px; box-shadow: var(--shadow); margin-bottom: 30px; }
    h2 { color: var(--text); margin-bottom: 20px; }
    .form-group { margin-bottom: 18px; }
    label { display: block; margin-bottom: 6px; color: var(--text-secondary); font-weight: bold; }
    input[type="text"], input[type="date"], input[type="tel"] {
        width: 100%; padding: 11px 12px; border: 1px solid #ddd;
        border-radius: 6px; font-size: 14px; background: var(--card); color: var(--text); box-sizing: border-box;
    }
    .btn { background: #4CAF50; color: white; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; }
    .btn:hover { background: #45a049; }
    .btn:disabled { background: #ccc; cursor: not-allowed; }
    .msg { margin-bottom: 15px; padding: 12px; border-radius: 6px; }
    .success { background: var(--success-bg); color: var(--success-text); }
    .error { background: var(--error-bg); color: var(--error-text); }
    .inventory-note { background: var(--card); box-shadow: var(--shadow); border-left: 5px solid #2196F3; border-radius: 10px; padding: 16px 18px; margin-bottom: 20px; color: var(--text-secondary); }
    table { width: 100%; background: var(--card); border-radius: 10px; box-shadow: var(--shadow); border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; color: var(--text); }
    th { background: #4CAF50; color: white; }
    tr:hover { background: var(--bg); }
    .delete-btn { background: #f44336; color: white; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-size: 12px; }
    .delete-btn:hover { background: #d32f2f; }

    /* ===== BOOK SEARCH DROPDOWN ===== */
    .book-search-wrap { position: relative; }

    .book-search-input {
        width: 100%; padding: 11px 12px 11px 38px; border: 1px solid #ddd;
        border-radius: 6px; font-size: 14px; background: var(--card); color: var(--text);
        box-sizing: border-box; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23999' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: 12px center;
    }

    .book-dropdown {
        display: none; position: absolute; top: 100%; left: 0; right: 0;
        max-height: 240px; overflow-y: auto; background: var(--card);
        border: 1px solid #ddd; border-top: none; border-radius: 0 0 6px 6px;
        z-index: 100; box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    }

    .dropdown-item {
        padding: 10px 14px; cursor: pointer; border-bottom: 1px solid #f0f0f0;
        font-size: 14px; color: var(--text); display: flex; justify-content: space-between; align-items: center;
    }
    .dropdown-item:hover { background: var(--bg); }
    .dropdown-item.active { background: #e3f2fd; }
    .dropdown-item.unavailable { opacity: 0.6; }

    .dropdown-item .avail-tag {
        font-size: 11px; padding: 3px 10px; border-radius: 12px; font-weight: 600;
    }
    .dropdown-item .avail-tag.in-stock { background: #e8f5e9; color: #2e7d32; }
    .dropdown-item .avail-tag.out-stock { background: #ffebee; color: #c62828; }

    .dropdown-empty { padding: 16px; color: #999; font-size: 13px; text-align: center; }

    .book-status {
        margin-top: 6px; font-size: 13px; min-height: 18px; font-weight: 500;
    }
    .book-status.ok { color: #2e7d32; }
    .book-status.bad { color: #c62828; }
    .book-status.warn { color: #999; }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    /* ========== RESPONSIVE STYLES ========== */
    /* Tablets */
    @media (max-width: 768px) {
        .container { margin: 20px auto; padding: 0 15px; }
        .form-box { padding: 25px; margin-bottom: 25px; }
        h2 { font-size: 22px; margin-bottom: 18px; }
        .form-group { margin-bottom: 16px; }
        input[type="text"], input[type="date"], input[type="tel"] { padding: 12px; font-size: 15px; }
        .btn { width: 100%; padding: 14px; font-size: 17px; }
        .inventory-note { padding: 14px 16px; font-size: 14px; }
        th, td { padding: 10px; font-size: 14px; }
        .delete-btn { padding: 8px 14px; font-size: 13px; display: inline-block; }
        .book-dropdown { max-height: 200px; }
        .dropdown-item { padding: 12px 14px; font-size: 15px; }
        .book-search-input { padding: 12px 12px 12px 38px; font-size: 15px; }
        .navbar { padding: 15px 20px; }
        .navbar h1 { font-size: 22px; }
        .nav-right { gap: 12px; font-size: 14px; }
    }

    /* Mobile phones */
    @media (max-width: 480px) {
        .container { margin: 15px auto; padding: 0 12px; }
        .form-box { padding: 20px 16px; margin-bottom: 20px; border-radius: 12px; }
        h2 { font-size: 20px; margin-bottom: 16px; }
        .form-group { margin-bottom: 14px; }
        label { font-size: 14px; margin-bottom: 5px; }
        input[type="text"], input[type="date"], input[type="tel"] { padding: 13px; font-size: 16px; }
        .btn { padding: 14px; font-size: 17px; }
        .msg { padding: 10px; font-size: 14px; }
        .inventory-note { padding: 12px 14px; font-size: 13px; margin-bottom: 15px; }
        th, td { padding: 10px 8px; font-size: 13px; white-space: nowrap; }
        .delete-btn { padding: 7px 10px; font-size: 12px; }
        .book-dropdown { max-height: 180px; border-radius: 0 0 8px 8px; }
        .dropdown-item { padding: 12px; font-size: 14px; }
        .dropdown-item .avail-tag { font-size: 10px; padding: 2px 8px; }
        .book-status { font-size: 12px; }
        .book-search-input { padding: 13px 13px 13px 38px; font-size: 16px; }
        .navbar { padding: 12px 15px; flex-wrap: wrap; gap: 10px; }
        .navbar h1 { font-size: 18px; }
        .nav-right { flex-wrap: wrap; gap: 8px; font-size: 13px; width: 100%; justify-content: flex-end; }
    }

    @media (max-width: 600px) {
        .form-row { grid-template-columns: 1fr; }
    }
</style>

<div class="navbar">
    <h1>📚 Add Borrowing</h1>
    <div class="nav-right">
        <button class="theme-toggle" onclick="toggleTheme()">
            <span id="theme-icon">🌙</span>
            <span id="theme-text">Dark</span>
        </button>
        <span>Welcome, <?php echo $_SESSION['school_name']; ?>!</span>
        <a href="Dashboard.php">&larr; Back to Dashboard</a>
    </div>
</div>
<script>updateToggleButton('<?php echo $darkMode ? 'dark' : 'light'; ?>');</script>

<div class="container">
    <?php if (!$books): ?>
        <div class="inventory-note">
            No books are in your inventory yet. Add books first from <a href="books.php">Book Inventory</a> before creating a borrowing.
        </div>
    <?php endif; ?>

    <div class="form-box">
        <h2>➕ Add New Borrowing</h2>

        <?php if ($message): ?>
            <div class="msg <?php echo $messageClass; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" onsubmit="return validateBook();">
            <div class="form-group">
                <label>Book Name</label>
                <div class="book-search-wrap">
                    <input
                        type="text"
                        id="book-search"
                        class="book-search-input"
                        placeholder="Search book by name..."
                        autocomplete="off"
                        <?php echo !$books ? 'disabled' : ''; ?>
                    >
                    <div class="book-dropdown" id="book-dropdown"></div>
                    <input type="hidden" name="book_name" id="book-name-hidden" required>
                    <div class="book-status warn" id="book-status">Search and select a book from your inventory</div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Borrower Name</label>
                    <input type="text" name="borrower_name" required placeholder="Who is borrowing?">
                </div>
                <div class="form-group">
                    <label>Class Name</label>
                    <input type="text" name="class_name" required placeholder="e.g. 10-A, CS-301">
                </div>
            </div>

            <div class="form-group">
                <label>Phone Number (PH)</label>
                <input type="tel" name="phone" required placeholder="e.g. +60 12-345 6789" pattern="[0-9+\-\s()]*">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Borrow Date</label>
                    <input type="date" name="borrow_date" required>
                </div>
                <div class="form-group">
                    <label>Return Date</label>
                    <input type="date" name="return_date" required>
                </div>
            </div>

            <button type="submit" name="add" class="btn" <?php echo !$books ? 'disabled' : ''; ?>>Add Borrowing</button>
        </form>
    </div>

    <h2>📋 Current Borrowings</h2>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Book Name</th>
                    <th>Borrower</th>
                    <th>Class</th>
                    <th>Phone</th>
                    <th>Borrow Date</th>
                    <th>Return Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($borrowings) > 0): ?>
                    <?php foreach ($borrowings as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['book_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['borrower_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['class_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                            <td><?php echo $row['borrow_date']; ?></td>
                            <td><?php echo $row['return_date']; ?></td>
                            <td>
                                <a href="?delete=<?php echo $row['id']; ?>" class="delete-btn"
                                   onclick="return confirm('Delete this record? Book returned?')">Delete (Returned)</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; color:#999;">No borrowings yet</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const bookData = <?php echo json_encode($books); ?>;
const searchInput = document.getElementById('book-search');
const dropdown = document.getElementById('book-dropdown');
const hiddenInput = document.getElementById('book-name-hidden');
const statusDiv = document.getElementById('book-status');

function updateStatus(book) {
    if (!book) {
        statusDiv.textContent = 'Book not found in inventory';
        statusDiv.className = 'book-status bad';
        hiddenInput.value = '';
        return;
    }
    hiddenInput.value = book.book_name;
    if (book.available_count > 0) {
        statusDiv.textContent = '✓ ' + book.book_name + ' — ' + book.available_count + ' available of ' + book.total_copies;
        statusDiv.className = 'book-status ok';
    } else {
        statusDiv.textContent = '✗ ' + book.book_name + ' — No copies available (all ' + book.total_copies + ' borrowed)';
        statusDiv.className = 'book-status bad';
    }
}

function renderList(filter = '') {
    dropdown.innerHTML = '';
    const f = filter.toLowerCase().trim();
    const filtered = f === '' ? bookData : bookData.filter(b => b.book_name.toLowerCase().includes(f));

    if (filtered.length === 0) {
        dropdown.innerHTML = '<div class="dropdown-empty">No matching books in inventory</div>';
        return;
    }

    filtered.forEach(book => {
        const div = document.createElement('div');
        div.className = 'dropdown-item';
        if (book.available_count <= 0) div.classList.add('unavailable');

        const nameSpan = document.createElement('span');
        nameSpan.textContent = book.book_name;

        const tagSpan = document.createElement('span');
        tagSpan.className = 'avail-tag ' + (book.available_count > 0 ? 'in-stock' : 'out-stock');
        tagSpan.textContent = book.available_count + ' avail';

        div.appendChild(nameSpan);
        div.appendChild(tagSpan);

        div.onclick = function() {
            searchInput.value = book.book_name;
            updateStatus(book);
            dropdown.style.display = 'none';
        };
        dropdown.appendChild(div);
    });
}

// Show dropdown on focus
searchInput.addEventListener('focus', function() {
    renderList(this.value);
    dropdown.style.display = 'block';
});

// Filter on type + live check exact match
searchInput.addEventListener('input', function() {
    renderList(this.value);
    dropdown.style.display = 'block';

    const val = this.value.trim();
    const exact = bookData.find(b => b.book_name.toLowerCase() === val.toLowerCase());
    if (exact) {
        updateStatus(exact);
    } else {
        statusDiv.textContent = 'Search and select a book from your inventory';
        statusDiv.className = 'book-status warn';
        hiddenInput.value = '';
    }
});

// Click outside: close dropdown, validate what's in the box
document.addEventListener('click', function(e) {
    if (!e.target.closest('.book-search-wrap')) {
        dropdown.style.display = 'none';
        const val = searchInput.value.trim();
        const exact = bookData.find(b => b.book_name.toLowerCase() === val.toLowerCase());
        if (exact) {
            updateStatus(exact);
        } else {
            searchInput.value = '';
            hiddenInput.value = '';
            statusDiv.textContent = 'Please select a valid book from the list';
            statusDiv.className = 'book-status bad';
        }
    }
});

function validateBook() {
    const val = hiddenInput.value.trim();
    if (!val) {
        alert('Please search and select a valid book from your inventory.');
        searchInput.focus();
        return false;
    }
    const book = bookData.find(b => b.book_name === val);
    if (!book) {
        alert('Please select a valid book from your inventory.');
        return false;
    }
    if (book.available_count <= 0) {
        alert('No copies of "' + val + '" are currently available.');
        return false;
    }
    return true;
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>