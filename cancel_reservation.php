<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$input = json_decode(file_get_contents("php://input"), true) ?: $_POST;

$user_id        = $input['user_id'] ?? 0;
$reservation_id = $input['reservation_id'] ?? 0;

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing user_id"]);
    exit;
}

// ✅ جيب sensor_id قبل الإلغاء
$get = $conn->prepare("
    SELECT sensor_id, entrance_code 
    FROM reservations 
    WHERE user_id = ? 
      AND (reservation_id = ? OR reservation_id IS NULL) 
      AND status = 'Ongoing' 
    ORDER BY reservation_id DESC LIMIT 1
");
$get->bind_param("ii", $user_id, $reservation_id);
$get->execute();
$getResult = $get->get_result();
$res = $getResult->fetch_assoc();

if (!$res) {
    echo json_encode(["status" => "error", "message" => "No active reservation found"]);
    exit;
}

$sensor_id = $res['sensor_id'];

// ✅ إلغاء الحجز
$stmt = $conn->prepare("
    UPDATE reservations 
    SET status = 'Cancelled', 
        end_time = NOW() 
    WHERE user_id = ? 
      AND status = 'Ongoing'
");
$stmt->bind_param("i", $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    // ✅ تحرير السنسور
    $update = $conn->prepare("UPDATE sensors SET current_status = 0 WHERE sensor_id = ?");
    $update->bind_param("i", $sensor_id);
    $update->execute();

    echo json_encode([
        "status" => "success", 
        "message" => "Reservation cancelled successfully",
        "reservation_id" => $reservation_id,
        "sensor_id" => $sensor_id
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to cancel reservation"]);
}

$conn->close();
?>