<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$offer_id = $_POST['offer_id'] ?? null;
$user_id  = $_POST['user_id'] ?? null;
$rating   = $_POST['rating'] ?? null;

if (empty($offer_id) || empty($user_id) || empty($rating)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(["status" => "error", "message" => "Rating must be between 1 and 5"]);
    exit;
}

// ✅ إدخال التقييم
$stmt = $conn->prepare("
    INSERT INTO ratings (offer_id, user_id, rating, created_at)
    VALUES (?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE rating = ?, created_at = NOW()
");
$stmt->bind_param("iiii", $offer_id, $user_id, $rating, $rating);

if ($stmt->execute()) {
    // ✅ تحديث حالة العرض إلى completed
    $update = $conn->prepare("UPDATE spot_offers SET status = 'completed' WHERE id = ?");
    $update->bind_param("i", $offer_id);
    $update->execute();
    $update->close();

    echo json_encode([
        "status"  => "success", 
        "message" => "Rating submitted successfully"
    ]);
} else {
    echo json_encode([
        "status"  => "error", 
        "message" => "Failed to submit rating"
    ]);
}

$stmt->close();
$conn->close();
?>