<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id = $_POST['user_id'] ?? '';
$type    = $_POST['type']    ?? '';

if (empty($user_id) || empty($type) || !in_array($type, ['profile', 'header'])) {
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}

if (!isset($_FILES['picture']) || $_FILES['picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status" => "error", "message" => "No image uploaded"]);
    exit;
}

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$extension = pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION);
$fileName  = $type . "_" . $user_id . "_" . time() . "." . $extension;
$destPath  = $uploadDir . $fileName;

if (!move_uploaded_file($_FILES['picture']['tmp_name'], $destPath)) {
    echo json_encode(["status" => "error", "message" => "Failed to save image"]);
    exit;
}

$column = $type === 'profile' ? 'profile_picture' : 'header_picture';

$stmt = $conn->prepare("UPDATE users SET $column = ? WHERE user_id = ?");
$stmt->bind_param("si", $fileName, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        "status"     => "success",
        "message"    => "Picture updated successfully",
        "file_name"  => $fileName,
        "file_url"   => "uploads/" . $fileName,
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Database update failed", "sql_error" => $stmt->error]);
}

$conn->close();
?>
