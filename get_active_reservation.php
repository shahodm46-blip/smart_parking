<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

date_default_timezone_set('UTC');

include 'db.php';

$input   = json_decode(file_get_contents("php://input"), true);
$user_id = $input['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT r.reservation_id, r.start_time, r.end_time, r.total_price, r.qr_code_key, r.status,
           s.slot_label, g.garage_name, g.address, g.price_per_hour, g.latitude, g.longitude
    FROM reservations r
    JOIN sensors s ON r.sensor_id = s.sensor_id
    JOIN parking_garages g ON s.garage_id = g.garage_id
    WHERE r.user_id = ? AND r.status IN ('Ongoing', 'active', 'Active', 'reserved', 'pending')
    ORDER BY r.reservation_id DESC LIMIT 1
");

if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "No active reservation found for this user."]);
    exit;
}

$row = $result->fetch_assoc();

$now       = new DateTime('now', new DateTimeZone('UTC'));
$endTime   = new DateTime($row['end_time'], new DateTimeZone('UTC'));
$remaining = max(0, $endTime->getTimestamp() - $now->getTimestamp());

echo json_encode([
    "status"            => "success",
    "reservation_id"    => $row['reservation_id'],
    "location"          => $row['garage_name'],
    "address"           => $row['address'],
    "spot"              => $row['slot_label'],
    "floor"             => "Ground Floor",
    "vehicle_type"      => "Car",
    "total_price"       => $row['total_price'],
    "qr_code"           => $row['qr_code_key'],
    "start_time"        => $row['start_time'],
    "end_time"          => $row['end_time'],
    "remaining_seconds" => $remaining,
    "latitude"          => $row['latitude'],
    "longitude"         => $row['longitude'],
]);

$stmt->close();
$conn->close();
?>