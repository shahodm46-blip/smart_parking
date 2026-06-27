<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$email    = $_POST['email']    ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

$stmt = $conn->prepare("SELECT user_id, full_name, username, phone_number, email, password_hash, is_admin FROM users WHERE email = ? AND is_active = 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Email not found"]);
    exit;
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password_hash'])) {
    echo json_encode(["status" => "error", "message" => "Incorrect password"]);
    exit;
}

// ✅ جيب رصيد المحفظة
$wallet = $conn->prepare("SELECT wallet_id FROM wallets WHERE user_id = ?");
$wallet->bind_param("i", $user['user_id']);
$wallet->execute();
$walletResult = $wallet->get_result();
$walletData = $walletResult->fetch_assoc();

echo json_encode([
    "status"      => "success",
    "message"     => "Login successful",
    "user_id"     => $user['user_id'],
    "full_name"   => $user['full_name'],
    "username"    => $user['username'],
    "email"       => $user['email'],
    "phone"       => $user['phone_number'],
    "wallet_id"   => $walletData['wallet_id'] ?? null,
    "is_admin"    => (bool)$user['is_admin'],     // ← Added
    "role"        => $user['is_admin'] ? "admin" : "user"  // ← Added for easier checking
]);

$conn->close();
?>