<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id     = $_POST['user_id'] ?? '';
$offer_id    = $_POST['offer_id'] ?? null;        // مهم
$location    = $_POST['location'] ?? '';
$price       = $_POST['price'] ?? '0';
$duration    = $_POST['duration'] ?? '1';
$total       = $_POST['total'] ?? '0';

if (empty($user_id) || empty($location)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// إذا كان فيه offer_id، نتأكد إنه موجود
if ($offer_id) {
    $check = $conn->prepare("SELECT id FROM spot_offers WHERE id = ?");
    $check->bind_param("i", $offer_id);
    $check->execute();
    if ($check->get_result()->num_rows == 0) {
        echo json_encode(["status" => "error", "message" => "Offer not found"]);
        exit;
    }
}

$stmt = $conn->prepare("
    INSERT INTO counter_offers 
    (user_id, offer_id, location, price_per_hour, duration_hours, total_amount, status, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
");

$stmt->bind_param("iissss", $user_id, $offer_id, $location, $price, $duration, $total);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success", 
        "message" => "Counter offer sent successfully",
        "counter_id" => $conn->insert_id
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to send offer"]);
}

$conn->close();
?>