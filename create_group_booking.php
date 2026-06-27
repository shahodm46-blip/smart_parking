<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// ✅ بيقرا JSON و POST مع بعض
$input         = json_decode(file_get_contents("php://input"), true);
$user_id       = $input['user_id']    ?? $_POST['user_id']    ?? '';
$group_size    = $input['group_size'] ?? $_POST['group_size'] ?? '1';
$group_name    = $input['group_name'] ?? $_POST['group_name'] ?? '';
$location_name = $input['location']   ?? $_POST['location']   ?? null;
$from_time     = $input['from_time']  ?? $_POST['from_time']  ?? null;
$to_time       = $input['to_time']    ?? $_POST['to_time']    ?? null;
$member_ids    = $input['member_ids'] ?? $_POST['member_ids'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

// ✅ كود 6 أرقام عشوائي
$pass_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// ✅ QR code للتتبع الداخلي
$qr_code = "GROUP_" . $user_id . "_" . time();

$stmt = $conn->prepare("
    INSERT INTO group_bookings 
        (user_id, location_name, from_time, to_time, total_slots_booked, group_qr_code, pass_code) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("isssisr",
    $user_id,
    $location_name,
    $from_time,
    $to_time,
    $group_size,
    $qr_code,
    $pass_code
);

if (!$stmt->execute()) {
    echo json_encode([
        "status"    => "error",
        "message"   => "Failed to create group booking",
        "sql_error" => $stmt->error
    ]);
    exit;
}

$group_id = $conn->insert_id;
$stmt->close();

// ✅ إضافة الأعضاء
if (!empty($member_ids)) {
    $idsArray = is_array($member_ids)
        ? $member_ids
        : array_filter(array_map('intval', explode(',', $member_ids)));

    $memberStmt = $conn->prepare("
        INSERT INTO group_booking_members (group_id, user_id) VALUES (?, ?)
    ");
    foreach ($idsArray as $memberId) {
        if ($memberId > 0) {
            $memberStmt->bind_param("ii", $group_id, $memberId);
            $memberStmt->execute();
        }
    }
    $memberStmt->close();
}

// ✅ إضافة الـ owner نفسه كعضو
$ownerStmt = $conn->prepare("
    INSERT IGNORE INTO group_booking_members (group_id, user_id) VALUES (?, ?)
");
$ownerStmt->bind_param("ii", $group_id, $user_id);
$ownerStmt->execute();
$ownerStmt->close();

echo json_encode([
    "status"    => "success",
    "message"   => "Group booking created successfully",
    "group_id"  => $group_id,
    "qr_code"   => $qr_code,
    "pass_code" => $pass_code,
]);

$conn->close();
?>