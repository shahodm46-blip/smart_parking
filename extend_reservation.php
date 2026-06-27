<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id       = $_POST['user_id']       ?? '';
$extra_seconds = $_POST['extra_seconds'] ?? '';
$extra_cost    = $_POST['additional_cost'] ?? '0';

if (empty($user_id) || empty($extra_seconds)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// ✅ تمديد وقت الانتهاء
$stmt = $conn->prepare("UPDATE reservations SET end_time = DATE_ADD(end_time, INTERVAL ? SECOND), total_price = total_price + ? WHERE user_id = ? AND status = 'Ongoing'");
$stmt->bind_param("idi", $extra_seconds, $extra_cost, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Reservation extended successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "No active reservation found"]);
}

$conn->close();
?>
