<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id = $_POST['user_id'] ?? 0;

if ($user_id <= 0) {
    echo json_encode(["status" => "error", "message" => "User ID required"]);
    exit;
}

$ongoing = [];
$history = [];

$stmt = $conn->prepare("
    SELECT 
        r.reservation_id,
        r.start_time,
        r.end_time,
        r.total_price,
        r.status,
        r.entrance_code,
        s.slot_label,
        g.garage_name as location
    FROM reservations r
    JOIN sensors s ON r.sensor_id = s.sensor_id
    JOIN parking_garages g ON s.garage_id = g.garage_id
    WHERE r.user_id = ?
    ORDER BY r.start_time DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $item = [
        "reservation_id" => (int)$row['reservation_id'],
        "location"       => $row['location'],
        "slot_label"     => $row['slot_label'],
        "start_time"     => $row['start_time'],
        "end_time"       => $row['end_time'],
        "total_price"    => (float)$row['total_price'],
        "status"         => $row['status'],
        "entrance_code"  => $row['entrance_code'],
    ];

    if ($row['status'] == 'Ongoing') {
        $ongoing[] = $item;
    } else {
        $history[] = $item;
    }
}

echo json_encode([
    "status"  => "success",
    "ongoing" => $ongoing,
    "history" => $history
]);

$conn->close();
?>