<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$offer_id = $_POST['offer_id'] ?? null;
$user_id  = $_POST['user_id'] ?? null;

if (empty($offer_id) || empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// ✅ تأكد إن الـ offer موجود
$check = $conn->prepare("SELECT id FROM spot_offers WHERE id = ?");
$check->bind_param("i", $offer_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Offer not found"]);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

// ✅ تسجيل الرفض باستخدام split_details (الجدول الموجود)
$stmt = $conn->prepare("
    INSERT INTO split_details (split_id, user_id, has_paid)
    VALUES (?, ?, 0)
    ON DUPLICATE KEY UPDATE has_paid = 0
");
$stmt->bind_param("ii", $offer_id, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "status"  => "success",
        "message" => "Split request declined successfully"
    ]);
} else {
    echo json_encode([
        "status"  => "error",
        "message" => "Failed to decline request"
    ]);
}

$stmt->close();
$conn->close();
?>