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
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $user_id = $data['user_id'] ?? '';
        $full_name = $data['full_name'] ?? '';
        $email = $data['email'] ?? '';
        $phone_number = $data['phone_number'] ?? '';
        $role = $data['role'] ?? '';
        $password = $data['password'] ?? '';
        
        if (!$user_id || !$full_name || !$email) {
            echo json_encode(['success' => false, 'message' => 'User ID, full name, and email are required.']);
            exit;
        }
        
        // Check if email already exists for another user
        $check_stmt = $conn->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $check_stmt->bind_param('si', $email, $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists for another user.']);
            exit;
        }
        
        // Update user information
        if (!empty($password)) {
            // If password is provided, hash and update it
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, phone_number = ?, role = ?, password = ? WHERE id = ?');
            $stmt->bind_param('sssssi', $full_name, $email, $phone_number, $role, $hashed_password, $user_id);
        } else {
            // If password is not provided, do not update it
            $stmt = $conn->prepare('UPDATE users SET full_name = ?, email = ?, phone_number = ?, role = ? WHERE id = ?');
            $stmt->bind_param('ssssi', $full_name, $email, $phone_number, $role, $user_id);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating user.']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
?> 