<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$data    = json_decode(file_get_contents("php://input"), true);
if (empty($data)) $data = $_POST;

$user_id = isset($data['user_id']) ? intval($data['user_id']) : null;

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

// جيب wallet_id
$walletStmt = $conn->prepare("SELECT wallet_id FROM wallets WHERE user_id = ? LIMIT 1");
$walletStmt->bind_param("i", $user_id);
$walletStmt->execute();
$walletResult = $walletStmt->get_result();
if ($walletResult->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Wallet not found"]);
    exit;
}
$wallet_id = $walletResult->fetch_assoc()['wallet_id'];
$walletStmt->close();

// ✅ آخر 3 transactions نوع Transfer (recent) - العلامة حسب direction الحقيقي
$recentStmt = $conn->prepare("
    SELECT t.transaction_id, t.amount, t.direction, t.transaction_date
    FROM transactions t
    WHERE t.wallet_id = ? AND t.transaction_type = 'Transfer'
    ORDER BY t.transaction_date DESC
    LIMIT 3
");
$recentStmt->bind_param("i", $wallet_id);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();
$recent = [];
while ($row = $recentResult->fetch_assoc()) {
    $sign = ($row['direction'] === 'in') ? '+' : '-';
    $recent[] = [
        "name"   => "PaySpot User",
        "amount" => "$sign" . "EGP " . number_format($row['amount'], 2),
        "date"   => date("d M Y, H:i", strtotime($row['transaction_date'])),
    ];
}
$recentStmt->close();

// ✅ كل الـ transactions نوع Transfer - العلامة حسب direction الحقيقي
$allStmt = $conn->prepare("
    SELECT t.transaction_id, t.amount, t.direction, t.transaction_date
    FROM transactions t
    WHERE t.wallet_id = ? AND t.transaction_type = 'Transfer'
    ORDER BY t.transaction_date DESC
    LIMIT 50
");
$allStmt->bind_param("i", $wallet_id);
$allStmt->execute();
$allResult = $allStmt->get_result();
$all = [];
while ($row = $allResult->fetch_assoc()) {
    $sign = ($row['direction'] === 'in') ? '+' : '-';
    $all[] = [
        "name"   => "PaySpot User",
        "amount" => "$sign" . "EGP " . number_format($row['amount'], 2),
        "date"   => date("d M Y, H:i", strtotime($row['transaction_date'])),
    ];
}
$allStmt->close();
$conn->close();

echo json_encode([
    "status" => "success",
    "recent" => $recent,
    "all"    => $all,
]);
?>