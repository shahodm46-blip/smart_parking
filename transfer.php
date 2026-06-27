<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$data           = json_decode(file_get_contents("php://input"), true);
if (empty($data)) $data = $_POST;

$sender_user_id = isset($data['user_id'])        ? intval($data['user_id'])      : null;
$receiver_phone = isset($data['receiver_phone']) ? trim($data['receiver_phone']) : null;
$amount         = isset($data['amount'])         ? floatval($data['amount'])     : null;

if (!$sender_user_id || !$receiver_phone || !$amount) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

if ($amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Amount must be greater than zero"]);
    exit;
}

// جيب wallet المرسل
$senderStmt = $conn->prepare("
    SELECT u.user_id, u.full_name, w.wallet_id
    FROM users u
    JOIN wallets w ON w.user_id = u.user_id
    WHERE u.user_id = ? LIMIT 1
");
$senderStmt->bind_param("i", $sender_user_id);
$senderStmt->execute();
$senderResult = $senderStmt->get_result();
if ($senderResult->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Sender wallet not found"]);
    exit;
}
$sender = $senderResult->fetch_assoc();
$senderStmt->close();

// احسب رصيد المرسل
$balStmt = $conn->prepare("
    SELECT COALESCE(SUM(CASE
        WHEN transaction_type = 'Top Up' THEN amount
        WHEN transaction_type IN ('Payment','Transfer','Split') THEN -amount
        ELSE 0 END), 0) AS balance
    FROM transactions WHERE wallet_id = ? AND status = 'available'
");
$balStmt->bind_param("i", $sender['wallet_id']);
$balStmt->execute();
$balance = floatval($balStmt->get_result()->fetch_assoc()['balance']);
$balStmt->close();

if ($balance < $amount) {
    echo json_encode(["status" => "error", "message" => "Insufficient balance"]);
    exit;
}

// جيب wallet المستقبل عن طريق رقم الموبايل
$receiverStmt = $conn->prepare("
    SELECT u.user_id, u.full_name, w.wallet_id
    FROM users u
    JOIN wallets w ON w.user_id = u.user_id
    WHERE u.phone_number = ? LIMIT 1
");
$receiverStmt->bind_param("s", $receiver_phone);
$receiverStmt->execute();
$receiverResult = $receiverStmt->get_result();
if ($receiverResult->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Receiver not found"]);
    exit;
}
$receiver = $receiverResult->fetch_assoc();
$receiverStmt->close();

if ($receiver['user_id'] == $sender_user_id) {
    echo json_encode(["status" => "error", "message" => "Cannot transfer to yourself"]);
    exit;
}

// ✅ خصم من المرسل - direction = out
$debit = $conn->prepare("
    INSERT INTO transactions (wallet_id, transaction_type, direction, amount, status)
    VALUES (?, 'Transfer', 'out', ?, 'available')
");
$debit->bind_param("id", $sender['wallet_id'], $amount);
$debit->execute();
$debit->close();

// ✅ إضافة للمستقبل - direction = in
$credit = $conn->prepare("
    INSERT INTO transactions (wallet_id, transaction_type, direction, amount, status)
    VALUES (?, 'Transfer', 'in', ?, 'available')
");
$credit->bind_param("id", $receiver['wallet_id'], $amount);
$credit->execute();
$credit->close();

// إشعار للمستقبل
$notif = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Money Received', ?)");
$msg = $sender['full_name'] . " sent you EGP " . number_format($amount, 2);
$notif->bind_param("is", $receiver['user_id'], $msg);
$notif->execute();
$notif->close();

$conn->close();

echo json_encode([
    "status"        => "success",
    "message"       => "Transfer successful",
    "receiver_name" => $receiver['full_name'],
    "amount"        => number_format($amount, 2),
]);
?>