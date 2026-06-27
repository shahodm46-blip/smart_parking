<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data)) $data = $_POST;

$qr_code = isset($data['qr_code']) ? trim($data['qr_code']) : null;
$user_id = isset($data['user_id']) ? intval($data['user_id']) : null;

if (!$qr_code || !$user_id) {
    echo json_encode(["status" => "error", "message" => "Missing fields"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        b.id           AS booking_id,
        b.offer_id,
        b.total_amount,
        b.payment_method,
        b.entry_code,
        s.location,
        s.price_per_hour,
        s.duration_hours,
        u.full_name,
        u.user_id      AS owner_id
    FROM bookings b
    JOIN spot_offers s ON s.id = b.offer_id
    JOIN users u ON u.user_id = s.user_id
    WHERE b.entry_code = ? AND b.user_id = ?
    LIMIT 1
");
$stmt->bind_param("si", $qr_code, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid QR code or not authorized"]);
    $stmt->close();
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

$update = $conn->prepare("UPDATE spot_offers SET status = 'active' WHERE id = ?");
$update->bind_param("i", $row['offer_id']);
$update->execute();
$update->close();

$conn->close();

echo json_encode([
    "status" => "success",
    "offer"  => [
        "id"             => $row['offer_id'],
        "location"       => $row['location'],
        "price_per_hour" => $row['price_per_hour'],
        "duration_hours" => $row['duration_hours'],
        "full_name"      => $row['full_name'],
        "total_amount"   => $row['total_amount'],
        "payment_method" => $row['payment_method'],
    ],
]);
?>
