<?php
// ==================== send_message.php ====================
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$host = "localhost"; $db = "your_database_name"; $user = "root"; $password = "12345678";
$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) { echo json_encode(["status" => "error", "message" => "Database connection failed"]); exit; }

$data = json_decode(file_get_contents("php://input"), true);
if (empty($data)) $data = $_POST;

$offer_id  = isset($data['offer_id'])  ? intval($data['offer_id'])  : null;
$sender_id = isset($data['sender_id']) ? intval($data['sender_id']) : null;
$message   = isset($data['message'])   ? trim($data['message'])     : null;

if (!$offer_id || !$sender_id || !$message) { echo json_encode(["status" => "error", "message" => "Missing fields"]); exit; }

$stmt = $conn->prepare("INSERT INTO messages (offer_id, sender_id, message, sent_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $offer_id, $sender_id, $message);

if (!$stmt->execute()) { echo json_encode(["status" => "error", "message" => "Failed to send message"]); $stmt->close(); $conn->close(); exit; }

$stmt->close();
$conn->close();

echo json_encode(["status" => "success", "message" => "Message sent"]);
