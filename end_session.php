<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id = $_POST['user_id'] ?? '';
$offer_id = $_POST['offer_id'] ?? null;   // ← جديد: دعم إنهاء من خلال offer
$rating  = $_POST['rating']  ?? '0';
$comment = $_POST['comment'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

// ✅ الطريقة الأولى: لو فيه offer_id (أفضل للـ Split Offers)
if (!empty($offer_id)) {
    // جلب reservation_id من spot_offers
    $getOffer = $conn->prepare("
        SELECT reservation_id, status 
        FROM spot_offers 
        WHERE id = ? AND user_id = ?
    ");
    $getOffer->bind_param("ii", $offer_id, $user_id);
    $getOffer->execute();
    $offerResult = $getOffer->get_result();
    $offerRow = $offerResult->fetch_assoc();
    $getOffer->close();

    if (!$offerRow || $offerRow['status'] === 'completed') {
        echo json_encode(["status" => "error", "message" => "No active offer session found"]);
        $conn->close();
        exit;
    }

    $reservation_id = $offerRow['reservation_id'];

    // تحديث spot_offers
    $updateOffer = $conn->prepare("UPDATE spot_offers SET status = 'completed' WHERE id = ?");
    $updateOffer->bind_param("i", $offer_id);
    $updateOffer->execute();
    $updateOffer->close();
} 
// ✅ الطريقة الثانية: fallback على reservations (للحالات العادية)
else {
    $getRes = $conn->prepare("
        SELECT reservation_id, sensor_id 
        FROM reservations 
        WHERE user_id = ? AND status = 'Ongoing' 
        ORDER BY reservation_id DESC LIMIT 1
    ");
    $getRes->bind_param("i", $user_id);
    $getRes->execute();
    $resResult = $getRes->get_result();
    $res = $resResult->fetch_assoc();
    $getRes->close();

    if (!$res) {
        echo json_encode(["status" => "error", "message" => "No active session found"]);
        $conn->close();
        exit;
    }

    $reservation_id = $res['reservation_id'];
    $sensor_id = $res['sensor_id'];
}

// ✅ إنهاء الحجز في جدول reservations
$stmt = $conn->prepare("
    UPDATE reservations 
    SET status = 'Completed', 
        end_time = NOW() 
    WHERE reservation_id = ?
");
$stmt->bind_param("i", $reservation_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    // تحرير السنسور
    if (isset($sensor_id)) {
        $updateSensor = $conn->prepare("UPDATE sensors SET current_status = 0 WHERE sensor_id = ?");
        $updateSensor->bind_param("i", $sensor_id);
        $updateSensor->execute();
        $updateSensor->close();
    }

    echo json_encode([
        "status" => "success", 
        "message" => "Session ended successfully"
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to end session"]);
}

$conn->close();
?>