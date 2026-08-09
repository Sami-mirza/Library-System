<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

echo "Step 1: Session OK<br>";

require_once 'Config/DB.php';
echo "Step 2: DB loaded<br>";

$answer = "";
$usedOfflineMode = false;

// Get API key
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
echo "Step 3: API key loaded (length: " . strlen($apiKey) . ")<br>";

// Fetch borrowings
$stmt = $pdo->prepare("SELECT * FROM borrowings WHERE user_id = ? ORDER BY borrow_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Step 4: Borrowings fetched: " . count($borrowings) . "<br>";

// Build summary
$dataSummary = "";
foreach ($borrowings as $b) {
    $status = (strtotime($b['return_date']) < strtotime(date('Y-m-d'))) ? "OVERDUE" : "Active";
    $dataSummary .= $b['book_name'] . " | " . $b['borrower_name'] . " | " . $b['return_date'] . " | " . $status . "
";
}
echo "Step 5: Data summary built<br>";

// Offline analysis
function getOfflineAnswer($question, $borrowings) {
    $q = strtolower($question);
    $overdue = 0;
    foreach ($borrowings as $b) {
        if (strtotime($b['return_date']) < strtotime(date('Y-m-d'))) $overdue++;
    }
    if (strpos($q, 'overdue') !== false && strpos($q, 'how many') !== false) {
        return "You have " . $overdue . " overdue books.";
    }
    return null;
}
echo "Step 6: Function defined<br>";

// Handle question
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['question'])) {
    echo "Step 7: POST received<br>";
    $question = $_POST['question'];
    $offlineAnswer = getOfflineAnswer($question, $borrowings);

    if ($offlineAnswer !== null) {
        $answer = $offlineAnswer;
        $usedOfflineMode = true;
        echo "Step 8: Offline answer found<br>";
    } elseif (!empty($apiKey)) {
        echo "Step 8: Trying API...<br>";
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
        $prompt = "You are a library assistant. Answer based ONLY on this data:

" . $dataSummary . "

Question: " . $question;
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

        echo "Step 9: API response code: " . $code . "<br>";

        if ($code == 200) {
            $result = json_decode($response, true);
            $answer = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No answer';
        } else {
            $answer = "API unavailable (code " . $code . ")";
            $usedOfflineMode = true;
        }
    } else {
        $answer = "No API key";
        $usedOfflineMode = true;
    }
} else {
    echo "Step 7: No POST yet<br>";
}

echo "Step 10: About to output HTML...<br>";
?>
<!DOCTYPE html>
<html>
<head><title>Ask AI</title></head>
<body style="font-family:Arial;padding:20px;">
    <h2>Ask AI</h2>
    <form method="POST">
        <input type="text" name="question" placeholder="Ask..." required style="padding:10px;width:300px;">
        <button type="submit" style="padding:10px;">Ask</button>
    </form>
    <?php if ($answer): ?>
        <div style="background:#f0f0f0;padding:20px;margin-top:20px;border-left:5px solid #9C27B0;">
            <?php if ($usedOfflineMode): ?><span style="background:#4CAF50;color:white;padding:4px 10px;border-radius:12px;font-size:12px;">⚡ Offline</span><?php endif; ?>
            <p><?php echo nl2br(htmlspecialchars($answer)); ?></p>
        </div>
    <?php endif; ?>
    <a href="Dashboard.php">← Back</a>
</body>
</html>