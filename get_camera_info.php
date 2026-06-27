<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

// ✅ بسيط: بنجيب أول جراج موجود (لو عندك أكتر من جراج تقدري تبعتي garage_id من الفلاتر بدل كده)
$garage_id = $_POST['garage_id'] ?? 1;

$stmt = $conn->prepare("
    SELECT 
        g.garage_id,
        g.garage_name,
        g.address,
        c.camera_name,
        c.stream_url,
        (SELECT COUNT(*) FROM sensors s WHERE s.garage_id = g.garage_id AND s.current_status = 0) AS available_spots,
        (SELECT COUNT(*) FROM sensors s WHERE s.garage_id = g.garage_id) AS total_spots
    FROM parking_garages g
    LEFT JOIN cameras c ON c.garage_id = g.garage_id
    WHERE g.garage_id = ?
");
$stmt->bind_param("i", $garage_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Garage not found"]);
    exit;
}

$data = $result->fetch_assoc();

echo json_encode([
    "status"          => "success",
    "garage_name"     => $data['garage_name'],
    "address"         => $data['address'],
    "camera_name"     => $data['camera_name'] ?? "ESP32-CAM",
    "stream_url"      => $data['stream_url'] ?? "",
    "available_spots" => (int)$data['available_spots'],
    "total_spots"     => (int)$data['total_spots'],
]);

$conn->close();
?>
