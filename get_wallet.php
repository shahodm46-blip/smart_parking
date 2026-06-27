<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id = $_POST['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

// ✅ جيب الـ wallet
$wallet = $conn->prepare("SELECT wallet_id FROM wallets WHERE user_id = ?");
$wallet->bind_param("i", $user_id);
$wallet->execute();
$walletResult = $wallet->get_result();
$walletData = $walletResult->fetch_assoc();

if (!$walletData) {
    echo json_encode(["status" => "error", "message" => "Wallet not found"]);
    exit;
}

$wallet_id = $walletData['wallet_id'];

// ✅ حساب الرصيد بمنطق موحّد بيدعم direction لـ Transfer و Payment
$balance = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE
            WHEN transaction_type = 'Top Up' THEN amount
            WHEN transaction_type IN ('Transfer', 'Payment', 'Split') AND direction = 'in'  THEN amount
            WHEN transaction_type IN ('Transfer', 'Payment', 'Split') AND direction = 'out' THEN -amount
            -- ✅ Backward compatibility: معاملات قديمة من قبل إضافة direction (NULL) تُحسب كخارجة
            WHEN transaction_type IN ('Payment', 'Transfer', 'Split') AND direction IS NULL THEN -amount
            ELSE 0
        END), 0) AS balance
    FROM transactions 
    WHERE wallet_id = ? AND status = 'available'
");
$balance->bind_param("i", $wallet_id);
$balance->execute();
$balanceResult = $balance->get_result();
$balanceData = $balanceResult->fetch_assoc();

// ✅ جيب آخر 5 معاملات (ضفنا direction للاستخدام في الفلاتر لو احتاجت)
$transactions = $conn->prepare("
    SELECT t.transaction_id, t.transaction_type, t.direction, t.amount, t.transaction_date
    FROM transactions t
    WHERE t.wallet_id = ?
    ORDER BY t.transaction_date DESC
    LIMIT 5
");
$transactions->bind_param("i", $wallet_id);
$transactions->execute();
$transResult = $transactions->get_result();

$trans = [];
while ($row = $transResult->fetch_assoc()) {
    $trans[] = [
        "id"        => $row['transaction_id'],
        "type"      => $row['transaction_type'],
        "direction" => $row['direction'],
        "amount"    => $row['amount'],
        "date"      => $row['transaction_date'],
    ];
}

echo json_encode([
    "status"       => "success",
    "wallet_id"    => $wallet_id,
    "balance"      => number_format($balanceData['balance'], 2),
    "transactions" => $trans,
]);

$conn->close();
?>