<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host     = "127.0.0.1";
$dbname   = "smart_parking_db";
$username = "root";
$password = "";
$port     = 3307;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "DB connection failed: " . $e->getMessage()]);
    exit();
}

$input  = json_decode(file_get_contents("php://input"), true);
$action = isset($input['action']) ? $input['action'] : '';

// ─────────────────────────────────────────────
if ($action === 'get_slots') {
    $garage_id = isset($input['garage_id']) ? intval($input['garage_id']) : 1;

    try {
        $stmt = $pdo->prepare("
            SELECT 
                s.sensor_id,
                s.slot_label,
                s.current_status,
                g.garage_name,
                g.price_per_hour
            FROM sensors s
            JOIN parking_garages g ON s.garage_id = g.garage_id
            WHERE s.garage_id = :garage_id
            ORDER BY s.slot_label ASC
        ");
        $stmt->execute([':garage_id' => $garage_id]);
        $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $available_count = 0;
        $formatted = [];
        foreach ($slots as $slot) {
            $is_available = $slot['current_status'] == 0;
            if ($is_available) $available_count++;
            $formatted[] = [
                "sensor_id"      => (int)$slot['sensor_id'],
                "slot_label"     => $slot['slot_label'],
                "is_available"   => $is_available,
                "price_per_hour" => (float)$slot['price_per_hour'],
            ];
        }

        echo json_encode([
            "status"          => "success",
            "garage_name"     => $slots[0]['garage_name'] ?? 'Galaxy Mall',
            "total_slots"     => count($formatted),
            "available_count" => $available_count,
            "slots"           => $formatted,
        ]);

    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit();
}

// ─────────────────────────────────────────────
if ($action === 'reserve_slot') {
    $user_id    = isset($input['user_id'])    ? intval($input['user_id'])   : 0;
    $sensor_id  = isset($input['sensor_id'])  ? intval($input['sensor_id']) : 0;
    $start_time = isset($input['start_time']) ? $input['start_time'] : date('Y-m-d H:i:s');
    $end_time   = isset($input['end_time'])   ? $input['end_time']   : date('Y-m-d H:i:s', strtotime('+1 hour'));

    if ($user_id <= 0 || $sensor_id <= 0) {
        echo json_encode(["status" => "error", "message" => "user_id and sensor_id are required"]);
        exit();
    }

    try {
        // ── تحقق إن الـ slot موجود ومش محجوز
        $stmtCheck = $pdo->prepare("SELECT current_status, garage_id FROM sensors WHERE sensor_id = :sid");
        $stmtCheck->execute([':sid' => $sensor_id]);
        $sensor = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$sensor) {
            echo json_encode(["status" => "error", "message" => "Slot not found"]);
            exit();
        }
        if ($sensor['current_status'] == 1) {
            echo json_encode(["status" => "error", "message" => "Slot is already taken"]);
            exit();
        }

        // ── جيب الـ price_per_hour من الداتا بيز
        $stmtPrice = $pdo->prepare("SELECT price_per_hour FROM parking_garages WHERE garage_id = :gid");
        $stmtPrice->execute([':gid' => $sensor['garage_id']]);
        $garage = $stmtPrice->fetch(PDO::FETCH_ASSOC);
        $price_per_hour = $garage ? (float)$garage['price_per_hour'] : 10.00;

        // ── احسب المدة والسعر ← التعديل الأساسي
        $start = new DateTime($start_time);
        $end   = new DateTime($end_time);

        $duration_hours = isset($input['duration_hours']) 
            ? intval($input['duration_hours']) 
            : max(1, ceil(($end->getTimestamp() - $start->getTimestamp()) / 3600));

        $total = isset($input['total_price']) 
            ? floatval($input['total_price']) 
            : $duration_hours * $price_per_hour;

        // ── ولد الـ QR key
        $qr_key = 'QR_' . $user_id . '_' . $sensor_id . '_' . time();

        $pdo->beginTransaction();

        // ── احفظ الحجز
        $stmtRes = $pdo->prepare("
            INSERT INTO reservations 
                (user_id, sensor_id, start_time, end_time, duration_hours, total_price, qr_code_key, status)
            VALUES 
                (:uid, :sid, :start, :end, :hours, :price, :qr, 'Ongoing')
        ");
        $stmtRes->execute([
            ':uid'   => $user_id,
            ':sid'   => $sensor_id,
            ':start' => $start_time,
            ':end'   => $end_time,
            ':hours' => $duration_hours,
            ':price' => $total,
            ':qr'    => $qr_key,
        ]);
        $reservation_id = $pdo->lastInsertId();

        // ── ابعت notification
        $stmtNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message)
            VALUES (:uid, 'Reservation Confirmed', :msg)
        ");
        $stmtNotif->execute([
            ':uid' => $user_id,
            ':msg' => 'Your spot has been reserved successfully. QR: ' . $qr_key,
        ]);

        $pdo->commit();

        echo json_encode([
            "status"         => "success",
            "message"        => "Spot reserved successfully",
            "reservation_id" => $reservation_id,
            "qr_code_key"    => $qr_key,
            "total_price"    => $total,
            "hours"          => $duration_hours,
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit();
}

// ─────────────────────────────────────────────
echo json_encode(["status" => "error", "message" => "Unknown action. Use 'get_slots' or 'reserve_slot'"]);
?>