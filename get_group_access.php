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

$input    = json_decode(file_get_contents("php://input"), true);
$group_id = $input['group_id'] ?? $_POST['group_id'] ?? '';
$user_id  = $input['user_id']  ?? $_POST['user_id']  ?? '';

if (empty($group_id) || empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "Missing group_id or user_id"]);
    exit;
}

// ── جيب بيانات الجروب
$stmt = $conn->prepare("
    SELECT group_id, location_name, pass_code, status, amount
    FROM group_bookings
    WHERE group_id = ? AND user_id = ?
");
$stmt->bind_param("ii", $group_id, $user_id);
$stmt->execute();
$group = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$group) {
    echo json_encode(["status" => "error", "message" => "Group booking not found"]);
    exit;
}

// ── لو pass_code لسه NULL ولّد واحد وحفظه
$pass_code = $group['pass_code'];
if (empty($pass_code)) {
    $pass_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $upd = $conn->prepare("UPDATE group_bookings SET pass_code = ? WHERE group_id = ?");
    $upd->bind_param("si", $pass_code, $group_id);
    $upd->execute();
    $upd->close();
}

// ── جيب الأعضاء الحقيقيين من الداتا بيز
$mStmt = $conn->prepare("
    SELECT u.full_name, u.email,
           CASE WHEN gb.user_id = u.user_id THEN 'Primary Owner' ELSE 'Member' END AS role
    FROM group_booking_members gbm
    JOIN users u ON gbm.user_id = u.user_id
    JOIN group_bookings gb ON gbm.group_id = gb.group_id
    WHERE gbm.group_id = ?
    ORDER BY role DESC
");
$mStmt->bind_param("i", $group_id);
$mStmt->execute();
$membersResult = $mStmt->get_result();
$mStmt->close();

$members = [];
while ($row = $membersResult->fetch_assoc()) {
    $members[] = [
        "name" => $row['full_name'],
        "role" => $row['role'],
    ];
}

echo json_encode([
    "status"    => "success",
    "pass_code" => $pass_code,
    "location"  => $group['location_name'],
    "members"   => $members,
]);

$conn->close();
?>
