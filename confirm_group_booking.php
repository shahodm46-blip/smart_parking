<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$group_id      = $_POST['group_id']      ?? '';
$user_id       = $_POST['user_id']       ?? '';
$location      = $_POST['location']      ?? '';
$from_time     = $_POST['from_time']     ?? '';
$to_time       = $_POST['to_time']       ?? '';
$members_count = $_POST['members_count'] ?? '1';
$amount        = $_POST['amount']        ?? '0';

if (empty($group_id) || empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$stmt = $conn->prepare("UPDATE group_bookings SET status = 'confirmed', amount = ? WHERE group_id = ? AND user_id = ? AND status = 'pending'");
$stmt->bind_param("sii", $amount, $group_id, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Group booking confirmed successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to confirm booking", "sql_error" => $stmt->error]);
}

$stmt->close();
$conn->close();
?>