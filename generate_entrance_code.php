<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$input = json_decode(file_get_contents("php://input"), true);

$reservation_id = $input['reservation_id'] ?? 0;
$user_id        = $input['user_id'] ?? 0;

if ($reservation_id <= 0 || $user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing reservation_id or user_id"]);
    exit;
}

// توليد كود 6 أرقام جديد
function generateEntranceCode() {
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= rand(0, 9);
    }
    return $code;
}

$entrance_code = generateEntranceCode();

// تحديث الـ entrance_code في الجدول
$stmt = $conn->prepare("
    UPDATE reservations 
    SET entrance_code = ? 
    WHERE reservation_id = ? AND user_id = ?
");
$stmt->bind_param("sii", $entrance_code, $reservation_id, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode([
        "status"        => "success",
        "message"       => "Entrance code generated successfully",
        "entrance_code" => $entrance_code,
        "valid_for"     => "15 minutes"
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update entrance code or reservation not found"]);
}

$conn->close();
?>