<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id = $_POST['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        so.location,
        so.price_per_hour,
        so.duration_hours,
        so.created_at,
        so.expires_at,
        so.status,
        so.accepted_at,
        g.garage_name,
        g.address,
        g.latitude,
        g.longitude,
        s.slot_label
    FROM spot_offers so
    LEFT JOIN reservations r ON so.reservation_id = r.reservation_id
    LEFT JOIN sensors s ON r.sensor_id = s.sensor_id
    LEFT JOIN parking_garages g ON s.garage_id = g.garage_id
    WHERE so.user_id = ? 
      AND so.status IN ('active', 'accepted')
    ORDER BY so.created_at DESC 
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "No active offer"]);
    exit;
}

$row = $result->fetch_assoc();

// حساب الوقت المتبقي
$endTime = !empty($row['expires_at']) 
         ? strtotime($row['expires_at']) 
         : strtotime($row['created_at']) + (intval($row['duration_hours']) * 3600);

$remaining = max(0, $endTime - time());

echo json_encode([
    "status"            => "success",
    "location"          => $row['garage_name'] ?? $row['location'] ?? "Unknown Location",
    "zone"              => $row['slot_label'] ?? "Zone A",           // أو استخدم address
    "price_per_hour"    => $row['price_per_hour'],
    "remaining_seconds" => $remaining,
    "latitude"          => $row['latitude'] ?? 30.0444,   // fallback للقاهرة
    "longitude"         => $row['longitude'] ?? 31.2357,
    "address"           => $row['address'] ?? "",
]);

$conn->close();
?>