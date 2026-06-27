<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id        = $_POST['user_id']        ?? '';
$reservation_id = $_POST['reservation_id'] ?? '';
$location       = $_POST['location']       ?? '';
$price_per_hour = $_POST['price_per_hour'] ?? '0';
$duration_hours = $_POST['duration_hours'] ?? '1';
$total_earnings = $_POST['total_earnings'] ?? '0';

if (empty($user_id) || empty($reservation_id) || empty($location)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// ✅ التأكد من الحجز النشط
$checkStmt = $conn->prepare("
    SELECT reservation_id FROM reservations
    WHERE reservation_id = ? AND user_id = ? AND status = 'Ongoing'
    LIMIT 1
");
$checkStmt->bind_param("ii", $reservation_id, $user_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "No active reservation found to offer"]);
    exit;
}
$checkStmt->close();

// ✅ منع التكرار
$dupStmt = $conn->prepare("
    SELECT id FROM spot_offers
    WHERE reservation_id = ? AND status IN ('active', 'accepted')
    LIMIT 1
");
$dupStmt->bind_param("i", $reservation_id);
$dupStmt->execute();
$dupResult = $dupStmt->get_result();

if ($dupResult->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "This reservation is already being offered"]);
    exit;
}
$dupStmt->close();

// ✅ حساب وقت انتهاء العرض
$durationSeconds = intval($duration_hours) * 3600;
$expiresAt = date('Y-m-d H:i:s', time() + $durationSeconds);

// ✅ إدراج العرض الجديد
$stmt = $conn->prepare("
    INSERT INTO spot_offers 
    (user_id, reservation_id, location, price_per_hour, duration_hours, 
     total_earnings, status, created_at, expires_at)
    VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), ?)
");
$stmt->bind_param("iissssi", 
    $user_id, 
    $reservation_id, 
    $location, 
    $price_per_hour, 
    $duration_hours, 
    $total_earnings, 
    $expiresAt
);

if ($stmt->execute()) {
    echo json_encode([
        "status"   => "success",
        "message"  => "Spot offered successfully",
        "offer_id" => $conn->insert_id,
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to offer spot"]);
}

$stmt->close();
$conn->close();
?>