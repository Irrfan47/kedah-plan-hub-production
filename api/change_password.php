<?php
header('Content-Type: application/json');
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
    $old_password = $data['old_password'] ?? '';
    $new_password = $data['new_password'] ?? '';
    if (!$id || !$old_password || !$new_password) {
        echo json_encode(['success' => false, 'message' => 'All fields required.']);
        exit;
    }
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if ($user && password_verify($old_password, $user['password'])) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $update->bind_param('si', $hashed, $id);
        if ($update->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating password.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Old password incorrect.']);
    }
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 