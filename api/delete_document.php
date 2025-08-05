<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? '';
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Document ID required.']);
        exit;
    }
    $stmt = $conn->prepare('SELECT * FROM documents WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $doc = $result->fetch_assoc();
    if ($doc) {
        $file_path = __DIR__ . '/uploads/' . $doc['filename'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $del_stmt = $conn->prepare('DELETE FROM documents WHERE id = ?');
        $del_stmt->bind_param('i', $id);
        $del_stmt->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Document not found.']);
    }
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 