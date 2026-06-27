<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include 'db.php';

// ✅ بيقرا من GET و POST و JSON مع بعض
$input   = json_decode(file_get_contents("php://input"), true);
$user_id = $input['user_id'] ?? $_POST['user_id'] ?? $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "User not identified"]);
    exit;
}

// ✅ ضفنا is_admin في الـ SELECT
$stmt = $conn->prepare("
    SELECT full_name, username, email, phone_number, 
           profile_picture, header_picture, created_at, is_admin
    FROM users 
    WHERE user_id = ? AND is_active = 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "User not found"]);
    exit;
}

$user = $result->fetch_assoc();

echo json_encode([
    "status"          => "success",
    "full_name"       => $user['full_name'],
    "username"        => $user['username'],
    "email"           => $user['email'],
    "phone"           => $user['phone_number'],
    "profile_picture" => $user['profile_picture'],
    "header_picture"  => $user['header_picture'],
    "created_at"      => $user['created_at'],
    "is_admin"        => (bool)$user['is_admin'], // ✅ مهم
]);

$conn->close();
?>