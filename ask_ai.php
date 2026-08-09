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

// ==================== RATE LIMITING ====================
$dailyLimit = 10;

$pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
    user_id INT PRIMARY KEY,
    request_count INT DEFAULT 0,
    last_request_date DATE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$today = date('Y-m-d');
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT request_count, last_request_date FROM rate_limits WHERE user_id = ?");
$stmt->execute([$userId]);
$rateData = $stmt->fetch(PDO::FETCH_ASSOC);

$requestsUsed = 0;
$remaining = $dailyLimit;

if ($rateData) {
    if ($rateData['last_request_date'] === $today) {
        $requestsUsed = (int)$rateData['request_count'];
        $remaining = max(0, $dailyLimit - $requestsUsed);
    } else {
        $pdo->prepare("UPDATE rate_limits SET request_count = 0, last_request_date = ? WHERE user_id = ?")
        ->execute([$today, $userId]);
        $remaining = $dailyLimit;
    }
} else {
    $pdo->prepare("INSERT INTO rate_limits (user_id, request_count, last_request_date) VALUES (?, 0, ?)")
    ->execute([$userId, $today]);
    $remaining = $dailyLimit;
}

$rateLimitExceeded = false;
// ======================================================

$darkMode = isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true';
$answer = "";
$usedOfflineMode = false;

$apiKey = '';
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'GEMINI_API_KEY=') === 0) {
            $apiKey = trim(substr($line, 15));
            if (substr($apiKey, 0, 1) === '"' && substr($apiKey, -1) === '"') {
                $apiKey = substr($apiKey, 1, -1);
            }
            break;
        }
    }
}

// ---- FETCH BORROWINGS ----
$stmt = $pdo->prepare("SELECT * FROM borrowings WHERE user_id = ? ORDER BY borrow_date DESC");
$stmt->execute([$userId]);
$borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- FETCH INVENTORY ----
$books = fetchUserBooks($pdo, $userId);

// ---- BUILD ENRICHED DATA SUMMARY ----
$inventorySummary = "";
$totalTitles = count($books);
$totalCopiesAll = 0;
$totalBorrowedAll = 0;
$totalAvailableAll = 0;

foreach ($books as $bk) {
    $loc = !empty($bk['location']) ? $bk['location'] : 'No location set';
    $inventorySummary .= "- " . $bk['book_name'] . " | Location: " . $loc . " | Total: " . $bk['total_copies'] . " | Borrowed: " . $bk['borrowed_count'] . " | Available: " . $bk['available_count'] . "\n";
    $totalCopiesAll += $bk['total_copies'];
    $totalBorrowedAll += $bk['borrowed_count'];
    $totalAvailableAll += $bk['available_count'];
}

$borrowingSummary = "";
$borrowerList = []; // track unique borrowers
foreach ($borrowings as $b) {
    $status = (strtotime($b['return_date']) < strtotime(date('Y-m-d'))) ? "OVERDUE" : "Active";
    $phone = !empty($b['phone']) ? $b['phone'] : 'N/A';
    $class = !empty($b['class_name']) ? $b['class_name'] : 'N/A';
    $borrowingSummary .= "- " . $b['book_name'] . " | Borrower: " . $b['borrower_name'] . " | Class: " . $class . " | Phone: " . $phone . " | Due: " . $b['return_date'] . " | Status: " . $status . "\n";
    
    // Build borrower profile
    $name = $b['borrower_name'];
    if (!isset($borrowerList[$name])) {
        $borrowerList[$name] = ['phone' => $phone, 'class' => $class, 'books' => []];
    }
    $borrowerList[$name]['books'][] = $b['book_name'] . " (Due: " . $b['return_date'] . ", " . $status . ")";
}

$dataSummary = "=== INVENTORY ===\n";
if ($inventorySummary) {
    $dataSummary .= $inventorySummary;
    $dataSummary .= "--- Totals ---\n";
    $dataSummary .= "Total Titles: " . $totalTitles . " | Total Copies: " . $totalCopiesAll . " | Total Borrowed: " . $totalBorrowedAll . " | Total Available: " . $totalAvailableAll . "\n";
} else {
    $dataSummary .= "No books in inventory.\n";
}

$dataSummary .= "\n=== BORROWINGS ===\n";
if ($borrowingSummary) {
    $dataSummary .= $borrowingSummary;
} else {
    $dataSummary .= "No borrowings recorded.\n";
}

$dataSummary .= "\n=== BORROWER PROFILES ===\n";
if ($borrowerList) {
    foreach ($borrowerList as $name => $info) {
        $dataSummary .= "- " . $name . " | Class: " . $info['class'] . " | Phone: " . $info['phone'] . " | Books borrowed: " . count($info['books']) . "\n";
    }
} else {
    $dataSummary .= "No borrowers on record.\n";
}

// ---- OFFLINE ANSWER ENGINE (EXPANDED) ----
function getOfflineAnswer($question, $books, $borrowings, $totalTitles, $totalCopiesAll, $totalBorrowedAll, $totalAvailableAll, $borrowerList) {
    $q = strtolower(trim($question));

    // --- INVENTORY TOTALS ---
    if ((strpos($q, 'how many books') !== false || strpos($q, 'total books') !== false || strpos($q, 'book titles') !== false) && strpos($q, 'title') !== false) {
        return "You have " . $totalTitles . " book title(s) in your inventory.";
    }
    if ((strpos($q, 'how many copies') !== false || strpos($q, 'total copies') !== false)) {
        return "You have " . $totalCopiesAll . " total copy/copies across all titles.";
    }
    if (strpos($q, 'available') !== false && (strpos($q, 'how many') !== false || strpos($q, 'total') !== false)) {
        return "You have " . $totalAvailableAll . " copy/copies available right now.";
    }
    if (strpos($q, 'borrowed') !== false && (strpos($q, 'how many') !== false || strpos($q, 'total') !== false)) {
        return "You have " . $totalBorrowedAll . " copy/copies currently borrowed out.";
    }

    // --- LOCATION QUERIES ---
    if (strpos($q, 'where is') !== false || strpos($q, 'location') !== false || strpos($q, 'shelf') !== false || strpos($q, 'found') !== false || strpos($q, 'kept') !== false) {
        foreach ($books as $bk) {
            $name = strtolower($bk['book_name']);
            // Match if book name appears in question
            if (strpos($q, $name) !== false) {
                $loc = !empty($bk['location']) ? $bk['location'] : 'No location set';
                return "'" . $bk['book_name'] . "' is located at: " . $loc . ".";
            }
        }
        // If no specific book matched but they asked about location
        if (strpos($q, 'where') !== false) {
            return "Please mention a specific book name so I can tell you its location.";
        }
    }

    // --- SPECIFIC BOOK DETAILS (copies, available, borrowed) ---
    foreach ($books as $bk) {
        $name = strtolower($bk['book_name']);
        if (strpos($q, $name) !== false) {
            if (strpos($q, 'copy') !== false || strpos($q, 'total') !== false || strpos($q, 'many') !== false) {
                return "'" . $bk['book_name'] . "' has " . $bk['total_copies'] . " total copy/copies. " . $bk['borrowed_count'] . " are borrowed and " . $bk['available_count'] . " are available.";
            }
            if (strpos($q, 'available') !== false) {
                return "'" . $bk['book_name'] . "' has " . $bk['available_count'] . " copy/copies available.";
            }
            if (strpos($q, 'borrowed') !== false) {
                return "'" . $bk['book_name'] . "' has " . $bk['borrowed_count'] . " copy/copies currently borrowed out.";
            }
        }
    }

    // --- OVERDUE ---
    $overdue = 0;
    $overdueList = [];
    foreach ($borrowings as $b) {
        if (strtotime($b['return_date']) < strtotime(date('Y-m-d'))) {
            $overdue++;
            $overdueList[] = $b;
        }
    }
    if (strpos($q, 'overdue') !== false && (strpos($q, 'how many') !== false || strpos($q, 'total') !== false || strpos($q, 'count') !== false)) {
        return "You have " . $overdue . " overdue book(s).";
    }
    if (strpos($q, 'overdue') !== false && (strpos($q, 'list') !== false || strpos($q, 'show') !== false || strpos($q, 'which') !== false || strpos($q, 'what') !== false)) {
        if ($overdue === 0) return "You have no overdue books.";
        $out = "Here are your overdue books:\n";
        foreach ($overdueList as $b) {
            $phone = !empty($b['phone']) ? " | Phone: " . $b['phone'] : "";
            $class = !empty($b['class_name']) ? " | Class: " . $b['class_name'] : "";
            $out .= "• '" . $b['book_name'] . "' borrowed by " . $b['borrower_name'] . $class . $phone . " (Due: " . $b['return_date'] . ")\n";
        }
        return $out;
    }

    // --- PHONE / CONTACT QUERIES ---
    if (strpos($q, 'phone') !== false || strpos($q, 'mobile') !== false || strpos($q, 'contact') !== false || strpos($q, 'number') !== false) {
        // Search for a specific borrower
        foreach ($borrowerList as $name => $info) {
            if (strpos($q, strtolower($name)) !== false) {
                if ($info['phone'] !== 'N/A') {
                    return $name . "'s phone number is: " . $info['phone'] . ".";
                } else {
                    return $name . " does not have a phone number on record.";
                }
            }
        }
        // If asking about a book's borrower phone
        foreach ($borrowings as $b) {
            $bookName = strtolower($b['book_name']);
            if (strpos($q, $bookName) !== false && !empty($b['phone'])) {
                return "The borrower of '" . $b['book_name'] . "' is " . $b['borrower_name'] . ". Phone: " . $b['phone'] . ".";
            }
        }
        return "Please mention a borrower's name or a book title to find the phone number.";
    }

    // --- CLASS NAME QUERIES ---
    if (strpos($q, 'class') !== false || strpos($q, 'section') !== false || strpos($q, 'grade') !== false) {
        foreach ($borrowerList as $name => $info) {
            if (strpos($q, strtolower($name)) !== false) {
                if ($info['class'] !== 'N/A') {
                    return $name . " is in class: " . $info['class'] . ".";
                } else {
                    return $name . " does not have a class assigned.";
                }
            }
        }
        foreach ($borrowings as $b) {
            $bookName = strtolower($b['book_name']);
            if (strpos($q, $bookName) !== false && !empty($b['class_name'])) {
                return "'" . $b['book_name'] . "' is borrowed by " . $b['borrower_name'] . " from class " . $b['class_name'] . ".";
            }
        }
        return "Please mention a borrower's name or a book title to find the class info.";
    }

    // --- BORROWER PROFILE / WHO BORROWED ---
    if (strpos($q, 'who borrowed') !== false || strpos($q, 'who has') !== false || strpos($q, 'borrower of') !== false) {
        foreach ($borrowings as $b) {
            $bookName = strtolower($b['book_name']);
            if (strpos($q, $bookName) !== false) {
                $phone = !empty($b['phone']) ? " | Phone: " . $b['phone'] : "";
                $class = !empty($b['class_name']) ? " | Class: " . $b['class_name'] : "";
                $status = (strtotime($b['return_date']) < strtotime(date('Y-m-d'))) ? "OVERDUE" : "Active";
                return "'" . $b['book_name'] . "' is borrowed by " . $b['borrower_name'] . $class . $phone . ". Due date: " . $b['return_date'] . " (" . $status . ").";
            }
        }
    }

    // --- LIST ALL BORROWINGS (with full details) ---
    if (strpos($q, 'list all') !== false || strpos($q, 'show all') !== false || strpos($q, 'all records') !== false) {
        if (empty($borrowings)) return "You have no borrowings recorded.";
        $out = "Here are all your borrowings:\n";
        foreach ($borrowings as $b) {
            $status = (strtotime($b['return_date']) < strtotime(date('Y-m-d'))) ? "OVERDUE" : "Active";
            $phone = !empty($b['phone']) ? " | Phone: " . $b['phone'] : "";
            $class = !empty($b['class_name']) ? " | Class: " . $b['class_name'] : "";
            $out .= "• '" . $b['book_name'] . "' → " . $b['borrower_name'] . $class . $phone . " | Due: " . $b['return_date'] . " — " . $status . "\n";
        }
        return $out;
    }

    // --- NEXT DUE DATE ---
    if (strpos($q, 'next due') !== false || strpos($q, 'due next') !== false || strpos($q, 'closest due') !== false) {
        if (empty($borrowings)) return "You have no borrowings recorded.";
        $next = null;
        foreach ($borrowings as $b) {
            if ($next === null || strtotime($b['return_date']) < strtotime($next['return_date'])) {
                $next = $b;
            }
        }
        $phone = !empty($next['phone']) ? " | Phone: " . $next['phone'] : "";
        $class = !empty($next['class_name']) ? " | Class: " . $next['class_name'] : "";
        return "The next due book is '" . $next['book_name'] . "' borrowed by " . $next['borrower_name'] . $class . $phone . ", due on " . $next['return_date'] . ".";
    }

    // --- TOP BORROWER ---
    if (strpos($q, 'top borrower') !== false || strpos($q, 'most books') !== false || strpos($q, 'who borrowed the most') !== false) {
        if (empty($borrowerList)) return "No borrowing records found.";
        $topName = '';
        $topCount = 0;
        foreach ($borrowerList as $name => $info) {
            $count = count($info['books']);
            if ($count > $topCount) {
                $topCount = $count;
                $topName = $name;
            }
        }
        if ($topCount > 0) {
            $info = $borrowerList[$topName];
            $phone = ($info['phone'] !== 'N/A') ? " | Phone: " . $info['phone'] : "";
            $class = ($info['class'] !== 'N/A') ? " | Class: " . $info['class'] : "";
            return "Top borrower: " . $topName . $class . $phone . " with " . $topCount . " book(s) borrowed.";
        }
    }

    // --- SEARCH BY BORROWER NAME ---
    if (strpos($q, 'what did') !== false || strpos($q, 'books borrowed by') !== false || strpos($q, 'what has') !== false) {
        foreach ($borrowerList as $name => $info) {
            if (strpos($q, strtolower($name)) !== false) {
                $phone = ($info['phone'] !== 'N/A') ? " | Phone: " . $info['phone'] : "";
                $class = ($info['class'] !== 'N/A') ? " | Class: " . $info['class'] : "";
                $out = $name . $class . $phone . " has borrowed:\n";
                foreach ($info['books'] as $book) {
                    $out .= "• " . $book . "\n";
                }
                return $out;
            }
        }
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['question'])) {
    $question = $_POST['question'];

    $offlineAnswer = getOfflineAnswer($question, $books, $borrowings, $totalTitles, $totalCopiesAll, $totalBorrowedAll, $totalAvailableAll, $borrowerList);

    if ($offlineAnswer !== null) {
        $answer = $offlineAnswer;
        $usedOfflineMode = true;
    } else {
        if ($remaining <= 0) {
            $rateLimitExceeded = true;
            $answer = "⚠️ Daily AI limit reached! You can only ask " . $dailyLimit . " questions per day.\n\nTry asking offline queries like:\n• 'Where is Harry Potter?'\n• 'What is Ali's phone number?'\n• 'Which class is Sara in?'\n• 'Who borrowed the most books?'\n• 'List all overdue books'";
        } elseif (!empty($apiKey)) {
            $pdo->prepare("INSERT INTO rate_limits (user_id, request_count, last_request_date)
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
            request_count = CASE
            WHEN last_request_date = VALUES(last_request_date) THEN request_count + 1
            ELSE 1
            END,
            last_request_date = VALUES(last_request_date)")
            ->execute([$userId, $today]);

            $remaining--;
            $requestsUsed++;

            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
            $prompt = "You are a library assistant. Answer based ONLY on this data. Be concise and accurate.\n\n" . $dataSummary . "\n\nQuestion: " . $question;
            $data = array("contents" => array(array("parts" => array(array("text" => $prompt)))));

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code == 200) {
                $result = json_decode($response, true);
                $answer = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No answer';
            } else {
                $answer = "API temporarily unavailable. Using offline data.";
                $usedOfflineMode = true;
            }
        } else {
            $answer = "No API key configured.";
            $usedOfflineMode = true;
        }
    }
}
?>

<title>Ask AI - Library System</title>
<style>
.container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
.chat-box { background: var(--card); padding: 30px; border-radius: 15px; box-shadow: var(--shadow); }
h2 { color: var(--text); margin-bottom: 20px; }

.rate-limit-bar {
    background: var(--bg);
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 2px solid #ddd;
}
[data-theme="dark"] .rate-limit-bar { border-color: #333; }
.rate-limit-bar.warning { border-color: var(--warning); background: rgba(255,152,0,0.1); }
.rate-limit-bar.danger { border-color: var(--danger); background: rgba(244,67,54,0.1); }
.rate-limit-text { font-size: 14px; color: var(--text-secondary); }
.rate-limit-count { font-weight: bold; font-size: 16px; }
.rate-limit-count.safe { color: #4CAF50; }
.rate-limit-count.warning { color: var(--warning); }
.rate-limit-count.danger { color: var(--danger); }

.data-preview { background: var(--bg); padding: 15px; border-radius: 10px; margin-bottom: 20px; max-height: 150px; overflow-y: auto; border: 1px solid #ddd; }
.data-preview h4 { margin-bottom: 10px; color: var(--text-secondary); }
.data-preview pre { font-size: 12px; color: var(--text-secondary); white-space: pre-wrap; }
.question-box { display: flex; gap: 10px; margin-bottom: 20px; }
.question-box input { flex: 1; padding: 15px; border: 2px solid #ddd; border-radius: 10px; font-size: 16px; outline: none; background: var(--card); color: var(--text); }
.question-box input:focus { border-color: #9C27B0; }
.question-box button { padding: 15px 30px; background: #9C27B0; color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 16px; }
.question-box button:hover { background: #7B1FA2; }
.suggestions { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
.suggestion { background: var(--bg); color: #7B1FA2; padding: 8px 15px; border-radius: 20px; cursor: pointer; font-size: 14px; border: 1px solid #9C27B0; }
.suggestion:hover { background: #e1bee7; }
.suggestion.offline { border-color: #4CAF50; color: #2e7d32; }
.suggestion.offline:hover { background: #c8e6c9; }
.answer-box { background: var(--bg); padding: 25px; border-radius: 10px; border-left: 5px solid #9C27B0; margin-top: 20px; }
.answer-box.warning { border-left-color: var(--warning); }
.answer-box h3 { color: #9C27B0; margin-bottom: 15px; }
.answer-box p { color: var(--text); line-height: 1.6; white-space: pre-wrap; }
.offline-badge { display: inline-block; background: #4CAF50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-bottom: 10px; }
.limit-badge { display: inline-block; background: var(--danger); color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-bottom: 10px; }
.back { display: inline-block; margin-top: 20px; color: var(--text-secondary); text-decoration: none; }
.back:hover { color: var(--text); }

/* ========== RESPONSIVE STYLES ========== */
/* Tablets */
@media (max-width: 768px) {
    .container { margin: 20px auto; padding: 0 15px; }
    .chat-box { padding: 25px; }
    h2 { font-size: 24px; margin-bottom: 18px; }
    .rate-limit-bar { padding: 10px 16px; }
    .rate-limit-text { font-size: 13px; }
    .rate-limit-count { font-size: 15px; }
    .data-preview { padding: 12px; margin-bottom: 18px; }
    .data-preview h4 { font-size: 14px; }
    .question-box input { padding: 13px; font-size: 15px; }
    .question-box button { padding: 13px 24px; font-size: 15px; }
    .suggestions { gap: 8px; margin-bottom: 18px; }
    .suggestion { padding: 7px 12px; font-size: 13px; }
    .answer-box { padding: 20px; margin-top: 18px; }
    .answer-box h3 { font-size: 18px; }
    .navbar { padding: 15px 20px; }
    .navbar h1 { font-size: 22px; }
    .nav-right { gap: 12px; font-size: 14px; }
}

/* Mobile phones */
@media (max-width: 480px) {
    .container { margin: 15px auto; padding: 0 12px; }
    .chat-box { padding: 18px 14px; border-radius: 12px; }
    h2 { font-size: 20px; margin-bottom: 16px; }
    .rate-limit-bar { flex-direction: column; gap: 6px; padding: 10px 14px; text-align: center; }
    .rate-limit-text { font-size: 12px; }
    .rate-limit-count { font-size: 14px; }
    .data-preview { padding: 10px; margin-bottom: 15px; max-height: 120px; }
    .data-preview h4 { font-size: 13px; margin-bottom: 8px; }
    .data-preview pre { font-size: 11px; }
    .question-box { flex-direction: column; gap: 8px; }
    .question-box input { width: 100%; padding: 14px; font-size: 16px; }
    .question-box button { width: 100%; padding: 14px; font-size: 16px; }
    .suggestions { gap: 6px; margin-bottom: 15px; }
    .suggestion { padding: 6px 10px; font-size: 12px; border-radius: 16px; }
    .answer-box { padding: 16px; margin-top: 15px; border-left-width: 4px; }
    .answer-box h3 { font-size: 16px; margin-bottom: 12px; }
    .answer-box p { font-size: 14px; line-height: 1.5; }
    .offline-badge, .limit-badge { font-size: 11px; padding: 3px 8px; }
    .back { margin-top: 16px; font-size: 14px; }
    .navbar { padding: 12px 15px; flex-wrap: wrap; gap: 10px; }
    .navbar h1 { font-size: 18px; }
    .nav-right { flex-wrap: wrap; gap: 8px; font-size: 13px; width: 100%; justify-content: flex-end; }
}
</style>
</head>
<body>

<div class="navbar">
<h1>🤖 Ask AI</h1>
<div class="nav-right">
<button class="theme-toggle" onclick="toggleTheme()">
<span id="theme-icon"><?php echo $darkMode ? '☀️' : '🌙'; ?></span>
<span id="theme-text"><?php echo $darkMode ? 'Light' : 'Dark'; ?></span>
</button>
<span>Welcome, <?php echo $_SESSION['school_name']; ?>!</span>
<a href="Dashboard.php">← Back to Dashboard</a>
</div>
</div>

<div class="container">
<div class="chat-box">
<h2>Ask About Your Library Data</h2>

<?php
$pct = ($dailyLimit > 0) ? ($requestsUsed / $dailyLimit) * 100 : 0;
$barClass = $pct >= 100 ? 'danger' : ($pct >= 70 ? 'warning' : '');
$countClass = $pct >= 100 ? 'danger' : ($pct >= 70 ? 'warning' : 'safe');
?>
<div class="rate-limit-bar <?php echo $barClass; ?>">
<span class="rate-limit-text">🕐 Daily AI Quota</span>
<span class="rate-limit-count <?php echo $countClass; ?>">
<?php echo $requestsUsed; ?> / <?php echo $dailyLimit; ?> used (<?php echo $remaining; ?> left)
</span>
</div>

<div class="data-preview">
<h4>📊 Your Data Preview (<?php echo count($books); ?> titles, <?php echo count($borrowings); ?> borrowings)</h4>
<pre><?php echo substr($dataSummary, 0, 600) . (strlen($dataSummary) > 600 ? '...' : ''); ?></pre>
</div>

<p style="color:var(--text-secondary); margin-bottom:10px;">Try asking:</p>
<div class="suggestions">
<button class="suggestion offline" onclick="ask('How many books do I have?')">How many books? ⚡</button>
<button class="suggestion offline" onclick="ask('Where is [book name]?')">Where is a book? ⚡</button>
<button class="suggestion offline" onclick="ask('What is [borrower name] phone number?')">Phone number? ⚡</button>
<button class="suggestion offline" onclick="ask('Which class is [borrower name] in?')">Class name? ⚡</button>
<button class="suggestion offline" onclick="ask('How many overdue books do I have?')">How many overdue? ⚡</button>
<button class="suggestion offline" onclick="ask('Who borrowed the most books?')">Top borrower? ⚡</button>
<button class="suggestion offline" onclick="ask('List all overdue books')">List overdue ⚡</button>
<button class="suggestion" onclick="ask('Who borrowed Harry Potter?')">Who borrowed a book?</button>
<button class="suggestion" onclick="ask('List all active borrowings')">List all records</button>
<button class="suggestion" onclick="ask('When is the next book due?')">Next due date?</button>
</div>

<form method="POST" id="aiForm">
<div class="question-box">
<input type="text" name="question" id="questionInput"
placeholder="<?php echo $remaining <= 0 ? 'Daily limit reached. Try offline queries above.' : 'Ask anything about your library data...'; ?>"
required>
<button type="submit">Ask AI</button>
</div>
</form>

<?php if ($answer): ?>
<div class="answer-box <?php echo $rateLimitExceeded ? 'warning' : ''; ?>">
<?php if ($usedOfflineMode): ?>
<span class="offline-badge">⚡ Offline Mode</span>
<?php endif; ?>
<?php if ($rateLimitExceeded): ?>
<span class="limit-badge">⛔ Limit Reached</span>
<?php endif; ?>
<h3><?php echo $rateLimitExceeded ? '⚠️ Limit Exceeded' : '🤖 AI Answer'; ?></h3>
<p><?php echo nl2br(htmlspecialchars($answer)); ?></p>
</div>
<?php endif; ?>

<a href="Dashboard.php" class="back">← Back to Dashboard</a>
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
    if (next === 'dark') {
        icon.textContent = '☀️';
        text.textContent = 'Light';
    } else {
        icon.textContent = '🌙';
        text.textContent = 'Dark';
    }
}

function ask(text) {
    document.getElementById('questionInput').value = text;
    document.getElementById('aiForm').submit();
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>