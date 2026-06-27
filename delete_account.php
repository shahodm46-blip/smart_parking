<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id = $_POST['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

// ✅ تعطيل الحساب بدل الحذف الكامل للحفاظ على البيانات
$stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Account deleted successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete account"]);
}

$conn->close();
?>
