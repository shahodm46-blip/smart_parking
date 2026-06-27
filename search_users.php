<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$search  = $_POST['search']  ?? '';
$user_id = $_POST['user_id'] ?? '';

if (empty($search)) {
    echo json_encode(["status" => "error", "message" => "Search query is empty"]);
    exit;
}

$stmt = $conn->prepare("SELECT user_id, full_name, username, phone_number FROM users WHERE (full_name LIKE ? OR username LIKE ?) AND user_id != ? AND is_active = 1 LIMIT 10");
$searchTerm = "%$search%";
$stmt->bind_param("ssi", $searchTerm, $searchTerm, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = [
        "id"        => $row['user_id'],
        "full_name" => $row['full_name'],
        "username"  => $row['username'],
        "phone"     => $row['phone_number'],
    ];
}

echo json_encode(["status" => "success", "users" => $users]);
$conn->close();
?>
