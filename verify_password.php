<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id  = $_POST['user_id']  ?? '';
$password = $_POST['password'] ?? '';

if (empty($user_id) || empty($password)) {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

$stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode(["status" => "error", "message" => "Incorrect password"]);
    exit;
}

echo json_encode(["status" => "success", "message" => "Password verified"]);
$conn->close();
?>
