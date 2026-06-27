<?php
// top_up.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ✅ استخدمنا db.php الموحّد بدل اتصال PDO منفصل وبورت غلط
include 'db.php';

// ─── Read & Validate Input ────────────────────────────────────
$input = json_decode(file_get_contents("php://input"), true);
if (empty($input)) $input = $_POST;

$user_id        = isset($input['user_id'])        ? intval($input['user_id'])     : 0;
$amount         = isset($input['amount'])         ? floatval($input['amount'])    : 0;
$payment_method = isset($input['payment_method']) ? trim($input['payment_method']) : '';

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "user_id is required"]);
    exit();
}
if ($amount <= 0) {
    echo json_encode(["status" => "error", "message" => "Amount must be greater than 0"]);
    exit();
}
if (empty($payment_method)) {
    echo json_encode(["status" => "error", "message" => "Payment method is required"]);
    exit();
}

// ─── تأكد إن المستخدم موجود ────────────────────────────────────
$stmtUser = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$userResult = $stmtUser->get_result();
if ($userResult->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit();
}
$stmtUser->close();

// ─── جيب الـ wallet أو أنشئها لو غير موجودة ────────────────────
$stmtWallet = $conn->prepare("SELECT wallet_id FROM wallets WHERE user_id = ? LIMIT 1");
$stmtWallet->bind_param("i", $user_id);
$stmtWallet->execute();
$walletResult = $stmtWallet->get_result();
$wallet = $walletResult->fetch_assoc();
$stmtWallet->close();

if (!$wallet) {
    $qr = 'wallet_qr_' . $user_id . '_' . time();
    $stmtCreate = $conn->prepare("INSERT INTO wallets (user_id, qr_code_data) VALUES (?, ?)");
    $stmtCreate->bind_param("is", $user_id, $qr);
    $stmtCreate->execute();
    $wallet_id = $conn->insert_id;
    $stmtCreate->close();
} else {
    $wallet_id = $wallet['wallet_id'];
}

// ─── سجّل عملية Top Up ──────────────────────────────────────────
$stmtTx = $conn->prepare("
    INSERT INTO transactions (wallet_id, transaction_type, amount, status)
    VALUES (?, 'Top Up', ?, 'available')
");
$stmtTx->bind_param("id", $wallet_id, $amount);
$stmtTx->execute();
$transaction_id = $conn->insert_id;
$stmtTx->close();
$conn->close();

echo json_encode([
    "status"         => "success",
    "message"        => "Top Up successful",
    "transaction_id" => $transaction_id,
    "wallet_id"      => $wallet_id,
    "amount"         => $amount,
    "payment_method" => $payment_method,
]);
?>