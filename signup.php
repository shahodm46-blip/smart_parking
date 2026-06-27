<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$full_name = $_POST['full_name']    ?? '';
$username  = $_POST['username']     ?? '';
$phone     = $_POST['phone_number'] ?? $_POST['phone'] ?? '';
$email     = $_POST['email']        ?? '';
$password  = $_POST['password']     ?? '';

if (empty($full_name) || empty($username) || empty($phone) || empty($email) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

$check = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Username already taken"]);
    exit;
}

$checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$checkEmail->bind_param("s", $email);
$checkEmail->execute();
$checkEmail->store_result();
if ($checkEmail->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already registered"]);
    exit;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (full_name, username, email, phone_number, password_hash) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $full_name, $username, $email, $phone, $hashed_password);

if ($stmt->execute()) {
    $user_id = $conn->insert_id;

    // ✅ إنشاء محفظة للمستخدم تلقائياً
    $qr_code = "wallet_" . $user_id . "_" . time();
    $wallet = $conn->prepare("INSERT INTO wallets (user_id, qr_code_data) VALUES (?, ?)");
    $wallet->bind_param("is", $user_id, $qr_code);
    $wallet->execute();

    echo json_encode(["status" => "success", "message" => "Account created successfully", "user_id" => $user_id]);
} else {
    echo json_encode(["status" => "error", "message" => "Registration failed: " . $conn->error]);
}

$conn->close();
?>
