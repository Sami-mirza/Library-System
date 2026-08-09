<?php
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

$message = "";
$borrowing = null;
$messageClass = "success";
$userId = $_SESSION['user_id'];
$books = fetchUserBooks($pdo, $userId);

// Get borrowing ID from URL
if (!isset($_GET['id'])) {
    header("Location: show_data.php");
    exit();
}

$id = (int) $_GET['id'];

// Fetch the borrowing record
$stmt = $pdo->prepare("SELECT * FROM borrowings WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $userId]);
$borrowing = $stmt->fetch(PDO::FETCH_ASSOC);

// If record not found or doesn't belong to this user
if (!$borrowing) {
    header("Location: show_data.php");
    exit();
}

// Handle UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $bookId = (int) ($_POST['book_id'] ?? 0);
    $borrowerName = trim($_POST['borrower_name'] ?? '');
    $className = trim($_POST['class_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $borrowDate = $_POST['borrow_date'] ?? '';
    $returnDate = $_POST['return_date'] ?? '';
    $selectedBook = fetchUserBookById($pdo, $userId, $bookId);

    if (!$selectedBook) {
        $message = "Please select a valid book from your inventory.";
        $messageClass = "error";
    } elseif ($borrowerName === '' || $className === '' || $phone === '' || $borrowDate === '' || $returnDate === '') {
        $message = "Please fill in all borrowing details.";
        $messageClass = "error";
    } elseif ($returnDate < $borrowDate) {
        $message = "Return date cannot be earlier than the borrow date.";
        $messageClass = "error";
    } else {
        $counts = fetchBookInventoryCounts($pdo, $userId, $selectedBook['id'], $selectedBook['book_name'], $id);
        $availableCount = max(0, $selectedBook['total_copies'] - $counts['borrowed_count']);

        if ($availableCount <= 0 && ((int) $borrowing['book_id'] !== $selectedBook['id'])) {
            $message = "No copies of this book are currently available.";
            $messageClass = "error";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE borrowings
                    SET book_id = ?, book_name = ?, borrower_name = ?, class_name = ?, phone = ?, borrow_date = ?, return_date = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $selectedBook['id'],
                    $selectedBook['book_name'],
                    $borrowerName,
                    $className,
                    $phone,
                    $borrowDate,
                    $returnDate,
                    $id,
                    $userId
                ]);

                $message = "Record updated successfully.";
                $borrowing['book_id'] = $selectedBook['id'];
                $borrowing['book_name'] = $selectedBook['book_name'];
                $borrowing['borrower_name'] = $borrowerName;
                $borrowing['class_name'] = $className;
                $borrowing['phone'] = $phone;
                $borrowing['borrow_date'] = $borrowDate;
                $borrowing['return_date'] = $returnDate;
                $books = fetchUserBooks($pdo, $userId);
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageClass = "error";
            }
        }
    }
}
?>

<style>
    .container {
        max-width: 620px;
        margin: 30px auto;
        padding: 0 20px;
    }

    .box {
        background: var(--card);
        padding: 35px;
        border-radius: 15px;
        box-shadow: var(--shadow);
    }

    h2 {
        color: var(--text);
        margin-bottom: 25px;
        text-align: center;
    }

    .form-group { margin-bottom: 18px; }

    label {
        display: block;
        margin-bottom: 6px;
        color: var(--text-secondary);
        font-weight: bold;
        font-size: 14px;
    }

    input, select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 15px;
        outline: none;
        background: var(--card);
        color: var(--text);
    }

    input:focus, select:focus { border-color: #2196F3; }

    .btn-update {
        width: 100%;
        padding: 14px;
        background: #2196F3;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
    }

    .btn-update:hover { background: #1976D2; }

    .btn-cancel {
        display: block;
        text-align: center;
        margin-top: 15px;
        color: var(--text-secondary);
        text-decoration: none;
        padding: 10px;
    }

    .btn-cancel:hover { color: var(--text); }

    .msg {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
    }

    .success { background: var(--success-bg); color: var(--success-text); }
    .error { background: var(--error-bg); color: var(--error-text); }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    /* ========== RESPONSIVE STYLES ========== */
    /* Tablets */
    @media (max-width: 768px) {
        .container { margin: 25px auto; padding: 0 15px; }
        .box { padding: 30px; border-radius: 12px; }
        h2 { font-size: 24px; margin-bottom: 22px; }
        .form-group { margin-bottom: 16px; }
        input, select { padding: 13px; font-size: 15px; }
        .btn-update { padding: 14px; font-size: 17px; }
        .navbar { padding: 15px 20px; }
        .navbar h1 { font-size: 22px; }
        .nav-right { gap: 12px; font-size: 14px; }
    }

    /* Mobile phones */
    @media (max-width: 480px) {
        .container { margin: 15px auto; padding: 0 12px; }
        .box { padding: 22px 16px; border-radius: 12px; }
        h2 { font-size: 20px; margin-bottom: 18px; }
        .form-group { margin-bottom: 14px; }
        label { font-size: 13px; margin-bottom: 5px; }
        input, select { padding: 13px; font-size: 16px; }
        .btn-update { padding: 14px; font-size: 17px; }
        .btn-cancel { margin-top: 12px; padding: 12px; font-size: 14px; }
        .msg { padding: 10px; font-size: 14px; margin-bottom: 16px; }
        .navbar { padding: 12px 15px; flex-wrap: wrap; gap: 10px; }
        .navbar h1 { font-size: 18px; }
        .nav-right { flex-wrap: wrap; gap: 8px; font-size: 13px; width: 100%; justify-content: flex-end; }
    }

    @media (max-width: 600px) {
        .form-row { grid-template-columns: 1fr; }
    }
</style>

<div class="navbar">
    <h1>✏️ Edit Borrowing</h1>
    <div class="nav-right">
        <button class="theme-toggle" onclick="toggleTheme()">
            <span id="theme-icon">🌙</span>
            <span id="theme-text">Dark</span>
        </button>
        <span>Welcome, <?php echo $_SESSION['school_name']; ?>!</span>
        <a href="show_data.php">← Back to Records</a>
    </div>
</div>
<script>updateToggleButton('<?php echo $darkMode ? 'dark' : 'light'; ?>');</script>

<div class="container">
    <div class="box">
        <h2>Edit Borrowing</h2>
        
        <?php if ($message): ?>
            <div class="msg <?php echo $messageClass; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Book Name</label>
                <select name="book_id" required>
                    <option value="">Select a book</option>
                    <?php foreach ($books as $book): ?>
                        <option
                            value="<?php echo (int) $book['id']; ?>"
                            <?php echo ((int) ($borrowing['book_id'] ?? 0) === (int) $book['id'] || (($borrowing['book_id'] ?? null) === null && $borrowing['book_name'] === $book['book_name'])) ? 'selected' : ''; ?>
                        >
                            <?php echo htmlspecialchars($book['book_name']); ?> (<?php echo $book['available_count']; ?> available of <?php echo $book['total_copies']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Borrower Name</label>
                    <input type="text" name="borrower_name" value="<?php echo htmlspecialchars($borrowing['borrower_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Class Name</label>
                    <input type="text" name="class_name" value="<?php echo htmlspecialchars($borrowing['class_name'] ?? ''); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Phone Number (PH)</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($borrowing['phone'] ?? ''); ?>" required pattern="[0-9+\-\s()]*">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Borrow Date</label>
                    <input type="date" name="borrow_date" value="<?php echo $borrowing['borrow_date']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Return Date</label>
                    <input type="date" name="return_date" value="<?php echo $borrowing['return_date']; ?>" required>
                </div>
            </div>
            
            <button type="submit" name="update" class="btn-update">Update Record</button>
        </form>
        
        <a href="show_data.php" class="btn-cancel">← Cancel & Go Back</a>
    </div>
</div>

</body>
</html>