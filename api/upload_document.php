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
    if (!isset($_POST['program_id']) || !isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'Program ID and file required.']);
        exit;
    }
    $program_id = $_POST['program_id'];
    $file = $_FILES['file'];
    $uploaded_by = $_POST['uploaded_by'] ?? '';
    $document_type = $_POST['document_type'] ?? 'original';
    
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $filename = uniqid() . '_' . basename($file['name']);
    $target_path = $upload_dir . $filename;
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $stmt = $conn->prepare('INSERT INTO documents (program_id, filename, original_name, uploaded_by, document_type) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('issss', $program_id, $filename, $file['name'], $uploaded_by, $document_type);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'document_id' => $stmt->insert_id, 'filename' => $filename]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving document record.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error uploading file.']);
    }
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 