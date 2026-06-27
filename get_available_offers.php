<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id = $_POST['user_id'] ?? '';

$stmt = $conn->prepare("
    SELECT s.id, s.location, s.price_per_hour, s.duration_hours, s.created_at,
           u.full_name, u.username
    FROM spot_offers s
    JOIN users u ON s.user_id = u.user_id
    WHERE s.status = 'active' AND s.user_id != ?
    ORDER BY s.created_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$offers = [];
while ($row = $result->fetch_assoc()) {
    $createdAt       = strtotime($row['created_at']);
    $durationSeconds = intval($row['duration_hours']) * 3600;
    $elapsed         = time() - $createdAt;
    $remaining       = max(0, $durationSeconds - $elapsed);

    $untilTime = date("g:i A", $createdAt + $durationSeconds);

    $offers[] = [
        "id"            => $row['id'],
        "location"      => $row['location'],
        "zone"          => "Zone A",
        "price_per_hour"=> $row['price_per_hour'],
        "full_name"     => $row['full_name'],
        "username"      => $row['username'],
        "distance"      => "Nearby",
        "until_time"    => "Until $untilTime",
        "remaining_seconds" => $remaining,
    ];
}

echo json_encode(["status" => "success", "offers" => $offers]);

$conn->close();
?>