<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $program_id = $_GET['program_id'] ?? '';
    if (!$program_id) {
        echo json_encode(['success' => false, 'message' => 'Program ID required.']);
        exit;
    }
    $stmt = $conn->prepare('SELECT * FROM documents WHERE program_id = ?');
    $stmt->bind_param('i', $program_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    echo json_encode(['success' => true, 'documents' => $documents]);
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 