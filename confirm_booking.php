<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data)) $data = $_POST;

$offer_id       = isset($data['offer_id'])       ? intval($data['offer_id'])      : null;
$user_id        = isset($data['user_id'])         ? intval($data['user_id'])       : null;
$payment_method = isset($data['payment_method'])  ? trim($data['payment_method'])  : null;
$total_amount   = isset($data['total_amount'])    ? floatval($data['total_amount']): null;

if (!$offer_id || !$user_id || !$payment_method || !$total_amount) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$check = $conn->prepare("SELECT id, status FROM spot_offers WHERE id = ?");
$check->bind_param("i", $offer_id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Offer not found"]);
    exit;
}

$offer = $result->fetch_assoc();
if ($offer['status'] === 'booked') {
    echo json_encode(["status" => "error", "message" => "Offer already booked"]);
    $conn->close();
    exit;
}
$check->close();

$reference  = "#PS-" . strtoupper(substr(md5($offer_id . $user_id . time()), 0, 5));
$entry_code = rand(100, 999) . "-" . rand(100, 999);
$date       = date("F d, Y");

$stmt = $conn->prepare("
    INSERT INTO bookings (offer_id, user_id, payment_method, total_amount, reference, entry_code, booked_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("iisdss", $offer_id, $user_id, $payment_method, $total_amount, $reference, $entry_code);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Failed to save booking"]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

$update = $conn->prepare("UPDATE spot_offers SET status = 'booked' WHERE id = ?");
$update->bind_param("i", $offer_id);
$update->execute();
$update->close();

$conn->close();

echo json_encode([
    "status"     => "success",
    "reference"  => $reference,
    "entry_code" => $entry_code,
    "date"       => $date,
]);
?>
