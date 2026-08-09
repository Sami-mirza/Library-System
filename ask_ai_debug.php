<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Show all errors immediately
echo "<h3>Debug Mode</h3>";

if (!isset($_SESSION['user_id'])) {
    echo "Session user_id not set<br>";
} else {
    echo "Session OK<br>";
}

echo "Loading DB...<br>";
require_once 'Config/DB.php';
echo "DB OK<br>";

echo "Loading header...<br>";
require_once 'header.php';
echo "Header OK<br>";

// Test if we can fetch borrowings
echo "Fetching borrowings...<br>";
$stmt = $pdo->prepare("SELECT * FROM borrowings WHERE user_id = ? ORDER BY borrow_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Found " . count($borrowings) . " borrowings<br>";

// Test .env reading
echo "Reading .env...<br>";
$apiKey = '';
if (file_exists(__DIR__ . '/.env')) {
    echo ".env exists<br>";
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, 'GEMINI_API_KEY=') === 0) {
            $apiKey = trim(substr($line, 15));
            echo "API key found (length: " . strlen($apiKey) . ")<br>";
            break;
        }
    }
} else {
    echo ".env NOT FOUND<br>";
}

// Simple offline answer function
function getAnswer($question, $borrowings) {
    $q = strtolower($question);
    $overdue = 0;
    foreach ($borrowings as $b) {
        if (strtotime($b['return_date']) < strtotime(date('Y-m-d'))) $overdue++;
    }

    if (strpos($q, 'overdue') !== false) {
        return "You have " . $overdue . " overdue books.";
    }
    return null;
}

$answer = "";
$usedOffline = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['question'])) {
    echo "Processing question...<br>";
    $question = $_POST['question'];
    $offline = getAnswer($question, $borrowings);

    if ($offline !== null) {
        $answer = $offline;
        $usedOffline = true;
        echo "Used offline mode<br>";
    } else {
        echo "Trying API...<br>";
        // Simple API call
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
        $data = array("contents" => array(array("parts" => array(array("text" => $question)))));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "API response code: " . $code . "<br>";

        if ($code == 200) {
            $result = json_decode($response, true);
            $answer = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No answer';
        } else {
            $answer = "API error: " . $code;
        }
    }
}
?>

<style>
.container { max-width: 900px; margin: 30px auto; padding: 0 20px; }
.chat-box { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
.question-box { display: flex; gap: 10px; margin: 20px 0; }
.question-box input { flex: 1; padding: 15px; border: 2px solid #ddd; border-radius: 10px; font-size: 16px; }
.question-box button { padding: 15px 30px; background: #9C27B0; color: white; border: none; border-radius: 10px; cursor: pointer; }
.answer-box { background: #f0f2f5; padding: 25px; border-radius: 10px; border-left: 5px solid #9C27B0; margin-top: 20px; }
.offline-badge { display: inline-block; background: #4CAF50; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-bottom: 10px; }
</style>

<div class="container">
    <div class="chat-box">
        <h2>Ask AI (Debug Mode)</h2>

        <form method="POST">
            <div class="question-box">
                <input type="text" name="question" placeholder="Ask anything..." required>
                <button type="submit">Ask</button>
            </div>
        </form>

        <?php if ($answer): ?>
            <div class="answer-box">
                <?php if ($usedOffline): ?>
                    <span class="offline-badge">⚡ Offline</span>
                <?php endif; ?>
                <p><?php echo nl2br(htmlspecialchars($answer)); ?></p>
            </div>
        <?php endif; ?>

        <a href="Dashboard.php">← Back</a>
    </div>
</div>