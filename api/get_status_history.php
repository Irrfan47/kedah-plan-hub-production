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

// Get parameters
$programId = isset($_GET['program_id']) ? $_GET['program_id'] : null;

try {
    if (!$programId) {
        throw new Exception('Program ID is required');
    }

    // Get status history for the program
    $stmt = $conn->prepare("
        SELECT 
            sh.id,
            sh.status,
            sh.changed_at,
            sh.remarks,
            u.full_name as changed_by_name,
            u.email as changed_by_email
        FROM status_history sh
        LEFT JOIN users u ON sh.changed_by = u.id
        WHERE sh.program_id = ?
        ORDER BY sh.changed_at ASC
    ");

    $stmt->bind_param("i", $programId);
    $stmt->execute();
    $result = $stmt->get_result();
    $statusHistory = [];
    
    while ($row = $result->fetch_assoc()) {
        $statusHistory[] = [
            'id' => $row['id'],
            'status' => $row['status'],
            'changed_at' => $row['changed_at'],
            'remarks' => $row['remarks'],
            'changed_by_name' => $row['changed_by_name'],
            'changed_by_email' => $row['changed_by_email']
        ];
    }

    echo json_encode([
        'success' => true,
        'status_history' => $statusHistory
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching status history: ' . $e->getMessage()
    ]);
}

$conn->close();
?> 