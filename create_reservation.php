<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include 'db.php';

$input = json_decode(file_get_contents("php://input"), true);

$user_id        = $input['user_id'] ?? 0;
$sensor_id      = $input['sensor_id'] ?? 0;
$duration_hours = $input['duration_hours'] ?? 1;
$start_time     = $input['start_time'] ?? date('Y-m-d H:i:s');
$end_time       = $input['end_time'] ?? date('Y-m-d H:i:s', strtotime("+$duration_hours hour"));

if ($user_id <= 0 || $sensor_id <= 0) {
    echo json_encode(["status" => "error", "message" => "user_id and sensor_id are required"]);
    exit;
}

// توليد entrance_code (6 أرقام)
function generateEntranceCode() {
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= rand(0, 9);
    }
    return $code;
}

$entrance_code = generateEntranceCode();

// التحقق من توفر السنسور
$check = $conn->prepare("SELECT current_status FROM sensors WHERE sensor_id = ?");
$check->bind_param("i", $sensor_id);
$check->execute();
$result = $check->get_result();
$sensor = $result->fetch_assoc();

if (!$sensor) {
    echo json_encode(["status" => "error", "message" => "Spot not found"]);
    exit;
}

if ($sensor['current_status'] == 1) {
    echo json_encode(["status" => "error", "message" => "This spot is already taken"]);
    exit;
}

// جلب سعر الساعة
$garage = $conn->prepare("SELECT price_per_hour FROM parking_garages WHERE garage_id = (SELECT garage_id FROM sensors WHERE sensor_id = ?)");
$garage->bind_param("i", $sensor_id);
$garage->execute();
$garageData = $garage->get_result()->fetch_assoc();
$price_per_hour = $garageData['price_per_hour'] ?? 30.00;

// حساب السعر الإجمالي
$total_price = $duration_hours * $price_per_hour;

// إنشاء الحجز
$stmt = $conn->prepare("
    INSERT INTO reservations 
    (user_id, sensor_id, start_time, end_time, duration_hours, total_price, entrance_code, status) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Ongoing')
");

$stmt->bind_param("iissdis", 
    $user_id, 
    $sensor_id, 
    $start_time, 
    $end_time, 
    $duration_hours, 
    $total_price, 
    $entrance_code
);

if ($stmt->execute()) {
    $reservation_id = $conn->insert_id;

    // تحديث حالة السنسور
    $update = $conn->prepare("UPDATE sensors SET current_status = 1 WHERE sensor_id = ?");
    $update->bind_param("i", $sensor_id);
    $update->execute();

    echo json_encode([
        "status"          => "success",
        "message"         => "Reservation created successfully",
        "reservation_id"  => $reservation_id,
        "entrance_code"   => $entrance_code,
        "total_price"     => round($total_price, 2),
        "duration_hours"  => $duration_hours,
        "price_per_hour"  => round($price_per_hour, 2)
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to create reservation"]);
}

$conn->close();
?>