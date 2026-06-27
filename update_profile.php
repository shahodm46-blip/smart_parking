<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id  = $_POST['user_id']  ?? '';
$username = $_POST['username'] ?? '';
$email    = $_POST['email']    ?? '';
$phone    = $_POST['phone']    ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

if (empty($username) || empty($email) || empty($phone)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// Check username taken by another user
$check = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
$check->bind_param("si", $username, $user_id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Username already taken"]);
    exit;
}

// Check email taken by another user
$checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$checkEmail->bind_param("si", $email, $user_id);
$checkEmail->execute();
$checkEmail->store_result();
if ($checkEmail->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Email already used"]);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, phone_number = ? WHERE user_id = ?");
$stmt->bind_param("sssi", $username, $email, $phone, $user_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Profile updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update profile"]);
}

$conn->close();
?>
