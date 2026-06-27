<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$offer_id    = $_POST['offer_id'] ?? null;
$user_id     = $_POST['user_id'] ?? null;
$tenant_name = $_POST['tenant_name'] ?? '';
$location    = $_POST['location'] ?? '';

if (empty($offer_id) || empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// ✅ تأكد إن الـ offer موجود في spot_offers
$check = $conn->prepare("SELECT id, status FROM spot_offers WHERE id = ?");
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

// ✅ تسجيل الـ Reminder (لو الجدول موجود)
$stmt = $conn->prepare("
    INSERT INTO reminders (offer_id, sent_by_user_id, tenant_name, location, sent_at)
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->bind_param("iiss", $offer_id, $user_id, $tenant_name, $location);

if ($stmt->execute()) {
    echo json_encode([
        "status"  => "success",
        "message" => "Reminder sent successfully"
    ]);
} else {
    echo json_encode([
        "status"  => "error", 
        "message" => "Failed to send reminder"
    ]);
}

$stmt->close();
$conn->close();
?>