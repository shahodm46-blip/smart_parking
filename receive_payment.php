<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data)) $data = $_POST;

$offer_id = isset($data['offer_id']) ? intval($data['offer_id']) : null;
$user_id  = isset($data['user_id'])  ? intval($data['user_id'])  : null;

if (!$offer_id || !$user_id) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// ✅ جيب بيانات العرض بدون اسم مقدّم الخدمة هنا (هنجيبه لو احتجناه لاحقًا)
$stmt = $conn->prepare("
    SELECT id AS offer_id, location, price_per_hour, status, user_id AS provider_id
    FROM spot_offers
    WHERE id = ?
");
$stmt->bind_param("i", $offer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Offer not found"]);
    $stmt->close();
    $conn->close();
    exit;
}

$offer = $result->fetch_assoc();
$stmt->close();

if ($offer['status'] !== 'accepted') {
    echo json_encode(["status" => "error", "message" => "Offer has not been accepted yet"]);
    $conn->close();
    exit;
}

// ✅ جيب بيانات الدفع الحقيقية + اسم الدافع نفسه (مش مقدّم الخدمة)
$stmt2 = $conn->prepare("
    SELECT a.amount, a.payment_method, a.accepted_at, u.full_name AS payer_name
    FROM accepted_offers a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.offer_id = ?
    ORDER BY a.accepted_at DESC
    LIMIT 1
");
$stmt2->bind_param("i", $offer_id);
$stmt2->execute();
$result2 = $stmt2->get_result();

if ($result2->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "No payment record found for this offer"]);
    $stmt2->close();
    $conn->close();
    exit;
}

$accepted = $result2->fetch_assoc();
$stmt2->close();

$amount         = floatval($accepted['amount']);
$payment_method = $accepted['payment_method'];
$accepted_at    = $accepted['accepted_at'];
$payer_name     = $accepted['payer_name']; // ✅ اسم الدافع الحقيقي

$reference = "#REC-" . strtoupper(substr(md5($offer_id . $user_id . $accepted_at), 0, 6));
$formatted_date = date("F d, Y", strtotime($accepted_at));

echo json_encode([
    "status"         => "success",
    "offer_id"       => $offer['offer_id'],
    "full_name"      => $payer_name, // ✅ اسم الدافع، مش مقدّم الخدمة
    "location"       => $offer['location'],
    "price_per_hour" => $offer['price_per_hour'],
    "amount"         => $amount,
    "payment_method" => $payment_method,
    "reference"      => $reference,
    "date"           => $formatted_date,
]);

$conn->close();
?>