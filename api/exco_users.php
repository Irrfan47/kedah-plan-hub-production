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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get all exco_users with their profile information
        $stmt = $conn->prepare('SELECT id, full_name, email, phone_number, profile_picture FROM users WHERE role = "exco_user" AND is_active = 1');
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $user = [
                'id' => $row['id'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'phone_number' => $row['phone_number'],
                'profile_picture' => null
            ];
            
            // Convert profile picture to base64 if it exists
            if ($row['profile_picture']) {
                $base64_image = base64_encode($row['profile_picture']);
                $user['profile_picture'] = 'data:image/jpeg;base64,' . $base64_image;
            }
            
            $users[] = $user;
        }
        
        echo json_encode(['success' => true, 'users' => $users]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching EXCO users: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']); 