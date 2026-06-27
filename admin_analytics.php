<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

include 'db.php'; // غيري المسار إذا لزم الأمر

// التحقق من أن المستخدم أدمن من جدول admins
$isAdmin = false;
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($userId > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as is_admin FROM admins WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $isAdmin = ($row['is_admin'] > 0);
}

if (!$isAdmin) {
    echo json_encode([
        "success" => false,
        "message" => "Access denied. Admin only."
    ]);
    exit;
}

// ====================== إحصائيات الأدمن ======================

// حالة المواقف
$spotsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status IN ('occupied', 'booked') THEN 1 ELSE 0 END) as occupied
FROM parking_spots";
$spotsResult = mysqli_query($conn, $spotsQuery);
$spots = mysqli_fetch_assoc($spotsResult) ?: ['total' => 0, 'occupied' => 0];

// الحجوزات
$resQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired
FROM reservations";
$resResult = mysqli_query($conn, $resQuery);
$reservations = mysqli_fetch_assoc($resResult) ?: [];

// الإيرادات (آخر 7 أيام)
$revenueQuery = "SELECT 
    DATE(created_at) as day,
    SUM(amount) as revenue
FROM transactions 
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(created_at) 
ORDER BY day ASC";
$revResult = mysqli_query($conn, $revenueQuery);

$dailyRevenue = [];
while ($row = mysqli_fetch_assoc($revResult)) {
    $dailyRevenue[] = [
        "day" => $row['day'],
        "revenue" => (float)$row['revenue']
    ];
}

// طرق الدفع
$pmQuery = "SELECT payment_method, SUM(amount) as total 
            FROM transactions 
            WHERE payment_method IS NOT NULL 
            GROUP BY payment_method";
$pmResult = mysqli_query($conn, $pmQuery);

$paymentMethods = [];
while ($row = mysqli_fetch_assoc($pmResult)) {
    $paymentMethods[$row['payment_method']] = (float)$row['total'];
}

// أكثر المواقف حجزاً
$topSpotsQuery = "SELECT spot_id, COUNT(*) as count 
                  FROM reservations 
                  GROUP BY spot_id 
                  ORDER BY count DESC LIMIT 5";
$topResult = mysqli_query($conn, $topSpotsQuery);
$topSpots = [];
while ($row = mysqli_fetch_assoc($topResult)) {
    $topSpots[] = $row;
}

echo json_encode([
    "success" => true,
    "spots" => [
        "total" => (int)$spots['total'],
        "occupied" => (int)$spots['occupied']
    ],
    "reservations" => $reservations,
    "revenue" => [
        "total" => 0,
        "daily_last_7_days" => $dailyRevenue,
        "by_payment_method" => $paymentMethods
    ],
    "top_spots" => $topSpots,
    "peak_hours" => [],
    "users" => ["top_by_balance" => []],
    "reports" => ["total" => 0]
]);

$conn->close();
?>