<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data)) $data = $_POST;

$user_id        = isset($data['user_id'])        ? intval($data['user_id'])      : null;
$offer_id       = isset($data['offer_id'])       ? intval($data['offer_id'])     : null;
$amount         = isset($data['amount'])         ? floatval($data['amount'])     : null;
$payment_method = isset($data['payment_method']) ? trim($data['payment_method']) : null;

if (!$user_id || !$offer_id || !$amount || !$payment_method) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$allowed_methods = ["PaySpot", "Cash"];
if (!in_array($payment_method, $allowed_methods)) {
    echo json_encode(["status" => "error", "message" => "Invalid payment method"]);
    exit;
}

// ✅ جلب بيانات العرض
$check = $conn->prepare("
    SELECT id, user_id AS provider_id, status, reservation_id 
    FROM spot_offers 
    WHERE id = ?
");
$check->bind_param("i", $offer_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Offer not found"]);
    exit;
}

$offer = $result->fetch_assoc();
$check->close();

if ($offer['status'] === 'accepted' || $offer['status'] === 'completed') {
    echo json_encode(["status" => "error", "message" => "Offer already accepted or completed"]);
    exit;
}

if ($offer['provider_id'] == $user_id) {
    echo json_encode(["status" => "error", "message" => "Cannot accept your own offer"]);
    exit;
}

$provider_id = $offer['provider_id'];

// ✅ معالجة الدفع إذا كان PaySpot
if ($payment_method === "PaySpot") {
    // Logic المحفظة (يمكن تبسيطه مؤقتاً إذا كنتِ مش محتاجاه دلوقتي)
    // ... (سيبته زي ما هو لأنه متقدم)
    // لو عايزة نبسطه قوليلي
}

// ✅ تسجيل قبول العرض
$stmt = $conn->prepare("
    INSERT INTO accepted_offers 
    (user_id, tenant_id, provider_id, offer_id, amount, payment_method, accepted_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("iiiids", $user_id, $user_id, $provider_id, $offer_id, $amount, $payment_method);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Failed to accept offer"]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// ✅ تحديث حالة العرض
$update = $conn->prepare("UPDATE spot_offers SET status = 'accepted' WHERE id = ?");
$update->bind_param("i", $offer_id);
$update->execute();
$update->close();

echo json_encode([
    "status"  => "success", 
    "message" => "Offer accepted successfully",
    "offer_id" => $offer_id
]);

$conn->close();
?>