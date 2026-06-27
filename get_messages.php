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

// ✅ جلب الرسائل
$stmt = $conn->prepare("
    SELECT 
        m.message AS text,
        (m.sender_id = ?) AS isMe,
        DATE_FORMAT(m.sent_at, '%h:%i %p') AS time
    FROM messages m
    WHERE m.offer_id = ?
    ORDER BY m.sent_at ASC
");
$stmt->bind_param("ii", $user_id, $offer_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        "text" => $row['text'],
        "isMe" => (bool)$row['isMe'],
        "time" => $row['time']
    ];
}
$stmt->close();
$conn->close();

echo json_encode([
    "status" => "success", 
    "messages" => $messages
]);
?>