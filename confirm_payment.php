<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id        = $_POST['user_id']        ?? '';
$amount         = $_POST['amount']         ?? '0';
$payment_method = $_POST['payment_method'] ?? '';
$members_count  = $_POST['members_count']  ?? '1';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

// ✅ جيب الـ wallet_id
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

// ✅ تسجيل المعاملة
$stmt = $conn->prepare("INSERT INTO transactions (wallet_id, transaction_type, amount, status) VALUES (?, 'Payment', ?, 'available')");
$stmt->bind_param("id", $wallet_id, $amount);

if ($stmt->execute()) {

    // ✅ توليد pass code عشوائي 6 أرقام
    $pass_code = rand(100000, 999999);

    // ✅ حفظ الـ pass code في جدول group_bookings
    $updatePass = $conn->prepare("UPDATE group_bookings SET pass_code = ? WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $updatePass->bind_param("si", $pass_code, $user_id);
    $updatePass->execute();

    // ✅ رجّع الـ pass code للـ Flutter
    echo json_encode([
        "status"    => "success",
        "message"   => "Payment confirmed successfully",
        "pass_code" => (string)$pass_code
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "Payment failed"]);
}

$conn->close();
?>