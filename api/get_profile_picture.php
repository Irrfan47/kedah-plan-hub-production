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
    $user_id = $_GET['user_id'] ?? '';
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID is required.']);
        exit;
    }
    
    try {
        // Get user's cropped profile picture for UI display
        $stmt = $conn->prepare('SELECT cropped_profile_picture FROM users WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && $user['cropped_profile_picture']) {
            $base64_image = base64_encode($user['cropped_profile_picture']);
            echo json_encode([
                'success' => true,
                'profile_picture' => 'data:image/jpeg;base64,' . $base64_image
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No profile picture found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
?> 