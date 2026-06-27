<?php
// transaction_history.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// ─── Read & validate input ────────────────────────────────────
$input = json_decode(file_get_contents("php://input"), true);
if (empty($input)) $input = $_POST;

$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "user_id is required"]);
    exit();
}

// ─── Query ────────────────────────────────────────────────────
// ✅ ضفنا t.direction، كانت ناقصة من الـ SELECT قبل كده
$sql = "
    SELECT 
        t.transaction_id,
        t.transaction_type,
        t.direction,
        t.amount,
        t.status,
        t.transaction_date,
        u.full_name,
        DATE_FORMAT(t.transaction_date, '%M %Y') AS month_label,
        DATE_FORMAT(t.transaction_date, '%d %b %Y, %H.%i') AS formatted_date
    FROM transactions t
    JOIN wallets w ON t.wallet_id = w.wallet_id
    JOIN users u ON w.user_id = u.user_id
    WHERE w.user_id = ?
    ORDER BY t.transaction_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$grouped = [];
while ($row = $result->fetch_assoc()) {
    $month = $row['month_label'];
    if (!isset($grouped[$month])) {
        $grouped[$month] = [];
    }

    // ✅ منطق موحّد للعلامة، شامل Top Up، Transfer، Payment، Split
    if ($row['transaction_type'] === 'Top Up') {
        $sign = '+';
    } elseif (in_array($row['transaction_type'], ['Transfer', 'Payment', 'Split'])) {
        if ($row['direction'] === 'in') {
            $sign = '+';
        } else {
            // direction = 'out' أو NULL (معاملات قديمة) تُحسب كخارجة
            $sign = '-';
        }
    } else {
        $sign = '-';
    }

    $grouped[$month][] = [
        "transaction_id"   => $row['transaction_id'],
        "full_name"        => $row['full_name'],
        "transaction_type" => $row['transaction_type'],
        "formatted_date"   => $row['formatted_date'],
        "amount"           => $sign . "EGP " . number_format($row['amount'], 2),
        "is_positive"      => $sign === '+',
    ];
}
$stmt->close();
$conn->close();

$result_final = [];
foreach ($grouped as $month => $items) {
    $result_final[] = [
        "month"        => $month,
        "transactions" => $items,
    ];
}

echo json_encode([
    "status" => "success",
    "data"   => $result_final,
]);
?>