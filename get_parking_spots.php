<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

// نستخدم استعلام مباشر ومحدد لجلب البيانات للجراج رقم 1
$stmt = $conn->prepare("
    SELECT 
        (SELECT garage_name FROM parking_garages WHERE garage_id = 1) as garage_name,
        (SELECT address FROM parking_garages WHERE garage_id = 1) as address,
        (SELECT price_per_hour FROM parking_garages WHERE garage_id = 1) as price_per_hour,
        (SELECT latitude FROM parking_garages WHERE garage_id = 1) as latitude,
        (SELECT COUNT(*) FROM sensors WHERE garage_id = 1) as total_spots,
        (SELECT COUNT(*) FROM sensors WHERE garage_id = 1 AND current_status = 0) as available_spots
");

$stmt->execute();
$result = $stmt->get_result();

$spots = [];
if ($row = $result->fetch_assoc()) {
    $spots[] = [
        "id"              => 1,
        "name"            => $row['garage_name'],
        "location"        => $row['address'],
        "price_per_hour"  => $row['price_per_hour'],
        "available_spots" => $row['available_spots'],
        "total_spots"     => $row['total_spots'],
        "latitude"        => $row['latitude'] ?? 30.0074,
        "longitude"       => 30.9733,
        "rating"          => 4.8,
    ];
}

echo json_encode(["status" => "success", "spots" => $spots]);
$conn->close();
?>