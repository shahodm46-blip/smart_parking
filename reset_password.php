<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id      = $_POST['user_id']      ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($user_id) || empty($new_password)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters"]);
    exit;
}

$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
$stmt->bind_param("si", $hashed_password, $user_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Password changed successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update password"]);
}

$conn->close();
?>
