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

// جلب السجلات
$stmt = $conn->prepare("
    SELECT 
        title,
        DATE_FORMAT(created_at, '%h:%i %p') AS time,
        description AS `desc`
    FROM session_logs 
    WHERE offer_id = ?
    ORDER BY created_at ASC
");
$stmt->bind_param("i", $offer_id);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = [
        "title" => $row['title'],
        "time"  => $row['time'],
        "desc"  => $row['desc']
    ];
}
$stmt->close();

// جلب مدة الجلسة
$dur_stmt = $conn->prepare("
    SELECT TIMEDIFF(NOW(), accepted_at) AS duration
    FROM accepted_offers 
    WHERE offer_id = ? 
    ORDER BY accepted_at DESC LIMIT 1
");
$dur_stmt->bind_param("i", $offer_id);
$dur_stmt->execute();
$dur_result = $dur_stmt->get_result();
$duration = "00:00:00";

if ($dur_result->num_rows > 0) {
    $row = $dur_result->fetch_assoc();
    $duration = $row['duration'] ?? "00:00:00";
}
$dur_stmt->close();
$conn->close();

echo json_encode([
    "status"           => "success",
    "logs"             => $logs,
    "session_duration" => $duration
]);
?>