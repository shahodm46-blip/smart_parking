<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id = $_POST['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

$stmt = $conn->prepare("UPDATE counter_offers SET status = 'cancelled' WHERE user_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Request cancelled successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to cancel request"]);
}

$conn->close();
?>
