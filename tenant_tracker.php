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

// جلب البيانات + حساب الوقت المنقضي
$stmt = $conn->prepare("
    SELECT 
        so.id              AS offer_id,
        so.location,
        so.price_per_hour,
        so.status,
        so.created_at,
        u.full_name,
        u.user_id          AS tenant_id,
        ao.amount,
        ao.payment_method,
        ao.accepted_at,
        so.user_id         AS provider_id,
        TIMESTAMPDIFF(SECOND, ao.accepted_at, NOW()) AS elapsed_seconds
    FROM spot_offers so
    JOIN users u         ON so.user_id = u.user_id
    JOIN accepted_offers ao ON ao.offer_id = so.id
    WHERE so.id = ?
    ORDER BY ao.accepted_at DESC
    LIMIT 1
");
$stmt->bind_param("i", $offer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Offer not found or not accepted yet"]);
    $stmt->close();
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

// حساب عدد الساعات المحجوزة
$price_per_hour = floatval($row['price_per_hour']);
$amount         = floatval($row['amount']);
$booked_hours   = ($price_per_hour > 0) ? round($amount / $price_per_hour, 1) : 2;

// تحديث الحالة
if ($row['status'] === 'accepted') {
    $update = $conn->prepare("UPDATE spot_offers SET status = 'active' WHERE id = ?");
    $update->bind_param("i", $offer_id);
    $update->execute();
    $update->close();
}

echo json_encode([
    "status"           => "success",
    "offer_id"         => $row['offer_id'],
    "full_name"        => $row['full_name'],
    "location"         => $row['location'],
    "price_per_hour"   => $row['price_per_hour'],
    "amount"           => $row['amount'],
    "payment_method"   => $row['payment_method'],
    "booked_hours"     => $booked_hours,
    "accepted_at"      => $row['accepted_at'],
    "elapsed_seconds"  => (int)$row['elapsed_seconds'],   // ← الوقت الفعلي المنقضي
    "provider_id"      => $row['provider_id'],
    "tenant_id"        => $row['tenant_id']
]);

$conn->close();
?>