<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_id = $_GET['id'] ?? '';
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required.']);
        exit;
    }
    $stmt = $conn->prepare('SELECT id, full_name, email, phone_number, role, is_active, created_at FROM users WHERE id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if ($user) {
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['id'] ?? '';
    $full_name = $data['full_name'] ?? '';
    $email = $data['email'] ?? '';
    $phone_number = $data['phone_number'] ?? '';
    if (!$user_id || !$full_name || !$email) {
        echo json_encode(['success' => false, 'message' => 'User ID, full name, and email required.']);
        exit;
    }
    
    // Check if email is already taken by another user
    $check_stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $check_stmt->bind_param('si', $email, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email is already taken by another user.']);
        exit;
    }
    $check_stmt->close();
    
    $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, phone_number = ? WHERE id = ?');
    $stmt->bind_param('sssi', $full_name, $email, $phone_number, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating profile.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 