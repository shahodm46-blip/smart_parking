<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'db.php';

$user_id     = $_POST['user_id']     ?? '';
$issue_type  = $_POST['issue_type']  ?? '';
$description = $_POST['description'] ?? '';

if (empty($user_id) || empty($issue_type)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// ✅ جديد: استقبال الصورة لو موجودة وحفظها في مجلد uploads
$image_path = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/uploads/reports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileExt  = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $fileName = "report_" . $user_id . "_" . time() . "." . $fileExt;
    $destPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
        $image_path = "uploads/reports/" . $fileName;
    }
}

// ✅ حفظ الريبورت كـ notification للأدمن
$title   = "Report: $issue_type";
$message = $description;

$stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, image_path) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $title, $message, $image_path);

if ($stmt->execute()) {
    $report_id = $conn->insert_id;

    // ✅ جديد: جلب الوقت الحقيقي من السيرفر بعد الحفظ
    $timeResult = $conn->query("SELECT created_at FROM notifications WHERE notification_id = $report_id");
    $timeRow = $timeResult->fetch_assoc();

    echo json_encode([
        "status"     => "success",
        "message"    => "Report submitted successfully",
        "ticket_id"  => "PS-" . (88000 + $report_id), // ✅ رقم تذكرة حقيقي ومتسلسل
        "created_at" => $timeRow['created_at'],         // ✅ الوقت الحقيقي للتقديم
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to submit report"]);
}

$conn->close();
?>