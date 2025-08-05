<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Get user ID from query parameter
$userId = isset($_GET['user_id']) ? $_GET['user_id'] : null;

try {
    // Build WHERE clause based on user ID
    $whereClause = $userId ? "WHERE created_by = ?" : "";
    $params = $userId ? [$userId] : [];
    
    // Get total programs count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM programs " . $whereClause);
    if ($userId) {
        $stmt->bind_param("i", $userId);
    }
    $stmt->execute();
    $totalPrograms = $stmt->get_result()->fetch_assoc()['total'];
    


    // Get payment completed programs count
    $stmt = $conn->prepare("SELECT COUNT(*) as payment_completed FROM programs WHERE status = 'payment_completed'" . ($userId ? " AND created_by = ?" : ""));
    if ($userId) {
        $stmt->bind_param("i", $userId);
    }
    $stmt->execute();
    $paymentCompletedPrograms = $stmt->get_result()->fetch_assoc()['payment_completed'];

    // Get rejected programs count
    $stmt = $conn->prepare("SELECT COUNT(*) as rejected FROM programs WHERE status = 'rejected'" . ($userId ? " AND created_by = ?" : ""));
    if ($userId) {
        $stmt->bind_param("i", $userId);
    }
    $stmt->execute();
    $rejectedPrograms = $stmt->get_result()->fetch_assoc()['rejected'];

    // Get pending programs count (all statuses except payment_completed and rejected)
    $stmt = $conn->prepare("SELECT COUNT(*) as pending FROM programs WHERE status NOT IN ('payment_completed', 'rejected')" . ($userId ? " AND created_by = ?" : ""));
    if ($userId) {
        $stmt->bind_param("i", $userId);
    }
    $stmt->execute();
    $pendingPrograms = $stmt->get_result()->fetch_assoc()['pending'];

    // Get recent programs (last 5)
    $stmt = $conn->prepare("SELECT id, program_name, budget, recipient_name, status, created_by, created_at FROM programs " . $whereClause . " ORDER BY id DESC LIMIT 5");
    if ($userId) {
        $stmt->bind_param("i", $userId);
    }
    $stmt->execute();
    $recentProgramsResult = $stmt->get_result();
    $recentPrograms = [];
    while ($row = $recentProgramsResult->fetch_assoc()) {
        $recentPrograms[] = [
            'id' => $row['id'],
            'name' => $row['program_name'],
            'budget' => (float)$row['budget'],
            'recipient' => $row['recipient_name'],
            'status' => $row['status'],
            'submittedBy' => $row['created_by'],
            'createdAt' => $row['created_at']
        ];
    }

    $stats = [
        'total' => (int)$totalPrograms,
        'payment_completed' => (int)$paymentCompletedPrograms,
        'rejected' => (int)$rejectedPrograms,
        'pending' => (int)$pendingPrograms
    ];

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_programs' => $recentPrograms
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching dashboard data: ' . $e->getMessage()
    ]);
}

$conn->close();
?> 